<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ApplyPaymentRequest;
use App\Http\Requests\Payment\RefundPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    /** @var list<string> */
    private const WITH = ['createdBy'];

    public function __construct(private PaymentService $paymentService) {}

    /**
     * Paginated (Phase 2.2, design doc §11/§14.4 — deferred from Phase 2.1, see TECH_DEBT.md's
     * now-resolved entry): 15/page, same convention as `InvoiceController::index()`.
     */
    public function index(Patient $patient)
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::query()
            ->forPatient($patient->id)
            ->with(self::WITH)
            ->latest('received_at')
            ->paginate(15);

        return PaymentResource::collection($payments);
    }

    /**
     * Payments applied to one specific invoice (TECH_DEBT.md, Phase 2.2) — via `Invoice::payments()`
     * (an existing `HasMany`), not a new scope; the FK relation already expresses exactly this
     * query. Replaces `InvoicePaymentsPanel.vue`'s former client-side filter of the full
     * patient-level payment list. Paginated, same 15/page convention as `index()` above.
     */
    public function forInvoice(Invoice $invoice)
    {
        $this->authorize('viewAny', Payment::class);

        $payments = $invoice->payments()
            ->with(self::WITH)
            ->latest('received_at')
            ->paginate(15);

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request, Patient $patient)
    {
        $payment = $this->paymentService->record(
            [...$request->validated(), 'patient_id' => $patient->id],
            $request->user()
        );

        return (new PaymentResource($payment->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(Payment $payment)
    {
        $this->authorize('view', $payment);

        return new PaymentResource($payment->load(self::WITH));
    }

    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        $payment = $this->paymentService->updateMetadata($payment, $request->validated());

        return new PaymentResource($payment->load(self::WITH));
    }

    public function apply(ApplyPaymentRequest $request, Payment $payment)
    {
        $payment = $this->paymentService->apply($payment, $request->validated()['invoice_id']);

        return new PaymentResource($payment->load(self::WITH));
    }

    public function refund(RefundPaymentRequest $request, Payment $payment)
    {
        $refund = $this->paymentService->refund(
            $payment,
            (string) $request->validated()['amount'],
            $request->validated()['notes'] ?? null,
            $request->user()
        );

        return (new PaymentResource($refund->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function destroy(Payment $payment)
    {
        $this->authorize('delete', $payment);

        $this->paymentService->delete($payment);

        return response()->noContent();
    }
}
