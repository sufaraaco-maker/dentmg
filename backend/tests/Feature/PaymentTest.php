<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payments design doc §16: written and CI-verified as part of this module's own implementation
 * sequence, not deferred — the exact debt Treatment Plans logged (shipped without a permanent
 * Feature/E2E suite) and Billing itself is still carrying (Unit-only so far). This is the first
 * Feature test file in this financial-module family.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    // ---- index --------------------------------------------------------------------------------

    public function test_guest_cannot_list_payments(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/patients/{$patient->id}/payments");

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_a_patients_payments(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        Payment::factory()->count(2)->create(['patient_id' => $patient->id]);
        Payment::factory()->create(); // different patient, excluded

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/payments");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_a_patients_payment_list_is_paginated(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        Payment::factory()->count(20)->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/payments");

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    // ---- invoice-scoped index (Phase 2.2 — TECH_DEBT.md's now-resolved entry) -------------------

    public function test_guest_cannot_list_an_invoices_payments(): void
    {
        $invoice = Invoice::factory()->issued()->create();

        $response = $this->getJson("/api/invoices/{$invoice->id}/payments");

        $response->assertUnauthorized();
    }

    public function test_an_invoices_payment_list_is_scoped_to_that_invoice_only(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $otherInvoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        Payment::factory()->count(2)->create(['patient_id' => $patient->id, 'invoice_id' => $invoice->id]);
        Payment::factory()->create(['patient_id' => $patient->id, 'invoice_id' => $otherInvoice->id]);

        $response = $this->actingAs($actor)->getJson("/api/invoices/{$invoice->id}/payments");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_an_invoices_payment_list_is_paginated(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create();
        Payment::factory()->count(20)->create(['patient_id' => $invoice->patient_id, 'invoice_id' => $invoice->id]);

        $response = $this->actingAs($actor)->getJson("/api/invoices/{$invoice->id}/payments");

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    // ---- store ----------------------------------------------------------------------------------

    public function test_guest_cannot_record_a_payment(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->postJson("/api/patients/{$patient->id}/payments", [
            'method' => 'cash',
            'amount' => 100,
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_record_an_unapplied_payment(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/payments", [
            'method' => 'cash',
            'amount' => 150,
        ]);

        $response->assertCreated();
        $this->assertSame('150.00', $response->json('amount'));
        $this->assertNull($response->json('invoice_id'));
    }

    public function test_receptionist_can_record_a_payment_against_an_issued_invoice(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/payments", [
            'invoice_id' => $invoice->id,
            'method' => 'card',
            'amount' => 200,
        ]);

        $response->assertCreated();
        $this->assertSame($invoice->id, $response->json('invoice_id'));
    }

    public function test_dentist_cannot_record_a_payment(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/payments", [
            'method' => 'cash',
            'amount' => 100,
        ]);

        $response->assertForbidden();
    }

    public function test_record_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/payments", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['method', 'amount']);
    }

    public function test_record_against_a_draft_invoice_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/payments", [
            'invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 100,
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'invalid_payment_operation']);
    }

    // ---- show / update ----------------------------------------------------------------------------

    public function test_admin_can_update_a_payments_reference_and_notes(): void
    {
        $actor = User::factory()->admin()->create();
        $payment = Payment::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/payments/{$payment->id}", [
            'reference' => 'REF-1',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk();
        $this->assertSame('REF-1', $response->json('reference'));
        $this->assertSame('Updated notes', $response->json('notes'));
    }

    public function test_receptionist_cannot_edit_amount_via_update(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $payment = Payment::factory()->create(['amount' => 100]);

        $response = $this->actingAs($actor)->putJson("/api/payments/{$payment->id}", [
            'reference' => 'attempt',
        ]);

        $response->assertOk();
        $this->assertSame('100.00', $response->json('amount'));
    }

    public function test_dentist_cannot_update_a_payment(): void
    {
        $actor = User::factory()->dentist()->create();
        $payment = Payment::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/payments/{$payment->id}", ['notes' => 'x']);

        $response->assertForbidden();
    }

    // ---- apply ------------------------------------------------------------------------------------

    public function test_admin_can_apply_an_unapplied_payment_to_an_issued_invoice(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $payment = Payment::factory()->create(['patient_id' => $patient->id, 'invoice_id' => null]);

        $response = $this->actingAs($actor)->postJson("/api/payments/{$payment->id}/apply", [
            'invoice_id' => $invoice->id,
        ]);

        $response->assertOk();
        $this->assertSame($invoice->id, $response->json('invoice_id'));
    }

    public function test_apply_twice_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $invoiceA = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $invoiceB = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $payment = Payment::factory()->create(['patient_id' => $patient->id, 'invoice_id' => $invoiceA->id]);

        $response = $this->actingAs($actor)->postJson("/api/payments/{$payment->id}/apply", [
            'invoice_id' => $invoiceB->id,
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'invalid_payment_operation']);
    }

    public function test_dentist_cannot_apply_a_payment(): void
    {
        $actor = User::factory()->dentist()->create();
        $payment = Payment::factory()->create(['invoice_id' => null]);
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $payment->patient_id]);

        $response = $this->actingAs($actor)->postJson("/api/payments/{$payment->id}/apply", [
            'invoice_id' => $invoice->id,
        ]);

        $response->assertForbidden();
    }

    // ---- refund -----------------------------------------------------------------------------------

    public function test_admin_can_partially_refund_a_payment(): void
    {
        $actor = User::factory()->admin()->create();
        $payment = Payment::factory()->create(['amount' => 200]);

        $response = $this->actingAs($actor)->postJson("/api/payments/{$payment->id}/refund", [
            'amount' => 80,
            'notes' => 'Patient overpaid',
        ]);

        $response->assertCreated();
        $this->assertSame('-80.00', $response->json('amount'));
        $this->assertSame($payment->id, $response->json('refunded_payment_id'));
    }

    public function test_refund_exceeding_remaining_balance_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $payment = Payment::factory()->create(['amount' => 100]);

        $response = $this->actingAs($actor)->postJson("/api/payments/{$payment->id}/refund", [
            'amount' => 150,
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'payment_refund_exceeds_remaining_balance']);
    }

    public function test_receptionist_can_refund_a_payment(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $payment = Payment::factory()->create(['amount' => 100]);

        $response = $this->actingAs($actor)->postJson("/api/payments/{$payment->id}/refund", ['amount' => 50]);

        $response->assertCreated();
    }

    public function test_dentist_cannot_refund_a_payment(): void
    {
        $actor = User::factory()->dentist()->create();
        $payment = Payment::factory()->create(['amount' => 100]);

        $response = $this->actingAs($actor)->postJson("/api/payments/{$payment->id}/refund", ['amount' => 50]);

        $response->assertForbidden();
    }

    // ---- destroy ----------------------------------------------------------------------------------

    public function test_admin_can_delete_a_payment_with_no_refunds(): void
    {
        $actor = User::factory()->admin()->create();
        $payment = Payment::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/payments/{$payment->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_receptionist_cannot_delete_a_payment(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $payment = Payment::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/payments/{$payment->id}");

        $response->assertForbidden();
    }

    public function test_deleting_a_refunded_payment_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $payment = Payment::factory()->create(['amount' => 100]);
        $this->actingAs($actor)->postJson("/api/payments/{$payment->id}/refund", ['amount' => 50]);

        $response = $this->actingAs($actor)->deleteJson("/api/payments/{$payment->id}");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_payment_operation']);
    }

    // ---- Invoice balance integration -----------------------------------------------------------

    public function test_invoice_response_reflects_payment_status_after_a_payment(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);

        $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/payments", [
            'invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 100,
        ])->assertCreated();

        $response = $this->actingAs($actor)->getJson("/api/invoices/{$invoice->id}");

        $response->assertOk();
        $this->assertSame('100.00', $response->json('amount_paid'));
        $this->assertSame('0.00', $response->json('balance_due'));
        $this->assertSame('paid', $response->json('payment_status'));
    }
}
