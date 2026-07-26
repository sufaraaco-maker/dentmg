<?php

namespace App\Models;

use App\Enums\ClinicalNoteStatus;
use App\Enums\ClinicalNoteType;
use App\Models\Concerns\Auditable;
use Database\Factories\ClinicalNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalNote extends Model
{
    /** @use HasFactory<ClinicalNoteFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'dentist_id',
        'note_type',
        'chief_complaint',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'status',
        'signed_at',
        'signed_by_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'note_type' => ClinicalNoteType::class,
            'status' => ClinicalNoteStatus::class,
            'signed_at' => 'datetime',
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
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
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
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by_id');
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

    /**
     * @return HasMany<ClinicalNoteAddendum, $this>
     */
    public function addendums(): HasMany
    {
        return $this->hasMany(ClinicalNoteAddendum::class)->oldest('created_at');
    }

    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeWithStatus(Builder $query, ClinicalNoteStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
