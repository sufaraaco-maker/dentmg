<?php

namespace App\Models;

use App\Enums\TreatmentPlanStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\TreatmentPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentPlan extends Model
{
    /** @use HasFactory<TreatmentPlanFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'dentist_id',
        'created_by_id',
        'title',
        'status',
        'notes',
        'presented_at',
        'accepted_at',
        'rejected_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'superseded_by_plan_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => TreatmentPlanStatus::class,
            'presented_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
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
     * The plan this one was replaced by (design doc §15 Q4) — set on the OLD plan, pointing
     * forward to its replacement.
     *
     * @return BelongsTo<TreatmentPlan, $this>
     */
    public function supersededByPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'superseded_by_plan_id');
    }

    /**
     * @return HasMany<TreatmentPlanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TreatmentPlanItem::class);
    }

    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeWithStatus(Builder $query, TreatmentPlanStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
