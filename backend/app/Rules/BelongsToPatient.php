<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared by Treatment Plan Items' `diagnosis_entry_id` (-> dental_chart_entries) and
 * `appointment_id` (-> appointments) references: both models have their own `patient_id` column,
 * so a single generic rule covers "does this reference exist AND belong to the same patient" for
 * either, per the design doc's explicit requirement (§9/§16) that a treatment plan item can never
 * point at another patient's chart entry or appointment. Existence and patient-ownership are
 * checked together in one query — a stray uuid that doesn't exist at all fails the same as one that
 * exists but belongs to someone else, so no separate `exists:` rule is needed alongside this one.
 */
class BelongsToPatient implements ValidationRule
{
    public function __construct(
        private string $modelClass,
        private ?string $patientId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->patientId === null) {
            $fail('The :attribute cannot be validated without a resolved patient.');

            return;
        }

        $belongsToPatient = $this->modelClass::query()
            ->where('id', $value)
            ->where('patient_id', $this->patientId)
            ->exists();

        if (! $belongsToPatient) {
            $fail('The selected :attribute does not belong to this patient.');
        }
    }
}
