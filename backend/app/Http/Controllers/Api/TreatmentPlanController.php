<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TreatmentPlan\CreateTreatmentPlanRevisionRequest;
use App\Http\Requests\TreatmentPlan\StoreTreatmentPlanRequest;
use App\Http\Requests\TreatmentPlan\UpdateTreatmentPlanRequest;
use App\Http\Resources\TreatmentPlanResource;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Services\TreatmentPlanService;
use Illuminate\Http\Request;

class TreatmentPlanController extends Controller
{
    /**
     * Eager-loaded on every response (design doc §9/§13): mutation endpoints return the full
     * updated plan so the frontend re-hydrates from one response, avoiding the N+1-per-mutation
     * gap already logged as TECH_DEBT.md debt for Appointments and Dental Chart.
     *
     * @var list<string>
     */
    private const WITH = ['items.dentalCondition', 'items.diagnosisEntry', 'items.appointment', 'dentist', 'createdBy', 'supersededByPlan'];

    public function __construct(private TreatmentPlanService $treatmentPlanService) {}

    public function index(Patient $patient)
    {
        $this->authorize('viewAny', TreatmentPlan::class);

        $plans = TreatmentPlan::query()
            ->forPatient($patient->id)
            ->with(self::WITH)
            ->latest()
            ->get();

        return TreatmentPlanResource::collection($plans);
    }

    public function store(StoreTreatmentPlanRequest $request, Patient $patient)
    {
        $plan = $this->treatmentPlanService->createPlan(
            [...$request->validated(), 'patient_id' => $patient->id],
            $request->user()
        );

        return (new TreatmentPlanResource($plan->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(TreatmentPlan $treatment_plan)
    {
        $this->authorize('view', $treatment_plan);

        return new TreatmentPlanResource($treatment_plan->load(self::WITH));
    }

    public function update(UpdateTreatmentPlanRequest $request, TreatmentPlan $treatment_plan)
    {
        $plan = $this->treatmentPlanService->updatePlan($treatment_plan, $request->validated(), $request->user());

        return new TreatmentPlanResource($plan->load(self::WITH));
    }

    public function present(Request $request, TreatmentPlan $treatment_plan)
    {
        $this->authorize('present', $treatment_plan);

        $plan = $this->treatmentPlanService->present($treatment_plan, $request->user());

        return new TreatmentPlanResource($plan->load(self::WITH));
    }

    public function accept(Request $request, TreatmentPlan $treatment_plan)
    {
        $this->authorize('accept', $treatment_plan);

        $plan = $this->treatmentPlanService->accept($treatment_plan, $request->user());

        return new TreatmentPlanResource($plan->load(self::WITH));
    }

    public function reject(Request $request, TreatmentPlan $treatment_plan)
    {
        $this->authorize('reject', $treatment_plan);

        $plan = $this->treatmentPlanService->reject($treatment_plan, $request->user());

        return new TreatmentPlanResource($plan->load(self::WITH));
    }

    public function start(Request $request, TreatmentPlan $treatment_plan)
    {
        $this->authorize('start', $treatment_plan);

        $plan = $this->treatmentPlanService->start($treatment_plan, $request->user());

        return new TreatmentPlanResource($plan->load(self::WITH));
    }

    public function complete(Request $request, TreatmentPlan $treatment_plan)
    {
        $this->authorize('complete', $treatment_plan);

        $plan = $this->treatmentPlanService->complete($treatment_plan, $request->user());

        return new TreatmentPlanResource($plan->load(self::WITH));
    }

    public function cancel(Request $request, TreatmentPlan $treatment_plan)
    {
        $this->authorize('cancel', $treatment_plan);

        $plan = $this->treatmentPlanService->cancel($treatment_plan, $request->user());

        return new TreatmentPlanResource($plan->load(self::WITH));
    }

    public function storeRevision(CreateTreatmentPlanRevisionRequest $request, TreatmentPlan $treatment_plan)
    {
        $revision = $this->treatmentPlanService->createSupersedingPlan(
            $treatment_plan,
            $request->validated(),
            $request->user()
        );

        return (new TreatmentPlanResource($revision->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function destroy(TreatmentPlan $treatment_plan)
    {
        $this->authorize('delete', $treatment_plan);

        $this->treatmentPlanService->deletePlan($treatment_plan);

        return response()->noContent();
    }
}
