<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientMedicalCondition;
use App\Models\PatientMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalHistoryTest extends TestCase
{
    use RefreshDatabase;

    // ---- Allergies ------------------------------------------------------------------------

    public function test_guest_cannot_list_allergies(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/patients/{$patient->id}/allergies");

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_a_patients_allergies(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();
        $other = Patient::factory()->create();

        PatientAllergy::factory()->count(2)->create(['patient_id' => $patient->id]);
        PatientAllergy::factory()->create(['patient_id' => $other->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/allergies");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_allergies_list_is_paginated(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        PatientAllergy::factory()->count(20)->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/allergies");

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    public function test_admin_can_create_an_allergy(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/allergies", [
            'allergen' => 'Penicillin',
            'severity' => 'severe',
            'reaction' => 'Anaphylaxis',
        ]);

        $response->assertCreated()->assertJson([
            'patient_id' => $patient->id,
            'allergen' => 'Penicillin',
            'severity' => 'severe',
        ]);
        $this->assertDatabaseHas('patient_allergies', [
            'id' => $response->json('id'),
            'patient_id' => $patient->id,
            'created_by_id' => $actor->id,
        ]);
    }

    public function test_dentist_can_create_an_allergy(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/allergies", [
            'allergen' => 'Latex',
        ]);

        $response->assertCreated();
    }

    public function test_receptionist_cannot_create_an_allergy(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/allergies", [
            'allergen' => 'Latex',
        ]);

        $response->assertForbidden();
    }

    public function test_create_allergy_requires_an_allergen(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/allergies", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['allergen']);
    }

    public function test_admin_can_update_an_allergy(): void
    {
        $actor = User::factory()->admin()->create();
        $allergy = PatientAllergy::factory()->create(['allergen' => 'Old']);

        $response = $this->actingAs($actor)->putJson("/api/allergies/{$allergy->id}", [
            'allergen' => 'New',
        ]);

        $response->assertOk()->assertJson(['allergen' => 'New']);
        $this->assertDatabaseHas('patient_allergies', ['id' => $allergy->id, 'updated_by_id' => $actor->id]);
    }

    public function test_receptionist_cannot_update_an_allergy(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $allergy = PatientAllergy::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/allergies/{$allergy->id}", [
            'allergen' => 'New',
        ]);

        $response->assertForbidden();
    }

    public function test_dentist_can_delete_an_allergy(): void
    {
        $actor = User::factory()->dentist()->create();
        $allergy = PatientAllergy::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/allergies/{$allergy->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('patient_allergies', ['id' => $allergy->id]);
    }

    public function test_receptionist_cannot_delete_an_allergy(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $allergy = PatientAllergy::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/allergies/{$allergy->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('patient_allergies', ['id' => $allergy->id, 'deleted_at' => null]);
    }

    // ---- Medical Conditions -----------------------------------------------------------------

    public function test_any_authenticated_role_can_list_a_patients_medical_conditions(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        PatientMedicalCondition::factory()->count(2)->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/medical-conditions");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_create_a_medical_condition(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/medical-conditions", [
            'condition_name' => 'Diabetes Type 2',
            'status' => 'chronic',
        ]);

        $response->assertCreated()->assertJson([
            'patient_id' => $patient->id,
            'condition_name' => 'Diabetes Type 2',
            'status' => 'chronic',
        ]);
    }

    public function test_medical_condition_status_defaults_to_active(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/medical-conditions", [
            'condition_name' => 'Asthma',
        ]);

        $response->assertCreated()->assertJson(['status' => 'active']);
    }

    public function test_receptionist_cannot_create_a_medical_condition(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/medical-conditions", [
            'condition_name' => 'Asthma',
        ]);

        $response->assertForbidden();
    }

    public function test_dentist_can_update_a_medical_condition(): void
    {
        $actor = User::factory()->dentist()->create();
        $condition = PatientMedicalCondition::factory()->create(['status' => 'active']);

        $response = $this->actingAs($actor)->putJson("/api/medical-conditions/{$condition->id}", [
            'status' => 'resolved',
        ]);

        $response->assertOk()->assertJson(['status' => 'resolved']);
    }

    public function test_admin_can_delete_a_medical_condition(): void
    {
        $actor = User::factory()->admin()->create();
        $condition = PatientMedicalCondition::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/medical-conditions/{$condition->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('patient_medical_conditions', ['id' => $condition->id]);
    }

    public function test_receptionist_cannot_delete_a_medical_condition(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $condition = PatientMedicalCondition::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/medical-conditions/{$condition->id}");

        $response->assertForbidden();
    }

    // ---- Medications ------------------------------------------------------------------------

    public function test_any_authenticated_role_can_list_a_patients_medications(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        PatientMedication::factory()->count(2)->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/medications");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_create_a_medication(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/medications", [
            'medication_name' => 'Metformin',
            'dosage' => '500mg',
            'frequency' => 'Twice daily',
        ]);

        $response->assertCreated()->assertJson([
            'patient_id' => $patient->id,
            'medication_name' => 'Metformin',
            'is_current' => true,
        ]);
    }

    public function test_receptionist_cannot_create_a_medication(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/medications", [
            'medication_name' => 'Metformin',
        ]);

        $response->assertForbidden();
    }

    public function test_medication_end_date_must_not_precede_start_date(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/medications", [
            'medication_name' => 'Metformin',
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-01',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
    }

    public function test_dentist_can_update_a_medication(): void
    {
        $actor = User::factory()->dentist()->create();
        $medication = PatientMedication::factory()->create(['is_current' => true]);

        $response = $this->actingAs($actor)->putJson("/api/medications/{$medication->id}", [
            'is_current' => false,
        ]);

        $response->assertOk()->assertJson(['is_current' => false]);
        $this->assertDatabaseHas('patient_medications', ['id' => $medication->id, 'updated_by_id' => $actor->id]);
    }

    public function test_admin_can_delete_a_medication(): void
    {
        $actor = User::factory()->admin()->create();
        $medication = PatientMedication::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/medications/{$medication->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('patient_medications', ['id' => $medication->id]);
    }

    public function test_receptionist_cannot_delete_a_medication(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $medication = PatientMedication::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/medications/{$medication->id}");

        $response->assertForbidden();
    }
}
