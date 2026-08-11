<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\RolePermission;
use App\Models\User;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\LabCaseReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 5A design doc §12.1 — the read path: endpoint contracts plus the two authorization layers
 * that guard them (§8.1 structural ownership, §8.2 the read-time category re-check).
 */
class NotificationEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Writes a notification row directly. The dispatch path is already proven end-to-end in
     * NotificationDispatchTest; these tests are about what the endpoints do with rows that exist,
     * so building them directly keeps each test's intent visible instead of burying it in setup.
     */
    private function makeNotification(User $for, string $category = 'appointments', ?string $readAt = null): Notification
    {
        $appointment = Appointment::factory()->create();

        return Notification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => $category === 'laboratory'
                ? LabCaseReceivedNotification::class
                : AppointmentCancelledNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $for->id,
            'category' => $category,
            'subject_type' => Appointment::class,
            'subject_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'data' => ['type' => 'appointment.cancelled', 'params' => []],
            'read_at' => $readAt,
        ]);
    }

    // ---------------------------------------------------------------------
    // Authentication
    // ---------------------------------------------------------------------

    public function test_guest_cannot_reach_any_notification_endpoint(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
        $this->postJson('/api/notifications/'.Str::uuid().'/read')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // Layer 1 — structural ownership (design doc §8.1)
    // ---------------------------------------------------------------------

    public function test_a_user_only_ever_sees_their_own_notifications(): void
    {
        $alice = User::factory()->admin()->create();
        $bob = User::factory()->admin()->create();

        $hers = $this->makeNotification($alice);
        $this->makeNotification($bob);
        $this->makeNotification($bob);

        $response = $this->actingAs($alice)->getJson('/api/notifications');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($hers->id, $response->json('data.0.id'));
    }

    /**
     * 404, not 403 — the row is never in scope to begin with, because every query starts from
     * `$request->user()->notifications()`. There is no lookup-then-reject step that could be
     * forgotten.
     */
    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $alice = User::factory()->admin()->create();
        $bob = User::factory()->admin()->create();
        $bobs = $this->makeNotification($bob);

        $this->actingAs($alice)
            ->postJson("/api/notifications/{$bobs->id}/read")
            ->assertNotFound();

        $this->assertNull($bobs->fresh()->read_at);
    }

    public function test_mark_all_as_read_never_touches_another_users_notifications(): void
    {
        $alice = User::factory()->admin()->create();
        $bob = User::factory()->admin()->create();
        $this->makeNotification($alice);
        $bobs = $this->makeNotification($bob);

        $response = $this->actingAs($alice)->postJson('/api/notifications/read-all');

        $response->assertOk()->assertJson(['marked' => 1]);
        $this->assertNull($bobs->fresh()->read_at);
    }

    public function test_unread_count_is_scoped_to_the_caller(): void
    {
        $alice = User::factory()->admin()->create();
        $bob = User::factory()->admin()->create();
        $this->makeNotification($alice);
        $this->makeNotification($alice, readAt: now()->toDateTimeString());
        $this->makeNotification($bob);
        $this->makeNotification($bob);

        $this->actingAs($alice)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertExactJson(['count' => 1]);
    }

    // ---------------------------------------------------------------------
    // Layer 2 — read-time category re-check (design doc §8.2)
    // ---------------------------------------------------------------------

    /**
     * The permission-drift case this layer exists for, asserted directly: the notification is
     * created while the dentist can read Laboratory, then the permission is revoked. The row still
     * exists in the database, but must disappear from both the list and the count — a badge that
     * counted it would leak that it exists.
     */
    public function test_a_notification_disappears_when_its_category_permission_is_revoked_afterwards(): void
    {
        $dentist = User::factory()->dentist()->create();
        $this->makeNotification($dentist, 'laboratory');

        $this->actingAs($dentist)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        RolePermission::query()
            ->where('role', UserRole::Dentist->value)
            ->where('permission_key', 'lab_cases.view')
            ->delete();
        RolePermission::flushCache();

        $this->actingAs($dentist)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($dentist)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertExactJson(['count' => 0]);

        // The row was filtered out of the query, not deleted — this is a visibility rule.
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_a_hidden_notification_cannot_be_marked_as_read_either(): void
    {
        $dentist = User::factory()->dentist()->create();
        $hidden = $this->makeNotification($dentist, 'laboratory');

        RolePermission::query()
            ->where('role', UserRole::Dentist->value)
            ->where('permission_key', 'lab_cases.view')
            ->delete();
        RolePermission::flushCache();

        $this->actingAs($dentist)
            ->postJson("/api/notifications/{$hidden->id}/read")
            ->assertNotFound();
    }

    /**
     * Filtering by a category you cannot read must return nothing, not everything — the requested
     * category is intersected with the allowed set rather than replacing it.
     */
    public function test_filtering_by_a_forbidden_category_returns_nothing(): void
    {
        $dentist = User::factory()->dentist()->create();
        $this->makeNotification($dentist, 'appointments');
        $this->makeNotification($dentist, 'laboratory');

        RolePermission::query()
            ->where('role', UserRole::Dentist->value)
            ->where('permission_key', 'lab_cases.view')
            ->delete();
        RolePermission::flushCache();

        $this->actingAs($dentist)
            ->getJson('/api/notifications?category=laboratory')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    // ---------------------------------------------------------------------
    // Endpoint contracts (design doc §7)
    // ---------------------------------------------------------------------

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->admin()->create();
        $this->makeNotification($user);
        $this->makeNotification($user, readAt: now()->toDateTimeString());

        $this->actingAs($user)->getJson('/api/notifications?status=unread')
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->actingAs($user)->getJson('/api/notifications?status=read')
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->actingAs($user)->getJson('/api/notifications')
            ->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_index_filters_by_category(): void
    {
        $user = User::factory()->admin()->create();
        $this->makeNotification($user, 'appointments');
        $this->makeNotification($user, 'laboratory');

        $this->actingAs($user)->getJson('/api/notifications?category=laboratory')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.category', 'laboratory');
    }

    public function test_index_rejects_an_unknown_category(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/notifications?category=not-a-category')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_index_paginates_at_15_per_page_by_default(): void
    {
        $user = User::factory()->admin()->create();
        for ($i = 0; $i < 18; $i++) {
            $this->makeNotification($user);
        }

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(18, $response->json('meta.total'));
    }

    public function test_index_returns_newest_first(): void
    {
        $user = User::factory()->admin()->create();
        $older = $this->makeNotification($user);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = $this->makeNotification($user);

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
    }

    public function test_mark_as_read_is_idempotent(): void
    {
        $user = User::factory()->admin()->create();
        $notification = $this->makeNotification($user);

        $first = $this->actingAs($user)->postJson("/api/notifications/{$notification->id}/read");
        $first->assertOk();
        $readAt = $first->json('read_at');
        $this->assertNotNull($readAt);

        // A double-click must not move the timestamp or error.
        $this->travel(1)->minute();
        $this->actingAs($user)
            ->postJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('read_at', $readAt);
    }

    public function test_mark_all_as_read_honours_the_active_category_filter(): void
    {
        $user = User::factory()->admin()->create();
        $appointments = $this->makeNotification($user, 'appointments');
        $laboratory = $this->makeNotification($user, 'laboratory');

        $this->actingAs($user)
            ->postJson('/api/notifications/read-all', ['category' => 'laboratory'])
            ->assertOk()
            ->assertJson(['marked' => 1]);

        // Clearing Laboratory must not silently clear Appointments too.
        $this->assertNotNull($laboratory->fresh()->read_at);
        $this->assertNull($appointments->fresh()->read_at);
    }

    public function test_the_resource_exposes_the_payload_without_flattening_it(): void
    {
        $user = User::factory()->admin()->create();
        $notification = $this->makeNotification($user);

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertOk()->assertJsonPath('data.0.id', $notification->id);
        $this->assertSame('appointment.cancelled', $response->json('data.0.data.type'));
        $this->assertSame('appointments', $response->json('data.0.category'));
        $this->assertNull($response->json('data.0.read_at'));
    }
}
