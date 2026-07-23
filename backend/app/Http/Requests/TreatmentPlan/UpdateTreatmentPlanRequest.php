<?php

namespace App\Http\Requests\TreatmentPlan;

use App\Enums\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTreatmentPlanRequest extends FormRequest
{
    /**
     * Same administrative fields as Store, all `sometimes` — no `status` (transitions only move
     * through the dedicated `/present`, `/accept`, `/reject`, `/start`, `/complete`, `/cancel`
     * endpoints, design doc §5/§9, mirroring `UpdateDentalChartEntryRequest`'s identical shape).
     * Whether the plan is actually still editable in its current status (terminal plans reject
     * every field) is a `TreatmentPlanService` concern, not this layer's.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('treatment_plan'));
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
