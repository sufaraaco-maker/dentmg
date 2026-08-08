<?php

namespace App\Http\Requests\LabCase;

use App\Models\Appointment;
use App\Models\LabCase;
use App\Models\TreatmentPlanItem;
use App\Rules\BelongsToPatient;
use App\Support\ToothChart;
use Illuminate\Foundation\Http\FormRequest;

class StoreLabCaseRequest extends FormRequest
{
    /**
     * `status` is not a field — a new case always starts Draft (design doc §4). `case_number`/
     * `sequence_number` are never accepted from the client — always assigned server-side by
     * LabCaseService::create() (design doc §3a).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', LabCase::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patientId = $this->input('patient_id');

        return [
            'patient_id' => ['required', 'uuid', 'exists:patients,id'],
            'lab_id' => ['required', 'uuid', 'exists:labs,id'],
            'dentist_id' => ['nullable', 'uuid', 'exists:users,id'],

            // Traceability links — must belong to the same patient as the case itself (design doc
            // §3, exact convention as Treatment Plan Item's diagnosis_entry_id/appointment_id).
            //
            // NOT App\Rules\BelongsToPatient for treatment_plan_item_id: TreatmentPlanItem has no
            // direct patient_id column (only treatment_plan_id -> treatment_plans.patient_id) —
            // using BelongsToPatient against it throws a real "column does not exist" SQL error on
            // Postgres. Fixed here per Phase 2.4 (patient-laboratory-redesign-design.md §5) using
            // the same whereHas('treatmentPlan', ...) pattern already proven correct in
            // StorePatientImageRequest — see TECH_DEBT.md's now-resolved entry for the history.
            'treatment_plan_item_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($patientId) {
                    $belongsToPatient = TreatmentPlanItem::query()
                        ->where('id', $value)
                        ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $patientId))
                        ->exists();

                    if (! $belongsToPatient) {
                        $fail('The selected treatment plan item does not belong to this patient.');
                    }
                },
            ],
            'appointment_id' => ['nullable', 'uuid', new BelongsToPatient(Appointment::class, $patientId)],

            'tooth_numbers' => ['nullable', 'array'],
            'tooth_numbers.*' => [
                'string',
                function ($attribute, $value, $fail) {
                    if (! ToothChart::isValidCode($value)) {
                        $fail('The selected tooth number is not a valid FDI tooth code.');
                    }
                },
            ],

            'case_type' => ['nullable', 'string', 'max:100'],
            'shade' => ['nullable', 'string', 'max:50'],
            'material' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
