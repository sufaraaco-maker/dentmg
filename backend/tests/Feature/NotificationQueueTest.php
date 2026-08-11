<?php

namespace Tests\Feature;

use App\Listeners\RecordsPatientActivity;
use App\Listeners\SendsNotifications;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\PatientActivity;
use App\Models\User;
use App\Notifications\AppointmentCancelledNotification;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 5B (Notification System) — proves the queue/scheduler infrastructure this phase added is
 * genuinely wired up, not merely configured.
 *
 * This distinction is the entire point of Phase B. Before it, `QUEUE_CONNECTION=redis` had been
 * set since the project's first `.env` while **no worker process existed in either compose file**,
 * so anything `ShouldQueue` would have been enqueued and silently never run. These tests are the
 * regression guard against that state returning: if someone removes the queue container, the
 * compose file changes but these assertions still pass — so they are paired with a documented
 * manual verification (a real job observed going RUNNING -> DONE in `dentalsuite_queue`, recorded
 * in `docs/PROJECT_STATUS.md`), which is what actually proves the container consumes work.
 */
class NotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notification_listener_is_queued(): void
    {
        Queue::fake();

        $dentist = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create(['dentist_id' => $dentist->id]);

        $this->actingAs(User::factory()->create());
        (new AppointmentService)->cancel($appointment, 'Queued test');

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job) => $job->class === SendsNotifications::class,
        );

        // The stronger half of the assertion: with the queue faked, nothing ran the listener, so a
        // notification row must NOT exist. If this were still synchronous the row would be here,
        // and the assertion above would pass anyway on some other queued job.
        $this->assertSame(0, Notification::query()->count());

        // ...whereas the Timeline row, whose listener is deliberately still synchronous, is there.
        $this->assertSame(1, PatientActivity::query()->count());
    }

    /**
     * Load-bearing, not decoration: `InvoiceService::void()`, `PaymentService::refund()` and
     * `LabCaseService::receive()` all fire the event from inside a `DB::transaction()`. Without
     * `$afterCommit`, the worker can pick the job up before the transaction commits and find no
     * row — a race that only appears under load.
     */
    public function test_the_listener_defers_until_after_the_database_transaction_commits(): void
    {
        $this->assertTrue((new SendsNotifications(app(NotificationService::class)))->afterCommit);
    }

    /**
     * The Timeline listener must stay synchronous — a `PatientActivity` row is part of the record,
     * whereas a notification is a delivery side effect. Phase 5 deliberately did not change it.
     */
    public function test_the_timeline_listener_remains_synchronous(): void
    {
        $this->assertNotInstanceOf(
            ShouldQueue::class,
            new RecordsPatientActivity,
        );
    }

    // ---------------------------------------------------------------------
    // Pruning (design doc §6.4) — only reachable now that a scheduler exists
    // ---------------------------------------------------------------------

    private function makeNotification(User $for, ?string $readAt, string $createdAt): Notification
    {
        $appointment = Appointment::factory()->create();

        $notification = Notification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => AppointmentCancelledNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $for->id,
            'category' => 'appointments',
            'subject_type' => Appointment::class,
            'subject_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'data' => ['type' => 'appointment.cancelled', 'params' => []],
            'read_at' => $readAt,
        ]);

        $notification->forceFill(['created_at' => $createdAt])->save();

        return $notification;
    }

    public function test_pruning_deletes_read_notifications_past_the_retention_window(): void
    {
        $user = User::factory()->admin()->create();
        $old = $this->makeNotification(
            $user,
            now()->subDays(Notification::RETENTION_DAYS + 1)->toDateTimeString(),
            now()->subDays(Notification::RETENTION_DAYS + 1)->toDateTimeString(),
        );

        $this->artisan('model:prune', ['--model' => [Notification::class]])->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $old->id]);
    }

    /**
     * An unread notification is still an outstanding thing to look at — deleting one silently
     * would be worse than letting the table grow, so age alone never prunes it.
     */
    public function test_pruning_never_deletes_an_unread_notification_however_old(): void
    {
        $user = User::factory()->admin()->create();
        $ancientUnread = $this->makeNotification(
            $user,
            null,
            now()->subYears(3)->toDateTimeString(),
        );

        $this->artisan('model:prune', ['--model' => [Notification::class]])->assertSuccessful();

        $this->assertDatabaseHas('notifications', ['id' => $ancientUnread->id]);
    }

    public function test_pruning_keeps_recently_read_notifications(): void
    {
        $user = User::factory()->admin()->create();
        $recentlyRead = $this->makeNotification(
            $user,
            now()->subDay()->toDateTimeString(),
            now()->subDay()->toDateTimeString(),
        );

        $this->artisan('model:prune', ['--model' => [Notification::class]])->assertSuccessful();

        $this->assertDatabaseHas('notifications', ['id' => $recentlyRead->id]);
    }

    public function test_the_prune_task_is_actually_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')
            ->filter(fn (string $command) => str_contains($command, 'model:prune'));

        $this->assertNotEmpty(
            $commands,
            'model:prune is not scheduled — read notifications would accumulate forever.',
        );
        $this->assertStringContainsString('Notification', $commands->first());
    }
}
