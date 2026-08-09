<?php

namespace App\Services;

use App\Enums\MedicalConditionStatus;
use App\Events\PatientActivityOccurred;
use App\Models\PatientAllergy;
use App\Models\PatientMedicalCondition;
use App\Models\PatientMedication;
use App\Models\User;

/**
 * One service for all three Medical History entities (design doc §6.3) — they're one logical
 * module (allergies/conditions/medications all edited from the same tab), not three near-identical
 * services.
 */
class MedicalHistoryService
{
    /**
     * @param  array<string, mixed>  $data  Must include `patient_id` (set by the controller from
     *                                      the nested route).
     */
    public function addAllergy(array $data, User $actor): PatientAllergy
    {
        $allergy = PatientAllergy::create([
            'patient_id' => $data['patient_id'],
            'allergen' => $data['allergen'],
            'severity' => $data['severity'] ?? null,
            'reaction' => $data['reaction'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $actor->id,
        ]);

        event(new PatientActivityOccurred(
            $allergy, $actor, 'medical_history.allergy_added', 'medical_history', "Allergy added: {$allergy->allergen}",
        ));

        return $allergy;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAllergy(PatientAllergy $allergy, array $data, User $actor): PatientAllergy
    {
        $allergy->fill($data);
        $allergy->updated_by_id = $actor->id;
        $allergy->save();

        return $allergy;
    }

    public function deleteAllergy(PatientAllergy $allergy): void
    {
        $allergy->delete();
    }

    /**
     * @param  array<string, mixed>  $data  Must include `patient_id` (set by the controller from
     *                                      the nested route).
     */
    public function addCondition(array $data, User $actor): PatientMedicalCondition
    {
        $condition = PatientMedicalCondition::create([
            'patient_id' => $data['patient_id'],
            'condition_name' => $data['condition_name'],
            'status' => $data['status'] ?? MedicalConditionStatus::Active,
            'diagnosed_date' => $data['diagnosed_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $actor->id,
        ]);

        event(new PatientActivityOccurred(
            $condition, $actor, 'medical_history.condition_added', 'medical_history',
            "Medical condition added: {$condition->condition_name}",
        ));

        return $condition;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCondition(PatientMedicalCondition $condition, array $data, User $actor): PatientMedicalCondition
    {
        $condition->fill($data);
        $condition->updated_by_id = $actor->id;
        $condition->save();

        return $condition;
    }

    public function deleteCondition(PatientMedicalCondition $condition): void
    {
        $condition->delete();
    }

    /**
     * @param  array<string, mixed>  $data  Must include `patient_id` (set by the controller from
     *                                      the nested route).
     */
    public function addMedication(array $data, User $actor): PatientMedication
    {
        $medication = PatientMedication::create([
            'patient_id' => $data['patient_id'],
            'medication_name' => $data['medication_name'],
            'dosage' => $data['dosage'] ?? null,
            'frequency' => $data['frequency'] ?? null,
            'is_current' => $data['is_current'] ?? true,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_id' => $actor->id,
        ]);

        event(new PatientActivityOccurred(
            $medication, $actor, 'medical_history.medication_added', 'medical_history',
            "Medication added: {$medication->medication_name}",
        ));

        return $medication;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMedication(PatientMedication $medication, array $data, User $actor): PatientMedication
    {
        $medication->fill($data);
        $medication->updated_by_id = $actor->id;
        $medication->save();

        return $medication;
    }

    public function deleteMedication(PatientMedication $medication): void
    {
        $medication->delete();
    }
}
