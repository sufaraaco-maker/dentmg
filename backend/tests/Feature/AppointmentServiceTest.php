<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Exceptions\Appointments\DentistConflictException;
use App\Exceptions\Appointments\EarlyNoShowException;
use App\Exceptions\Appointments\InvalidStatusTransitionException;
use App\Exceptions\Appointments\OutsideWorkingHoursException;
use App\Exceptions\Appointments\PatientConflictException;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AppointmentService;
    }

    /**
     * Bypasses the two soft-warning checks by default (working-hours/patient-conflict) so tests
     * that aren't specifically about those checks aren't coupled to a dentist schedule setup.
     * The dentist hard-conflict check is never bypassable, by design.
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'dentist_id' => User::factory()->dentist()->create()->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'start_at' => Carbon::parse('next monday 10:00'),
            'duration_minutes' => 30,
            'override_patient_conflict' => true,
            'override_outside_working_hours' => true,
        ], $overrides);
    }

    public function test_create_creates_an_appointment_with_a_server_computed_end_at(): void
    {
        $appointment = $this->service->create($this->payload(['start_at' => Carbon::parse('next monday 10:00'), 'duration_minutes' => 45]));

        $this->assertSame(AppointmentStatus::Scheduled, $appointment->status);
        $this->assertTrue($appointment->end_at->equalTo($appointment->start_at->copy()->addMinutes(45)));
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'duration_minutes' => 45]);
    }

    public function test_creating_an_appointment_writes_an_audit_log_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);

        $appointment = $this->service->create($this->payload());

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Appointment::class,
            'auditable_id' => $appointment->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }

    public function test_create_throws_a_dentist_conflict_exception_that_cannot_be_overridden(): void
    {
        $dentist = User::factory()->dentist()->create();
        Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => Carbon::parse('next monday 10:00'),
            'end_at' => Carbon::parse('next monday 10:30'),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->expectException(DentistConflictException::class);

        $this->service->create($this->payload([
            'dentist_id' => $dentist->id,
            'start_at' => Carbon::parse('next monday 10:15'),
            'duration_minutes' => 30,
            // Even with both override flags set, a dentist conflict is a hard block.
            'override_patient_conflict' => true,
            'override_outside_working_hours' => true,
        ]));
    }

    public function test_create_throws_a_patient_conflict_exception_unless_overridden(): void
    {
        $patient = Patient::factory()->create();
        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'start_at' => Carbon::parse('next monday 10:00'),
            'end_at' => Carbon::parse('next monday 10:30'),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->expectException(PatientConflictException::class);

        $this->service->create($this->payload([
            'patient_id' => $patient->id,
            'start_at' => Carbon::parse('next monday 10:15'),
            'duration_minutes' => 30,
            'override_patient_conflict' => false,
        ]));
    }

    public function test_create_succeeds_when_patient_conflict_is_overridden(): void
    {
        $patient = Patient::factory()->create();
        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'start_at' => Carbon::parse('next monday 10:00'),
            'end_at' => Carbon::parse('next monday 10:30'),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $appointment = $this->service->create($this->payload([
            'patient_id' => $patient->id,
            'start_at' => Carbon::parse('next monday 10:15'),
            'duration_minutes' => 30,
            'override_patient_conflict' => true,
        ]));

        $this->assertNotNull($appointment->id);
    }

    public function test_create_throws_an_outside_working_hours_exception_unless_overridden(): void
    {
        $this->expectException(OutsideWorkingHoursException::class);

        // No working-hours row exists for this brand-new dentist, so any slot is "outside hours".
        $this->service->create($this->payload(['override_outside_working_hours' => false]));
    }

    public function test_create_succeeds_when_outside_working_hours_is_overridden(): void
    {
        $appointment = $this->service->create($this->payload(['override_outside_working_hours' => true]));

        $this->assertNotNull($appointment->id);
    }

    public function test_reschedule_moves_the_appointment_and_increments_reschedule_count(): void
    {
        $appointment = $this->service->create($this->payload(['start_at' => Carbon::parse('next monday 10:00')]));
        $newStart = Carbon::parse('next monday 14:00');

        $rescheduled = $this->service->reschedule($appointment, [
            'start_at' => $newStart,
            'override_patient_conflict' => true,
            'override_outside_working_hours' => true,
        ]);

        $this->assertTrue($rescheduled->start_at->equalTo($newStart));
        $this->assertSame(1, $rescheduled->reschedule_count);
    }

    public function test_reschedule_does_not_change_status(): void
    {
        $appointment = $this->service->create($this->payload());
        $this->service->confirm($appointment);

        $rescheduled = $this->service->reschedule($appointment, [
            'start_at' => Carbon::parse('next monday 15:00'),
            'override_patient_conflict' => true,
            'override_outside_working_hours' => true,
        ]);

        $this->assertSame(AppointmentStatus::Confirmed, $rescheduled->status);
    }

    public function test_reschedule_writes_an_audit_log_entry_with_old_and_new_start_at(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);

        $appointment = $this->service->create($this->payload(['start_at' => Carbon::parse('next monday 10:00')]));
        $newStart = Carbon::parse('next monday 14:00');

        $this->service->reschedule($appointment, [
            'start_at' => $newStart,
            'override_patient_conflict' => true,
            'override_outside_working_hours' => true,
        ]);

        $log = AuditLog::query()
            ->where('auditable_id', $appointment->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertArrayHasKey('start_at', $log->changes);
    }

    public function test_reschedule_does_not_increment_count_when_only_notes_change(): void
    {
        $appointment = $this->service->create($this->payload());

        $rescheduled = $this->service->reschedule($appointment, ['notes' => 'Patient called to confirm']);

        $this->assertSame(0, $rescheduled->reschedule_count);
        $this->assertSame('Patient called to confirm', $rescheduled->notes);
    }

    public function test_reschedule_re_checks_dentist_conflict_but_excludes_the_appointment_itself(): void
    {
        $dentist = User::factory()->dentist()->create();
        $appointment = $this->service->create($this->payload([
            'dentist_id' => $dentist->id,
            'start_at' => Carbon::parse('next monday 10:00'),
        ]));

        // Rescheduling to its own current slot must not conflict with itself.
        $rescheduled = $this->service->reschedule($appointment, [
            'start_at' => Carbon::parse('next monday 10:00'),
            'override_patient_conflict' => true,
            'override_outside_working_hours' => true,
        ]);

        $this->assertTrue($rescheduled->start_at->equalTo(Carbon::parse('next monday 10:00')));

        // But rescheduling onto a genuinely different, already-booked slot for the same dentist must still fail.
        Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => Carbon::parse('next monday 16:00'),
            'end_at' => Carbon::parse('next monday 16:30'),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->expectException(DentistConflictException::class);

        $this->service->reschedule($appointment, [
            'start_at' => Carbon::parse('next monday 16:15'),
            'override_patient_conflict' => true,
            'override_outside_working_hours' => true,
        ]);
    }

    public function test_status_transitions_happy_path(): void
    {
        $appointment = $this->service->create($this->payload());

        $appointment = $this->service->confirm($appointment);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);

        $appointment = $this->service->checkIn($appointment);
        $this->assertSame(AppointmentStatus::CheckedIn, $appointment->status);
        $this->assertNotNull($appointment->checked_in_at);

        $appointment = $this->service->start($appointment);
        $this->assertSame(AppointmentStatus::InProgress, $appointment->status);
        $this->assertNotNull($appointment->started_at);

        $appointment = $this->service->complete($appointment);
        $this->assertSame(AppointmentStatus::Completed, $appointment->status);
        $this->assertNotNull($appointment->completed_at);
    }

    public function test_scheduled_can_skip_confirmed_and_go_directly_to_checked_in(): void
    {
        $appointment = $this->service->create($this->payload());

        $appointment = $this->service->checkIn($appointment);

        $this->assertSame(AppointmentStatus::CheckedIn, $appointment->status);
    }

    public function test_checked_in_cannot_skip_in_progress_and_jump_to_completed(): void
    {
        $appointment = $this->service->create($this->payload());
        $appointment = $this->service->checkIn($appointment);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->complete($appointment);
    }

    public function test_a_terminal_status_cannot_transition_anywhere(): void
    {
        $appointment = $this->service->create($this->payload());
        $appointment = $this->service->cancel($appointment, null);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->confirm($appointment);
    }

    public function test_cancel_is_allowed_from_any_active_status(): void
    {
        foreach (['scheduled', 'confirmed', 'checked_in', 'in_progress'] as $targetStatus) {
            $appointment = $this->service->create($this->payload());

            $appointment = match ($targetStatus) {
                'scheduled' => $appointment,
                'confirmed' => $this->service->confirm($appointment),
                'checked_in' => $this->service->checkIn($appointment),
                'in_progress' => $this->service->start($this->service->checkIn($appointment)),
            };

            $cancelled = $this->service->cancel($appointment, 'Patient requested');

            $this->assertSame(AppointmentStatus::Cancelled, $cancelled->status);
            $this->assertSame('Patient requested', $cancelled->cancellation_reason);
            $this->assertNotNull($cancelled->cancelled_at);
        }
    }

    public function test_cannot_cancel_a_completed_appointment(): void
    {
        $appointment = $this->service->create($this->payload());
        $appointment = $this->service->complete($this->service->start($this->service->checkIn($appointment)));

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->cancel($appointment, null);
    }

    public function test_mark_no_show_before_start_at_requires_an_explicit_override(): void
    {
        $appointment = $this->service->create($this->payload(['start_at' => Carbon::parse('next monday 10:00')]));

        $this->expectException(EarlyNoShowException::class);

        $this->service->markNoShow($appointment);
    }

    public function test_mark_no_show_succeeds_with_override_before_start_at(): void
    {
        $appointment = $this->service->create($this->payload(['start_at' => Carbon::parse('next monday 10:00')]));

        $marked = $this->service->markNoShow($appointment, overrideEarly: true);

        $this->assertSame(AppointmentStatus::NoShow, $marked->status);
        $this->assertNotNull($marked->no_show_at);
    }

    public function test_mark_no_show_succeeds_without_override_once_start_at_has_passed(): void
    {
        $appointment = $this->service->create($this->payload(['start_at' => Carbon::now()->subHour()]));

        $marked = $this->service->markNoShow($appointment);

        $this->assertSame(AppointmentStatus::NoShow, $marked->status);
    }

    public function test_cannot_mark_no_show_once_checked_in(): void
    {
        $appointment = $this->service->create($this->payload(['start_at' => Carbon::now()->subHour()]));
        $appointment = $this->service->checkIn($appointment);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->markNoShow($appointment, overrideEarly: true);
    }
}
