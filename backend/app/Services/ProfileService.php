<?php

namespace App\Services;

use App\Models\User;

/**
 * Self-service account management (design doc §4.3/§5) — every method operates on the actor's own
 * `User` row, passed in by the controller from `$request->user()`. No role/self-delete here; those
 * remain admin-only via `UserPolicy`/`UserController`, untouched by this module.
 */
class ProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }
}
