<?php

namespace App\Http\Resources;

use App\Models\BillingSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BillingSetting */
class BillingSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency_code' => $this->currency_code,
            'tax_rate' => $this->tax_rate,
            'invoice_number_prefix' => $this->invoice_number_prefix,
            // Read-only — system-managed by InvoiceService, never accepted from the update request
            // (design doc §4.2/§8 decision 3).
            'next_invoice_sequence' => $this->next_invoice_sequence,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
