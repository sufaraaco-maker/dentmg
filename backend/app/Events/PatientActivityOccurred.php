<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Design doc §4/§16 decision 1: one generic event class, not 22 near-identical ones — the
 * business intent that distinguishes e.g. "invoice.issued" from "invoice.voided" lives in which
 * $eventType/$category/$summary the calling service method passes at its own call site, not in
 * which PHP class fires. This keeps the per-event dispatch call sites (design doc §5's inventory)
 * explicit and reviewable without 22 files of boilerplate. RecordsPatientActivity (the single
 * listener) subscribes to this one event and writes one PatientActivity row per occurrence.
 *
 * $subject must have a direct patient_id column — true of every subject type in §5's inventory
 * (confirmed by reading each candidate model directly, not assumed).
 */
class PatientActivityOccurred
{
    use Dispatchable;

    public function __construct(
        public readonly Model $subject,
        public readonly ?User $actor,
        public readonly string $eventType,
        public readonly string $category,
        public readonly string $summary,
        public readonly array $metadata = [],
    ) {}
}
