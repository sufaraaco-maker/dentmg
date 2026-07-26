<?php

namespace App\Http\Requests\ClinicalNote;

use App\Enums\ClinicalNoteType;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Rules\BelongsToPatient;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalNoteRequest extends FormRequest
{
    /**
     * `patient_id` is not a field — the parent Patient comes from the nested route
     * (`patients/{patient}/clinical-notes`, design doc §9), same pattern as
     * `StoreTreatmentPlanRequest`/`StorePaymentRequest`. `status` is not a field either — a new note
     * always starts `draft` (design doc §5); `ClinicalNoteService::create()` sets it, never the
     * client.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ClinicalNote::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patientId = $this->route('patient')?->id;

        return [
            'dentist_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],

            // Not every note is visit-scoped (design doc §2/§6) — must belong to the same patient
            // as the parent route when given.
            'appointment_id' => ['nullable', 'uuid', new BelongsToPatient(Appointment::class, $patientId)],

            'note_type' => ['required', Rule::enum(ClinicalNoteType::class)],

            'chief_complaint' => ['nullable', 'string', 'max:1000'],
            'subjective' => ['nullable', 'string', 'max:10000'],
            'objective' => ['nullable', 'string', 'max:10000'],
            'assessment' => ['nullable', 'string', 'max:10000'],
            'plan' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
