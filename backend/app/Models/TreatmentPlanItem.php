<?php

namespace App\Models;

use App\Enums\TreatmentPlanItemStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\TreatmentPlanItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentPlanItem extends Model
{
    /** @use HasFactory<TreatmentPlanItemFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'treatment_plan_id',
        'dental_condition_id',
        'procedure_name',
        'procedure_description',
        'diagnosis_entry_id',
        'tooth_number',
        'surfaces',
        'quantity',
        'unit_cost',
        'phase',
        'sequence',
        'status',
        'appointment_id',
        'notes',
        'completed_at',
        'cancelled_at',
        'created_by_id',
        'updated_by_id',
    ];

    protected $appends = ['estimated_cost'];

    protected function casts(): array
    {
        return [
            'surfaces' => 'array',
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'phase' => 'integer',
            'sequence' => 'integer',
            'status' => TreatmentPlanItemStatus::class,
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * unit_cost * quantity — deliberately never stored (design doc §6): a plain computed accessor,
     * the same "don't store derivable state" principle already applied to
     * DentalChartEntry::dentition_type.
     */
    public function getEstimatedCostAttribute(): string
    {
        return bcmul((string) $this->unit_cost, (string) $this->quantity, 2);
    }

    /**
     * @return BelongsTo<TreatmentPlan, $this>
     */
    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class);
    }

    /**
     * @return BelongsTo<DentalCondition, $this>
     */
    public function dentalCondition(): BelongsTo
    {
        return $this->belongsTo(DentalCondition::class);
    }

    /**
     * Read-only traceability reference to the Dental Chart finding this item addresses — never
     * mutated by this module (design doc §7, Decision 1).
     *
     * @return BelongsTo<DentalChartEntry, $this>
     */
    public function diagnosisEntry(): BelongsTo
    {
        return $this->belongsTo(DentalChartEntry::class, 'diagnosis_entry_id');
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
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

    public function scopeForPlan(Builder $query, string $treatmentPlanId): Builder
    {
        return $query->where('treatment_plan_id', $treatmentPlanId);
    }

    public function scopeWithStatus(Builder $query, TreatmentPlanItemStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
