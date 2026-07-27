<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabCase\StoreLabCaseRequest;
use App\Http\Requests\LabCase\UpdateLabCaseRequest;
use App\Http\Resources\LabCaseResource;
use App\Models\LabCase;
use App\Services\LabCaseService;
use Illuminate\Http\Request;

class LabCaseController extends Controller
{
    /** @var list<string> */
    private const WITH = ['patient', 'lab', 'dentist'];

    public function __construct(private LabCaseService $labCaseService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', LabCase::class);

        $cases = LabCase::query()
            ->with(self::WITH)
            ->when($request->query('patient_id'), fn ($query, $patientId) => $query->where('patient_id', $patientId))
            ->when($request->query('lab_id'), fn ($query, $labId) => $query->where('lab_id', $labId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return LabCaseResource::collection($cases);
    }

    /**
     * Dashboard widget (design doc §2/§6): due today or overdue, still out at the lab.
     */
    public function dueOrOverdue()
    {
        $this->authorize('viewAny', LabCase::class);

        $cases = LabCase::query()->dueOrOverdue()->with(self::WITH)->orderBy('due_at')->get();

        return LabCaseResource::collection($cases);
    }

    public function store(StoreLabCaseRequest $request)
    {
        $labCase = $this->labCaseService->create($request->validated());

        return (new LabCaseResource($labCase->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(LabCase $lab_case)
    {
        $this->authorize('view', $lab_case);

        return new LabCaseResource($lab_case->load(self::WITH));
    }

    public function update(UpdateLabCaseRequest $request, LabCase $lab_case)
    {
        $labCase = $this->labCaseService->update($lab_case, $request->validated());

        return new LabCaseResource($labCase->load(self::WITH));
    }

    public function send(LabCase $lab_case)
    {
        $this->authorize('send', $lab_case);

        $labCase = $this->labCaseService->send($lab_case);

        return new LabCaseResource($labCase->load(self::WITH));
    }

    public function receive(LabCase $lab_case)
    {
        $this->authorize('receive', $lab_case);

        $labCase = $this->labCaseService->receive($lab_case);

        return new LabCaseResource($labCase->load(self::WITH));
    }

    public function qualityCheck(LabCase $lab_case)
    {
        $this->authorize('qualityCheck', $lab_case);

        $labCase = $this->labCaseService->qualityCheck($lab_case);

        return new LabCaseResource($labCase->load(self::WITH));
    }

    public function cancel(LabCase $lab_case)
    {
        $this->authorize('cancel', $lab_case);

        $labCase = $this->labCaseService->cancel($lab_case);

        return new LabCaseResource($labCase->load(self::WITH));
    }

    public function destroy(LabCase $lab_case)
    {
        $this->authorize('delete', $lab_case);

        $this->labCaseService->delete($lab_case);

        return response()->noContent();
    }
}
