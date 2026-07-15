<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    /**
     * Summary stat cards for the main dashboard.
     * Falls back to 0 for modules that are not implemented yet.
     */
    public function summary(): array
    {
        return [
            'total_patients' => $this->countIfExists('patients'),
            'today_appointments' => $this->countIfExists('appointments'),
            'monthly_revenue' => 0,
        ];
    }

    private function countIfExists(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }
}
