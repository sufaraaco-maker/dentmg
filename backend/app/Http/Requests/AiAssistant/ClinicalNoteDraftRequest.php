<?php

namespace App\Http\Requests\AiAssistant;

use App\Models\ClinicalNote;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mirrors `ClinicalNotePolicy::update()` exactly (design doc §7) — receptionist has zero Clinical
 * Notes access today, unchanged here. Whether the note is actually still a draft is checked in
 * `AiAssistantService::draftClinicalNote()` via the existing `ClinicalNoteLockedException`.
 */
class ClinicalNoteDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ClinicalNote $clinicalNote */
        $clinicalNote = $this->route('clinical_note');

        return $this->user()->can('update', $clinicalNote);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shorthand' => ['required', 'string', 'max:4000'],
        ];
    }
}
