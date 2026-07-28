<?php

namespace App\Http\Requests\Report;

use App\Enums\UserRole;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductionReportRequest extends FormRequest
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
            'dentist_id' => [
                'nullable', 'uuid',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query) => $query->where('role', UserRole::Dentist->value)->whereNull('deleted_at')
                ),
            ],
            'format' => ['nullable', 'in:csv'],
        ];
    }
}
