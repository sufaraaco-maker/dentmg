<?php

namespace App\Models;

use App\Enums\StockMovementReason;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'supply_id',
        'quantity_delta',
        'reason',
        'purchase_order_item_id',
        'expiration_date',
        'notes',
        'performed_by_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'reason' => StockMovementReason::class,
            'expiration_date' => 'date',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supply, $this>
     */
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    /**
     * Set only for reason = received (design doc §6/§8) — traces back to the Purchase Order line
     * that generated this movement.
     *
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }
}
