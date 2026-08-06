<?php

namespace Tests\Unit\Services;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceItemKind;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportService;
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_production_attributes_charges_to_the_treatment_plan_dentist_and_groups_unassigned_items(): void
    {
        $dentist = User::factory()->dentist()->create(['name' => 'Dr. Ada']);
        $plan = TreatmentPlan::factory()->create(['dentist_id' => $dentist->id]);
        $planItem = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);

        $invoice = Invoice::factory()->issued()->create(['issue_date' => '2026-06-15']);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'treatment_plan_item_id' => $planItem->id,
            'kind' => InvoiceItemKind::Charge,
            'quantity' => 1,
            'unit_amount' => '100.00',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'treatment_plan_item_id' => null,
            'kind' => InvoiceItemKind::Charge,
            'quantity' => 1,
            'unit_amount' => '40.00',
        ]);
        // A discount line must never be counted as production.
        InvoiceItem::factory()->discount()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_amount' => '10.00',
        ]);

        $result = $this->service->production('2026-06-01', '2026-06-30');

        $this->assertSame('140.00', $result['summary']['total']);
        $byDentist = collect($result['summary']['by_dentist'])->keyBy('dentist');
        $this->assertSame('100.00', $byDentist['Dr. Ada']['amount']);
        $this->assertSame('40.00', $byDentist['Unassigned']['amount']);
    }

    public function test_production_excludes_draft_invoices_and_items_outside_the_date_range(): void
    {
        $draftInvoice = Invoice::factory()->create(['issue_date' => '2026-06-10']);
        InvoiceItem::factory()->create(['invoice_id' => $draftInvoice->id, 'unit_amount' => '500.00']);

        $outOfRangeInvoice = Invoice::factory()->issued()->create(['issue_date' => '2026-05-01']);
        InvoiceItem::factory()->create(['invoice_id' => $outOfRangeInvoice->id, 'unit_amount' => '300.00']);

        $result = $this->service->production('2026-06-01', '2026-06-30');

        $this->assertSame('0.00', $result['summary']['total']);
    }

    public function test_collections_nets_out_refunds_and_groups_by_method(): void
    {
        $payment = Payment::factory()->create([
            'method' => PaymentMethod::Card,
            'amount' => '200.00',
            'received_at' => '2026-06-05',
        ]);
        Payment::factory()->refundOf($payment, '50.00')->create(['received_at' => '2026-06-06']);
        Payment::factory()->create([
            'method' => PaymentMethod::Cash,
            'amount' => '75.00',
            'received_at' => '2026-06-10',
        ]);

        $result = $this->service->collections('2026-06-01', '2026-06-30');

        $this->assertSame('225.00', $result['summary']['total']);
        $byMethod = collect($result['summary']['by_method'])->keyBy('method');
        $this->assertSame('150.00', $byMethod['card']['amount']);
        $this->assertSame('75.00', $byMethod['cash']['amount']);
    }

    public function test_collections_rows_are_ordered_most_recent_first(): void
    {
        // Deliberately created out of chronological order, so a passing assertion can only mean
        // the query itself sorts — not that insertion order happened to match (the real gap this
        // regression test closes: `collections()` previously had no ORDER BY at all).
        Payment::factory()->create(['received_at' => '2026-06-10', 'amount' => '10.00']);
        Payment::factory()->create(['received_at' => '2026-06-20', 'amount' => '20.00']);
        Payment::factory()->create(['received_at' => '2026-06-05', 'amount' => '30.00']);

        $result = $this->service->collections('2026-06-01', '2026-06-30');

        $this->assertSame(['2026-06-20', '2026-06-10', '2026-06-05'], array_column($result['rows'], 'date'));
    }

    public function test_collections_breaks_same_day_ties_by_created_at(): void
    {
        // `received_at` is deliberately date-only (Payment::$casts), so every payment recorded on
        // the same calendar day ties on that column alone — this is the actual root cause the
        // reports.spec.ts E2E flake traced back to (a freshly-recorded payment landing anywhere
        // among same-day rows, not necessarily page 1). `created_at` breaks the tie.
        $first = Payment::factory()->create(['received_at' => '2026-06-15', 'amount' => '10.00']);
        $first->forceFill(['created_at' => '2026-06-15 09:00:00'])->save();
        $second = Payment::factory()->create(['received_at' => '2026-06-15', 'amount' => '20.00']);
        $second->forceFill(['created_at' => '2026-06-15 14:00:00'])->save();

        $result = $this->service->collections('2026-06-01', '2026-06-30');

        $this->assertSame(['20.00', '10.00'], array_column($result['rows'], 'amount'));
    }

    public function test_ar_aging_buckets_outstanding_balance_by_days_overdue(): void
    {
        Date::setTestNow('2026-07-28');

        $current = $this->issuedInvoiceWithBalance(dueDate: '2026-08-15', balance: '100.00');
        $bucket1to30 = $this->issuedInvoiceWithBalance(dueDate: '2026-07-10', balance: '200.00');
        $bucket31to60 = $this->issuedInvoiceWithBalance(dueDate: '2026-06-01', balance: '300.00');
        $bucket90plus = $this->issuedInvoiceWithBalance(dueDate: '2026-01-01', balance: '400.00');
        // Fully paid — must not appear at all.
        $this->issuedInvoiceWithBalance(dueDate: '2026-06-01', balance: '0.00');

        $result = $this->service->arAging();

        $this->assertSame('1000.00', $result['summary']['total']);
        $this->assertSame('100.00', $result['summary']['buckets']['current']);
        $this->assertSame('200.00', $result['summary']['buckets']['1_30']);
        $this->assertSame('300.00', $result['summary']['buckets']['31_60']);
        $this->assertSame('400.00', $result['summary']['buckets']['90_plus']);
        $this->assertCount(4, $result['rows']);
    }

    public function test_appointment_analytics_computes_no_show_and_cancellation_rates(): void
    {
        Appointment::factory()->count(2)->create(['status' => AppointmentStatus::Completed, 'start_at' => '2026-06-05 09:00:00']);
        Appointment::factory()->create(['status' => AppointmentStatus::NoShow, 'start_at' => '2026-06-06 09:00:00']);
        Appointment::factory()->create(['status' => AppointmentStatus::Cancelled, 'start_at' => '2026-06-07 09:00:00']);

        $result = $this->service->appointmentAnalytics('2026-06-01', '2026-06-30');

        $this->assertSame(4, $result['summary']['total']);
        $this->assertSame(25.0, $result['summary']['no_show_rate']);
        $this->assertSame(25.0, $result['summary']['cancellation_rate']);
    }

    public function test_treatment_plan_acceptance_counts_by_accepted_at_not_current_status(): void
    {
        // Presented then moved all the way to Completed — accepted_at is still set, must count as accepted.
        TreatmentPlan::factory()->completed()->create(['presented_at' => '2026-06-05']);
        TreatmentPlan::factory()->rejected()->create(['presented_at' => '2026-06-10']);
        TreatmentPlan::factory()->presented()->create(['presented_at' => '2026-06-15']);

        $result = $this->service->treatmentPlanAcceptance('2026-06-01', '2026-06-30');

        $this->assertSame(3, $result['summary']['presented']);
        $this->assertSame(1, $result['summary']['accepted']);
        $this->assertSame(1, $result['summary']['rejected']);
        $this->assertEqualsWithDelta(33.3, $result['summary']['acceptance_rate'], 0.1);
    }

    public function test_new_patients_counts_only_registrations_inside_the_range(): void
    {
        Patient::factory()->create(['created_at' => '2026-06-10']);
        Patient::factory()->create(['created_at' => '2026-06-20']);
        Patient::factory()->create(['created_at' => '2026-05-01']);

        $result = $this->service->newPatients('2026-06-01', '2026-06-30');

        $this->assertSame(2, $result['summary']['total']);
    }

    /**
     * A charge line equal to `$balance` with no payment against it — `balance_due` is then exactly
     * `$balance` (an unpaid `0.00` charge is the simplest way to build the "fully paid, must be
     * excluded" fixture without an extra Payment row).
     */
    private function issuedInvoiceWithBalance(string $dueDate, string $balance): Invoice
    {
        $invoice = Invoice::factory()->issued()->create(['due_date' => $dueDate]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => InvoiceItemKind::Charge,
            'quantity' => 1,
            'unit_amount' => $balance,
        ]);

        return $invoice;
    }
}
