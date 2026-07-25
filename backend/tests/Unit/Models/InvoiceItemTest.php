<?php

namespace Tests\Unit\Models;

use App\Enums\InvoiceItemKind;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_invoice_and_created_by(): void
    {
        $invoice = Invoice::factory()->create();
        $admin = User::factory()->admin()->create();

        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'created_by_id' => $admin->id,
        ]);

        $this->assertTrue($item->invoice->is($invoice));
        $this->assertTrue($item->createdBy->is($admin));
    }

    public function test_treatment_plan_item_is_a_nullable_read_only_reference(): void
    {
        $sourceItem = TreatmentPlanItem::factory()->create();
        $item = InvoiceItem::factory()->fromTreatmentPlanItem()->create([
            'treatment_plan_item_id' => $sourceItem->id,
        ]);

        $this->assertTrue($item->treatmentPlanItem->is($sourceItem));

        $manualItem = InvoiceItem::factory()->create(['treatment_plan_item_id' => null]);
        $this->assertNull($manualItem->treatmentPlanItem);
    }

    public function test_kind_is_cast_to_the_backed_enum(): void
    {
        $item = InvoiceItem::factory()->discount()->create();

        $this->assertInstanceOf(InvoiceItemKind::class, $item->kind);
        $this->assertSame(InvoiceItemKind::Discount, $item->fresh()->kind);
    }

    public function test_amount_is_unit_amount_times_quantity_and_never_stored(): void
    {
        $item = InvoiceItem::factory()->create(['unit_amount' => 150.00, 'quantity' => 2]);

        $this->assertSame('300.00', $item->amount);

        // Not a real column — confirmed absent from the raw database attributes.
        $this->assertArrayNotHasKey('amount', $item->getAttributes());
    }

    public function test_amount_is_always_positive_regardless_of_kind(): void
    {
        $discount = InvoiceItem::factory()->discount()->create(['unit_amount' => 50, 'quantity' => 1]);

        // Stored value and derived amount are both positive — the sign only applies when
        // InvoiceService::total() aggregates by kind (design doc §6).
        $this->assertSame('50.00', (string) $discount->unit_amount);
        $this->assertSame('50.00', $discount->amount);
    }

    public function test_description_and_unit_amount_are_snapshotted_not_live_joined(): void
    {
        $sourceItem = TreatmentPlanItem::factory()->create([
            'procedure_name' => 'Crown',
            'unit_cost' => 800,
        ]);

        $item = InvoiceItem::factory()->fromTreatmentPlanItem()->create([
            'treatment_plan_item_id' => $sourceItem->id,
            'description' => $sourceItem->procedure_name,
            'unit_amount' => $sourceItem->unit_cost,
        ]);

        // The source treatment plan item changes afterwards — the already-created invoice item's
        // own snapshot columns must not change (design doc §6/§7, Decision Log item 6).
        $sourceItem->update(['procedure_name' => 'Crown (Revised)', 'unit_cost' => 950]);

        $this->assertSame('Crown', $item->fresh()->description);
        $this->assertSame('800.00', (string) $item->fresh()->unit_amount);
    }

    public function test_soft_delete_does_not_remove_the_row(): void
    {
        $item = InvoiceItem::factory()->create();

        $item->delete();

        $this->assertSoftDeleted('invoice_items', ['id' => $item->id]);
    }

    public function test_uses_auditable_and_records_creation_in_audit_logs(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);

        $item = InvoiceItem::factory()->create(['created_by_id' => $actor->id]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => InvoiceItem::class,
            'auditable_id' => $item->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }
}
