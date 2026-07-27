<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabRequest;
use App\Http\Requests\Lab\UpdateLabRequest;
use App\Http\Resources\LabResource;
use App\Models\Lab;
use App\Services\LabService;

class LabController extends Controller
{
    public function __construct(private LabService $labService) {}

    public function index()
    {
        $this->authorize('viewAny', Lab::class);

        return LabResource::collection($this->labService->list());
    }

    public function store(StoreLabRequest $request)
    {
        $lab = $this->labService->create($request->validated());

        return (new LabResource($lab))->response()->setStatusCode(201);
    }

    public function show(Lab $lab)
    {
        $this->authorize('view', $lab);

        return new LabResource($lab);
    }

    public function update(UpdateLabRequest $request, Lab $lab)
    {
        $lab = $this->labService->update($lab, $request->validated());

        return new LabResource($lab);
    }

    public function destroy(Lab $lab)
    {
        $this->authorize('delete', $lab);

        $this->labService->deactivate($lab);

        return response()->noContent();
    }
}
