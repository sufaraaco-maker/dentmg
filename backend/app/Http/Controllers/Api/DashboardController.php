<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardFinancialSummaryRequest;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /** Operational data — open to every authenticated role (`auth:sanctum` only, no further gate). */
    public function summary()
    {
        return response()->json($this->dashboardService->summary());
    }

    /** Revenue-bearing data — admin only, enforced by the Form Request's `authorize()`. */
    public function financialSummary(DashboardFinancialSummaryRequest $request)
    {
        return response()->json($this->dashboardService->financialSummary());
    }
}
