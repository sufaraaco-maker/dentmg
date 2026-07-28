<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class ArAgingReportRequest extends FormRequest
{
    /**
     * Financial report — admin only (design doc §5, Approval Log item 3).
     */
    public function authorize(): bool
    {
        return $this->user()->can('view-financial-reports');
    }

    /**
     * No date range — a point-in-time "as of today" snapshot (design doc §4.3).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', 'in:csv'],
        ];
    }
}
