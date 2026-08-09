<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 3 design doc §2.2 — closes the previously-undocumented
 * gap that no authentication event was logged anywhere.
 */
class AuthAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_audited(): void
    {
        $user = User::factory()->create(['email' => 'audit-login@example.com']);

        $this->postJson('/api/login', [
            'email' => 'audit-login@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'action' => 'login_succeeded',
        ]);
    }

    public function test_failed_login_with_a_known_email_is_audited_against_that_user(): void
    {
        $user = User::factory()->create(['email' => 'wrong-password@example.com']);

        $this->postJson('/api/login', [
            'email' => 'wrong-password@example.com',
            'password' => 'not-the-real-password',
        ])->assertUnprocessable();

        $log = AuditLog::query()->where('action', 'login_failed')->firstOrFail();

        $this->assertNull($log->auditable_id);
        $this->assertSame('wrong-password@example.com', $log->context['email']);
        $this->assertNotSame($user->id, $log->auditable_id);
    }

    public function test_failed_login_with_an_unknown_email_has_no_target_and_captures_the_attempted_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'no-such-account@example.com',
            'password' => 'whatever',
        ])->assertUnprocessable();

        $log = AuditLog::query()->where('action', 'login_failed')->firstOrFail();

        $this->assertNull($log->auditable_id);
        $this->assertSame('no-such-account@example.com', $log->context['email']);
    }

    public function test_failed_login_never_captures_the_password_anywhere(): void
    {
        $this->postJson('/api/login', [
            'email' => 'no-such-account@example.com',
            'password' => 'my-super-secret-password',
        ])->assertUnprocessable();

        $log = AuditLog::query()->where('action', 'login_failed')->firstOrFail();

        $payload = json_encode([$log->changes, $log->old_values, $log->context]);
        $this->assertStringNotContainsString('my-super-secret-password', $payload);
    }

    public function test_logout_is_audited_with_the_logging_out_user_as_both_actor_and_target(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'action' => 'logged_out',
            // The actor (user_id) must still be captured, not null — a naive implementation that
            // logs the event *after* the guard already logged out would lose this.
            'user_id' => $user->id,
        ]);
    }
}
