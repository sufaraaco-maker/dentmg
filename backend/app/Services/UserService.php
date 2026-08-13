<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Always the `public` disk — same reasoning as ClinicSettingService::updateLogo(): a profile
     * picture renders as a plain <img> (Users list, header avatar) with no authenticated round
     * trip, so it can never live on the auth-gated `local` disk patient images/documents use.
     */
    public function updateAvatar(User $user, UploadedFile $file): User
    {
        $this->deleteAvatarFile($user);

        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'jpg');
        $path = "avatars/{$user->id}-".Str::uuid().'.'.$extension;

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $user->update(['avatar_disk' => 'public', 'avatar_path' => $path]);

        return $user;
    }

    public function removeAvatar(User $user): User
    {
        $this->deleteAvatarFile($user);
        $user->update(['avatar_disk' => null, 'avatar_path' => null]);

        return $user;
    }

    private function deleteAvatarFile(User $user): void
    {
        if ($user->avatar_disk && $user->avatar_path) {
            Storage::disk($user->avatar_disk)->delete($user->avatar_path);
        }
    }
}
