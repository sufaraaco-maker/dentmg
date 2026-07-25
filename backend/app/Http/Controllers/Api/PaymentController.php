<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ApplyPaymentRequest;
use App\Http\Requests\Payment\RefundPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Patient;
use App\Models\Payment;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    /** @var list<string> */
    private const WITH = ['createdBy'];

    public function __construct(private PaymentService $paymentService) {}

    /**
     * Not paginated (design doc §9) — same documented exception class as Invoices/Treatment Plans:
     * a single patient's lifetime payment count stays small. No
     * `GET /api/invoices/{invoice}/payments` (§9/§7) — the frontend filters this list client-side
     * by `invoice_id` per invoice, the same technique already used for billable treatment plan items.
     */
    public function index(Patient $patient)
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::query()
            ->forPatient($patient->id)
            ->with(self::WITH)
            ->latest('received_at')
            ->get();

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
