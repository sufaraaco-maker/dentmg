<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supply\StoreSupplyRequest;
use App\Http\Requests\Supply\UpdateSupplyRequest;
use App\Http\Resources\SupplyResource;
use App\Models\Supply;
use App\Services\SupplyService;
use Illuminate\Http\Request;

class SupplyController extends Controller
{
    /** @var list<string> */
    private const WITH = ['category', 'defaultSupplier'];

    public function __construct(private SupplyService $supplyService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Supply::class);

        $supplies = $this->supplyService->paginate(
            search: $request->query('search'),
            categoryId: $request->query('category_id'),
            lowStockOnly: $request->boolean('low_stock_only'),
            perPage: (int) $request->query('per_page', 20),
        );

        return SupplyResource::collection($supplies);
    }

    /**
     * Design doc §9: the Low Stock list / Dashboard widget source — every active Supply whose
     * computed on-hand is at or below its own reorder_level. Deliberately not paginated: a
     * dashboard widget wants the full at-risk set at a glance, not a page of it.
     */
    public function lowStock()
    {
        $this->authorize('viewAny', Supply::class);

        $supplies = Supply::query()->active()->lowStock()->with(self::WITH)->get();

        return SupplyResource::collection($supplies);
    }

    public function store(StoreSupplyRequest $request)
    {
        $supply = $this->supplyService->create($request->validated());

        return (new SupplyResource($supply->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(Supply $supply)
    {
        $this->authorize('view', $supply);

        return new SupplyResource($supply->load(self::WITH));
    }

    public function update(UpdateSupplyRequest $request, Supply $supply)
    {
        $supply = $this->supplyService->update($supply, $request->validated());

        return new SupplyResource($supply->load(self::WITH));
    }

    public function destroy(Supply $supply)
    {
        $this->authorize('delete', $supply);

        $this->supplyService->deactivate($supply);

        return response()->noContent();
    }
}
