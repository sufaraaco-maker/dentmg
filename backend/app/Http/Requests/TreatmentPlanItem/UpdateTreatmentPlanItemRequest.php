<?php

namespace App\Http\Requests\TreatmentPlanItem;

use App\Enums\DentalConditionCategory;
use App\Models\Appointment;
use App\Models\DentalChartEntry;
use App\Models\TreatmentPlanItem;
use App\Rules\BelongsToPatient;
use App\Rules\ValidDentalChartSurfaces;
use App\Support\ToothChart;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTreatmentPlanItemRequest extends FormRequest
{
    /**
     * Same shape as Store minus `status` (transitions only move through the dedicated
     * `/complete`/`/cancel` endpoints, design doc §5). Whether a given field is actually editable
     * right now is a `TreatmentPlanService` concern, not this layer's — two separate locks apply
     * there: a terminal item (`completed`/`cancelled`) rejects everything but `notes`, and a
     * non-terminal item whose *parent plan* has left `draft` rejects the "commercial offer" fields
     * specifically (`dental_condition_id`/`tooth_number`/`surfaces`/`quantity`/`unit_cost`, design
     * doc §6/§8, Decision 2) while `notes`/`appointment_id`/`diagnosis_entry_id`/`phase`/`sequence`
     * stay editable throughout — see `TreatmentPlanService::updateItem()`.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('treatment_plan_item'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TreatmentPlanItem|null $item */
        $item = $this->route('treatment_plan_item');
        $patientId = $item?->treatmentPlan?->patient_id;

        return [
            'dental_condition_id' => [
                'sometimes', 'required', 'uuid',
                Rule::exists('dental_conditions', 'id')->where(
                    fn (Builder $query) => $query->where('category', DentalConditionCategory::Procedure->value)
                ),
            ],
            'diagnosis_entry_id' => ['sometimes', 'nullable', 'uuid', new BelongsToPatient(DentalChartEntry::class, $patientId)],
            'tooth_number' => [
                'sometimes', 'nullable', 'string',
                'required_with:surfaces',
                function ($attribute, $value, $fail) {
                    if ($value !== null && ! ToothChart::isValidCode($value)) {
                        $fail('The selected tooth number is not a valid FDI tooth code.');
                    }
                },
            ],
            'surfaces' => [
                'sometimes', 'array',
                new ValidDentalChartSurfaces($item?->dental_condition_id, $item?->tooth_number),
            ],
            'surfaces.*' => [Rule::in(['M', 'D', 'F', 'L', 'O', 'I'])],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'unit_cost' => ['sometimes', 'numeric', 'min:0'],
            'phase' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sequence' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'appointment_id' => ['sometimes', 'nullable', 'uuid', new BelongsToPatient(Appointment::class, $patientId)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
