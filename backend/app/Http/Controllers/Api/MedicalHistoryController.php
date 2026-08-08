<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MedicalHistory\StoreAllergyRequest;
use App\Http\Requests\MedicalHistory\StoreMedicalConditionRequest;
use App\Http\Requests\MedicalHistory\StoreMedicationRequest;
use App\Http\Requests\MedicalHistory\UpdateAllergyRequest;
use App\Http\Requests\MedicalHistory\UpdateMedicalConditionRequest;
use App\Http\Requests\MedicalHistory\UpdateMedicationRequest;
use App\Http\Resources\PatientAllergyResource;
use App\Http\Resources\PatientMedicalConditionResource;
use App\Http\Resources\PatientMedicationResource;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientMedicalCondition;
use App\Models\PatientMedication;
use App\Services\MedicalHistoryService;

/**
 * One controller for all three Medical History entities, mirroring `MedicalHistoryService`'s
 * "one logical module" shape (design doc §6.3/§6.4) rather than three near-identical controllers.
 * Listing logic lives here via each model's `forPatient` scope, same convention every other
 * patient-scoped index action already uses (`ClinicalNoteController::index`).
 */
class MedicalHistoryController extends Controller
{
    /** @var list<string> */
    private const WITH = ['createdBy', 'updatedBy'];

    public function __construct(private MedicalHistoryService $medicalHistoryService) {}

    // ---- Allergies ----------------------------------------------------------------------

    public function indexAllergies(Patient $patient)
    {
        $this->authorize('viewAny', PatientAllergy::class);

        $allergies = PatientAllergy::query()
            ->forPatient($patient->id)
            ->with(self::WITH)
            ->latest('created_at')
            ->paginate(15);

        return PatientAllergyResource::collection($allergies);
    }

    public function storeAllergy(StoreAllergyRequest $request, Patient $patient)
    {
        $allergy = $this->medicalHistoryService->addAllergy(
            [...$request->validated(), 'patient_id' => $patient->id],
            $request->user()
        );

        return (new PatientAllergyResource($allergy->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function updateAllergy(UpdateAllergyRequest $request, PatientAllergy $allergy)
    {
        $updated = $this->medicalHistoryService->updateAllergy($allergy, $request->validated(), $request->user());

        return new PatientAllergyResource($updated->load(self::WITH));
    }

    public function destroyAllergy(PatientAllergy $allergy)
    {
        $this->authorize('delete', $allergy);

        $this->medicalHistoryService->deleteAllergy($allergy);

        return response()->noContent();
    }

    // ---- Medical Conditions ---------------------------------------------------------------

    public function indexConditions(Patient $patient)
    {
        $this->authorize('viewAny', PatientMedicalCondition::class);

        $conditions = PatientMedicalCondition::query()
            ->forPatient($patient->id)
            ->with(self::WITH)
            ->latest('created_at')
            ->paginate(15);

        return PatientMedicalConditionResource::collection($conditions);
    }

    public function storeCondition(StoreMedicalConditionRequest $request, Patient $patient)
    {
        $condition = $this->medicalHistoryService->addCondition(
            [...$request->validated(), 'patient_id' => $patient->id],
            $request->user()
        );

        return (new PatientMedicalConditionResource($condition->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function updateCondition(UpdateMedicalConditionRequest $request, PatientMedicalCondition $medical_condition)
    {
        $updated = $this->medicalHistoryService->updateCondition(
            $medical_condition,
            $request->validated(),
            $request->user()
        );

        return new PatientMedicalConditionResource($updated->load(self::WITH));
    }

    public function destroyCondition(PatientMedicalCondition $medical_condition)
    {
        $this->authorize('delete', $medical_condition);

        $this->medicalHistoryService->deleteCondition($medical_condition);

        return response()->noContent();
    }

    // ---- Medications ------------------------------------------------------------------------

    public function indexMedications(Patient $patient)
    {
        $this->authorize('viewAny', PatientMedication::class);

        $medications = PatientMedication::query()
            ->forPatient($patient->id)
            ->with(self::WITH)
            ->latest('created_at')
            ->paginate(15);

        return PatientMedicationResource::collection($medications);
    }

    public function storeMedication(StoreMedicationRequest $request, Patient $patient)
    {
        $medication = $this->medicalHistoryService->addMedication(
            [...$request->validated(), 'patient_id' => $patient->id],
            $request->user()
        );

        return (new PatientMedicationResource($medication->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function updateMedication(UpdateMedicationRequest $request, PatientMedication $medication)
    {
        $updated = $this->medicalHistoryService->updateMedication(
            $medication,
            $request->validated(),
            $request->user()
        );

        return new PatientMedicationResource($updated->load(self::WITH));
    }

    public function destroyMedication(PatientMedication $medication)
    {
        $this->authorize('delete', $medication);

        $this->medicalHistoryService->deleteMedication($medication);

        return response()->noContent();
    }
}
