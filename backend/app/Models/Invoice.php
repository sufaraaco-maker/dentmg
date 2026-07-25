<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'created_by_id',
        'sequence_number',
        'invoice_number',
        'currency_code',
        'status',
        'notes',
        'issue_date',
        'due_date',
        'issued_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * The inverse of Payment::invoice() — used only to compute amount_paid/balance_due/
     * payment_status (Payments design doc §7). `Invoice`/`InvoiceItem` stay otherwise unaware
     * `Payment` exists as a concept: no column, no event, no callback added here beyond this
     * read-only relation.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeWithStatus(Builder $query, InvoiceStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}
