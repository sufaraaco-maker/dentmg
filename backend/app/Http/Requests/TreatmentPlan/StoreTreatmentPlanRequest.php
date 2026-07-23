<?php

namespace App\Http\Requests\TreatmentPlan;

use App\Enums\UserRole;
use App\Models\TreatmentPlan;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTreatmentPlanRequest extends FormRequest
{
    /**
     * `patient_id` is not a field here — the parent Patient comes from the nested route
     * (`patients/{patient}/treatment-plans`, design doc §9), same pattern as
     * `StoreDentalChartEntryRequest`. `status` is not a field either — a new plan always starts
     * `draft` (design doc §5); `TreatmentPlanService::createPlan()` sets it, never the client.
     * Items are added afterwards through their own endpoint (`POST .../treatment-plans/{plan}/items`,
     * design doc §9) — not bundled into plan creation, matching the API design exactly.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', TreatmentPlan::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dentist_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
