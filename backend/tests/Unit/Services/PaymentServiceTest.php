<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\Payment\InvalidPaymentOperationException;
use App\Exceptions\Payment\PaymentRefundExceedsRemainingBalanceException;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaymentService;
    }

    // --- record -----------------------------------------------------------------------------------

    public function test_record_creates_an_unapplied_payment_when_no_invoice_given(): void
    {
        $patient = Patient::factory()->create();
        $actor = User::factory()->admin()->create();

        $payment = $this->service->record([
            'patient_id' => $patient->id,
            'method' => PaymentMethod::Cash,
            'amount' => 150,
        ], $actor);

        $this->assertNull($payment->invoice_id);
        $this->assertSame('150.00', (string) $payment->amount);
        $this->assertSame($actor->id, $payment->created_by_id);
    }

    public function test_record_applies_directly_to_an_issued_invoice_belonging_to_the_same_patient(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $actor = User::factory()->admin()->create();

        $payment = $this->service->record([
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'method' => PaymentMethod::Card,
            'amount' => 200,
        ], $actor);

        $this->assertSame($invoice->id, $payment->invoice_id);
    }

    public function test_record_rejects_an_invoice_belonging_to_a_different_patient(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        $otherPatient = Patient::factory()->create();
        $actor = User::factory()->admin()->create();

        $this->expectException(InvalidPaymentOperationException::class);

        $this->service->record([
            'patient_id' => $otherPatient->id,
            'invoice_id' => $invoice->id,
            'method' => PaymentMethod::Cash,
            'amount' => 100,
        ], $actor);
    }

    public function test_record_rejects_a_draft_invoice(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->create(['patient_id' => $patient->id]);
        $actor = User::factory()->admin()->create();

        $this->expectException(InvalidPaymentOperationException::class);

        $this->service->record([
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'method' => PaymentMethod::Cash,
            'amount' => 100,
        ], $actor);
    }

    public function test_record_rejects_a_zero_or_negative_amount(): void
    {
        $patient = Patient::factory()->create();
        $actor = User::factory()->admin()->create();

        $this->expectException(InvalidPaymentOperationException::class);

        $this->service->record([
            'patient_id' => $patient->id,
            'method' => PaymentMethod::Cash,
            'amount' => 0,
        ], $actor);
    }

    public function test_record_snapshots_currency_code_from_billing_settings(): void
    {
        BillingSetting::factory()->create(['currency_code' => 'EUR']);
        $patient = Patient::factory()->create();
        $actor = User::factory()->admin()->create();

        $payment = $this->service->record([
            'patient_id' => $patient->id,
            'method' => PaymentMethod::Cash,
            'amount' => 100,
        ], $actor);

        $this->assertSame('EUR', $payment->currency_code);
    }

    public function test_record_defaults_received_at_to_today_when_not_given(): void
    {
        $patient = Patient::factory()->create();
        $actor = User::factory()->admin()->create();

        $payment = $this->service->record([
            'patient_id' => $patient->id,
            'method' => PaymentMethod::Cash,
            'amount' => 100,
        ], $actor);

        $this->assertSame(now()->toDateString(), $payment->received_at->toDateString());
    }

    // --- updateMetadata -----------------------------------------------------------------------------

    public function test_update_metadata_edits_reference_notes_and_received_at(): void
    {
        $payment = Payment::factory()->create(['reference' => 'Old', 'notes' => 'Old note']);

        $updated = $this->service->updateMetadata($payment, [
            'reference' => 'New',
            'notes' => 'New note',
            'received_at' => '2026-07-10',
        ]);

        $this->assertSame('New', $updated->reference);
        $this->assertSame('New note', $updated->notes);
        $this->assertSame('2026-07-10', $updated->received_at->toDateString());
    }

    public function test_update_metadata_ignores_amount_method_and_patient_id(): void
    {
        $patient = Patient::factory()->create();
        $payment = Payment::factory()->create(['patient_id' => $patient->id, 'amount' => 100, 'method' => PaymentMethod::Cash]);

        $updated = $this->service->updateMetadata($payment, [
            'amount' => 999,
            'method' => PaymentMethod::Card->value,
            'patient_id' => Patient::factory()->create()->id,
        ]);

        $this->assertSame('100.00', (string) $updated->amount);
        $this->assertSame(PaymentMethod::Cash, $updated->method);
        $this->assertSame($patient->id, $updated->patient_id);
    }

    // --- apply --------------------------------------------------------------------------------------

    public function test_apply_sets_invoice_id_on_an_unapplied_payment(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $payment = Payment::factory()->create(['patient_id' => $patient->id, 'invoice_id' => null]);

        $applied = $this->service->apply($payment, $invoice->id);

        $this->assertSame($invoice->id, $applied->invoice_id);
    }

    public function test_apply_rejects_a_payment_already_applied(): void
    {
        $patient = Patient::factory()->create();
        $invoiceA = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $invoiceB = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $payment = Payment::factory()->create(['patient_id' => $patient->id, 'invoice_id' => $invoiceA->id]);

        $this->expectException(InvalidPaymentOperationException::class);

        $this->service->apply($payment, $invoiceB->id);
    }

    public function test_apply_rejects_a_refund_row(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $original = Payment::factory()->create(['patient_id' => $patient->id, 'amount' => 100]);
        $refund = Payment::factory()->refundOf($original)->create(['patient_id' => $patient->id]);

        $this->expectException(InvalidPaymentOperationException::class);

        $this->service->apply($refund, $invoice->id);
    }

    public function test_apply_rejects_an_invoice_belonging_to_a_different_patient(): void
    {
        $payment = Payment::factory()->create(['invoice_id' => null]);
        $otherInvoice = Invoice::factory()->issued()->create();

        $this->expectException(InvalidPaymentOperationException::class);

        $this->service->apply($payment, $otherInvoice->id);
    }

    public function test_apply_rejects_a_draft_invoice(): void
    {
        $patient = Patient::factory()->create();
        $draft = Invoice::factory()->create(['patient_id' => $patient->id]);
        $payment = Payment::factory()->create(['patient_id' => $patient->id, 'invoice_id' => null]);

        $this->expectException(InvalidPaymentOperationException::class);

        $this->service->apply($payment, $draft->id);
    }

    // --- refund -------------------------------------------------------------------------------------

    public function test_refund_creates_a_negative_payment_row_linked_to_the_original(): void
    {
        $actor = User::factory()->admin()->create();
        $original = Payment::factory()->create(['amount' => 200]);

        $refund = $this->service->refund($original, '80', 'Overpayment', $actor);

        $this->assertSame('-80.00', (string) $refund->amount);
        $this->assertSame($original->id, $refund->refunded_payment_id);
        $this->assertSame($original->patient_id, $refund->patient_id);
        $this->assertSame($original->invoice_id, $refund->invoice_id);
        $this->assertSame('Overpayment', $refund->notes);
        $this->assertSame($actor->id, $refund->created_by_id);
    }

    public function test_refund_does_not_edit_the_original_payment(): void
    {
        $original = Payment::factory()->create(['amount' => 200]);
        $actor = User::factory()->admin()->create();

        $this->service->refund($original, '80', null, $actor);

        $this->assertSame('200.00', (string) $original->fresh()->amount);
    }

    public function test_refund_allows_a_full_refund(): void
    {
        $original = Payment::factory()->create(['amount' => 150]);
        $actor = User::factory()->admin()->create();

        $refund = $this->service->refund($original, '150', null, $actor);

        $this->assertSame('-150.00', (string) $refund->amount);
        $this->assertSame('0.00', $original->fresh()->remaining_refundable_amount);
    }

    public function test_refund_allows_repeated_partial_refunds_up_to_the_remaining_balance(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        $actor = User::factory()->admin()->create();

        $this->service->refund($original, '40', null, $actor);
        $this->service->refund($original->fresh(), '60', null, $actor);

        $this->assertSame('0.00', $original->fresh()->remaining_refundable_amount);
    }

    public function test_refund_rejects_an_amount_exceeding_the_remaining_balance(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        $actor = User::factory()->admin()->create();
        $this->service->refund($original, '60', null, $actor);

        $this->expectException(PaymentRefundExceedsRemainingBalanceException::class);

        $this->service->refund($original->fresh(), '50', null, $actor);
    }

    public function test_refund_rejects_a_zero_or_negative_amount(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        $actor = User::factory()->admin()->create();

        $this->expectException(PaymentRefundExceedsRemainingBalanceException::class);

        $this->service->refund($original, '0', null, $actor);
    }

    public function test_refund_rejects_a_soft_deleted_payment(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        $original->delete();
        $actor = User::factory()->admin()->create();

        $this->expectException(InvalidPaymentOperationException::class);

        $this->service->refund($original, '50', null, $actor);
    }

    public function test_refund_of_a_refund_is_capped_by_its_own_remaining_balance(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        $actor = User::factory()->admin()->create();
        $refund = $this->service->refund($original, '100', null, $actor);

        // A refund row's own "remaining balance" is its (negative) amount plus its own refunds sum
        // — already fully negative and non-refundable further (design doc §8: no special-casing).
        $this->expectException(PaymentRefundExceedsRemainingBalanceException::class);

        $this->service->refund($refund, '10', null, $actor);
    }

    /**
     * Sequential correctness of the row-locking added to apply()/refund() (mirrors
     * InvoiceServiceTest's identical documented limitation for reserveNextSequenceNumber()):
     * PHPUnit's RefreshDatabase runs each test on a single DB connection in a single process, so
     * this cannot literally exercise two transactions blocking on the same locked payment row the
     * way real concurrent requests would. What it does verify: repeated sequential apply()/refund()
     * calls against the same payment still produce correct results now that both are wrapped in
     * DB::transaction() + lockForUpdate() — the locking change didn't silently break the normal,
     * non-concurrent path.
     */
    public function test_apply_still_works_correctly_now_that_it_is_transactional_and_row_locked(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $payment = Payment::factory()->create(['patient_id' => $patient->id, 'invoice_id' => null]);

        $applied = $this->service->apply($payment, $invoice->id);

        $this->assertSame($invoice->id, $applied->invoice_id);
        $this->assertSame($invoice->id, $payment->fresh()->invoice_id);
    }

    public function test_repeated_sequential_refunds_still_serialize_correctly_now_that_refund_is_row_locked(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        $actor = User::factory()->admin()->create();

        $this->service->refund($original, '30', null, $actor);
        $this->service->refund($original->fresh(), '30', null, $actor);
        $this->service->refund($original->fresh(), '40', null, $actor);

        $this->assertSame('0.00', $original->fresh()->remaining_refundable_amount);
        $this->assertCount(3, $original->fresh()->refunds);
    }

    // --- delete -------------------------------------------------------------------------------------

    public function test_delete_soft_deletes_a_payment_with_no_refunds(): void
    {
        $payment = Payment::factory()->create();

        $this->service->delete($payment);

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_delete_rejects_a_payment_that_has_been_refunded(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        $actor = User::factory()->admin()->create();
        $this->service->refund($original, '50', null, $actor);

        $this->expectException(InvalidPaymentOperationException::class);

        $this->service->delete($original->fresh());
    }

    // --- Audit ----------------------------------------------------------------------------------------

    public function test_record_records_an_audit_log_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $patient = Patient::factory()->create();

        $payment = $this->service->record([
            'patient_id' => $patient->id,
            'method' => PaymentMethod::Cash,
            'amount' => 100,
        ], $actor);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }
}
