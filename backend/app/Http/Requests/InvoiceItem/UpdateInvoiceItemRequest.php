<?php

namespace App\Http\Requests\InvoiceItem;

use App\Enums\InvoiceItemKind;
use App\Models\InvoiceItem;
use App\Models\TreatmentPlanItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceItemRequest extends FormRequest
{
    /**
     * Same shape as Store, all `sometimes` (mirroring UpdateTreatmentPlanItemRequest's identical
     * pattern) — whether the parent invoice is still `draft` (so this edit is even reachable) is
     * an InvoiceService concern (InvoiceService::assertEditable()), not this layer's.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice_item'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var InvoiceItem|null $item */
        $item = $this->route('invoice_item');
        $patientId = $item?->invoice?->patient_id;
        $kind = $this->input('kind', $item?->kind?->value);

        return [
            'kind' => ['sometimes', Rule::enum(InvoiceItemKind::class)],

            // Same ownership/kind checks as Store, resolving `kind` from either the submitted
            // value or the item's current one — a `treatment_plan_item_id` left untouched on this
            // request must still be validated against whichever `kind` will actually be in effect.
            'treatment_plan_item_id' => [
                'sometimes', 'nullable', 'uuid',
                function ($attribute, $value, $fail) use ($patientId, $kind) {
                    if ($value === null) {
                        return;
                    }

                    if ($kind !== InvoiceItemKind::Charge->value) {
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
            'description' => ['sometimes', 'string', 'max:500'],
            'unit_amount' => ['sometimes', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'sequence' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
