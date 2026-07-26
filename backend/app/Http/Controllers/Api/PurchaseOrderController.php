<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    /** @var list<string> */
    private const WITH = ['supplier', 'createdBy', 'items.supply'];

    public function __construct(private PurchaseOrderService $purchaseOrderService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::query()
            ->with(self::WITH)
            ->when($request->query('supplier_id'), fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return PurchaseOrderResource::collection($orders);
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $order = $this->purchaseOrderService->create($request->validated(), $request->user());

        return (new PurchaseOrderResource($order->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchase_order)
    {
        $this->authorize('view', $purchase_order);

        return new PurchaseOrderResource($purchase_order->load(self::WITH));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        $order = $this->purchaseOrderService->update($purchase_order, $request->validated());

        return new PurchaseOrderResource($order->load(self::WITH));
    }

    public function place(PurchaseOrder $purchase_order)
    {
        $this->authorize('place', $purchase_order);

        $order = $this->purchaseOrderService->place($purchase_order);

        return new PurchaseOrderResource($order->load(self::WITH));
    }

    public function cancel(PurchaseOrder $purchase_order)
    {
        $this->authorize('cancel', $purchase_order);

        $order = $this->purchaseOrderService->cancel($purchase_order);

        return new PurchaseOrderResource($order->load(self::WITH));
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        $this->authorize('delete', $purchase_order);

        $this->purchaseOrderService->delete($purchase_order);

        return response()->noContent();
    }
}
