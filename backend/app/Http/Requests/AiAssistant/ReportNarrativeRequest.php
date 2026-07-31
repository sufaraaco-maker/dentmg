<?php

namespace App\Http\Requests\AiAssistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Explain this report" (design doc §4.3) — admin-only for the three financial reports, matching
 * `ReportPolicy`'s Gates exactly; re-fetches the report itself via `ReportService` rather than
 * trusting a client-supplied summary, so every number the model narrates is still one this
 * server actually computed (§6).
 */
class ReportNarrativeRequest extends FormRequest
{
    private const FINANCIAL_TYPES = ['production', 'collections', 'ar_aging'];

    private const OPERATIONAL_TYPES = ['appointment_analytics', 'treatment_plan_acceptance', 'new_patients'];

    /**
     * An unrecognized `report_type` authorizes here and is rejected by `rules()` instead (422, not
     * 403) — `authorize()` runs before validation, so returning `false` for a merely-invalid value
     * would misreport a bad-input error as a permissions error.
     */
    public function authorize(): bool
    {
        $type = $this->input('report_type');

        if (in_array($type, self::FINANCIAL_TYPES, true)) {
            return $this->user()->can('view-financial-reports');
        }

        if (in_array($type, self::OPERATIONAL_TYPES, true)) {
            return $this->user()->can('view-operational-reports');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $needsDateRange = $this->input('report_type') !== 'ar_aging';

        return [
            'report_type' => ['required', Rule::in([...self::FINANCIAL_TYPES, ...self::OPERATIONAL_TYPES])],
            'date_from' => [Rule::requiredIf($needsDateRange), 'date'],
            'date_to' => [Rule::requiredIf($needsDateRange), 'date', 'after_or_equal:date_from'],
            'dentist_id' => ['nullable', 'uuid'],
            'method' => ['nullable', 'string'],
        ];
    }
}
