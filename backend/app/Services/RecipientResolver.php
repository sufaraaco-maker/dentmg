<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Phase 5 (Notification System) design doc §3.2 / §8.4 — **the single multi-tenant seam of this
 * module**.
 *
 * Every "who should receive this" database query in the whole notification system goes through
 * this class. No notification rule builds its own `User::where(...)`. That is the entire point:
 * DentalSuite V1 is single-organization by design, but when the multi-clinic model arrives, adding
 * tenant isolation to notifications is one `where('clinic_id', ...)` here — not an audit of every
 * notification type looking for a query someone forgot to scope. This is the standing SaaS
 * multi-tenant readiness principle applied concretely, not just asserted.
 *
 * Role lookups are cached per-request: a single `appointment.cancelled` resolves admins and
 * receptionists, and a burst of events in one request (a batched status change) would otherwise
 * re-query the same small staff table repeatedly. Deliberately request-scoped and NOT written to
 * Redis — staff role changes must take effect immediately, and this module is not the right place
 * to introduce a cache-invalidation contract for the `users` table.
 */
class RecipientResolver
{
    /** @var array<string, Collection<int, User>> */
    private array $roleCache = [];

    /**
     * @param  list<UserRole>  $roles
     * @return Collection<int, User>
     */
    public function byRoles(array $roles): Collection
    {
        $key = implode(',', array_map(fn (UserRole $role) => $role->value, $roles));

        return $this->roleCache[$key] ??= User::query()
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, $roles))
            ->get();
    }

    /**
     * A single user by id — e.g. the appointment's assigned dentist. Returns an empty collection
     * rather than throwing when the id is null or the user no longer exists (a soft-deleted
     * dentist, an appointment with no dentist assigned): a notification with no resolvable
     * recipient is a no-op, never an error that breaks the business operation which triggered it.
     *
     * @return Collection<int, User>
     */
    public function byId(?string $userId): Collection
    {
        if ($userId === null) {
            return new Collection;
        }

        return User::query()->whereKey($userId)->get();
    }

    /**
     * Combines recipient sets, de-duplicated by id — the assigned dentist may also be an admin,
     * and must still receive exactly one notification.
     *
     * @param  Collection<int, User>  ...$sets
     * @return Collection<int, User>
     */
    public function merge(Collection ...$sets): Collection
    {
        $merged = new Collection;

        foreach ($sets as $set) {
            $merged = $merged->concat($set);
        }

        /** @var Collection<int, User> */
        return $merged->unique('id')->values();
    }
}
