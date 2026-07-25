<?php

namespace App\Http\Requests\InvoiceItem;

use App\Enums\InvoiceItemKind;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TreatmentPlanItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceItemRequest extends FormRequest
{
    /**
     * `invoice_id` is not a field — the parent invoice comes from the nested route
     * (`invoices/{invoice}/items`, design doc §9). `status` doesn't exist for InvoiceItem at all
     * (design doc §5 — no separate status enum). Whether the parent invoice is still `draft` (so
     * this add is even reachable) is an InvoiceService concern
     * (InvoiceService::assertEditable()), not this layer's.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', InvoiceItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->route('invoice');
        $patientId = $invoice?->patient_id;

        return [
            'kind' => ['required', Rule::enum(InvoiceItemKind::class)],

            // Traceability link to a completed Treatment Plan item (design doc §7/§8) — only a
            // `charge`-kind item may carry one, and only one belonging to this invoice's patient.
            // `TreatmentPlanItem` has no `patient_id` column of its own (design doc §7), so the
            // generic `BelongsToPatient` rule doesn't apply here — this checks ownership through
            // the `treatmentPlan` relation instead, mirroring InvoiceService::findTreatmentPlanItemForPatient()
            // as the friendlier Form-Request-level UX on top of that Service-layer backstop.
            'treatment_plan_item_id' => [
                'nullable', 'uuid',
                function ($attribute, $value, $fail) use ($patientId) {
                    if ($value === null) {
                        return;
                    }

                    if ($this->input('kind') !== InvoiceItemKind::Charge->value) {
                        $fail('Only charge items may reference a treatment plan item.');

                        return;
                    }

                    if ($patientId === null) {
                        $fail('The :attribute cannot be validated without a resolved patient.');

                        return;
                    }

                    $belongsToPatient = TreatmentPlanItem::query()
                        ->where('id', $value)
                        ->whereHas('treatmentPlan', fn ($query) => $query->where('patient_id', $patientId))
                        ->exists();

                    if (! $belongsToPatient) {
                        $fail('The selected treatment plan item does not belong to this invoice\'s patient.');
                    }
                },
            ],

            // Nullable at the HTTP layer only so it can default from the referenced treatment plan
            // item's procedure_name/unit_cost (InvoiceService::addItem(), design doc §7) — but a
            // value must be resolvable from *somewhere*: enforced here for a friendlier field-level
            // error and, as a hard backstop reachable from every call path, by
            // InvalidInvoiceItemException in the Service layer (design doc §8).
            'description' => ['nullable', 'string', 'max:500', 'required_without:treatment_plan_item_id'],
            'unit_amount' => ['nullable', 'numeric', 'min:0', 'required_without:treatment_plan_item_id'],

            'quantity' => ['nullable', 'integer', 'min:1'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
