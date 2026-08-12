<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 5C (Notification System — scheduled/administrative types) design doc §8.1/§8.2.
 *
 * Gives `NotificationService::dispatchFor()`'s send-time authorization check
 * (`$recipient->can('view', $subject)`, layer 3) a resolvable target for the two `security`-category
 * types (12: repeated login failures, 13: permission-matrix updated) — neither has a natural
 * business-entity subject, so both use the triggering `AuditLog` row itself.
 *
 * Deliberately just a one-method proxy to the existing Gate, not a new authorization rule: Phase 4
 * §1.4 already decided audit-log access is a hardcoded admin-only Gate, never routed through the
 * permission matrix. Delegating here keeps that single source of truth rather than duplicating the
 * `isAdmin()` check.
 */
class AuditLogPolicy
{
    public function view(User $actor, AuditLog $auditLog): bool
    {
        return Gate::forUser($actor)->allows('view-audit-logs');
    }
}
