<?php

namespace App\Http\Requests\PatientImage;

use App\Enums\ImageType;
use App\Models\Appointment;
use App\Models\TreatmentPlanItem;
use App\Rules\BelongsToPatient;
use App\Support\ToothChart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Metadata only — the image file itself is immutable once uploaded (design doc §4/§12). No `images`/
 * file field exists here at all.
 */
class UpdatePatientImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('patient_image'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patientId = $this->route('patient_image')?->patient_id;

        return [
            'image_type' => ['sometimes', new Enum(ImageType::class)],

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

            'taken_at' => ['sometimes', 'date', 'before_or_equal:today'],

            // See StorePatientImageRequest for why this can't use App\Rules\BelongsToPatient like
            // appointment_id below — TreatmentPlanItem has no direct patient_id column.
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
