<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Interactively create the first production admin user (no demo/default credentials)';

    public function handle(): int
    {
        if (User::query()->where('role', UserRole::Admin)->exists()
            && ! $this->confirm('An admin user already exists. Create another one anyway?')) {
            return self::SUCCESS;
        }

        $name = $this->ask('Admin full name');
        $email = $this->ask('Admin email address');
        $password = $this->secret('Admin password (input hidden)');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'role' => UserRole::Admin,
        ]);

        $this->info("Admin user created: {$user->email}");

        return self::SUCCESS;
    }
}
