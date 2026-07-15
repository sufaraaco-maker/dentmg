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
        return true;
    }

    public function view(User $actor, User $target): bool
    {
        return true;
    }

    /**
     * Managing accounts (creating, editing, deleting) is restricted to admins.
     */
    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin() && $actor->isNot($target);
    }
}
