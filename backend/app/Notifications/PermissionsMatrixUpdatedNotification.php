<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 13 (design doc §5.3). Reactive, not scheduled — dispatched by `AuditLogObserver` on every
 * `role_permissions_updated` audit row, with `$subject` being that `AuditLog` row itself (no other
 * natural Eloquent subject for a matrix-wide change — §8.1) and `$actor` the admin who made the
 * change, so `dispatchFor()`'s standard actor-exclusion rule keeps them off their own recipient
 * list without any special-casing here.
 */
class PermissionsMatrixUpdatedNotification extends BaseNotification
{
    public function type(): string
    {
        return 'permissions.matrix_updated';
    }

    public function category(): string
    {
        return 'security';
    }

    public function params(): array
    {
        return [];
    }

    public function route(): array
    {
        return ['name' => 'permissions'];
    }

    public function recipients(RecipientResolver $resolver): Collection
    {
        return $resolver->byRoles([UserRole::Admin]);
    }
}
