<?php

namespace App\Models;

use Database\Factories\LabFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lab extends Model
{
    /** @use HasFactory<LabFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'address',
        'default_turnaround_days',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_turnaround_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LabCase, $this>
     */
    public function labCases(): HasMany
    {
        return $this->hasMany(LabCase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
