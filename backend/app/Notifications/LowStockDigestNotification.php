<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\Supply;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 10 of Phase 5C's scheduled types (design doc §5.2/§3.2). One digest notification, not one
 * per low-stock item — `NotifyLowStockDigest` builds this from `Supply::lowStock()` (the same query
 * already backing the Dashboard widget/`SupplyController::lowStock()`) and picks the single
 * most-urgent item (that scope's own `ORDER BY` — lowest margin first) as `$subject`, since the
 * `notifications` table requires one real Eloquent subject per row (§8.1) but a digest has no
 * single natural one. The deep link routes to the low-stock list, not to that one item, so the
 * choice of representative subject is invisible to the reader.
 *
 * `params()` re-runs the same cheap, already-indexed `lowStock()` scope rather than taking the list
 * through the constructor — every other notification in this module derives its params from
 * `$this->subject` alone via the standard `(subject, actor)` contract `dispatchFor()` instantiates
 * with; keeping that contract uniform beats saving one small query.
 */
class LowStockDigestNotification extends BaseNotification
{
    public function type(): string
    {
        return 'inventory.low_stock';
    }

    public function category(): string
    {
        return 'inventory';
    }

    public function params(): array
    {
        $lowStockItems = Supply::query()->active()->lowStock()->get();

        return [
            'count' => $lowStockItems->count(),
            'itemNames' => $lowStockItems->take(5)->pluck('name')->all(),
        ];
    }

    public function route(): array
    {
        return ['name' => 'supplies', 'query' => ['low_stock_only' => '1']];
    }

    public function recipients(RecipientResolver $resolver): Collection
    {
        return $resolver->byRoles([UserRole::Admin, UserRole::Receptionist]);
    }
}
