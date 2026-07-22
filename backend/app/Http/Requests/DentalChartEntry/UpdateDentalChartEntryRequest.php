<?php

namespace App\Http\Requests\DentalChartEntry;

use App\Enums\UserRole;
use App\Models\DentalChartEntry;
use App\Rules\ValidDentalChartSurfaces;
use App\Support\ToothChart;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDentalChartEntryRequest extends FormRequest
{
    /**
     * Same shape as Store minus `status` — transitions only move through the dedicated
     * `/complete` and `/cancel` endpoints (plan §1.6/§1.8). Whether the entry is actually
     * editable in its current status (terminal states reject everything but `notes`) is a
     * DentalChartService concern, not this layer's.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('dental_chart_entry'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var DentalChartEntry|null $entry */
        $entry = $this->route('dental_chart_entry');

        return [
            'dental_condition_id' => ['sometimes', 'required', 'uuid', Rule::exists('dental_conditions', 'id')],
            'dentist_id' => [
                'sometimes', 'required', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],
            'tooth_number' => ['sometimes', 'required', 'string', function ($attribute, $value, $fail) {
                if (! ToothChart::isValidCode($value)) {
                    $fail('The selected tooth number is not a valid FDI tooth code.');
                }
            }],
            'surfaces' => [
                'sometimes', 'array',
                new ValidDentalChartSurfaces($entry?->dental_condition_id, $entry?->tooth_number),
            ],
            'surfaces.*' => [Rule::in(['M', 'D', 'F', 'L', 'O', 'I'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'recorded_at' => ['sometimes', 'required', 'date'],
        ];
    }
}
