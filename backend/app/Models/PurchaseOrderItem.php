<?php

namespace App\Models;

use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'purchase_order_id',
        'supply_id',
        'description',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
    ];

    protected $appends = ['subtotal', 'quantity_remaining'];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'quantity_received' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Supply, $this>
     */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    /**
     * Traceability only (design doc §7) — every `received` stock_movements row this item's own
     * Receive actions generated.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * unit_cost * quantity_ordered — deliberately never stored, same "don't store derivable state"
     * principle as InvoiceItem::getAmountAttribute().
     */
    public function getSubtotalAttribute(): string
    {
        return bcmul((string) $this->unit_cost, (string) $this->quantity_ordered, 2);
    }

    public function getQuantityRemainingAttribute(): int
    {
        return $this->quantity_ordered - $this->quantity_received;
    }
}
