<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DentalChartEntry\IndexDentalChartEntryRequest;
use App\Http\Requests\DentalChartEntry\StoreDentalChartEntryRequest;
use App\Http\Requests\DentalChartEntry\UpdateDentalChartEntryRequest;
use App\Http\Resources\DentalChartEntryResource;
use App\Models\DentalChartEntry;
use App\Models\Patient;
use App\Services\DentalChartService;
use Illuminate\Http\Request;

class DentalChartEntryController extends Controller
{
    public function __construct(private DentalChartService $dentalChartService) {}

    public function index(IndexDentalChartEntryRequest $request, Patient $patient)
    {
        $this->authorize('viewAny', DentalChartEntry::class);

        $entries = $this->dentalChartService->listForPatient($patient->id, $request->validated());

        return DentalChartEntryResource::collection($entries);
    }

    public function store(StoreDentalChartEntryRequest $request, Patient $patient)
    {
        $entry = $this->dentalChartService->create(
            [...$request->validated(), 'patient_id' => $patient->id],
            $request->user()
        );

        return (new DentalChartEntryResource($entry))->response()->setStatusCode(201);
    }

    public function update(UpdateDentalChartEntryRequest $request, DentalChartEntry $dental_chart_entry)
    {
        $entry = $this->dentalChartService->update($dental_chart_entry, $request->validated(), $request->user());

        return new DentalChartEntryResource($entry);
    }

    public function complete(Request $request, DentalChartEntry $dental_chart_entry)
    {
        $this->authorize('complete', $dental_chart_entry);

        $entry = $this->dentalChartService->complete($dental_chart_entry, $request->user());

        return new DentalChartEntryResource($entry);
    }

    public function cancel(Request $request, DentalChartEntry $dental_chart_entry)
    {
        $this->authorize('cancel', $dental_chart_entry);

        $entry = $this->dentalChartService->cancel($dental_chart_entry, $request->user());

        return new DentalChartEntryResource($entry);
    }

    public function destroy(DentalChartEntry $dental_chart_entry)
    {
        $this->authorize('delete', $dental_chart_entry);

        $this->dentalChartService->delete($dental_chart_entry);

        return response()->noContent();
    }
}
