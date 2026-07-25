<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Payments design doc §4/§6/§9: amount_paid/balance_due/payment_status are purely additive fields
 * on InvoiceResource, computed from the eager-loaded `items`/`payments` relations exactly like the
 * pre-existing `total` field — no dedicated Resource test file existed before this module (`total`
 * itself is only covered indirectly via InvoiceServiceTest), so this is the first one, scoped to
 * just the new fields.
 */
class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    private function toArray(Invoice $invoice): array
    {
        return (new InvoiceResource($invoice->load(['items', 'payments'])))->toArray(Request::create('/'));
    }

    public function test_amount_paid_sums_payments_including_negative_refund_rows(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 200, 'quantity' => 1]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 150]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => -50]);

        $data = $this->toArray($invoice);

        $this->assertSame('100.00', $data['amount_paid']);
    }

    public function test_balance_due_is_total_minus_amount_paid(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 200, 'quantity' => 1]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 75]);

        $data = $this->toArray($invoice);

        $this->assertSame('200.00', $data['total']);
        $this->assertSame('75.00', $data['amount_paid']);
        $this->assertSame('125.00', $data['balance_due']);
    }

    public function test_balance_due_can_go_negative_on_an_overpayment(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 150]);

        $data = $this->toArray($invoice);

        $this->assertSame('-50.00', $data['balance_due']);
    }

    public function test_payment_status_is_unpaid_with_no_payments(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);

        $this->assertSame('unpaid', $this->toArray($invoice)['payment_status']);
    }

    public function test_payment_status_is_partially_paid_between_zero_and_total(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 40]);

        $this->assertSame('partially_paid', $this->toArray($invoice)['payment_status']);
    }

    public function test_payment_status_is_paid_once_amount_paid_reaches_total(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 100]);

        $this->assertSame('paid', $this->toArray($invoice)['payment_status']);
    }

    public function test_a_full_refund_nets_amount_paid_back_to_zero(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);
        $original = Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => 100]);
        Payment::factory()->refundOf($original)->create(['invoice_id' => $invoice->id]);

        $data = $this->toArray($invoice);

        $this->assertSame('0.00', $data['amount_paid']);
        $this->assertSame('unpaid', $data['payment_status']);
    }
}
