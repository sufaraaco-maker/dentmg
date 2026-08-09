<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasUuids, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Phase 4 (Advanced Permissions & Audit) design doc §1.3 — the replacement for raw
     * `$this->role === UserRole::X` checks scattered across Policies. Backed by
     * RolePermission::permissionKeysForRole()'s per-role cache, so this is one cached lookup, not
     * one query per authorization check.
     */
    public function hasPermission(string $key): bool
    {
        return in_array($key, RolePermission::permissionKeysForRole($this->role), true);
    }

    public function appointmentsAsDentist(): HasMany
    {
        return $this->hasMany(Appointment::class, 'dentist_id');
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(DentistWorkingHour::class);
    }

    public function timeOff(): HasMany
    {
        return $this->hasMany(DentistTimeOff::class);
    }
}
