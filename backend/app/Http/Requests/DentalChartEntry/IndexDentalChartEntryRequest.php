<?php

namespace App\Http\Requests\DentalChartEntry;

use App\Enums\DentalChartEntryStatus;
use App\Enums\DentalConditionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDentalChartEntryRequest extends FormRequest
{
    /**
     * Open to any authenticated role (design draft §19 — clinic-wide read visibility); the
     * controller still calls `DentalChartEntryPolicy::viewAny()` for consistency with the rest of
     * the API, mirroring `IndexAppointmentRequest`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Plan §1.9: `?status=&tooth_number=&category=`, all optional — this is a per-patient list,
     * not paginated (design draft §14, restated in plan §1.9).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(DentalChartEntryStatus::class)],
            'tooth_number' => ['nullable', 'string'],
            'category' => ['nullable', Rule::enum(DentalConditionCategory::class)],
        ];
    }
}
