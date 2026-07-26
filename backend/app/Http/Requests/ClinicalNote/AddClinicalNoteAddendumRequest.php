<?php

namespace App\Http\Requests\ClinicalNote;

use Illuminate\Foundation\Http\FormRequest;

class AddClinicalNoteAddendumRequest extends FormRequest
{
    /**
     * Whether the parent note is actually `signed` yet (design doc §8 rule 4) is a
     * `ClinicalNoteService` concern (throws `InvalidClinicalNoteOperationException`), not this
     * layer's.
     */
    public function authorize(): bool
    {
        return $this->user()->can('addAddendum', $this->route('clinical_note'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
