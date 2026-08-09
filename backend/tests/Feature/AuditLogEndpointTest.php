<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Advanced Permissions & Audit) Step 3 design doc §2.6 — the general
 * (non-patient-scoped) Audit Log viewer. `Patient` is used as the fixture model — it's a real
 * `Auditable` model with simple CRUD.
 */
class AuditLogEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_audit_logs(): void
    {
        $this->getJson('/api/audit-logs')->assertUnauthorized();
    }

    public function test_non_admin_cannot_list_audit_logs(): void
    {
        $actor = User::factory()->dentist()->create();

        $this->actingAs($actor)->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_admin_can_list_audit_logs(): void
    {
        $admin = User::factory()->admin()->create();
        Patient::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/audit-logs');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_filters_by_action(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $patient = Patient::factory()->create();
        $patient->update(['phone' => '0509999999']);

        $response = $this->getJson('/api/audit-logs?action=updated');

        $response->assertOk();
        $actions = collect($response->json('data'))->pluck('action')->unique()->values()->all();
        $this->assertSame(['updated'], $actions);
    }

    public function test_filters_by_auditable_type(): void
    {
        // Creating the admin itself produces a User-type audit row (User is now Auditable) —
        // filtering by Patient::class must exclude it.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Patient::factory()->create();

        $response = $this->getJson('/api/audit-logs?auditable_type='.urlencode(Patient::class));

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('auditable_type')->unique()->values()->all();
        $this->assertSame([Patient::class], $types);
    }

    public function test_filters_by_user_id(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/patients', [
            'first_name' => 'Layla', 'last_name' => 'Hassan', 'date_of_birth' => '1990-05-10',
            'gender' => 'female', 'phone' => '0501234567',
        ])->assertCreated();

        $response = $this->actingAs($admin)->getJson('/api/audit-logs?user_id='.$otherAdmin->id);

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_filters_by_date_range(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Patient::factory()->create();

        $future = now()->addDays(5)->toDateString();
        $response = $this->getJson("/api/audit-logs?date_from={$future}");

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_response_is_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Patient::factory()->count(20)->create();

        $response = $this->getJson('/api/audit-logs');

        $response->assertOk();
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertLessThanOrEqual(15, count($response->json('data')));
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/audit-logs?date_from=2026-08-10&date_to=2026-08-01');

        $response->assertUnprocessable();
    }
}
