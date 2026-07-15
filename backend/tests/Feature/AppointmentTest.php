<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\DentistWorkingHour;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function dentistWithWorkingHours(Carbon $day, string $start = '09:00', string $end = '17:00'): User
    {
        $dentist = User::factory()->dentist()->create();

        DentistWorkingHour::factory()->create([
            'user_id' => $dentist->id,
            'day_of_week' => $day->dayOfWeek,
            'start_time' => $start,
            'end_time' => $end,
            'is_active' => true,
        ]);

        return $dentist;
    }

    private function validPayload(array $overrides = []): array
    {
        $day = Carbon::parse('next monday');
        $dentist = $overrides['dentist_id'] ?? null;

        if (! $dentist) {
            $dentist = $this->dentistWithWorkingHours($day)->id;
        }

        return array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'dentist_id' => $dentist,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'start_at' => $day->copy()->setTime(10, 0)->toIso8601String(),
            'duration_minutes' => 30,
        ], $overrides);
    }

    // ---- store / create -----------------------------------------------------------------

    public function test_guest_cannot_create_an_appointment(): void
    {
        $response = $this->postJson('/api/appointments', $this->validPayload());

        $response->assertUnauthorized();
    }

    public function test_receptionist_can_create_an_appointment(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->postJson('/api/appointments', $this->validPayload());

        $response->assertCreated()->assertJson(['status' => 'scheduled']);
        $this->assertDatabaseHas('appointments', ['id' => $response->json('id'), 'status' => 'scheduled']);
    }

    public function test_dentist_cannot_create_an_appointment(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson('/api/appointments', $this->validPayload());

        $response->assertForbidden();
    }

    public function test_create_appointment_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/appointments', []);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'patient_id', 'dentist_id', 'appointment_type_id', 'start_at', 'duration_minutes',
        ]);
    }

    public function test_creating_an_appointment_that_overlaps_the_same_dentist_is_a_hard_conflict(): void
    {
        $actor = User::factory()->admin()->create();
        $day = Carbon::parse('next monday');
        $dentist = $this->dentistWithWorkingHours($day);

        $this->actingAs($actor)->postJson('/api/appointments', $this->validPayload(['dentist_id' => $dentist->id]))
            ->assertCreated();

        $response = $this->actingAs($actor)->postJson('/api/appointments', $this->validPayload([
            'dentist_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(10, 15)->toIso8601String(),
        ]));

        $response->assertStatus(409)->assertJson(['code' => 'dentist_conflict']);
    }

    public function test_creating_an_appointment_that_overlaps_the_same_patient_is_a_soft_conflict_overridable(): void
    {
        $actor = User::factory()->admin()->create();
        $day = Carbon::parse('next monday');
        $patient = Patient::factory()->create();
        $dentistA = $this->dentistWithWorkingHours($day);
        $dentistB = $this->dentistWithWorkingHours($day);

        $this->actingAs($actor)->postJson('/api/appointments', $this->validPayload([
            'patient_id' => $patient->id,
            'dentist_id' => $dentistA->id,
        ]))->assertCreated();

        $conflicting = $this->validPayload([
            'patient_id' => $patient->id,
            'dentist_id' => $dentistB->id,
            'start_at' => $day->copy()->setTime(10, 15)->toIso8601String(),
        ]);

        $blocked = $this->actingAs($actor)->postJson('/api/appointments', $conflicting);
        $blocked->assertStatus(409)->assertJson([
            'code' => 'patient_conflict',
            'overridable' => true,
            'override_field' => 'override_patient_conflict',
        ]);

        $overridden = $this->actingAs($actor)->postJson('/api/appointments', array_merge($conflicting, [
            'override_patient_conflict' => true,
        ]));
        $overridden->assertCreated();
    }

    public function test_creating_an_appointment_outside_working_hours_is_a_soft_conflict_overridable(): void
    {
        $actor = User::factory()->admin()->create();
        $day = Carbon::parse('next monday');
        $dentist = User::factory()->dentist()->create(); // no working hours configured at all

        $payload = $this->validPayload([
            'dentist_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(10, 0)->toIso8601String(),
        ]);

        $blocked = $this->actingAs($actor)->postJson('/api/appointments', $payload);
        $blocked->assertStatus(422)->assertJson([
            'code' => 'outside_working_hours',
            'overridable' => true,
            'override_field' => 'override_outside_working_hours',
        ]);

        $overridden = $this->actingAs($actor)->postJson('/api/appointments', array_merge($payload, [
            'override_outside_working_hours' => true,
        ]));
        $overridden->assertCreated();
    }

    // ---- show -----------------------------------------------------------------------------

    public function test_guest_cannot_view_an_appointment(): void
    {
        $appointment = Appointment::factory()->create();

        $response = $this->getJson("/api/appointments/{$appointment->id}");

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_view_an_appointment(): void
    {
        $actor = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/appointments/{$appointment->id}");

        $response->assertOk()->assertJson(['id' => $appointment->id]);
    }

    // ---- index ------------------------------------------------------------------------------

    public function test_index_requires_a_date_range(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->getJson('/api/appointments');

        $response->assertUnprocessable()->assertJsonValidationErrors(['date_from', 'date_to']);
    }

    public function test_index_lists_appointments_within_the_date_range_and_supports_filters(): void
    {
        $actor = User::factory()->create();
        $day = Carbon::parse('next monday');
        $dentist = $this->dentistWithWorkingHours($day);
        $otherDentist = $this->dentistWithWorkingHours($day);

        $inRange = Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(10, 30),
        ]);
        Appointment::factory()->create([
            'dentist_id' => $otherDentist->id,
            'start_at' => $day->copy()->setTime(11, 0),
            'end_at' => $day->copy()->setTime(11, 30),
        ]);
        Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => $day->copy()->addWeek()->setTime(10, 0),
            'end_at' => $day->copy()->addWeek()->setTime(10, 30),
        ]);

        $response = $this->actingAs($actor)->getJson('/api/appointments?'.http_build_query([
            'date_from' => $day->toDateString(),
            'date_to' => $day->toDateString(),
            'dentist_id' => $dentist->id,
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame($inRange->id, $response->json('0.id'));
    }

    // ---- update / reschedule -----------------------------------------------------------------

    public function test_receptionist_can_reschedule_an_appointment(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $day = Carbon::parse('next monday');
        $dentist = $this->dentistWithWorkingHours($day);
        $appointment = Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(10, 30),
            'duration_minutes' => 30,
            'reschedule_count' => 0,
        ]);

        $newStart = $day->copy()->setTime(13, 0);
        $response = $this->actingAs($actor)->putJson("/api/appointments/{$appointment->id}", [
            'start_at' => $newStart->toIso8601String(),
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('reschedule_count'));
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'reschedule_count' => 1]);
    }

    public function test_dentist_cannot_reschedule_an_appointment(): void
    {
        $actor = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/appointments/{$appointment->id}", [
            'notes' => 'updated',
        ]);

        $response->assertForbidden();
    }

    // ---- destroy ----------------------------------------------------------------------------

    public function test_admin_can_delete_an_appointment(): void
    {
        $actor = User::factory()->admin()->create();
        $appointment = Appointment::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/appointments/{$appointment->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('appointments', ['id' => $appointment->id]);
    }

    public function test_receptionist_cannot_delete_an_appointment(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $appointment = Appointment::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/appointments/{$appointment->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'deleted_at' => null]);
    }

    // ---- cancel -----------------------------------------------------------------------------

    public function test_receptionist_can_cancel_an_appointment(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $response = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/cancel", [
            'cancellation_reason' => 'Patient requested',
        ]);

        $response->assertOk()->assertJson(['status' => 'cancelled', 'cancellation_reason' => 'Patient requested']);
    }

    public function test_dentist_cannot_cancel_an_appointment(): void
    {
        $actor = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $response = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/cancel");

        $response->assertForbidden();
    }

    // ---- no-show ----------------------------------------------------------------------------

    public function test_marking_no_show_before_start_time_is_a_soft_conflict_overridable(): void
    {
        $actor = User::factory()->admin()->create();
        $appointment = Appointment::factory()->create([
            'status' => AppointmentStatus::Scheduled,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addMinutes(30),
        ]);

        $blocked = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/no-show");
        $blocked->assertStatus(422)->assertJson([
            'code' => 'early_no_show',
            'overridable' => true,
            'override_field' => 'override_early_no_show',
        ]);

        $overridden = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/no-show", [
            'override_early_no_show' => true,
        ]);
        $overridden->assertOk()->assertJson(['status' => 'no_show']);
    }

    public function test_marking_no_show_after_start_time_has_passed_needs_no_override(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $appointment = Appointment::factory()->create([
            'status' => AppointmentStatus::Scheduled,
            'start_at' => now()->subHour(),
            'end_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/no-show");

        $response->assertOk()->assertJson(['status' => 'no_show']);
    }

    // ---- confirm ----------------------------------------------------------------------------

    public function test_receptionist_can_confirm_an_appointment(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $response = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/confirm");

        $response->assertOk()->assertJson(['status' => 'confirmed']);
    }

    public function test_dentist_cannot_confirm_an_appointment(): void
    {
        $actor = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $response = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/confirm");

        $response->assertForbidden();
    }

    // ---- check-in ---------------------------------------------------------------------------

    public function test_receptionist_can_check_in_an_appointment(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $response = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/check-in");

        $response->assertOk()->assertJson(['status' => 'checked_in']);
        $this->assertNotNull($response->json('checked_in_at'));
    }

    public function test_dentist_cannot_check_in_an_appointment(): void
    {
        $actor = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $response = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/check-in");

        $response->assertForbidden();
    }

    // ---- start (dentist-ownership IDOR) ------------------------------------------------------

    public function test_receptionist_can_start_any_appointment(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::CheckedIn]);

        $response = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/start");

        $response->assertOk()->assertJson(['status' => 'in_progress']);
    }

    public function test_treating_dentist_can_start_their_own_appointment(): void
    {
        $dentist = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'status' => AppointmentStatus::CheckedIn,
        ]);

        $response = $this->actingAs($dentist)->postJson("/api/appointments/{$appointment->id}/start");

        $response->assertOk()->assertJson(['status' => 'in_progress']);
    }

    public function test_a_different_dentist_cannot_start_someone_elses_appointment(): void
    {
        $treatingDentist = User::factory()->dentist()->create();
        $otherDentist = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create([
            'dentist_id' => $treatingDentist->id,
            'status' => AppointmentStatus::CheckedIn,
        ]);

        $response = $this->actingAs($otherDentist)->postJson("/api/appointments/{$appointment->id}/start");

        $response->assertForbidden();
    }

    // ---- complete (same dentist-ownership IDOR rule) -------------------------------------------

    public function test_treating_dentist_can_complete_their_own_appointment(): void
    {
        $dentist = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'status' => AppointmentStatus::InProgress,
        ]);

        $response = $this->actingAs($dentist)->postJson("/api/appointments/{$appointment->id}/complete");

        $response->assertOk()->assertJson(['status' => 'completed']);
    }

    public function test_a_different_dentist_cannot_complete_someone_elses_appointment(): void
    {
        $treatingDentist = User::factory()->dentist()->create();
        $otherDentist = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create([
            'dentist_id' => $treatingDentist->id,
            'status' => AppointmentStatus::InProgress,
        ]);

        $response = $this->actingAs($otherDentist)->postJson("/api/appointments/{$appointment->id}/complete");

        $response->assertForbidden();
    }

    // ---- invalid status transition -------------------------------------------------------------

    public function test_completing_a_scheduled_appointment_that_never_checked_in_is_an_invalid_transition(): void
    {
        $actor = User::factory()->admin()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $response = $this->actingAs($actor)->postJson("/api/appointments/{$appointment->id}/complete");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_status_transition']);
    }

    // ---- available-slots ------------------------------------------------------------------------

    public function test_available_slots_returns_candidate_start_times(): void
    {
        $actor = User::factory()->create();
        $day = Carbon::parse('next monday');
        $dentist = $this->dentistWithWorkingHours($day, '09:00', '10:00');

        $response = $this->actingAs($actor)->getJson('/api/available-slots?'.http_build_query([
            'dentist_id' => $dentist->id,
            'date' => $day->toDateString(),
            'duration_minutes' => 30,
        ]));

        $response->assertOk();
        $this->assertNotEmpty($response->json('slots'));
    }

    public function test_available_slots_requires_a_dentist_id(): void
    {
        $actor = User::factory()->create();

        $response = $this->actingAs($actor)->getJson('/api/available-slots?date=2026-07-20&duration_minutes=30');

        $response->assertUnprocessable()->assertJsonValidationErrors('dentist_id');
    }

    // ---- broad guest-401 sweep for transition/write endpoints ------------------------------------

    public function test_guest_cannot_access_any_write_or_transition_endpoint(): void
    {
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $this->putJson("/api/appointments/{$appointment->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/appointments/{$appointment->id}")->assertUnauthorized();
        $this->postJson("/api/appointments/{$appointment->id}/cancel")->assertUnauthorized();
        $this->postJson("/api/appointments/{$appointment->id}/no-show")->assertUnauthorized();
        $this->postJson("/api/appointments/{$appointment->id}/confirm")->assertUnauthorized();
        $this->postJson("/api/appointments/{$appointment->id}/check-in")->assertUnauthorized();
        $this->postJson("/api/appointments/{$appointment->id}/start")->assertUnauthorized();
        $this->postJson("/api/appointments/{$appointment->id}/complete")->assertUnauthorized();
        $this->getJson('/api/available-slots')->assertUnauthorized();
    }
}
