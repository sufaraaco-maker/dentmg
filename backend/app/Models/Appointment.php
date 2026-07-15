<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'dentist_id',
        'appointment_type_id',
        'start_at',
        'end_at',
        'duration_minutes',
        'status',
        'reason',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'checked_in_at',
        'started_at',
        'completed_at',
        'no_show_at',
        'reschedule_count',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => AppointmentStatus::class,
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'no_show_at' => 'datetime',
            'reschedule_count' => 'integer',
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
    public function dentist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id');
    }

    /**
     * @return BelongsTo<AppointmentType, $this>
     */
    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<AppointmentReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    /**
     * Appointments in a status that occupies the dentist's time slot.
     * Mirrors the status set enforced by the Postgres EXCLUDE constraint (§10 of the design doc).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn(
            'status',
            array_map(fn (AppointmentStatus $status) => $status->value, AppointmentStatus::occupyingStatuses())
        );
    }

    public function scopeForDentist(Builder $query, string $dentistId): Builder
    {
        return $query->where('dentist_id', $dentistId);
    }

    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }
}
