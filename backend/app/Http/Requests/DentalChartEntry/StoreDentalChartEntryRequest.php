<?php

namespace App\Http\Requests\DentalChartEntry;

use App\Enums\DentalChartEntryStatus;
use App\Enums\UserRole;
use App\Models\DentalChartEntry;
use App\Rules\ValidDentalChartSurfaces;
use App\Support\ToothChart;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDentalChartEntryRequest extends FormRequest
{
    /**
     * `patient_id` is not a field here — the parent Patient comes from the nested route
     * (`patients/{patient}/dental-chart-entries`, plan §1.9), same pattern as
     * `DentistWorkingHourController`'s nested `dentists/{user}/working-hours`.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', DentalChartEntry::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dental_condition_id' => ['required', 'uuid', Rule::exists('dental_conditions', 'id')],
            'dentist_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],
            'tooth_number' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! ToothChart::isValidCode($value)) {
                    $fail('The selected tooth number is not a valid FDI tooth code.');
                }
            }],
            'surfaces' => ['array', new ValidDentalChartSurfaces],
            'surfaces.*' => [Rule::in(['M', 'D', 'F', 'L', 'O', 'I'])],

            // A brand-new entry is never created pre-cancelled — `cancelled` is only reachable
            // via the dedicated `/cancel` transition endpoint (plan §1.6).
            'status' => [
                'required',
                Rule::enum(DentalChartEntryStatus::class)->only([
                    DentalChartEntryStatus::Existing,
                    DentalChartEntryStatus::Active,
                    DentalChartEntryStatus::Planned,
                    DentalChartEntryStatus::Completed,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
