<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * `patient_id` is not a field here — the parent Patient comes from the nested route
     * (`patients/{patient}/invoices`, design doc §9), same pattern as StoreTreatmentPlanRequest.
     * `status`/`currency_code`/`sequence_number`/`invoice_number` are not fields either — a new
     * invoice always starts `draft` with a server-snapshotted currency (design doc §5/§6/§8);
     * InvoiceService::createDraft() sets all of these, never the client. Items are added
     * afterwards through their own endpoint (`POST .../invoices/{invoice}/items`), not bundled
     * into invoice creation (design doc §9).
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
        ];
    }
}
