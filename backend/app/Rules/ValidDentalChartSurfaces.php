<?php

namespace App\Rules;

use App\Models\DentalCondition;
use App\Support\ToothChart;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Design draft §4: `surfaces` is required (min 1) when the referenced condition's
 * `applies_to_surface` is true, and must be empty when false; `O` (Occlusal) is only valid on a
 * posterior tooth, `I` (Incisal) only on an anterior one, derived from `tooth_number` via
 * `ToothChart::isAnterior()`.
 *
 * A `DataAwareRule` (not a `withValidator()`/`after()` closure) so it reads sibling fields from
 * the data actually being validated, regardless of whether that's a live FormRequest or a plain
 * `Validator::make($payload, $rules)` call in a unit test — this codebase's existing FormRequest
 * test convention only exercises `rules()`, and `after()` hooks don't fire in that path.
 *
 * `UpdateDentalChartEntryRequest` passes `$fallbackDentalConditionId`/`$fallbackToothNumber` from
 * the route-bound entry, since a partial update may omit either field from the payload.
 */
class ValidDentalChartSurfaces implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function __construct(
        protected ?string $fallbackDentalConditionId = null,
        protected ?string $fallbackToothNumber = null,
    ) {}

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
        $surfaces = array_values(array_filter((array) $value, fn ($surface) => is_string($surface)));

        $dentalConditionId = $this->data['dental_condition_id'] ?? $this->fallbackDentalConditionId;
        $condition = $dentalConditionId ? DentalCondition::find($dentalConditionId) : null;

        if ($condition) {
            if ($condition->applies_to_surface && count($surfaces) === 0) {
                $fail('At least one surface is required for this condition.');
            }

            if (! $condition->applies_to_surface && count($surfaces) > 0) {
                $fail('Surfaces are not applicable to this condition.');
            }
        }

        $toothNumber = $this->data['tooth_number'] ?? $this->fallbackToothNumber;

        if (! $toothNumber || ! ToothChart::isValidCode($toothNumber)) {
            return;
        }

        $isAnterior = ToothChart::isAnterior($toothNumber);

        if (in_array('O', $surfaces, true) && $isAnterior) {
            $fail('The Occlusal (O) surface is only valid on posterior teeth.');
        }

        if (in_array('I', $surfaces, true) && ! $isAnterior) {
            $fail('The Incisal (I) surface is only valid on anterior teeth.');
        }
    }
}
