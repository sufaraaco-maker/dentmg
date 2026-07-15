<?php

namespace App\Models;

use Database\Factories\AppointmentReminderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Database foundation only — not yet wired to a Service, Job, or API endpoint.
 * See docs/modules/appointments-design-draft.md and the appointment_reminders migration.
 */
class AppointmentReminder extends Model
{
    /** @use HasFactory<AppointmentReminderFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'appointment_id',
        'channel',
        'scheduled_at',
        'sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
