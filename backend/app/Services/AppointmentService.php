<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Events\PatientActivityOccurred;
use App\Exceptions\Appointments\DentistConflictException;
use App\Exceptions\Appointments\EarlyNoShowException;
use App\Exceptions\Appointments\InvalidStatusTransitionException;
use App\Exceptions\Appointments\OutsideWorkingHoursException;
use App\Exceptions\Appointments\PatientConflictException;
use App\Models\Appointment;
use App\Models\DentistTimeOff;
use App\Models\DentistWorkingHour;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    /**
     * Allowed forward transitions, keyed by current status value (design doc §3). `cancelled`
     * is reachable from every non-terminal status ("any time before completion", §12); `no_show`
     * only from `scheduled`/`confirmed` (before check-in, §13). `confirmed` is optional —
     * `scheduled` can jump straight to `checked_in`. `checked_in → completed` cannot skip
     * `in_progress`, per the design doc's data-quality recommendation for wait-time reporting.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'scheduled' => ['confirmed', 'checked_in', 'no_show', 'cancelled'],
        'confirmed' => ['checked_in', 'no_show', 'cancelled'],
        'checked_in' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    /**
     * Computes candidate appointment start times for one dentist on one calendar day: working
     * hours minus existing occupying appointments minus time-off, sliced at the configured
     * granularity (design doc §6/§9). Pure read — never mutates anything.
     *
     * @return list<Carbon>
     */
    public function availableSlots(string $dentistId, CarbonInterface $date, int $durationMinutes): array
    {
        $day = Carbon::instance($date)->startOfDay();

        $workingRanges = DentistWorkingHour::query()
            ->forDentist($dentistId)
            ->active()
            ->forDayOfWeek($day->dayOfWeek)
            ->get()
            ->map(fn (DentistWorkingHour $hour) => [
                'start' => $day->copy()->setTimeFromTimeString($hour->start_time),
                'end' => $day->copy()->setTimeFromTimeString($hour->end_time),
            ]);

        if ($workingRanges->isEmpty()) {
            return [];
        }

        $busyRanges = $this->busyRangesForDentist($dentistId, $day);
        $intervalMinutes = (int) config('appointments.slot_interval_minutes', 15);

        $slots = [];

        foreach ($workingRanges as $range) {
            $cursor = $range['start']->copy();

            while ($cursor->copy()->addMinutes($durationMinutes)->lte($range['end'])) {
                $slotEnd = $cursor->copy()->addMinutes($durationMinutes);

                $overlapsBusyRange = $busyRanges->contains(
                    fn (array $busy) => $cursor->lt($busy['end']) && $slotEnd->gt($busy['start'])
                );

                if (! $overlapsBusyRange) {
                    $slots[] = $cursor->copy();
                }

                $cursor->addMinutes($intervalMinutes);
            }
        }

        return $slots;
    }

    /**
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function busyRangesForDentist(string $dentistId, Carbon $day): Collection
    {
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        $appointments = Appointment::query()
            ->forDentist($dentistId)
            ->active()
            ->where('start_at', '<', $dayEnd)
            ->where('end_at', '>', $dayStart)
            ->get(['start_at', 'end_at'])
            ->map(fn (Appointment $appointment) => ['start' => $appointment->start_at, 'end' => $appointment->end_at]);

        $timeOff = DentistTimeOff::query()
            ->forDentist($dentistId)
            ->where('start_at', '<', $dayEnd)
            ->where('end_at', '>', $dayStart)
            ->get(['start_at', 'end_at'])
            ->map(fn (DentistTimeOff $timeOff) => ['start' => $timeOff->start_at, 'end' => $timeOff->end_at]);

        return $appointments->concat($timeOff);
    }

    /**
     * Hard-block check (design doc §5.3, §10): true overlap against occupying-status
     * appointments for this dentist, excluding the appointment being rescheduled (if any).
     */
    public function dentistHasConflict(string $dentistId, CarbonInterface $startAt, CarbonInterface $endAt, ?string $excludeAppointmentId = null): bool
    {
        return Appointment::query()
            ->forDentist($dentistId)
            ->active()
            ->when($excludeAppointmentId, fn ($query) => $query->whereKeyNot($excludeAppointmentId))
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->exists();
    }

    /**
     * Soft-warning check (design doc §5.4): true overlap only, not merely another appointment
     * the same day.
     */
    public function patientHasConflict(string $patientId, CarbonInterface $startAt, CarbonInterface $endAt, ?string $excludeAppointmentId = null): bool
    {
        return Appointment::query()
            ->forPatient($patientId)
            ->active()
            ->when($excludeAppointmentId, fn ($query) => $query->whereKeyNot($excludeAppointmentId))
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->exists();
    }

    /**
     * Soft-warning check (design doc §5.5): outside the dentist's active working-hours ranges
     * for that day, or overlapping a time-off entry.
     */
    public function isOutsideWorkingHours(string $dentistId, CarbonInterface $startAt, CarbonInterface $endAt): bool
    {
        $withinWorkingHours = DentistWorkingHour::query()
            ->forDentist($dentistId)
            ->active()
            ->forDayOfWeek($startAt->dayOfWeek)
            ->get()
            ->contains(function (DentistWorkingHour $hour) use ($startAt, $endAt) {
                $rangeStart = Carbon::instance($startAt)->setTimeFromTimeString($hour->start_time);
                $rangeEnd = Carbon::instance($startAt)->setTimeFromTimeString($hour->end_time);

                return $startAt->gte($rangeStart) && $endAt->lte($rangeEnd);
            });

        if (! $withinWorkingHours) {
            return true;
        }

        return DentistTimeOff::query()
            ->forDentist($dentistId)
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->exists();
    }

    /**
     * Date-range-bounded list (design doc §16/§19) — deliberately not paginated, same
     * exemption as the Dashboard summary endpoint: a calendar-range query is naturally bounded
     * (a week of one clinic's appointments is at most a few hundred rows), so classic
     * pagination would only get in the way of a board/list view that wants the whole range at
     * once. `date_from`/`date_to` are treated as whole-day boundaries so a plain `YYYY-MM-DD`
     * filter (the common board-view case) captures the entire day, not just midnight.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Appointment>
     */
    public function search(array $filters): Collection
    {
        $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
        $dateTo = Carbon::parse($filters['date_to'])->endOfDay();

        return Appointment::query()
            ->with(['patient', 'dentist', 'appointmentType'])
            ->where('start_at', '>=', $dateFrom)
            ->where('start_at', '<=', $dateTo)
            ->when($filters['dentist_id'] ?? null, fn ($query, $dentistId) => $query->where('dentist_id', $dentistId))
            ->when($filters['patient_id'] ?? null, fn ($query, $patientId) => $query->where('patient_id', $patientId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('start_at')
            ->get();
    }

    /**
     * Mirrors PatientService::delete()'s existence for controller-calls-one-Service-method
     * consistency, even though it's a one-liner today. Soft delete only — reserved for pure
     * data-entry mistakes (§4); cancellation is a separate, non-destructive status transition.
     */
    public function delete(Appointment $appointment): void
    {
        $appointment->delete();
    }

    /**
     * Appointment creation workflow (design doc §5, §10): validates availability/conflicts,
     * then creates. Never receives `end_at` or `status` from the caller — both are always
     * computed here. Audit logging is automatic via Appointment's Auditable trait.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Appointment
    {
        $startAt = Carbon::parse($data['start_at']);
        $endAt = $startAt->copy()->addMinutes((int) $data['duration_minutes']);

        $this->assertNoDentistConflict($data['dentist_id'], $startAt, $endAt);
        $this->assertNoPatientConflict($data['patient_id'], $startAt, $endAt, (bool) ($data['override_patient_conflict'] ?? false));
        $this->assertWithinWorkingHours($data['dentist_id'], $startAt, $endAt, (bool) ($data['override_outside_working_hours'] ?? false));

        $appointment = $this->runGuardedAgainstDentistRace(fn () => Appointment::create([
            'patient_id' => $data['patient_id'],
            'dentist_id' => $data['dentist_id'],
            'appointment_type_id' => $data['appointment_type_id'],
            'start_at' => $startAt,
            'end_at' => $endAt,
            'duration_minutes' => $data['duration_minutes'],
            'status' => AppointmentStatus::Scheduled,
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'reschedule_count' => 0,
        ]));

        event(new PatientActivityOccurred(
            $appointment, Auth::user(), 'appointment.created', 'appointments', 'Appointment created',
        ));

        return $appointment;
    }

    /**
     * In-place reschedule (design doc §11) — never creates a new row; re-runs full
     * availability/conflict checks only when the slot actually moves (`dentist_id`, `start_at`,
     * or `duration_minutes` changed). `reschedule_count` only increments when the dentist or
     * start time actually changes, matching the doc's definition of "reschedule". Status is
     * left untouched. Old → new values are captured automatically by the Auditable trait.
     *
     * @param  array<string, mixed>  $data
     */
    public function reschedule(Appointment $appointment, array $data): Appointment
    {
        $movesSlot = array_key_exists('start_at', $data)
            || array_key_exists('dentist_id', $data)
            || array_key_exists('duration_minutes', $data);

        $dentistId = $data['dentist_id'] ?? $appointment->dentist_id;
        $durationMinutes = (int) ($data['duration_minutes'] ?? $appointment->duration_minutes);
        $startAt = array_key_exists('start_at', $data) ? Carbon::parse($data['start_at']) : $appointment->start_at->copy();
        $endAt = $startAt->copy()->addMinutes($durationMinutes);

        if ($movesSlot) {
            $this->assertNoDentistConflict($dentistId, $startAt, $endAt, $appointment->id);
            $this->assertNoPatientConflict(
                $appointment->patient_id,
                $startAt,
                $endAt,
                (bool) ($data['override_patient_conflict'] ?? false),
                $appointment->id
            );
            $this->assertWithinWorkingHours($dentistId, $startAt, $endAt, (bool) ($data['override_outside_working_hours'] ?? false));
        }

        return $this->runGuardedAgainstDentistRace(function () use ($appointment, $data, $dentistId, $durationMinutes, $startAt, $endAt, $movesSlot) {
            $appointment->fill(array_intersect_key($data, array_flip(['appointment_type_id', 'reason', 'notes'])));

            if ($movesSlot) {
                $appointment->dentist_id = $dentistId;
                $appointment->duration_minutes = $durationMinutes;
                $appointment->start_at = $startAt;
                $appointment->end_at = $endAt;

                if (array_key_exists('start_at', $data) || array_key_exists('dentist_id', $data)) {
                    $appointment->reschedule_count++;
                }
            }

            $appointment->save();

            return $appointment;
        });
    }

    public function cancel(Appointment $appointment, ?string $cancellationReason): Appointment
    {
        $this->assertCanTransitionTo($appointment, AppointmentStatus::Cancelled);

        $appointment->status = AppointmentStatus::Cancelled;
        $appointment->cancellation_reason = $cancellationReason;
        $appointment->cancelled_at = now();
        $appointment->cancelled_by = Auth::id();
        $appointment->save();

        event(new PatientActivityOccurred(
            $appointment, Auth::user(), 'appointment.cancelled', 'appointments', 'Appointment cancelled',
        ));

        return $appointment;
    }

    /**
     * @throws EarlyNoShowException if `start_at` hasn't passed yet and `$overrideEarly` is false
     *                              (soft rule, design doc §5.9/§13).
     */
    public function markNoShow(Appointment $appointment, bool $overrideEarly = false): Appointment
    {
        $this->assertCanTransitionTo($appointment, AppointmentStatus::NoShow);

        if (! $overrideEarly && $appointment->start_at->isFuture()) {
            throw new EarlyNoShowException;
        }

        $appointment->status = AppointmentStatus::NoShow;
        $appointment->no_show_at = now();
        $appointment->save();

        event(new PatientActivityOccurred(
            $appointment, Auth::user(), 'appointment.no_show', 'appointments', 'Appointment marked as no-show',
        ));

        return $appointment;
    }

    public function confirm(Appointment $appointment): Appointment
    {
        return $this->transitionTo($appointment, AppointmentStatus::Confirmed);
    }

    public function checkIn(Appointment $appointment): Appointment
    {
        return $this->transitionTo($appointment, AppointmentStatus::CheckedIn, ['checked_in_at' => now()]);
    }

    public function start(Appointment $appointment): Appointment
    {
        return $this->transitionTo($appointment, AppointmentStatus::InProgress, ['started_at' => now()]);
    }

    public function complete(Appointment $appointment): Appointment
    {
        return $this->transitionTo($appointment, AppointmentStatus::Completed, ['completed_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $extraAttributes
     */
    /**
     * @var array<string, string> Human-readable summary per reachable status — confirm/checkIn/
     *                            start/complete all funnel through here, so one dispatch site
     *                            covers all 4 §5-inventory events instead of 4 separate ones.
     */
    private const TRANSITION_SUMMARIES = [
        'confirmed' => 'Appointment confirmed',
        'checked_in' => 'Patient checked in',
        'in_progress' => 'Appointment started',
        'completed' => 'Appointment completed',
    ];

    private function transitionTo(Appointment $appointment, AppointmentStatus $to, array $extraAttributes = []): Appointment
    {
        $this->assertCanTransitionTo($appointment, $to);

        $appointment->fill($extraAttributes);
        $appointment->status = $to;
        $appointment->save();

        event(new PatientActivityOccurred(
            $appointment, Auth::user(), "appointment.{$to->value}", 'appointments', self::TRANSITION_SUMMARIES[$to->value],
        ));

        return $appointment;
    }

    private function assertCanTransitionTo(Appointment $appointment, AppointmentStatus $to): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$appointment->status->value];

        if (! in_array($to->value, $allowed, true)) {
            throw new InvalidStatusTransitionException($appointment->status, $to);
        }
    }

    private function assertNoDentistConflict(string $dentistId, CarbonInterface $startAt, CarbonInterface $endAt, ?string $excludeAppointmentId = null): void
    {
        if ($this->dentistHasConflict($dentistId, $startAt, $endAt, $excludeAppointmentId)) {
            throw new DentistConflictException;
        }
    }

    private function assertNoPatientConflict(
        string $patientId,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        bool $override,
        ?string $excludeAppointmentId = null
    ): void {
        if (! $override && $this->patientHasConflict($patientId, $startAt, $endAt, $excludeAppointmentId)) {
            throw new PatientConflictException;
        }
    }

    private function assertWithinWorkingHours(string $dentistId, CarbonInterface $startAt, CarbonInterface $endAt, bool $override): void
    {
        if (! $override && $this->isOutsideWorkingHours($dentistId, $startAt, $endAt)) {
            throw new OutsideWorkingHoursException;
        }
    }

    /**
     * Runs the write inside a transaction and translates a Postgres `appointments_no_overlapping_dentist_slots`
     * EXCLUDE-constraint violation into the same domain exception the app-level pre-check throws
     * (design doc §10's "belt-and-suspenders" race-condition guarantee — SQLite has no
     * equivalent constraint, so this only ever fires against Postgres).
     */
    private function runGuardedAgainstDentistRace(callable $callback): Appointment
    {
        try {
            return DB::transaction($callback);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'appointments_no_overlapping_dentist_slots')) {
                throw new DentistConflictException;
            }

            throw $exception;
        }
    }
}
