<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'invoice_id' => $this->invoice_id,
            'refunded_payment_id' => $this->refunded_payment_id,
            'is_refund' => $this->is_refund,
            'remaining_refundable_amount' => $this->remaining_refundable_amount,
            'method' => $this->method->value,
            'amount' => (string) $this->amount,
            'currency_code' => $this->currency_code,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'received_at' => $this->received_at->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
        ];
    }
}
