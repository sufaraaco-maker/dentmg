<?php

namespace Tests\Unit\Services;

use App\Enums\InvoiceItemKind;
use App\Enums\InvoiceStatus;
use App\Exceptions\Invoice\InvalidInvoiceItemException;
use App\Exceptions\Invoice\InvalidInvoiceStatusTransitionException;
use App\Exceptions\Invoice\InvoiceItemLockedException;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InvoiceService;
    }

    // --- createDraft -----------------------------------------------------------------------------

    public function test_create_draft_starts_as_draft_with_no_sequence_or_invoice_number(): void
    {
        $patient = Patient::factory()->create();
        $admin = User::factory()->admin()->create();

        $invoice = $this->service->createDraft(['patient_id' => $patient->id], $admin);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame($admin->id, $invoice->created_by_id);
        $this->assertSame($patient->id, $invoice->patient_id);
        $this->assertNull($invoice->sequence_number);
        $this->assertNull($invoice->invoice_number);
    }

    public function test_create_draft_snapshots_currency_code_from_billing_settings(): void
    {
        BillingSetting::factory()->create(['currency_code' => 'EUR']);
        $patient = Patient::factory()->create();
        $admin = User::factory()->admin()->create();

        $invoice = $this->service->createDraft(['patient_id' => $patient->id], $admin);

        $this->assertSame('EUR', $invoice->currency_code);
    }

    public function test_create_draft_falls_back_to_usd_when_no_billing_settings_row_exists(): void
    {
        $patient = Patient::factory()->create();
        $admin = User::factory()->admin()->create();

        $invoice = $this->service->createDraft(['patient_id' => $patient->id], $admin);

        $this->assertSame('USD', $invoice->currency_code);
    }

    // --- updateDraft -----------------------------------------------------------------------------

    public function test_update_draft_edits_notes_and_dates(): void
    {
        $invoice = Invoice::factory()->create(['notes' => 'Old note']);

        $updated = $this->service->updateDraft($invoice, ['notes' => 'New note', 'due_date' => '2026-08-15']);

        $this->assertSame('New note', $updated->notes);
        $this->assertSame('2026-08-15', $updated->due_date->toDateString());
    }

    public function test_update_draft_rejects_edits_once_issued(): void
    {
        $invoice = Invoice::factory()->issued()->create();

        $this->expectException(InvoiceItemLockedException::class);

        $this->service->updateDraft($invoice, ['notes' => 'Trying to sneak an edit in']);
    }

    public function test_update_draft_rejects_edits_once_void(): void
    {
        $invoice = Invoice::factory()->void()->create();

        $this->expectException(InvoiceItemLockedException::class);

        $this->service->updateDraft($invoice, ['notes' => 'Trying to sneak an edit in']);
    }

    // --- addItem ---------------------------------------------------------------------------------

    public function test_add_item_creates_a_manual_charge_item(): void
    {
        $invoice = Invoice::factory()->create();
        $actor = User::factory()->admin()->create();

        $item = $this->service->addItem($invoice, [
            'kind' => InvoiceItemKind::Charge,
            'description' => 'Teeth whitening kit',
            'unit_amount' => 120,
            'quantity' => 1,
        ], $actor);

        $this->assertSame('Teeth whitening kit', $item->description);
        $this->assertSame('120.00', (string) $item->unit_amount);
        $this->assertNull($item->treatment_plan_item_id);
        $this->assertSame($actor->id, $item->created_by_id);
    }

    public function test_add_item_snapshots_description_and_unit_amount_from_treatment_plan_item_when_referenced(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->create(['patient_id' => $patient->id]);
        $sourceItem = TreatmentPlanItem::factory()->create([
            'procedure_name' => 'Crown',
            'unit_cost' => 800,
        ]);
        $sourceItem->treatmentPlan()->update(['patient_id' => $patient->id]);
        $actor = User::factory()->admin()->create();

        $item = $this->service->addItem($invoice, [
            'kind' => InvoiceItemKind::Charge,
            'treatment_plan_item_id' => $sourceItem->id,
        ], $actor);

        $this->assertSame('Crown', $item->description);
        $this->assertSame('800.00', (string) $item->unit_amount);
        $this->assertSame($sourceItem->id, $item->treatment_plan_item_id);
    }

    public function test_add_item_allows_explicit_overrides_of_the_treatment_plan_item_snapshot(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->create(['patient_id' => $patient->id]);
        $sourceItem = TreatmentPlanItem::factory()->create(['procedure_name' => 'Crown', 'unit_cost' => 800]);
        $sourceItem->treatmentPlan()->update(['patient_id' => $patient->id]);
        $actor = User::factory()->admin()->create();

        $item = $this->service->addItem($invoice, [
            'kind' => InvoiceItemKind::Charge,
            'treatment_plan_item_id' => $sourceItem->id,
            'description' => 'Crown (courtesy discount applied)',
            'unit_amount' => 700,
        ], $actor);

        $this->assertSame('Crown (courtesy discount applied)', $item->description);
        $this->assertSame('700.00', (string) $item->unit_amount);
    }

    public function test_add_item_rejects_a_treatment_plan_item_belonging_to_a_different_patient(): void
    {
        $invoice = Invoice::factory()->create();
        $sourceItem = TreatmentPlanItem::factory()->create(); // different, unrelated patient
        $actor = User::factory()->admin()->create();

        $this->expectException(InvalidInvoiceItemException::class);

        $this->service->addItem($invoice, [
            'kind' => InvoiceItemKind::Charge,
            'treatment_plan_item_id' => $sourceItem->id,
        ], $actor);
    }

    public function test_add_item_rejects_discount_kind_with_a_treatment_plan_item_id(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->create(['patient_id' => $patient->id]);
        $sourceItem = TreatmentPlanItem::factory()->create();
        $sourceItem->treatmentPlan()->update(['patient_id' => $patient->id]);
        $actor = User::factory()->admin()->create();

        $this->expectException(InvalidInvoiceItemException::class);

        $this->service->addItem($invoice, [
            'kind' => InvoiceItemKind::Discount,
            'treatment_plan_item_id' => $sourceItem->id,
            'description' => 'Discount',
            'unit_amount' => 10,
        ], $actor);
    }

    public function test_add_item_rejects_a_manual_item_missing_description_or_unit_amount(): void
    {
        $invoice = Invoice::factory()->create();
        $actor = User::factory()->admin()->create();

        $this->expectException(InvalidInvoiceItemException::class);

        $this->service->addItem($invoice, ['kind' => InvoiceItemKind::Charge], $actor);
    }

    public function test_add_item_rejects_once_invoice_is_no_longer_draft(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        $actor = User::factory()->admin()->create();

        $this->expectException(InvoiceItemLockedException::class);

        $this->service->addItem($invoice, [
            'kind' => InvoiceItemKind::Charge,
            'description' => 'Late add attempt',
            'unit_amount' => 50,
        ], $actor);
    }

    // --- updateItem ------------------------------------------------------------------------------

    public function test_update_item_edits_fields_while_draft(): void
    {
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'description' => 'Old']);

        $updated = $this->service->updateItem($item, ['description' => 'New', 'unit_amount' => 200]);

        $this->assertSame('New', $updated->description);
        $this->assertSame('200.00', (string) $updated->unit_amount);
    }

    public function test_update_item_rejects_once_invoice_is_no_longer_draft(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->expectException(InvoiceItemLockedException::class);

        $this->service->updateItem($item, ['description' => 'Trying to sneak an edit in']);
    }

    // --- deleteItem ------------------------------------------------------------------------------

    public function test_delete_item_soft_deletes_while_draft(): void
    {
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->service->deleteItem($item);

        $this->assertSoftDeleted('invoice_items', ['id' => $item->id]);
    }

    public function test_delete_item_rejects_once_invoice_is_no_longer_draft(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->expectException(InvoiceItemLockedException::class);

        $this->service->deleteItem($item);
    }

    // --- issue -----------------------------------------------------------------------------------

    public function test_issue_assigns_sequence_number_and_invoice_number(): void
    {
        BillingSetting::factory()->create(['invoice_number_prefix' => 'INV', 'next_invoice_sequence' => 1]);
        $invoice = Invoice::factory()->create();

        $issued = $this->service->issue($invoice);

        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertSame(1, $issued->sequence_number);
        $this->assertSame('INV-000001', $issued->invoice_number);
        $this->assertNotNull($issued->issued_at);
        $this->assertNotNull($issued->issue_date);
    }

    public function test_issue_self_heals_when_no_billing_settings_row_exists_yet(): void
    {
        $this->assertSame(0, BillingSetting::query()->count());
        $invoice = Invoice::factory()->create();

        $issued = $this->service->issue($invoice);

        $this->assertSame('INV-000001', $issued->invoice_number);
        $this->assertSame(1, BillingSetting::query()->count());
    }

    public function test_issue_does_not_overwrite_an_explicitly_set_issue_date(): void
    {
        $invoice = Invoice::factory()->create(['issue_date' => '2026-07-01']);

        $issued = $this->service->issue($invoice);

        $this->assertSame('2026-07-01', $issued->issue_date->toDateString());
    }

    public function test_issue_rejects_an_already_issued_invoice(): void
    {
        $invoice = Invoice::factory()->issued()->create();

        $this->expectException(InvalidInvoiceStatusTransitionException::class);

        $this->service->issue($invoice);
    }

    public function test_issue_rejects_a_void_invoice(): void
    {
        $invoice = Invoice::factory()->void()->create();

        $this->expectException(InvalidInvoiceStatusTransitionException::class);

        $this->service->issue($invoice);
    }

    public function test_issue_does_not_burn_a_sequence_number_when_the_transition_is_invalid(): void
    {
        BillingSetting::factory()->create(['next_invoice_sequence' => 1]);
        $alreadyIssued = Invoice::factory()->issued()->create();

        try {
            $this->service->issue($alreadyIssued);
        } catch (InvalidInvoiceStatusTransitionException) {
            // expected
        }

        // The transition check runs, and fails, BEFORE the sequence number is reserved — an
        // invalid issue() attempt must not silently consume/skip a number (design doc §6:
        // sequence_number is assigned "exactly once, at the draft -> issued transition").
        $this->assertSame(1, BillingSetting::query()->value('next_invoice_sequence'));
    }

    /**
     * Sequential correctness of the reservation mechanism (requirement: "حالات التزامن" —
     * concurrency scenarios). PHPUnit's RefreshDatabase runs each test on a single DB connection
     * in a single process, so this cannot literally exercise two transactions blocking on the
     * same locked row the way real concurrent requests would — that guarantee comes from
     * InvoiceService::reserveNextSequenceNumber() locking and incrementing
     * billing_settings.next_invoice_sequence in place inside the same transaction as the write
     * that consumes it (see that method's docblock for the full reasoning on why this pattern,
     * not MAX(invoices.sequence_number), is what makes concurrent calls serialize safely rather
     * than collide). What this test does verify: repeated issue() calls never produce a
     * duplicate or out-of-order number, which is the observable correctness property that
     * reasoning is meant to guarantee.
     */
    public function test_repeated_issues_produce_unique_sequential_sequence_numbers(): void
    {
        $invoices = Invoice::factory()->count(5)->create();

        $sequenceNumbers = $invoices->map(fn (Invoice $invoice) => $this->service->issue($invoice)->sequence_number)->all();

        $this->assertSame([1, 2, 3, 4, 5], $sequenceNumbers);
        $this->assertSame(5, count(array_unique($sequenceNumbers)));
    }

    // --- void ------------------------------------------------------------------------------------

    public function test_void_transitions_an_issued_invoice(): void
    {
        $invoice = Invoice::factory()->issued()->create();

        $voided = $this->service->void($invoice);

        $this->assertSame(InvoiceStatus::Void, $voided->status);
        $this->assertNotNull($voided->voided_at);
    }

    public function test_void_preserves_the_invoice_number(): void
    {
        $invoice = Invoice::factory()->issued()->create(['invoice_number' => 'INV-000042']);

        $voided = $this->service->void($invoice);

        $this->assertSame('INV-000042', $voided->invoice_number);
    }

    public function test_void_rejects_a_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create();

        $this->expectException(InvalidInvoiceStatusTransitionException::class);

        $this->service->void($invoice);
    }

    public function test_void_rejects_an_already_void_invoice(): void
    {
        $invoice = Invoice::factory()->void()->create();

        $this->expectException(InvalidInvoiceStatusTransitionException::class);

        $this->service->void($invoice);
    }

    // --- deleteInvoice ---------------------------------------------------------------------------

    public function test_delete_invoice_soft_deletes_a_draft(): void
    {
        $invoice = Invoice::factory()->create();

        $this->service->deleteInvoice($invoice);

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_delete_invoice_rejects_once_issued(): void
    {
        $invoice = Invoice::factory()->issued()->create();

        $this->expectException(InvoiceItemLockedException::class);

        $this->service->deleteInvoice($invoice);
    }

    public function test_delete_invoice_rejects_once_void(): void
    {
        $invoice = Invoice::factory()->void()->create();

        $this->expectException(InvoiceItemLockedException::class);

        $this->service->deleteInvoice($invoice);
    }

    // --- total -----------------------------------------------------------------------------------

    public function test_total_sums_charges_plus_tax_minus_discount(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'kind' => InvoiceItemKind::Charge, 'unit_amount' => 100, 'quantity' => 1]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'kind' => InvoiceItemKind::Charge, 'unit_amount' => 50, 'quantity' => 2]);
        InvoiceItem::factory()->tax()->create(['invoice_id' => $invoice->id, 'unit_amount' => 10]);
        InvoiceItem::factory()->discount()->create(['invoice_id' => $invoice->id, 'unit_amount' => 20]);

        // (100*1) + (50*2) + 10 - 20 = 100 + 100 + 10 - 20 = 190
        $this->assertSame('190.00', $this->service->total($invoice));
    }

    public function test_total_excludes_soft_deleted_items(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 100, 'quantity' => 1]);
        $removed = InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_amount' => 500, 'quantity' => 1]);
        $removed->delete();

        $this->assertSame('100.00', $this->service->total($invoice));
    }

    public function test_total_is_zero_for_an_invoice_with_no_items(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertSame('0.00', $this->service->total($invoice));
    }

    // --- Audit -----------------------------------------------------------------------------------

    public function test_issue_records_an_audit_log_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);

        $invoice = Invoice::factory()->create();
        $this->service->issue($invoice);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
            'action' => 'updated',
            'user_id' => $actor->id,
        ]);
    }

    public function test_void_records_an_audit_log_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);

        $invoice = Invoice::factory()->issued()->create();
        $this->service->void($invoice);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
            'action' => 'updated',
            'user_id' => $actor->id,
        ]);
    }
}
