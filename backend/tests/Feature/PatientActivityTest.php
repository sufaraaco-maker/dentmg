<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientActivity;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientActivityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Unlike PatientActivityDispatchTest (which fakes the event, so the listener never actually
     * runs), this confirms Laravel's auto-discovery genuinely wires RecordsPatientActivity to
     * PatientActivityOccurred end-to-end — a real row lands in patient_activities, not just an
     * event object in memory.
     */
    public function test_the_listener_actually_persists_a_row_end_to_end(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $appointment = Appointment::factory()->create();

        (new AppointmentService)->confirm($appointment);

        $this->assertDatabaseHas('patient_activities', [
            'patient_id' => $appointment->patient_id,
            'event_type' => 'appointment.confirmed',
            'category' => 'appointments',
            'subject_type' => Appointment::class,
            'subject_id' => $appointment->id,
            'actor_id' => $actor->id,
        ]);
    }

    public function test_guest_cannot_list_activities(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/patients/{$patient->id}/activities");

        $response->assertUnauthorized();
    }

    public function test_index_paginates_at_15_per_page(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        PatientActivity::factory()->count(20)->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/activities");

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    public function test_index_filters_by_category(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        PatientActivity::factory()->create(['patient_id' => $patient->id, 'category' => 'laboratory', 'event_type' => 'lab_case.sent']);
        PatientActivity::factory()->create(['patient_id' => $patient->id, 'category' => 'imaging', 'event_type' => 'image.uploaded']);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/activities?category=laboratory");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('laboratory', $response->json('data.0.category'));
    }

    public function test_index_filters_by_date_range(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        PatientActivity::factory()->create(['patient_id' => $patient->id, 'occurred_at' => now()->subDays(10)]);
        PatientActivity::factory()->create(['patient_id' => $patient->id, 'occurred_at' => now()]);

        $response = $this->actingAs($actor)->getJson(
            "/api/patients/{$patient->id}/activities?from=".now()->subDay()->toDateString()
        );

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_index_orders_most_recent_first(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $older = PatientActivity::factory()->create(['patient_id' => $patient->id, 'occurred_at' => now()->subDays(2)]);
        $newer = PatientActivity::factory()->create(['patient_id' => $patient->id, 'occurred_at' => now()]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/activities");

        $response->assertOk();
        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
    }

    // ---- §9A Security Architecture Decision — the mandated enforcement test --------------------

    public function test_receptionist_never_receives_clinical_notes_category_activity(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();
        PatientActivity::factory()->create([
            'patient_id' => $patient->id,
            'category' => 'clinical_notes',
            'event_type' => 'clinical_note.signed',
        ]);
        PatientActivity::factory()->create([
            'patient_id' => $patient->id,
            'category' => 'appointments',
            'event_type' => 'appointment.confirmed',
        ]);

        $response = $this->actingAs($receptionist)->getJson("/api/patients/{$patient->id}/activities");

        $response->assertOk();
        $categories = collect($response->json('data'))->pluck('category');
        $this->assertNotContains('clinical_notes', $categories, 'Receptionist must never see clinical_notes-category rows.');
        $this->assertContains('appointments', $categories);
    }

    public function test_receptionist_requesting_clinical_notes_category_directly_gets_no_rows(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();
        PatientActivity::factory()->create([
            'patient_id' => $patient->id,
            'category' => 'clinical_notes',
            'event_type' => 'clinical_note.signed',
        ]);

        $response = $this->actingAs($receptionist)->getJson("/api/patients/{$patient->id}/activities?category=clinical_notes");

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_dentist_can_see_clinical_notes_category_activity(): void
    {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        PatientActivity::factory()->create([
            'patient_id' => $patient->id,
            'category' => 'clinical_notes',
            'event_type' => 'clinical_note.signed',
        ]);

        $response = $this->actingAs($dentist)->getJson("/api/patients/{$patient->id}/activities");

        $response->assertOk();
        $categories = collect($response->json('data'))->pluck('category');
        $this->assertContains('clinical_notes', $categories);
    }
}
