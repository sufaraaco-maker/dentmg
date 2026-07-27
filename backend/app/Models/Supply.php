<?php

namespace App\Models;

use Database\Factories\SupplyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supply extends Model
{
    /** @use HasFactory<SupplyFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'category_id',
        'default_supplier_id',
        'name',
        'sku',
        'unit_of_measure',
        'unit_cost',
        'reorder_level',
        'reorder_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'reorder_level' => 'integer',
            'reorder_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SupplyCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplyCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * SUM(stock_movements.quantity_delta) — deliberately never stored (design doc §0/§4/§6): the
     * source of truth is the ledger, always. Prefers the pre-computed value the
     * `withQuantityOnHand()` scope below already joined onto this row (design doc §13's
     * N+1-avoidance requirement for a list of many Supplies); falls back to a single aggregate
     * query only when that scope wasn't used (e.g. a single Supply fetched via `show()`), same
     * "derive it live, don't let a stored counter drift" principle as
     * Invoice::getAmountPaidAttribute() either way.
     */
    public function getQuantityOnHandAttribute(): int
    {
        if (array_key_exists('quantity_on_hand', $this->attributes)) {
            return (int) $this->attributes['quantity_on_hand'];
        }

        return (int) $this->stockMovements()->sum('quantity_delta');
    }

    /**
     * Design doc §4: a Supply is "low stock" the instant its computed on-hand is at or below its
     * own reorder_level — never a stored flag.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity_on_hand <= $this->reorder_level;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Design doc §13: a single grouped-aggregate left-join, computing every visible row's on-hand
     * quantity in one query — never N+1 per-row queries when listing many Supplies at once. Used
     * unconditionally by SupplyService::paginate() (every row in the Supplies list needs its
     * on-hand column) and as the basis for scopeLowStock() below.
     *
     * A plain static method, not a second scope calling this one: PHPStan/Larastan can't statically
     * resolve one magic `scope*` method calling another through the generic `Builder` return type,
     * so scopeLowStock() below calls this helper directly instead of chaining `->withQuantityOnHand()`.
     */
    public static function applyQuantityOnHandJoin(Builder $query): Builder
    {
        return $query
            ->select('supplies.*')
            ->selectRaw('COALESCE(sm.on_hand, 0) as quantity_on_hand')
            ->leftJoinSub(
                StockMovement::query()->selectRaw('supply_id, SUM(quantity_delta) as on_hand')->groupBy('supply_id'),
                'sm',
                'sm.supply_id',
                '=',
                'supplies.id'
            );
    }

    public function scopeWithQuantityOnHand(Builder $query): Builder
    {
        return static::applyQuantityOnHandJoin($query);
    }

    /**
     * Design doc §9/§13: Supplies whose on-hand is at or below their own reorder_level, most-urgent
     * (most negative margin) first. Postgres doesn't allow referencing a SELECT alias in WHERE, so
     * the comparison re-expresses the same joined subquery column directly instead.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return static::applyQuantityOnHandJoin($query)
            ->whereRaw('COALESCE(sm.on_hand, 0) <= supplies.reorder_level')
            ->orderByRaw('COALESCE(sm.on_hand, 0) - supplies.reorder_level');
    }
}
