<?php

namespace Tests\Feature;

use App\Events\PatientActivityOccurred;
use App\Listeners\SendsNotifications;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SECURITY (found in review, fixed here): `SendsNotifications` is `ShouldQueue`, so every dispatch
 * of `PatientActivityOccurred` is wrapped in a real `CallQueuedListener` and serialized (PHP
 * `serialize()`, then base64'd) into the Redis payload the queue worker reads. Before this fix, the
 * event's `subject`/`actor` were plain `readonly` `Model` properties with no serialization contract
 * of their own, so PHP's default object serialization walked their full `$attributes` array —
 * meaning a dentist's bcrypt password hash, `remember_token`, and a patient's PHI sat in Redis, in
 * `failed_jobs` on error, and in any log/monitoring tool that captures job payloads.
 *
 * The fix is `Illuminate\Queue\SerializesModels` on the event class. `readonly` promoted properties
 * are exactly why this needed verifying rather than assuming: the trait's `__serialize()` only
 * *reads* the live properties (never mutates them), and `__unserialize()` writes them for the first
 * time on a freshly-allocated, not-yet-constructed object — which is the one case PHP's readonly
 * rules permit. Both are proven directly below, not asserted from documentation.
 */
class NotificationEventSerializationTest extends TestCase
{
    use RefreshDatabase;

    private function buildQueuedJob(Appointment $appointment, User $actor): CallQueuedListener
    {
        $event = new PatientActivityOccurred(
            $appointment,
            $actor,
            'appointment.cancelled',
            'appointments',
            'Patient called to cancel',
        );

        return new CallQueuedListener(SendsNotifications::class, 'handle', [$event]);
    }

    public function test_serialized_payload_never_contains_the_actors_password_hash_or_remember_token(): void
    {
        $dentist = User::factory()->dentist()->create();
        $actor = User::factory()->create(['password' => Hash::make('a-real-looking-secret-99')]);
        $appointment = Appointment::factory()->create(['dentist_id' => $dentist->id]);

        $payload = serialize($this->buildQueuedJob($appointment, $actor));

        $this->assertStringNotContainsString($actor->password, $payload);
        $this->assertStringNotContainsString('a-real-looking-secret-99', $payload);
        $this->assertStringNotContainsString('remember_token', $payload);
        $this->assertStringNotContainsString($actor->remember_token, $payload);
    }

    /**
     * `AppointmentController::cancel()` etc. don't eager-load relations today (see `TECH_DEBT.md`'s
     * "Appointment mutation endpoints don't eager-load relations" item), so `$appointment->patient`
     * is not loaded in the current call sites — but `SerializesModels` must not depend on that
     * staying true. This loads `patient`/`dentist` explicitly to prove the fix holds even if a
     * future call site (or a different subject type) fires the event with relations already
     * hydrated: `ModelIdentifier` carries only the relation *name*, never the loaded model's data.
     */
    public function test_serialized_payload_never_contains_patient_phi_even_with_the_relation_preloaded(): void
    {
        $dentist = User::factory()->dentist()->create();
        $actor = User::factory()->create();
        $patient = Patient::factory()->create([
            'first_name' => 'Zzq1UniquePhiFirstName',
            'last_name' => 'Zzq1UniquePhiLastName',
        ]);
        $appointment = Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'patient_id' => $patient->id,
        ])->load(['patient', 'dentist']);

        $payload = serialize($this->buildQueuedJob($appointment, $actor));

        $this->assertStringNotContainsString('Zzq1UniquePhiFirstName', $payload);
        $this->assertStringNotContainsString('Zzq1UniquePhiLastName', $payload);
        $this->assertStringNotContainsString($dentist->password, $payload);
    }

    /**
     * The payload shrinking to an identifier is only half the fix — the listener still has to be
     * able to do its job after deserialization. This drives the exact same path the queue worker
     * does: serialize → unserialize → call `handle()` on the restored event, and asserts a real
     * notification row is written, not just that the properties look right.
     */
    public function test_the_listener_still_works_correctly_after_a_real_serialize_unserialize_round_trip(): void
    {
        $dentist = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();
        $actor = User::factory()->create();
        $appointment = Appointment::factory()->create(['dentist_id' => $dentist->id]);

        $payload = serialize($this->buildQueuedJob($appointment, $actor));

        /** @var CallQueuedListener $restoredJob */
        $restoredJob = unserialize($payload);
        /** @var PatientActivityOccurred $restoredEvent */
        $restoredEvent = $restoredJob->data[0];

        $this->assertInstanceOf(Appointment::class, $restoredEvent->subject);
        $this->assertSame($appointment->id, $restoredEvent->subject->id);
        $this->assertInstanceOf(User::class, $restoredEvent->actor);
        $this->assertSame($actor->id, $restoredEvent->actor->id);

        (new SendsNotifications(app(NotificationService::class)))->handle($restoredEvent);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $dentist->id,
            'subject_id' => $appointment->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'subject_id' => $appointment->id,
        ]);
    }
}
