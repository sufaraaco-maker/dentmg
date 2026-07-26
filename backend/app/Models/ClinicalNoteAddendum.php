<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ClinicalNoteAddendumFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately immutable (design doc §6/§8 rule 4, §15 Decision A): no `updated_at` column exists
 * (`UPDATED_AT = null` tells Eloquent not to manage one), no SoftDeletes trait, and no
 * update()/delete() call site exists anywhere in the codebase for this model. `Auditable` still
 * applies — it only reacts to Eloquent's own create/update/delete events, and since only `created`
 * can ever fire here, it captures exactly one permanent audit entry per addendum, never more.
 */
class ClinicalNoteAddendum extends Model
{
    /** @use HasFactory<ClinicalNoteAddendumFactory> */
    use Auditable, HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'clinical_note_id',
        'author_id',
        'body',
    ];

    /**
     * @return BelongsTo<ClinicalNote, $this>
     */
    public function clinicalNote(): BelongsTo
    {
        return $this->belongsTo(ClinicalNote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
