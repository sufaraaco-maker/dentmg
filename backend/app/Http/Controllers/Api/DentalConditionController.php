<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DentalCondition\StoreDentalConditionRequest;
use App\Http\Requests\DentalCondition\UpdateDentalConditionRequest;
use App\Http\Resources\DentalConditionResource;
use App\Models\DentalCondition;
use App\Services\DentalConditionService;

class DentalConditionController extends Controller
{
    public function __construct(private DentalConditionService $dentalConditionService) {}

    public function index()
    {
        $this->authorize('viewAny', DentalCondition::class);

        $conditions = $this->dentalConditionService->list();

        return DentalConditionResource::collection($conditions);
    }

    public function store(StoreDentalConditionRequest $request)
    {
        $condition = $this->dentalConditionService->create($request->validated());

        return (new DentalConditionResource($condition))->response()->setStatusCode(201);
    }

    public function show(DentalCondition $dentalCondition)
    {
        $this->authorize('view', $dentalCondition);

        return new DentalConditionResource($dentalCondition);
    }

    public function update(UpdateDentalConditionRequest $request, DentalCondition $dentalCondition)
    {
        $dentalCondition = $this->dentalConditionService->update($dentalCondition, $request->validated());

        return new DentalConditionResource($dentalCondition);
    }

    public function destroy(DentalCondition $dentalCondition)
    {
        $this->authorize('delete', $dentalCondition);

        $this->dentalConditionService->delete($dentalCondition);

        return response()->noContent();
    }
}
