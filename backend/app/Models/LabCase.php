<?php

namespace App\Models;

use App\Enums\LabCaseStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\LabCaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabCase extends Model
{
    /** @use HasFactory<LabCaseFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'sequence_number',
        'case_number',
        'patient_id',
        'lab_id',
        'dentist_id',
        'treatment_plan_item_id',
        'appointment_id',
        'tooth_numbers',
        'case_type',
        'shade',
        'material',
        'instructions',
        'fee',
        'tracking_number',
        'status',
        'sent_at',
        'due_at',
        'received_at',
        'quality_checked_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'tooth_numbers' => 'array',
            'fee' => 'decimal:2',
            'status' => LabCaseStatus::class,
            'sent_at' => 'datetime',
            'due_at' => 'datetime',
            'received_at' => 'datetime',
            'quality_checked_at' => 'datetime',
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
     * @return BelongsTo<Lab, $this>
     */
    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id');
    }

    /**
     * Traceability only — never mutated by this module (design doc §3, mirrors
     * TreatmentPlanItem::diagnosisEntry()'s identical one-way convention).
     *
     * @return BelongsTo<TreatmentPlanItem, $this>
     */
    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    /**
     * Traceability only — never mutated by this module (design doc §3).
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeWithStatus(Builder $query, LabCaseStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Patient Profile integration (Phase 2.4) — same shape as TreatmentPlan::scopeForPatient().
     */
    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Dashboard widget query (design doc §2/§6): due today or overdue, not yet back from the lab.
     */
    public function scopeDueOrOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [LabCaseStatus::Sent->value])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->endOfDay());
    }
}
