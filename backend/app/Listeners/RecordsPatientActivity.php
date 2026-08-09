<?php

namespace App\Listeners;

use App\Events\PatientActivityOccurred;
use App\Models\PatientActivity;

/**
 * The single listener design doc §4/§9.2 calls for — synchronous (no ShouldQueue): no queue
 * worker actually runs in this project today (confirmed by audit), and a single indexed INSERT
 * is cheap enough not to need one. $event->subject->patient_id is read directly — every subject
 * type in §5's inventory has a direct patient_id column (confirmed per-model, not assumed).
 */
class RecordsPatientActivity
{
    public function handle(PatientActivityOccurred $event): void
    {
        PatientActivity::create([
            // getAttribute(), not the magic property — $subject is typed as the generic base
            // Model (any of 11 subject classes), which has no statically-declared patient_id.
            'patient_id' => $event->subject->getAttribute('patient_id'),
            'event_type' => $event->eventType,
            'category' => $event->category,
            'subject_type' => $event->subject->getMorphClass(),
            'subject_id' => $event->subject->getKey(),
            'actor_id' => $event->actor?->id,
            'summary' => $event->summary,
            'metadata' => $event->metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
