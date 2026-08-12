<?php

namespace App\Notifications;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Services\RecipientResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Type 12 (design doc §5.3). Reactive, not scheduled — dispatched by `AuditLogObserver` the moment
 * a same-email `login_failed` count crosses Decision D9's threshold (5 within 15 minutes), from the
 * triggering `AuditLog` row itself as `$subject` (no natural business-entity subject exists for a
 * login attempt — §8.1). No actor: `AuditLogService::write()` sets `user_id` from `Auth::id()`,
 * which is always null during a failed login, matching or not matching a real account alike.
 */
class RepeatedLoginFailuresNotification extends BaseNotification
{
    public function type(): string
    {
        return 'security.repeated_login_failures';
    }

    public function category(): string
    {
        return 'security';
    }

    public function params(): array
    {
        /** @var AuditLog $auditLog */
        $auditLog = $this->subject;

        return [
            'email' => $auditLog->context['email'] ?? null,
        ];
    }

    public function route(): array
    {
        return ['name' => 'audit-logs'];
    }

    public function recipients(RecipientResolver $resolver): Collection
    {
        return $resolver->byRoles([UserRole::Admin]);
    }
}
