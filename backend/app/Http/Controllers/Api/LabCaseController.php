<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabCase\StoreLabCaseRequest;
use App\Http\Requests\LabCase\UpdateLabCaseRequest;
use App\Http\Resources\LabCaseResource;
use App\Models\LabCase;
use App\Models\Patient;
use App\Services\LabCaseService;
use Illuminate\Http\Request;

class LabCaseController extends Controller
{
    /** @var list<string> */
    private const WITH = ['patient', 'lab', 'dentist'];

    public function __construct(private LabCaseService $labCaseService) {}

    /**
     * Clinic-wide list (the standalone Lab Cases page). No longer accepts `?patient_id=` — that
     * filter was confirmed unused by any frontend caller (Phase 2.4 audit,
     * patient-laboratory-redesign-design.md §4.1) and is superseded by the real patient-scoped
     * route below.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', LabCase::class);

        $cases = LabCase::query()
            ->with(self::WITH)
            ->when($request->query('lab_id'), fn ($query, $labId) => $query->where('lab_id', $labId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return LabCaseResource::collection($cases);
    }

    /**
     * Patient Profile's Laboratory tab (Phase 2.4, design doc §4.3) — paginated at 15/page,
     * matching every other patient-scoped list added since Phase 2.1. `patient` is omitted from
     * the eager-load list (unlike index() above): the panel already knows `patientId` from its own
     * prop, so LabCaseResource's whenLoaded('patient') correctly omits it from the response.
     */
    public function forPatient(Request $request, Patient $patient)
    {
        $this->authorize('viewAny', LabCase::class);

        $cases = LabCase::query()
            ->forPatient($patient->id)
            ->with(['lab', 'dentist'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate(15);

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
