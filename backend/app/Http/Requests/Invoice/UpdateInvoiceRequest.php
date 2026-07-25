<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    /**
     * Same administrative fields as Store, all `sometimes` — no `status` (transitions only move
     * through the dedicated `/issue`/`/void` endpoints, design doc §5/§9, mirroring
     * UpdateTreatmentPlanRequest's identical shape). Whether the invoice is actually still
     * editable in its current status is an InvoiceService concern
     * (InvoiceService::assertEditable(), design doc §8), not this layer's.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'issue_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:issue_date'],
        ];
    }
}
