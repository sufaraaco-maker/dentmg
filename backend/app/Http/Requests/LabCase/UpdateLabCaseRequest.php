<?php

namespace App\Http\Requests\LabCase;

use App\Models\Appointment;
use App\Models\TreatmentPlanItem;
use App\Rules\BelongsToPatient;
use App\Support\ToothChart;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLabCaseRequest extends FormRequest
{
    /**
     * `patient_id` is not editable — immutable after creation, same "the party of a record never
     * changes" rule as purchase_orders.supplier_id (design doc §3a/§5). Whether the case is
     * actually still Draft is a LabCaseService concern, not this ability's.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lab_case'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patientId = $this->route('lab_case')?->patient_id;

        return [
            'lab_id' => ['sometimes', 'required', 'uuid', 'exists:labs,id'],
            'dentist_id' => ['nullable', 'uuid', 'exists:users,id'],
            'treatment_plan_item_id' => ['nullable', 'uuid', new BelongsToPatient(TreatmentPlanItem::class, $patientId)],
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
            'due_at' => ['nullable', 'date'],
        ];
    }
}
