<?php

namespace App\Http\Requests\PatientImage;

use App\Enums\ImageType;
use App\Models\Appointment;
use App\Models\PatientImage;
use App\Models\TreatmentPlanItem;
use App\Rules\BelongsToPatient;
use App\Support\ToothChart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Accepts one or more files in a single request (design doc §6), all sharing the same metadata —
 * the common case is a batch of related exposures taken in the same visit. Metadata can be edited
 * per-image afterward via UpdatePatientImageRequest if a batch needs to be split apart.
 */
class StorePatientImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PatientImage::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patientId = $this->route('patient')?->id;

        return [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:15360'],

            'image_type' => ['required', new Enum(ImageType::class)],

            'tooth_number' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (! ToothChart::isValidCode($value)) {
                        $fail('The selected tooth number is not a valid FDI tooth code.');
                    }
                },
            ],
            'surfaces' => ['nullable', 'array'],
            'surfaces.*' => ['string', 'in:M,D,F,L,O,I'],

            'taken_at' => ['required', 'date', 'before_or_equal:today'],

            // NOT App\Rules\BelongsToPatient here, unlike appointment_id below: TreatmentPlanItem has
            // no direct patient_id column (only treatment_plan_id -> treatment_plans.patient_id) —
            // confirmed by reading the migration directly. Using BelongsToPatient against it throws a
            // real "column does not exist" SQL error on Postgres, a pre-existing bug shared by
            // Laboratory's identical-looking StoreLabCaseRequest/UpdateLabCaseRequest (never exercised
            // by that module's own tests — flagged in TECH_DEBT.md rather than fixed there, to keep
            // this branch's diff scoped to Imaging).
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

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
