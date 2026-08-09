<?php

namespace App\Models;

use Database\Factories\PatientActivityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Deliberately does NOT use the Auditable trait — this model IS the dedicated event feed the
 * Security Architecture Decision (design doc §9A) requires instead of Auditable, not another
 * auditable record. Rows are written only by RecordsPatientActivity, never by a user-facing
 * create/update/delete endpoint; no SoftDeletes — activity rows are immutable, append-only facts.
 */
class PatientActivity extends Model
{
    /** @use HasFactory<PatientActivityFactory> */
    use HasFactory, HasUuids;

    // No updated_at — activity rows are immutable, append-only facts. created_at is still
    // auto-managed by Eloquent's default $timestamps=true.
    const UPDATED_AT = null;

    protected $fillable = [
        'patient_id',
        'event_type',
        'category',
        'subject_type',
        'subject_id',
        'actor_id',
        'summary',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
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
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
