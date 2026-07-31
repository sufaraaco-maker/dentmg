<?php

namespace App\Models;

use App\Enums\AiInteractionFeature;
use App\Models\Concerns\Auditable;
use Database\Factories\AiInteractionLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Purpose-built audit trail for AI interactions (design doc §3) — not the generic `Auditable`
 * trait, which hooks a single model's own lifecycle events. A single interaction routinely reads
 * across several models (e.g. a Dashboard Insight touches `ReportService`, not one Eloquent row)
 * before writing to none of them until the user explicitly accepts a suggestion.
 *
 * No `updated_at` column exists, mirroring `ClinicalNoteAddendum`'s immutability convention — but
 * unlike that model, `accepted` (and, for an edited-before-save suggestion, `response_summary`)
 * genuinely does change once via `AiAssistantService::recordAcceptance()`, so this model is not
 * fully write-once; only the created_at timestamp itself stays unmanaged.
 */
class AiInteractionLog extends Model
{
    /** @use HasFactory<AiInteractionLogFactory> */
    use Auditable, HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'feature',
        'patient_id',
        'prompt_summary',
        'response_summary',
        'accepted',
        'model',
    ];

    protected $casts = [
        'feature' => AiInteractionFeature::class,
        'accepted' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
