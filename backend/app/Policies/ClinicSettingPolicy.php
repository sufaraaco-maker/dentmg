<?php

namespace App\Policies;

use App\Models\User;

/**
 * A singleton resource, checked against the model class (`can('view', ClinicSetting::class)`) — no
 * per-row `{id}` to bind a route parameter to.
 *
 * `view` deviates from the design doc's original "admin only" (§5) — every role, not just admins,
 * needs to read this: the Lab Case printable slip (§4.1's named first consumer) is reachable by any
 * role with lab-case access, and clinic name/phone/address/email carries no more sensitivity than a
 * business card. `update` stays admin-only, unchanged from the design doc.
 */
class ClinicSettingPolicy
{
    public function view(User $actor): bool
    {
        return $actor->hasPermission('clinic_settings.view');
    }

    public function update(User $actor): bool
    {
        return $actor->hasPermission('clinic_settings.manage');
    }
}
