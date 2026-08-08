<?php

namespace App\Http\Requests\PatientDocument;

use App\Enums\DocumentCategory;
use App\Models\PatientDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * One file per request — unlike Imaging's shared-metadata batch upload (a set of exposures from the
 * same visit), a document's title/category is naturally per-file, so a batch here would force an
 * awkward shared title across unrelated files. Small, deliberate deviation from
 * StorePatientImageRequest's convention, not an oversight.
 */
class StorePatientDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PatientDocument::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:15360'],

            'category' => ['required', new Enum(DocumentCategory::class)],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
