<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockMovement\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Models\Supply;
use App\Services\StockMovementService;

class StockMovementController extends Controller
{
    public function __construct(private StockMovementService $stockMovementService) {}

    public function index(Supply $supply)
    {
        $this->authorize('viewAny', StockMovement::class);

        $movements = $this->stockMovementService->paginate($supply);

        return StockMovementResource::collection($movements);
    }

    public function store(StoreStockMovementRequest $request, Supply $supply)
    {
        $movement = $this->stockMovementService->record($supply, $request->validated(), $request->user());

        return (new StockMovementResource($movement->load('performedBy')))->response()->setStatusCode(201);
    }
}
