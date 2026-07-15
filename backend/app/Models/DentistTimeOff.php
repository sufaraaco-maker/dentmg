<?php

namespace App\Models;

use Database\Factories\DentistTimeOffFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DentistTimeOff extends Model
{
    /** @use HasFactory<DentistTimeOffFactory> */
    use HasFactory, HasUuids;

    protected $table = 'dentist_time_off';

    protected $fillable = [
        'user_id',
        'start_at',
        'end_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForDentist(Builder $query, string $dentistId): Builder
    {
        return $query->where('user_id', $dentistId);
    }
}
