<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Layla',
            'last_name' => 'Hassan',
            'date_of_birth' => '1990-05-10',
            'gender' => 'female',
            'phone' => '0501234567',
        ], $overrides);
    }

    public function test_guest_cannot_list_patients(): void
    {
        $response = $this->getJson('/api/patients');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_user_can_list_patients(): void
    {
        $actor = User::factory()->create();
        Patient::factory()->count(3)->create();

        $response = $this->actingAs($actor)->getJson('/api/patients');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_patients_can_be_searched_by_name_phone_or_national_id(): void
    {
        $actor = User::factory()->create();
        Patient::factory()->create(['first_name' => 'Ahmed', 'last_name' => 'Ali', 'phone' => '0501112222', 'national_id' => '11122233344']);
        Patient::factory()->create(['first_name' => 'Sara', 'last_name' => 'Omar', 'phone' => '0509998888', 'national_id' => '99988877766']);

        $response = $this->actingAs($actor)->getJson('/api/patients?search=Ahmed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Ahmed', $response->json('data.0.first_name'));
    }

    public function test_patient_search_is_case_insensitive(): void
    {
        // Regression test: Postgres's LIKE is case-sensitive by default (unlike SQLite's),
        // so this used to silently fail to match in the real database despite passing here.
        // PatientService must use whereLike()/orWhereLike(), not where(..., 'like', ...).
        $actor = User::factory()->create();
        Patient::factory()->create(['first_name' => 'Ahmed', 'last_name' => 'Ali']);

        $response = $this->actingAs($actor)->getJson('/api/patients?search=ahmed');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_receptionist_can_create_a_patient_with_generated_patient_code(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->postJson('/api/patients', $this->validPayload());

        $response->assertCreated();
        $this->assertSame('P-00001', $response->json('patient_code'));
        $this->assertDatabaseHas('patients', ['first_name' => 'Layla', 'last_name' => 'Hassan']);
    }

    public function test_patient_codes_increment_sequentially(): void
    {
        $actor = User::factory()->admin()->create();

        $this->actingAs($actor)->postJson('/api/patients', $this->validPayload(['phone' => '0501111111']));
        $response = $this->actingAs($actor)->postJson('/api/patients', $this->validPayload(['phone' => '0502222222']));

        $response->assertCreated();
        $this->assertSame('P-00002', $response->json('patient_code'));
    }

    public function test_dentist_cannot_create_a_patient(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson('/api/patients', $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseMissing('patients', ['first_name' => 'Layla']);
    }

    public function test_dentist_can_view_a_patient(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}");

        $response->assertOk()->assertJson(['id' => $patient->id]);
    }

    public function test_create_patient_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/patients', [
            'first_name' => '',
            'date_of_birth' => 'not-a-date',
            'gender' => 'unknown',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name', 'date_of_birth', 'gender', 'phone']);
    }

    public function test_create_patient_rejects_duplicate_national_id(): void
    {
        $actor = User::factory()->admin()->create();
        $existing = Patient::factory()->create(['national_id' => '12345678901']);

        $response = $this->actingAs($actor)->postJson('/api/patients', $this->validPayload([
            'national_id' => $existing->national_id,
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors('national_id');
    }

    public function test_receptionist_can_update_a_patient(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create(['phone' => '0500000000']);

        $response = $this->actingAs($actor)->putJson("/api/patients/{$patient->id}", [
            'phone' => '0509999999',
        ]);

        $response->assertOk()->assertJson(['phone' => '0509999999']);
    }

    public function test_dentist_cannot_update_a_patient(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create(['phone' => '0500000000']);

        $response = $this->actingAs($actor)->putJson("/api/patients/{$patient->id}", [
            'phone' => '0509999999',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'phone' => '0500000000']);
    }

    public function test_admin_can_delete_a_patient(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/patients/{$patient->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }

    public function test_receptionist_cannot_delete_a_patient(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/patients/{$patient->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'deleted_at' => null]);
    }

    public function test_creating_a_patient_writes_an_audit_log_entry(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/patients', $this->validPayload());
        $patientId = $response->json('id');

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Patient::class,
            'auditable_id' => $patientId,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }

    public function test_updating_a_patient_writes_an_audit_log_entry_with_changed_fields(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create(['phone' => '0500000000']);

        $this->actingAs($actor)->putJson("/api/patients/{$patient->id}", ['phone' => '0501111111']);

        $log = AuditLog::query()
            ->where('auditable_id', $patient->id)
            ->where('action', 'updated')
            ->firstOrFail();

        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame('0501111111', $log->changes['phone']);
    }

    public function test_admin_can_view_patient_audit_logs(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/audit-logs");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('created', $response->json('data.0.action'));
    }

    public function test_non_admin_cannot_view_patient_audit_logs(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/audit-logs");

        $response->assertForbidden();
    }
}
