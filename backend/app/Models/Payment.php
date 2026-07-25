<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'invoice_id',
        'refunded_payment_id',
        'method',
        'amount',
        'currency_code',
        'reference',
        'notes',
        'received_at',
        'created_by_id',
    ];

    protected $appends = ['is_refund', 'remaining_refundable_amount'];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'received_at' => 'date',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function refundedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'refunded_payment_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Payment::class, 'refunded_payment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getIsRefundAttribute(): bool
    {
        return $this->refunded_payment_id !== null;
    }

    /**
     * `amount + SUM(refunds against it)` (design doc §8) — never stored. Works recursively with no
     * special-casing whether this row is an ordinary payment or itself a refund row: a refund row's
     * own "remaining balance" is computed by the exact same formula.
     */
    public function getRemainingRefundableAmountAttribute(): string
    {
        return bcadd((string) $this->amount, (string) $this->refunds()->sum('amount'), 2);
    }

    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }
}
