<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Any authenticated staff member can see who else works at the clinic.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.view');
    }

    /**
     * Managing accounts (creating, editing, deleting) is restricted to admins.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.manage') && $actor->isNot($target);
    }
}
