<?php

namespace App\Http\Requests\TreatmentPlanItem;

use App\Enums\DentalConditionCategory;
use App\Models\Appointment;
use App\Models\DentalChartEntry;
use App\Models\TreatmentPlanItem;
use App\Rules\BelongsToPatient;
use App\Rules\RequiresCostWhenNoDefaultPrice;
use App\Rules\ValidDentalChartSurfaces;
use App\Support\ToothChart;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTreatmentPlanItemRequest extends FormRequest
{
    /**
     * `treatment_plan_id` is not a field here — the parent plan comes from the nested route
     * (`treatment-plans/{treatment_plan}/items`, design doc §9). `procedure_name`/
     * `procedure_description` are not fields either — they are always computed server-side from
     * the resolved `dental_condition_id` by `TreatmentPlanService::addItem()`, never accepted from
     * the client (design doc §6/§8, Decision 2 — a snapshot must reflect what the catalog actually
     * said, not an arbitrary client-supplied string). `status` is not a field — a new item always
     * starts `planned` (design doc §5).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', TreatmentPlanItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patientId = $this->route('treatment_plan')?->patient_id;

        return [
            'dental_condition_id' => [
                'required', 'uuid',
                Rule::exists('dental_conditions', 'id')->where(
                    fn (Builder $query) => $query->where('category', DentalConditionCategory::Procedure->value)
                ),
            ],

            // Traceability link to the Dental Chart finding this item addresses — must belong to
            // the same patient as the parent plan (explicit design requirement, §9). Read-only
            // reference: this module never writes to dental_chart_entries (design doc §7).
            'diagnosis_entry_id' => ['nullable', 'uuid', new BelongsToPatient(DentalChartEntry::class, $patientId)],

            'tooth_number' => [
                'nullable', 'string',
                // Required whenever surfaces are given — a surface with no tooth is meaningless.
                // Unlike dental_chart_entries, tooth_number itself stays optional otherwise: not
                // every planned procedure is tooth-specific (design doc §6).
                'required_with:surfaces',
                function ($attribute, $value, $fail) {
                    if ($value !== null && ! ToothChart::isValidCode($value)) {
                        $fail('The selected tooth number is not a valid FDI tooth code.');
                    }
                },
            ],
            'surfaces' => ['array', new ValidDentalChartSurfaces],
            'surfaces.*' => [Rule::in(['M', 'D', 'F', 'L', 'O', 'I'])],

            'quantity' => ['nullable', 'integer', 'min:1'],

            // Nullable at the HTTP layer only so it can default from the catalog's
            // dental_conditions.default_cost (design doc §6/§8) — but a cost must be resolvable
            // from *somewhere*: if the catalog entry itself has no default, the client must supply
            // one (design requirement: "validate snapshot fields are present where required").
            'unit_cost' => ['nullable', 'numeric', 'min:0', new RequiresCostWhenNoDefaultPrice],

            'phase' => ['nullable', 'integer', 'min:1'],
            'sequence' => ['nullable', 'integer', 'min:0'],

            // Set once the patient books this specific item (design doc §3/§7) — must belong to
            // the same patient as the parent plan. One-way reference: never mutated back.
            'appointment_id' => ['nullable', 'uuid', new BelongsToPatient(Appointment::class, $patientId)],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
