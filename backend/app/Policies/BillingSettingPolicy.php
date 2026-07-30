<?php

namespace App\Policies;

use App\Models\User;

/**
 * Design doc §5: same tier/shape as ClinicSettingPolicy — financial configuration is at least as
 * sensitive as Treatment Plans' pricing data. Singleton resource, checked against the model class.
 */
class BillingSettingPolicy
{
    public function view(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor): bool
    {
        return $actor->isAdmin();
    }
}
