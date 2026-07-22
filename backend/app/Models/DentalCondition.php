<?php

namespace App\Models;

use App\Enums\DentalConditionCategory;
use Database\Factories\DentalConditionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DentalCondition extends Model
{
    /** @use HasFactory<DentalConditionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'category',
        'applies_to_surface',
        'default_color',
        'icon_key',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => DentalConditionCategory::class,
            'applies_to_surface' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<DentalChartEntry, $this>
     */
    public function dentalChartEntries(): HasMany
    {
        return $this->hasMany(DentalChartEntry::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
