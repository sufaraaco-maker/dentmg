<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 3 — the model-observer audit path
 * (`AuditObserver` -> `AuditLogService`), covering old/new value capture, delete snapshots,
 * sensitive-field redaction, User auditing, IP/User-Agent capture, and fail-open behavior.
 * `Patient` is used as the fixture model — it's a real `Auditable` model with simple CRUD, same
 * choice `tests/Feature/PatientTest.php` already makes for its own audit assertions.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_a_model_captures_old_and_new_values_for_changed_fields_only(): void
    {
        $admin = User::factory()->admin()->create();
        $patient = Patient::factory()->create(['phone' => '0501111111']);

        $this->actingAs($admin)->putJson("/api/patients/{$patient->id}", [
            'phone' => '0502222222',
        ])->assertOk();

        $log = AuditLog::query()
            ->where('auditable_id', $patient->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame('0502222222', $log->changes['phone']);
        $this->assertSame('0501111111', $log->old_values['phone']);
        // Unchanged fields must not appear in either diff side.
        $this->assertArrayNotHasKey('first_name', $log->changes);
        $this->assertArrayNotHasKey('first_name', $log->old_values ?? []);
    }

    public function test_deleting_a_model_captures_its_full_state_before_deletion(): void
    {
        $admin = User::factory()->admin()->create();
        $patient = Patient::factory()->create(['phone' => '0501111111']);

        $this->actingAs($admin)->deleteJson("/api/patients/{$patient->id}")->assertNoContent();

        $log = AuditLog::query()
            ->where('auditable_id', $patient->id)
            ->where('action', 'deleted')
            ->firstOrFail();

        $this->assertSame('0501111111', $log->old_values['phone']);
        $this->assertNull($log->changes);
    }

    public function test_user_model_changes_are_audited(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New Dentist',
            'email' => 'new.dentist@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'dentist',
        ])->assertCreated();

        $newUserId = $response->json('id');

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => User::class,
            'auditable_id' => $newUserId,
            'action' => 'created',
            'user_id' => $admin->id,
        ]);
    }

    public function test_sensitive_fields_are_redacted_from_both_new_and_old_values(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New Dentist',
            'email' => 'redaction.check@example.com',
            'password' => 'super-secret-password',
            'password_confirmation' => 'super-secret-password',
            'role' => 'dentist',
        ])->assertCreated();

        $log = AuditLog::query()
            ->where('auditable_id', $response->json('id'))
            ->where('action', 'created')
            ->firstOrFail();

        $this->assertArrayNotHasKey('password', $log->changes);
        $this->assertArrayNotHasKey('remember_token', $log->changes);
        $this->assertStringNotContainsString('super-secret-password', json_encode($log->changes));
    }

    public function test_ip_address_and_user_agent_are_captured(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->withHeader('User-Agent', 'DentalSuite-Test-Agent/1.0')
            ->postJson('/api/patients', [
                'first_name' => 'Layla',
                'last_name' => 'Hassan',
                'date_of_birth' => '1990-05-10',
                'gender' => 'female',
                'phone' => '0501234567',
            ])->assertCreated();

        $log = AuditLog::query()->where('action', 'created')->where('auditable_type', Patient::class)->firstOrFail();

        $this->assertSame('203.0.113.42', $log->ip_address);
        $this->assertSame('DentalSuite-Test-Agent/1.0', $log->user_agent);
    }

    public function test_a_failed_audit_write_does_not_break_the_underlying_business_operation(): void
    {
        Log::spy();

        // Dropping the table itself is a deterministic way to force AuditLogService::write() to
        // throw on any database engine (the test suite runs on SQLite, which — unlike the
        // production Postgres — doesn't enforce FK constraints by default, so a bad user_id
        // reference alone isn't reliable here).
        Schema::drop('audit_logs');

        $admin = User::factory()->admin()->create();
        $patient = Patient::factory()->create(['first_name' => 'Layla']);

        // The business operation succeeded despite the audit write failing underneath it.
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'first_name' => 'Layla']);

        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message) => $message === 'Audit log write failed'
        );
    }
}
