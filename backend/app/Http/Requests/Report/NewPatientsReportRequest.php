<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class NewPatientsReportRequest extends FormRequest
{
    /**
     * Operational report — open to every role (design doc §5, Approval Log item 3).
     */
    public function authorize(): bool
    {
        return $this->user()->can('view-operational-reports');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['nullable', 'in:csv'],
        ];
    }
}
