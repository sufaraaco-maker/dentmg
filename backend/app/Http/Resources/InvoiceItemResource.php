<?php

namespace App\Http\Resources;

use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceItem */
class InvoiceItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'treatment_plan_item_id' => $this->treatment_plan_item_id,
            'kind' => $this->kind->value,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount,
            'amount' => $this->amount,
            'sequence' => $this->sequence,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'treatment_plan_item' => $this->whenLoaded('treatmentPlanItem', fn () => $this->treatmentPlanItem ? [
                'id' => $this->treatmentPlanItem->id,
                'procedure_name' => $this->treatmentPlanItem->procedure_name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
        ];
    }
}
