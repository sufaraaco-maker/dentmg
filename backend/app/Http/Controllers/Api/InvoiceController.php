<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\TreatmentPlanItemResource;
use App\Models\Invoice;
use App\Models\Patient;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Eager-loaded on every response (design doc §9/§13): mutation endpoints return the full
     * updated invoice so the frontend re-hydrates from one response, avoiding the
     * N+1-per-mutation gap already logged as TECH_DEBT.md debt for Appointments/Dental Chart.
     *
     * @var list<string>
     */
    private const WITH = ['items.treatmentPlanItem', 'createdBy', 'payments'];

    public function __construct(private InvoiceService $invoiceService) {}

    /**
     * Not paginated — same documented exception as Treatment Plans/Dental Chart/Patient audit
     * logs (design doc §9): a single patient's lifetime invoice count stays small.
     */
    public function index(Patient $patient)
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = Invoice::query()
            ->forPatient($patient->id)
            ->with(self::WITH)
            ->latest()
            ->get();

        return InvoiceResource::collection($invoices);
    }

    /**
     * Clinic-wide, paginated — the Sidebar's Billing entry needs a real destination across every
     * patient (frontend-ux-redesign design doc §5.1/§11), unlike `index()` above which stays
     * intentionally unpaginated per-patient. Same `viewAny` ability, same visibility rules.
     */
    public function indexAll(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        // `tryFrom`, not `from` — an unrecognized value is simply ignored (no filter applied)
        // rather than a 500, since this is a plain query param, not a validated FormRequest body.
        $status = $request->filled('status') ? InvoiceStatus::tryFrom($request->string('status')->value()) : null;

        $invoices = $this->invoiceService->paginateAll(
            $request->string('search')->value() ?: null,
            $status,
        );

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request, Patient $patient)
    {
        $invoice = $this->invoiceService->createDraft(
            [...$request->validated(), 'patient_id' => $patient->id],
            $request->user()
        );

        return (new InvoiceResource($invoice->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice->load(self::WITH));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $invoice = $this->invoiceService->updateDraft($invoice, $request->validated());

        return new InvoiceResource($invoice->load(self::WITH));
    }

    public function issue(Invoice $invoice)
    {
        $this->authorize('issue', $invoice);

        $invoice = $this->invoiceService->issue($invoice);

        return new InvoiceResource($invoice->load(self::WITH));
    }

    public function void(Invoice $invoice)
    {
        $this->authorize('void', $invoice);

        $invoice = $this->invoiceService->void($invoice);

        return new InvoiceResource($invoice->load(self::WITH));
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        $this->invoiceService->deleteInvoice($invoice);

        return response()->noContent();
    }

    /**
     * The "not yet invoiced, completed" picker source for design doc §3 step 1 — a derived,
     * read-only query (§7), not a new stored concept. Lives here rather than on
     * TreatmentPlanItemController because "already invoiced" is answered by querying
     * `invoice_items`, a Billing-owned table Treatment Plans has no reason to know about.
     */
    public function billableTreatmentPlanItems(Patient $patient)
    {
        $this->authorize('viewAny', Invoice::class);

        $items = $this->invoiceService->billableTreatmentPlanItems($patient->id);

        return TreatmentPlanItemResource::collection($items);
    }
}
