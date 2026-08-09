<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class DashboardFinancialSummaryRequest extends FormRequest
{
    /**
     * Revenue-bearing dashboard data — admin only, same gate and reasoning as `reports/collections`/
     * `reports/production`/`reports/ar-aging` (`ReportController`'s `view-financial-reports` Gate).
     */
    public function authorize(): bool
    {
        return $this->user()->can('view-financial-reports');
    }

    /**
     * No inputs — this is a point-in-time snapshot, same as `ArAgingReportRequest`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
