<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\BillingSettingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single-implicit-row settings table in V1 (design doc §14) — designed clinic-scoped-ready (one
 * row per organization) even though nothing yet reads/writes a clinic_id column. `Invoice` reads
 * `invoice_number_prefix` via a plain query, not a relation, since there is exactly one row to find.
 * No SoftDeletes — a settings row is configuration, not a real-world record a clinic recovers, same
 * exception class `database-design.md` already carves out for lookup/pivot tables.
 */
class BillingSetting extends Model
{
    /** @use HasFactory<BillingSettingFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'currency_code',
        'tax_rate',
        'invoice_number_prefix',
        'next_invoice_sequence',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:2',
        ];
    }
}
