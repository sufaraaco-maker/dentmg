<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\LabCase;
use App\Models\Notification;
use App\Models\RolePermission;
use App\Models\Supply;
use App\Models\User;
use App\Policies\NotificationPolicy;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5C (Notification System — scheduled/administrative types) design doc §12 equivalent —
 * types 9-13. Mirrors NotificationDispatchTest's own pattern: drive the real Commands/Observer
 * rather than calling NotificationService by hand, since that is the only way to prove the whole
 * path (scope → dedup → recipients → authorization) actually works end to end.
 */
class NotificationPhase5CTest extends TestCase
{
    use RefreshDatabase;

    private function receptionist(): User
    {
        return User::factory()->create(['role' => UserRole::Receptionist]);
    }

    // ---------------------------------------------------------------------
    // Type 9 — lab_case.overdue (NotifyOverdueLabCases)
    // ---------------------------------------------------------------------

    public function test_overdue_lab_cases_notify_the_prescribing_dentist_and_receptionists(): void
    {
        $dentist = User::factory()->dentist()->create();
        $receptionist = $this->receptionist();
        User::factory()->admin()->create();

        LabCase::factory()->sent()->create([
            'dentist_id' => $dentist->id,
            'due_at' => now()->subDay(),
        ]);

        $this->artisan('notifications:lab-cases-overdue')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$dentist->id, $receptionist->id],
            Notification::query()->pluck('notifiable_id')->all(),
        );
        $this->assertSame('laboratory', Notification::query()->first()->category);
    }

    public function test_a_lab_case_not_yet_due_produces_no_notification(): void
    {
        $dentist = User::factory()->dentist()->create();
        $this->receptionist();

        LabCase::factory()->sent()->create([
            'dentist_id' => $dentist->id,
            'due_at' => now()->addDay(),
        ]);

        $this->artisan('notifications:lab-cases-overdue')->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_an_already_received_case_is_never_flagged_overdue_even_past_its_due_date(): void
    {
        $dentist = User::factory()->dentist()->create();
        $this->receptionist();

        LabCase::factory()->received()->create(['dentist_id' => $dentist->id]);

        $this->artisan('notifications:lab-cases-overdue')->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_rerunning_the_overdue_check_does_not_duplicate_an_unread_notification(): void
    {
        $dentist = User::factory()->dentist()->create();
        $this->receptionist();

        LabCase::factory()->sent()->create([
            'dentist_id' => $dentist->id,
            'due_at' => now()->subDay(),
        ]);

        $this->artisan('notifications:lab-cases-overdue')->assertSuccessful();
        $this->artisan('notifications:lab-cases-overdue')->assertSuccessful();

        $this->assertSame(1, Notification::query()->where('notifiable_id', $dentist->id)->count());
    }

    public function test_reading_the_notification_lets_the_next_run_resurface_it(): void
    {
        $dentist = User::factory()->dentist()->create();
        $this->receptionist();

        $labCase = LabCase::factory()->sent()->create([
            'dentist_id' => $dentist->id,
            'due_at' => now()->subDay(),
        ]);

        $this->artisan('notifications:lab-cases-overdue')->assertSuccessful();
        Notification::query()->where('notifiable_id', $dentist->id)->update(['read_at' => now()]);

        $this->artisan('notifications:lab-cases-overdue')->assertSuccessful();

        $this->assertSame(2, Notification::query()->where('notifiable_id', $dentist->id)->count());
        $this->assertSame($labCase->id, Notification::query()->first()->subject_id);
    }

    // ---------------------------------------------------------------------
    // Type 10 — inventory.low_stock (NotifyLowStockDigest)
    // ---------------------------------------------------------------------

    public function test_low_stock_supplies_produce_one_digest_notification_to_admins_and_receptionists(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = $this->receptionist();
        $dentist = User::factory()->dentist()->create();

        // No StockMovement rows at all => quantity_on_hand is 0, and every factory-default
        // reorder_level is >= 5, so a freshly created active Supply is low-stock by construction.
        Supply::factory()->count(3)->create();

        $this->artisan('notifications:low-stock-digest')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$admin->id, $receptionist->id],
            Notification::query()->pluck('notifiable_id')->all(),
        );
        $this->assertNotContains($dentist->id, Notification::query()->pluck('notifiable_id')->all());

        $notification = Notification::query()->where('notifiable_id', $admin->id)->sole();
        $this->assertSame('inventory', $notification->category);
        $this->assertSame(Supply::class, $notification->subject_type);
        $this->assertSame(3, $notification->data['params']['count']);
    }

    public function test_no_low_stock_supplies_means_no_notification(): void
    {
        User::factory()->admin()->create();

        $this->artisan('notifications:low-stock-digest')->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_an_inactive_low_stock_supply_is_excluded(): void
    {
        User::factory()->admin()->create();
        Supply::factory()->create(['is_active' => false]);

        $this->artisan('notifications:low-stock-digest')->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    // ---------------------------------------------------------------------
    // Type 11 — appointment.unconfirmed (NotifyUnconfirmedAppointments)
    // ---------------------------------------------------------------------

    public function test_tomorrows_still_scheduled_appointment_notifies_front_desk(): void
    {
        $admin = User::factory()->admin()->create();
        $receptionist = $this->receptionist();
        $dentist = User::factory()->dentist()->create();

        Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(10, 30),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->artisan('notifications:appointments-unconfirmed')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$admin->id, $receptionist->id],
            Notification::query()->pluck('notifiable_id')->all(),
        );
    }

    public function test_a_confirmed_appointment_tomorrow_produces_no_notification(): void
    {
        User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(10, 30),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $this->artisan('notifications:appointments-unconfirmed')->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_a_scheduled_appointment_the_day_after_tomorrow_is_not_yet_flagged(): void
    {
        User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => now()->addDays(2)->setTime(10, 0),
            'end_at' => now()->addDays(2)->setTime(10, 30),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->artisan('notifications:appointments-unconfirmed')->assertSuccessful();

        $this->assertSame(0, Notification::query()->count());
    }

    // ---------------------------------------------------------------------
    // Type 12 — security.repeated_login_failures (AuditLogObserver, reactive)
    // ---------------------------------------------------------------------

    public function test_the_fifth_failed_login_for_one_email_within_the_window_notifies_admins(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'target@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'target@example.com',
                'password' => 'not-the-real-password',
            ])->assertUnprocessable();
        }

        $this->assertSame(5, AuditLog::query()->where('action', 'login_failed')->count());
        $notification = Notification::query()->where('notifiable_id', $admin->id)->sole();
        $this->assertSame('security', $notification->category);
        $this->assertSame('target@example.com', $notification->data['params']['email']);
    }

    public function test_fewer_than_five_failed_logins_notifies_nobody(): void
    {
        User::factory()->admin()->create();
        User::factory()->create(['email' => 'target2@example.com']);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/login', [
                'email' => 'target2@example.com',
                'password' => 'not-the-real-password',
            ])->assertUnprocessable();
        }

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_a_sixth_failure_within_the_same_window_does_not_duplicate_the_alert(): void
    {
        $admin = User::factory()->admin()->create();

        for ($i = 0; $i < 5; $i++) {
            AuditLog::query()->create([
                'action' => 'login_failed',
                'auditable_type' => User::class,
                'auditable_id' => null,
                'context' => ['email' => 'flooded@example.com'],
            ]);
        }
        $this->assertSame(1, Notification::query()->where('notifiable_id', $admin->id)->count());

        // A 6th failure in the same window: count is now 6, not 5 — the observer's own dedup
        // (notify on the exact threshold match, not >=) must not fire again.
        AuditLog::query()->create([
            'action' => 'login_failed',
            'auditable_type' => User::class,
            'auditable_id' => null,
            'context' => ['email' => 'flooded@example.com'],
        ]);

        $this->assertSame(1, Notification::query()->where('notifiable_id', $admin->id)->count());
    }

    public function test_failures_for_different_emails_are_counted_separately(): void
    {
        User::factory()->admin()->create();

        for ($i = 0; $i < 4; $i++) {
            AuditLog::query()->create([
                'action' => 'login_failed',
                'auditable_type' => User::class,
                'auditable_id' => null,
                'context' => ['email' => 'person-a@example.com'],
            ]);
        }
        AuditLog::query()->create([
            'action' => 'login_failed',
            'auditable_type' => User::class,
            'auditable_id' => null,
            'context' => ['email' => 'person-b@example.com'],
        ]);

        $this->assertSame(0, Notification::query()->count());
    }

    // ---------------------------------------------------------------------
    // Type 13 — permissions.matrix_updated (AuditLogObserver, reactive)
    // ---------------------------------------------------------------------

    public function test_a_permissions_matrix_update_notifies_admins_except_the_actor(): void
    {
        $actingAdmin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $service = app(RolePermissionService::class);
        // updateMatrix() is a full replace (design doc precedent), so the real call always sends
        // every role's complete assignment — reuse the current matrix rather than passing a partial
        // one and silently wiping the other two roles' permissions out from under this test.
        $matrix = $service->matrix();

        $this->actingAs($actingAdmin);
        $service->updateMatrix($matrix);

        $this->assertSame([$otherAdmin->id], Notification::query()->pluck('notifiable_id')->all());
        $this->assertSame('security', Notification::query()->sole()->category);
    }

    // ---------------------------------------------------------------------
    // NotificationPolicy — inventory/security category authorization (design doc §8.1/§8.2)
    // ---------------------------------------------------------------------

    public function test_only_admins_can_see_the_security_category(): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $this->assertContains('security', NotificationPolicy::allowedCategories($admin));
        $this->assertNotContains('security', NotificationPolicy::allowedCategories($dentist));
    }

    public function test_the_inventory_category_follows_supplies_view_like_any_other_policy_backed_category(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertContains('inventory', NotificationPolicy::allowedCategories($admin));

        RolePermission::query()
            ->where('role', UserRole::Admin->value)
            ->where('permission_key', 'supplies.view')
            ->delete();
        RolePermission::flushCache();

        $this->assertNotContains('inventory', NotificationPolicy::allowedCategories($admin->fresh()));
    }

    public function test_the_index_endpoint_accepts_the_two_new_categories_as_valid_filters(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/notifications?category=security')
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/notifications?category=inventory')
            ->assertOk();
    }
}
