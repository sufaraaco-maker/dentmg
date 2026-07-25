<?php

namespace App\Models;

use App\Enums\InvoiceItemKind;
use App\Models\Concerns\Auditable;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'treatment_plan_item_id',
        'kind',
        'description',
        'quantity',
        'unit_amount',
        'sequence',
        'notes',
        'created_by_id',
    ];

    protected $appends = ['amount'];

    protected function casts(): array
    {
        return [
            'kind' => InvoiceItemKind::class,
            'quantity' => 'integer',
            'unit_amount' => 'decimal:2',
        ];
    }

    /**
     * unit_amount * quantity — deliberately never stored (design doc §6), same "don't store
     * derivable state" principle as TreatmentPlanItem::getEstimatedCostAttribute(). Always positive
     * regardless of `kind`; the invoice-level total (service-layer SUM, Step 2) is what applies
     * each kind's sign (App\Enums\InvoiceItemKind).
     */
    public function getAmountAttribute(): string
    {
        return bcmul((string) $this->unit_amount, (string) $this->quantity, 2);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Traceability only — never used to resolve description/amount once written (design doc §7,
     * Decision Log item 6).
     *
     * @return BelongsTo<TreatmentPlanItem, $this>
     */
    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
