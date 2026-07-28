<?php

namespace App\Services;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Summary stat cards for the main dashboard.
     * Falls back to 0 for modules that are not implemented yet.
     */
    public function summary(): array
    {
        return [
            'total_patients' => $this->countIfExists('patients'),
            'today_appointments' => $this->countIfExists('appointments'),
            'monthly_revenue' => $this->monthlyRevenue(),
        ];
    }

    /**
     * Delegates to `ReportService::collections()` for the current calendar month rather than
     * reimplementing the aggregation (Reports design doc, Approval Log item 5) — this is the same
     * number the Collections Report shows for "this month", just read from the Dashboard.
     */
    private function monthlyRevenue(): string
    {
        $today = Date::today();

        return $this->reportService->collections(
            $today->copy()->startOfMonth()->toDateString(),
            $today->copy()->endOfMonth()->toDateString(),
        )['summary']['total'];
    }

    private function countIfExists(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }
}
