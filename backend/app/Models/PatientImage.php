<?php

namespace App\Models;

use App\Enums\ImageType;
use App\Models\Concerns\Auditable;
use Database\Factories\PatientImageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientImage extends Model
{
    /** @use HasFactory<PatientImageFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'uploaded_by',
        'image_type',
        'tooth_number',
        'surfaces',
        'taken_at',
        'treatment_plan_item_id',
        'appointment_id',
        'disk',
        'path',
        'thumbnail_path',
        'mime_type',
        'file_size',
        'width',
        'height',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'image_type' => ImageType::class,
            'surfaces' => 'array',
            'taken_at' => 'date',
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
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Traceability only — never mutated by this module (design doc §3, mirrors
     * LabCase::treatmentPlanItem()'s identical one-way convention).
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

    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeOfType(Builder $query, ImageType $type): Builder
    {
        return $query->where('image_type', $type->value);
    }

    public function scopeForTooth(Builder $query, string $toothNumber): Builder
    {
        return $query->where('tooth_number', $toothNumber);
    }
}
