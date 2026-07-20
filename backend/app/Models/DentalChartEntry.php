<?php

namespace App\Models;

use App\Enums\DentalChartEntryStatus;
use App\Models\Concerns\Auditable;
use App\Support\ToothChart;
use Database\Factories\DentalChartEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DentalChartEntry extends Model
{
    /** @use HasFactory<DentalChartEntryFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'dental_condition_id',
        'dentist_id',
        'created_by_id',
        'updated_by_id',
        'tooth_number',
        'surfaces',
        'status',
        'notes',
        'recorded_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $appends = ['dentition_type'];

    protected function casts(): array
    {
        return [
            'surfaces' => 'array',
            'status' => DentalChartEntryStatus::class,
            'recorded_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Derived from tooth_number (App\Support\ToothChart), never stored — see
     * docs/modules/dental-chart-design-draft.md §6 for why this is deliberately not a column.
     */
    public function getDentitionTypeAttribute(): string
    {
        return ToothChart::isPermanent($this->tooth_number) ? 'permanent' : 'primary';
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<DentalCondition, $this>
     */
    public function dentalCondition(): BelongsTo
    {
        return $this->belongsTo(DentalCondition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForTooth(Builder $query, string $toothNumber): Builder
    {
        return $query->where('tooth_number', $toothNumber);
    }

    public function scopeWithStatus(Builder $query, DentalChartEntryStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
