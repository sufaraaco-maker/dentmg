<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AppointmentAnalyticsReportRequest;
use App\Http\Requests\Report\ArAgingReportRequest;
use App\Http\Requests\Report\CollectionsReportRequest;
use App\Http\Requests\Report\NewPatientsReportRequest;
use App\Http\Requests\Report\ProductionReportRequest;
use App\Http\Requests\Report\TreatmentPlanAcceptanceReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every action: validate/authorize via a dedicated Form Request → call ReportService → return
 * JSON or stream a CSV (design doc §6). No query building or business rules live here — same
 * "thin controller" convention as every other module (`docs/api-guidelines.md`).
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function production(ProductionReportRequest $request): JsonResponse|StreamedResponse
    {
        $data = $this->reportService->production(
            $request->validated('date_from'),
            $request->validated('date_to'),
            $request->validated('dentist_id'),
        );

        return $this->respond($request, $data, 'production');
    }

    public function collections(CollectionsReportRequest $request): JsonResponse|StreamedResponse
    {
        $method = $request->validated('method');

        $data = $this->reportService->collections(
            $request->validated('date_from'),
            $request->validated('date_to'),
            $method !== null ? PaymentMethod::from($method) : null,
        );

        return $this->respond($request, $data, 'collections');
    }

    public function arAging(ArAgingReportRequest $request): JsonResponse|StreamedResponse
    {
        return $this->respond($request, $this->reportService->arAging(), 'ar-aging');
    }

    public function appointmentAnalytics(AppointmentAnalyticsReportRequest $request): JsonResponse|StreamedResponse
    {
        $data = $this->reportService->appointmentAnalytics(
            $request->validated('date_from'),
            $request->validated('date_to'),
            $request->validated('dentist_id'),
        );

        return $this->respond($request, $data, 'appointment-analytics');
    }

    public function treatmentPlanAcceptance(TreatmentPlanAcceptanceReportRequest $request): JsonResponse|StreamedResponse
    {
        $data = $this->reportService->treatmentPlanAcceptance(
            $request->validated('date_from'),
            $request->validated('date_to'),
            $request->validated('dentist_id'),
        );

        return $this->respond($request, $data, 'treatment-plan-acceptance');
    }

    public function newPatients(NewPatientsReportRequest $request): JsonResponse|StreamedResponse
    {
        $data = $this->reportService->newPatients(
            $request->validated('date_from'),
            $request->validated('date_to'),
        );

        return $this->respond($request, $data, 'new-patients');
    }

    /**
     * @param  array{summary: mixed, rows: list<array<string, mixed>>}  $data
     */
    private function respond(Request $request, array $data, string $filename): JsonResponse|StreamedResponse
    {
        if ($request->query('format') === 'csv') {
            return $this->streamCsv($data['rows'], $filename);
        }

        return response()->json($data);
    }

    /**
     * Native `fputcsv`, no new Composer dependency — matches this codebase's "browser print
     * instead of a PDF library" precedent (Laboratory's slip, Imaging's viewer) applied to CSV
     * (design doc §6/§8 decision 3).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function streamCsv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            if ($rows !== []) {
                fputcsv($handle, array_keys($rows[0]));

                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv']);
    }
}
