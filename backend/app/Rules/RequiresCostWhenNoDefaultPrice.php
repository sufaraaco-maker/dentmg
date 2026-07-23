<?php

namespace App\Rules;

use App\Models\DentalCondition;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Design doc §6/§8/§15 Q2: a treatment plan item's `unit_cost` prefills from
 * `dental_conditions.default_cost` when omitted — but that default is nullable ("no default —
 * dentist must enter one," mirroring Open Dental's `ExistCurProv`/`ExistOther` "no fee" precedent,
 * design doc §0). If both are absent there is no cost to snapshot at all, which the migration's
 * `unit_cost` NOT NULL column would otherwise reject as an opaque database error — this rule turns
 * that into a clear, actionable 422 instead.
 *
 * A `DataAwareRule` (not a plain closure reading `$this->input()`) for the same reason
 * `ValidDentalChartSurfaces` is one: it must read `dental_condition_id` from the data actually
 * being validated, whether that's a live FormRequest or a bare `Validator::make($payload, $rules)`
 * call in a unit test.
 *
 * `$implicit = true` — a public property Laravel's `InvokableValidationRule::make()` checks
 * directly (not an interface, confirmed by reading that class's source), telling the validator to
 * run this rule even when `unit_cost` is `nullable` and the value is literally `null`. Without it,
 * `nullable` short-circuits every other rule for that attribute once the value is null — exactly
 * the case this rule exists to catch.
 */
class RequiresCostWhenNoDefaultPrice implements DataAwareRule, ValidationRule
{
    public bool $implicit = true;

    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null) {
            return;
        }

        $conditionId = $this->data['dental_condition_id'] ?? null;
        $condition = $conditionId ? DentalCondition::find($conditionId) : null;

        if ($condition && $condition->default_cost === null) {
            $fail('A cost is required for this procedure since it has no default price.');
        }
    }
}
