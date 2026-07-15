<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function paginate(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @param  array{name: string, email: string, password: string, role: UserRole}  $data
     */
    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);
    }

    /**
     * @param  array{name?: string, email?: string, password?: string, role?: UserRole}  $data
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
