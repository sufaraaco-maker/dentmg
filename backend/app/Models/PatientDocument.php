<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Models\Concerns\Auditable;
use Database\Factories\PatientDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientDocument extends Model
{
    /** @use HasFactory<PatientDocumentFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'uploaded_by',
        'category',
        'title',
        'original_filename',
        'disk',
        'path',
        'mime_type',
        'file_size',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
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

    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeOfCategory(Builder $query, DocumentCategory $category): Builder
    {
        return $query->where('category', $category->value);
    }
}
