<?php

namespace Tests\Unit\Models;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_patient_invoice_and_created_by(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        $admin = User::factory()->admin()->create();

        $payment = Payment::factory()->create([
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'created_by_id' => $admin->id,
        ]);

        $this->assertTrue($payment->patient->is($patient));
        $this->assertTrue($payment->invoice->is($invoice));
        $this->assertTrue($payment->createdBy->is($admin));
    }

    public function test_invoice_id_is_nullable_for_an_unapplied_payment(): void
    {
        $payment = Payment::factory()->create(['invoice_id' => null]);

        $this->assertNull($payment->invoice);
    }

    public function test_patient_has_many_payments(): void
    {
        $patient = Patient::factory()->create();
        Payment::factory()->count(3)->create(['patient_id' => $patient->id]);

        $this->assertCount(3, $patient->payments);
    }

    public function test_method_is_cast_to_the_backed_enum(): void
    {
        $payment = Payment::factory()->create(['method' => PaymentMethod::Card]);

        $this->assertInstanceOf(PaymentMethod::class, $payment->method);
        $this->assertSame(PaymentMethod::Card, $payment->fresh()->method);
    }

    public function test_received_at_is_cast_to_date(): void
    {
        $payment = Payment::factory()->create(['received_at' => '2026-07-01']);

        $this->assertInstanceOf(Carbon::class, $payment->received_at);
        $this->assertSame('2026-07-01', $payment->received_at->toDateString());
    }

    public function test_is_refund_reflects_refunded_payment_id(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        $refund = Payment::factory()->refundOf($original)->create();

        $this->assertFalse($original->fresh()->is_refund);
        $this->assertTrue($refund->fresh()->is_refund);
    }

    public function test_refunds_relation_lists_rows_pointing_back_at_it(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        $refund = Payment::factory()->refundOf($original, '40')->create();

        $this->assertCount(1, $original->refunds);
        $this->assertTrue($original->refunds->first()->is($refund));
    }

    public function test_remaining_refundable_amount_subtracts_existing_refunds(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);

        $this->assertSame('100.00', $original->remaining_refundable_amount);

        Payment::factory()->refundOf($original, '40')->create();

        $this->assertSame('60.00', $original->fresh()->remaining_refundable_amount);
    }

    public function test_remaining_refundable_amount_is_zero_once_fully_refunded(): void
    {
        $original = Payment::factory()->create(['amount' => 100]);
        Payment::factory()->refundOf($original, '100')->create();

        $this->assertSame('0.00', $original->fresh()->remaining_refundable_amount);
    }

    public function test_scope_for_patient(): void
    {
        $patientA = Patient::factory()->create();
        $patientB = Patient::factory()->create();

        Payment::factory()->count(2)->create(['patient_id' => $patientA->id]);
        Payment::factory()->create(['patient_id' => $patientB->id]);

        $this->assertCount(2, Payment::forPatient($patientA->id)->get());
    }

    public function test_soft_delete_does_not_remove_the_row(): void
    {
        $payment = Payment::factory()->create();

        $payment->delete();

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_uses_auditable_and_records_creation_in_audit_logs(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);

        $payment = Payment::factory()->create(['created_by_id' => $actor->id]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }
}
