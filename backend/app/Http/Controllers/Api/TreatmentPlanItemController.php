<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TreatmentPlanItem\StoreTreatmentPlanItemRequest;
use App\Http\Requests\TreatmentPlanItem\UpdateTreatmentPlanItemRequest;
use App\Http\Resources\TreatmentPlanResource;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Services\TreatmentPlanService;
use Illuminate\Http\Request;

class TreatmentPlanItemController extends Controller
{
    /**
     * Item mutation endpoints return the full parent plan, not the item alone (design doc §9): an
     * item change moves the plan's derived cost roll-up, so returning the plan lets the frontend
     * re-hydrate from one response instead of a second `GET .../treatment-plans/{plan}` round-trip.
     *
     * @var list<string>
     */
    private const WITH = ['items.dentalCondition', 'items.diagnosisEntry', 'items.appointment', 'dentist', 'createdBy', 'supersededByPlan'];

    public function __construct(private TreatmentPlanService $treatmentPlanService) {}

    public function store(StoreTreatmentPlanItemRequest $request, TreatmentPlan $treatment_plan)
    {
        $this->treatmentPlanService->addItem($treatment_plan, $request->validated(), $request->user());

        return (new TreatmentPlanResource($treatment_plan->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(UpdateTreatmentPlanItemRequest $request, TreatmentPlanItem $treatment_plan_item)
    {
        $item = $this->treatmentPlanService->updateItem($treatment_plan_item, $request->validated(), $request->user());

        return new TreatmentPlanResource($item->treatmentPlan->load(self::WITH));
    }

    public function complete(Request $request, TreatmentPlanItem $treatment_plan_item)
    {
        $this->authorize('complete', $treatment_plan_item);

        $item = $this->treatmentPlanService->completeItem($treatment_plan_item, $request->user());

        return new TreatmentPlanResource($item->treatmentPlan->load(self::WITH));
    }

    public function cancel(Request $request, TreatmentPlanItem $treatment_plan_item)
    {
        $this->authorize('cancel', $treatment_plan_item);

        $item = $this->treatmentPlanService->cancelItem($treatment_plan_item, $request->user());

        return new TreatmentPlanResource($item->treatmentPlan->load(self::WITH));
    }

    public function destroy(TreatmentPlanItem $treatment_plan_item)
    {
        $this->authorize('delete', $treatment_plan_item);

        $this->treatmentPlanService->deleteItem($treatment_plan_item);

        return response()->noContent();
    }
}
