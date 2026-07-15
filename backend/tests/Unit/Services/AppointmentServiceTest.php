<?php

namespace Tests\Unit\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DentistTimeOff;
use App\Models\DentistWorkingHour;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

    private function dentistWithWorkingHours(Carbon $day, string $start = '09:00', string $end = '12:00'): User
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

    public function test_available_slots_are_sliced_at_the_configured_interval(): void
    {
        Config::set('appointments.slot_interval_minutes', 15);
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = $this->dentistWithWorkingHours($day);

        $slots = $this->service->availableSlots($dentist->id, $day, 30);

        // 09:00 .. 11:30 every 15 minutes (last slot must still end by 12:00).
        $this->assertCount(11, $slots);
        $this->assertSame('09:00', $slots[0]->format('H:i'));
        $this->assertSame('11:30', end($slots)->format('H:i'));
    }

    public function test_available_slots_respects_a_wider_interval(): void
    {
        Config::set('appointments.slot_interval_minutes', 30);
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = $this->dentistWithWorkingHours($day);

        $slots = $this->service->availableSlots($dentist->id, $day, 30);

        $this->assertCount(6, $slots);
        $this->assertSame('11:30', end($slots)->format('H:i'));
    }

    public function test_available_slots_excludes_existing_occupying_appointments(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = $this->dentistWithWorkingHours($day);

        Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(10, 30),
            'duration_minutes' => 30,
            'status' => AppointmentStatus::Scheduled,
        ]);

        $slots = $this->service->availableSlots($dentist->id, $day, 30);
        $formatted = array_map(fn (Carbon $slot) => $slot->format('H:i'), $slots);

        $this->assertNotContains('09:45', $formatted); // would end at 10:15, overlapping
        $this->assertNotContains('10:00', $formatted);
        $this->assertContains('10:30', $formatted); // starts exactly when the busy range ends
    }

    public function test_available_slots_excludes_time_off(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = $this->dentistWithWorkingHours($day);

        DentistTimeOff::factory()->create([
            'user_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(9, 0),
            'end_at' => $day->copy()->setTime(10, 0),
        ]);

        $slots = $this->service->availableSlots($dentist->id, $day, 30);
        $formatted = array_map(fn (Carbon $slot) => $slot->format('H:i'), $slots);

        $this->assertNotContains('09:00', $formatted);
        $this->assertContains('10:00', $formatted);
    }

    public function test_available_slots_is_empty_when_dentist_has_no_working_hours_that_day(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = User::factory()->dentist()->create();

        $slots = $this->service->availableSlots($dentist->id, $day, 30);

        $this->assertSame([], $slots);
    }

    public function test_dentist_has_conflict_detects_a_true_overlap(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = User::factory()->dentist()->create();

        Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(10, 30),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->assertTrue($this->service->dentistHasConflict(
            $dentist->id,
            $day->copy()->setTime(10, 15),
            $day->copy()->setTime(10, 45)
        ));
    }

    public function test_dentist_has_conflict_ignores_non_occupying_statuses(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = User::factory()->dentist()->create();

        Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(10, 30),
            'status' => AppointmentStatus::Cancelled,
        ]);

        $this->assertFalse($this->service->dentistHasConflict(
            $dentist->id,
            $day->copy()->setTime(10, 0),
            $day->copy()->setTime(10, 30)
        ));
    }

    public function test_dentist_has_conflict_excludes_the_given_appointment_id(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = User::factory()->dentist()->create();

        $appointment = Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(10, 30),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->assertFalse($this->service->dentistHasConflict(
            $dentist->id,
            $day->copy()->setTime(10, 0),
            $day->copy()->setTime(10, 30),
            $appointment->id
        ));
    }

    public function test_patient_has_conflict_requires_a_true_time_overlap_not_just_the_same_day(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'start_at' => $day->copy()->setTime(10, 0),
            'end_at' => $day->copy()->setTime(10, 30),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->assertTrue($this->service->patientHasConflict(
            $patient->id,
            $day->copy()->setTime(10, 15),
            $day->copy()->setTime(10, 45)
        ));

        $this->assertFalse($this->service->patientHasConflict(
            $patient->id,
            $day->copy()->setTime(14, 0),
            $day->copy()->setTime(14, 30)
        ));
    }

    public function test_is_outside_working_hours_is_true_with_no_working_hours_that_day(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue($this->service->isOutsideWorkingHours(
            $dentist->id,
            $day->copy()->setTime(10, 0),
            $day->copy()->setTime(10, 30)
        ));
    }

    public function test_is_outside_working_hours_is_true_during_time_off_even_within_nominal_hours(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = $this->dentistWithWorkingHours($day);

        DentistTimeOff::factory()->create([
            'user_id' => $dentist->id,
            'start_at' => $day->copy()->setTime(9, 0),
            'end_at' => $day->copy()->setTime(12, 0),
        ]);

        $this->assertTrue($this->service->isOutsideWorkingHours(
            $dentist->id,
            $day->copy()->setTime(10, 0),
            $day->copy()->setTime(10, 30)
        ));
    }

    public function test_is_outside_working_hours_is_false_within_hours_and_no_time_off(): void
    {
        $day = Carbon::parse('next monday')->startOfDay();
        $dentist = $this->dentistWithWorkingHours($day);

        $this->assertFalse($this->service->isOutsideWorkingHours(
            $dentist->id,
            $day->copy()->setTime(10, 0),
            $day->copy()->setTime(10, 30)
        ));
    }
}
