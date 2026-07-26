<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplyCategory\StoreSupplyCategoryRequest;
use App\Http\Requests\SupplyCategory\UpdateSupplyCategoryRequest;
use App\Http\Resources\SupplyCategoryResource;
use App\Models\SupplyCategory;
use App\Services\SupplyCategoryService;

class SupplyCategoryController extends Controller
{
    public function __construct(private SupplyCategoryService $supplyCategoryService) {}

    public function index()
    {
        $this->authorize('viewAny', SupplyCategory::class);

        return SupplyCategoryResource::collection($this->supplyCategoryService->list());
    }

    public function store(StoreSupplyCategoryRequest $request)
    {
        $category = $this->supplyCategoryService->create($request->validated());

        return (new SupplyCategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(SupplyCategory $supplyCategory)
    {
        $this->authorize('view', $supplyCategory);

        return new SupplyCategoryResource($supplyCategory);
    }

    public function update(UpdateSupplyCategoryRequest $request, SupplyCategory $supplyCategory)
    {
        $supplyCategory = $this->supplyCategoryService->update($supplyCategory, $request->validated());

        return new SupplyCategoryResource($supplyCategory);
    }

    public function destroy(SupplyCategory $supplyCategory)
    {
        $this->authorize('delete', $supplyCategory);

        $this->supplyCategoryService->deactivate($supplyCategory);

        return response()->noContent();
    }
}
