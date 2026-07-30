<?php

namespace App\Services;

use App\Models\BillingSetting;

/**
 * A singleton settings service (design doc §6), same self-heal shape as
 * `InvoiceService::reserveNextSequenceNumber()` — this is the module's first-ever read/write path
 * for `BillingSetting` other than that internal issue()-time self-heal.
 */
class BillingSettingService
{
    public function current(): BillingSetting
    {
        return BillingSetting::query()->firstOrCreate([], [
            'currency_code' => 'USD',
            'invoice_number_prefix' => 'INV',
            'next_invoice_sequence' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): BillingSetting
    {
        $settings = $this->current();
        $settings->update($data);

        return $settings;
    }
}
