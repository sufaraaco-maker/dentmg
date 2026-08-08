<?php

namespace Tests\Unit\Services;

use App\Enums\MedicalConditionStatus;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientMedicalCondition;
use App\Models\PatientMedication;
use App\Models\User;
use App\Services\MedicalHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private MedicalHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MedicalHistoryService;
    }

    // ---- Allergies ------------------------------------------------------------------------

    public function test_add_allergy_stamps_the_creating_actor(): void
    {
        $patient = Patient::factory()->create();
        $actor = User::factory()->admin()->create();

        $allergy = $this->service->addAllergy([
            'patient_id' => $patient->id,
            'allergen' => 'Penicillin',
            'severity' => 'severe',
        ], $actor);

        $this->assertSame($patient->id, $allergy->patient_id);
        $this->assertSame('Penicillin', $allergy->allergen);
        $this->assertSame($actor->id, $allergy->created_by_id);
        $this->assertNull($allergy->updated_by_id);
    }

    public function test_update_allergy_stamps_the_updating_actor(): void
    {
        $allergy = PatientAllergy::factory()->create(['allergen' => 'Old']);
        $actor = User::factory()->dentist()->create();

        $updated = $this->service->updateAllergy($allergy, ['allergen' => 'New'], $actor);

        $this->assertSame('New', $updated->allergen);
        $this->assertSame($actor->id, $updated->updated_by_id);
    }

    public function test_delete_allergy_soft_deletes(): void
    {
        $allergy = PatientAllergy::factory()->create();

        $this->service->deleteAllergy($allergy);

        $this->assertSoftDeleted('patient_allergies', ['id' => $allergy->id]);
    }

    // ---- Medical Conditions -----------------------------------------------------------------

    public function test_add_condition_defaults_status_to_active(): void
    {
        $patient = Patient::factory()->create();
        $actor = User::factory()->admin()->create();

        $condition = $this->service->addCondition([
            'patient_id' => $patient->id,
            'condition_name' => 'Asthma',
        ], $actor);

        $this->assertSame(MedicalConditionStatus::Active, $condition->status);
        $this->assertSame($actor->id, $condition->created_by_id);
    }

    public function test_update_condition_stamps_the_updating_actor(): void
    {
        $condition = PatientMedicalCondition::factory()->create(['status' => MedicalConditionStatus::Active]);
        $actor = User::factory()->admin()->create();

        $updated = $this->service->updateCondition($condition, ['status' => 'resolved'], $actor);

        $this->assertSame(MedicalConditionStatus::Resolved, $updated->status);
        $this->assertSame($actor->id, $updated->updated_by_id);
    }

    public function test_delete_condition_soft_deletes(): void
    {
        $condition = PatientMedicalCondition::factory()->create();

        $this->service->deleteCondition($condition);

        $this->assertSoftDeleted('patient_medical_conditions', ['id' => $condition->id]);
    }

    // ---- Medications ------------------------------------------------------------------------

    public function test_add_medication_defaults_is_current_to_true(): void
    {
        $patient = Patient::factory()->create();
        $actor = User::factory()->admin()->create();

        $medication = $this->service->addMedication([
            'patient_id' => $patient->id,
            'medication_name' => 'Metformin',
        ], $actor);

        $this->assertTrue($medication->is_current);
        $this->assertSame($actor->id, $medication->created_by_id);
    }

    public function test_update_medication_stamps_the_updating_actor(): void
    {
        $medication = PatientMedication::factory()->create(['is_current' => true]);
        $actor = User::factory()->dentist()->create();

        $updated = $this->service->updateMedication($medication, ['is_current' => false], $actor);

        $this->assertFalse($updated->is_current);
        $this->assertSame($actor->id, $updated->updated_by_id);
    }

    public function test_delete_medication_soft_deletes(): void
    {
        $medication = PatientMedication::factory()->create();

        $this->service->deleteMedication($medication);

        $this->assertSoftDeleted('patient_medications', ['id' => $medication->id]);
    }
}
