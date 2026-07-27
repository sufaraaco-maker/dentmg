<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\ReceivePurchaseOrderItemRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderItemRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderItemRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\PurchaseOrderService;

class PurchaseOrderItemController extends Controller
{
    /**
     * Item mutation endpoints return the full parent order, not the item alone (mirrors
     * InvoiceItemController exactly) — the frontend re-hydrates from one response.
     *
     * @var list<string>
     */
    private const WITH = ['supplier', 'createdBy', 'items.supply'];

    public function __construct(private PurchaseOrderService $purchaseOrderService) {}

    public function store(StorePurchaseOrderItemRequest $request, PurchaseOrder $purchase_order)
    {
        $this->purchaseOrderService->addItem($purchase_order, $request->validated());

        return (new PurchaseOrderResource($purchase_order->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(UpdatePurchaseOrderItemRequest $request, PurchaseOrderItem $purchase_order_item)
    {
        $item = $this->purchaseOrderService->updateItem($purchase_order_item, $request->validated());

        return new PurchaseOrderResource($item->purchaseOrder->load(self::WITH));
    }

    public function destroy(PurchaseOrderItem $purchase_order_item)
    {
        $this->authorize('update', $purchase_order_item->purchaseOrder);

        $order = $purchase_order_item->purchaseOrder;

        $this->purchaseOrderService->deleteItem($purchase_order_item);

        return new PurchaseOrderResource($order->load(self::WITH));
    }

    public function receive(ReceivePurchaseOrderItemRequest $request, PurchaseOrderItem $purchase_order_item)
    {
        $item = $this->purchaseOrderService->receive(
            $purchase_order_item,
            (int) $request->validated()['quantity'],
            $request->validated()['expiration_date'] ?? null,
            $request->user()
        );

        return new PurchaseOrderResource($item->purchaseOrder->load(self::WITH));
    }
}
