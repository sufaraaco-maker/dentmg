<?php

namespace App\Http\Requests\ClinicalNote;

use App\Enums\ClinicalNoteType;
use App\Models\Appointment;
use App\Rules\BelongsToPatient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClinicalNoteRequest extends FormRequest
{
    /**
     * `dentist_id`/`patient_id` are not fields — the author of record is fixed at creation
     * (design doc §7/§8, `ClinicalNoteService::EDITABLE_WHILE_DRAFT`). Whether the note is actually
     * still editable in its current status (draft-only) is a `ClinicalNoteService` concern
     * (throws `ClinicalNoteLockedException`), not this layer's.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('clinical_note'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $patientId = $this->route('clinical_note')?->patient_id;

        return [
            'appointment_id' => ['sometimes', 'nullable', 'uuid', new BelongsToPatient(Appointment::class, $patientId)],
            'note_type' => ['sometimes', Rule::enum(ClinicalNoteType::class)],
            'chief_complaint' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'subjective' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'objective' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'assessment' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
