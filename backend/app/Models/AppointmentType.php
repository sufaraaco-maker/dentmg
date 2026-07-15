<?php

namespace App\Models;

use Database\Factories\AppointmentTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentType extends Model
{
    /** @use HasFactory<AppointmentTypeFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'default_duration_minutes',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
