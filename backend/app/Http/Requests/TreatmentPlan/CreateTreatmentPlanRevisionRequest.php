<?php

namespace App\Http\Requests\TreatmentPlan;

use App\Enums\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Backs `POST /treatment-plans/{treatment_plan}/revisions` — a discovery made while wiring the API
 * layer: the design doc's §9 endpoint table didn't list a route for
 * `TreatmentPlanService::createSupersedingPlan()`, even though that service method, the
 * `TreatmentPlanPolicy::createRevision()` ability, and §15 Q4's "revision" behavior were all
 * already approved in Step 2. §9 has been updated to document this endpoint (not a scope
 * expansion — the underlying behavior was already designed and approved).
 *
 * All fields are optional overrides for the new plan; anything omitted falls back to the original
 * plan's own value (`TreatmentPlanService::createSupersedingPlan()`).
 */
class CreateTreatmentPlanRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createRevision', $this->route('treatment_plan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dentist_id' => [
                'sometimes', 'required', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
