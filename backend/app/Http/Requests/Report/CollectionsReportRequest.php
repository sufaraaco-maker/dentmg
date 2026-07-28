<?php

namespace App\Http\Requests\Report;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionsReportRequest extends FormRequest
{
    /**
     * Financial report — admin only (design doc §5, Approval Log item 3).
     */
    public function authorize(): bool
    {
        return $this->user()->can('view-financial-reports');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'format' => ['nullable', 'in:csv'],
        ];
    }
}
