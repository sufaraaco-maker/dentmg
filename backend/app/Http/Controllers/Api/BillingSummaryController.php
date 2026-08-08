<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\BillingSummaryService;

class BillingSummaryController extends Controller
{
    public function __construct(private BillingSummaryService $billingSummaryService) {}

    /**
     * Aggregate-only response (design doc §8/§11.4) — a plain array via `response()->json()`, not a
     * Resource class, matching `ReportController`'s existing convention for report-style endpoints
     * that aren't a single Eloquent model's representation.
     */
    public function show(Patient $patient)
    {
        $this->authorize('viewAny', Invoice::class);
        $this->authorize('viewAny', Payment::class);

        return response()->json($this->billingSummaryService->forPatient($patient->id));
    }
}
