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
 * `GET /patients/{patient}/billing-summary` (design doc §6.3/§8/§11.4, Phase 2.2) — the Billing
 * tab's Outstanding Balance hero + summary row. Computed via SQL aggregates only
 * (BillingSummaryService), so these tests assert on the resulting numbers/status rather than the
 * query shape itself.
 */
class BillingSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_a_patients_billing_summary(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/patients/{$patient->id}/billing-summary");

        $response->assertUnauthorized();
    }

    public function test_a_patient_with_no_invoices_has_no_activity_status(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/billing-summary");

        $response->assertOk();
        $this->assertSame('no_activity', $response->json('status'));
        $this->assertSame('0.00', $response->json('total_invoiced'));
        $this->assertSame('0.00', $response->json('outstanding_balance'));
        $this->assertSame(0, $response->json('invoice_count'));
        $this->assertNull($response->json('last_payment_date'));
    }

    public function test_status_is_paid_once_an_issued_invoice_is_fully_paid(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);
        Payment::factory()->create(['patient_id' => $patient->id, 'invoice_id' => $invoice->id, 'amount' => 100]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/billing-summary");

        $response->assertOk();
        $this->assertSame('paid', $response->json('status'));
        $this->assertSame('100.00', $response->json('total_invoiced'));
        $this->assertSame('100.00', $response->json('total_paid'));
        $this->assertSame('0.00', $response->json('outstanding_balance'));
        $this->assertSame(1, $response->json('invoice_count'));
    }

    public function test_status_is_partial_when_an_unpaid_balance_remains_and_nothing_is_overdue(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create([
            'patient_id' => $patient->id,
            'due_date' => now()->addWeek()->toDateString(),
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 200, 'quantity' => 1]);
        Payment::factory()->create(['patient_id' => $patient->id, 'invoice_id' => $invoice->id, 'amount' => 50]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/billing-summary");

        $response->assertOk();
        $this->assertSame('partial', $response->json('status'));
        $this->assertSame('150.00', $response->json('outstanding_balance'));
    }

    public function test_status_is_overdue_when_a_balance_remains_past_the_due_date(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create([
            'patient_id' => $patient->id,
            'due_date' => now()->subWeek()->toDateString(),
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/billing-summary");

        $response->assertOk();
        $this->assertSame('overdue', $response->json('status'));
    }

    public function test_draft_and_void_invoices_are_excluded_from_totals(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $draft = Invoice::factory()->create(['patient_id' => $patient->id]);
        InvoiceItem::factory()->create(['invoice_id' => $draft->id, 'unit_amount' => 500, 'quantity' => 1]);
        $void = Invoice::factory()->void()->create(['patient_id' => $patient->id]);
        InvoiceItem::factory()->create(['invoice_id' => $void->id, 'unit_amount' => 500, 'quantity' => 1]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/billing-summary");

        $response->assertOk();
        $this->assertSame('no_activity', $response->json('status'));
        $this->assertSame('0.00', $response->json('total_invoiced'));
        $this->assertSame(0, $response->json('invoice_count'));
    }

    public function test_last_payment_date_is_the_most_recent_payment(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        Payment::factory()->create([
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'received_at' => now()->subMonth()->toDateString(),
        ]);
        Payment::factory()->create([
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'received_at' => now()->toDateString(),
        ]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/billing-summary");

        $response->assertOk();
        $this->assertSame(now()->toDateString(), $response->json('last_payment_date'));
    }

    public function test_discounts_reduce_the_total_invoiced_amount(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->issued()->create(['patient_id' => $patient->id]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);
        InvoiceItem::factory()->discount()->create(['invoice_id' => $invoice->id, 'unit_amount' => 20, 'quantity' => 1]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/billing-summary");

        $response->assertOk();
        $this->assertSame('80.00', $response->json('total_invoiced'));
    }
}
