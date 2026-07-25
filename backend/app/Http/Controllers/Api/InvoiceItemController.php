<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceItem\StoreInvoiceItemRequest;
use App\Http\Requests\InvoiceItem\UpdateInvoiceItemRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceService;

class InvoiceItemController extends Controller
{
    /**
     * Item mutation endpoints — including removal — return the full parent invoice, not the item
     * alone (design doc §9): every item change moves the invoice's derived total, so returning
     * the invoice lets the frontend re-hydrate from one response instead of a second
     * `GET .../invoices/{invoice}` round-trip.
     *
     * @var list<string>
     */
    private const WITH = ['items.treatmentPlanItem', 'createdBy', 'payments'];

    public function __construct(private InvoiceService $invoiceService) {}

    public function store(StoreInvoiceItemRequest $request, Invoice $invoice)
    {
        $this->invoiceService->addItem($invoice, $request->validated(), $request->user());

        return (new InvoiceResource($invoice->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(UpdateInvoiceItemRequest $request, InvoiceItem $invoice_item)
    {
        $item = $this->invoiceService->updateItem($invoice_item, $request->validated());

        return new InvoiceResource($item->invoice->load(self::WITH));
    }

    public function destroy(InvoiceItem $invoice_item)
    {
        $this->authorize('delete', $invoice_item);

        $invoice = $invoice_item->invoice;

        $this->invoiceService->deleteItem($invoice_item);

        return new InvoiceResource($invoice->load(self::WITH));
    }
}
