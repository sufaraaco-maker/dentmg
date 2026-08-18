<?php

namespace Tests\Feature;

use App\Enums\TreatmentPlanItemStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TreatmentPlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP-layer coverage for invoice item mutation endpoints (store/update/destroy) -- no Feature test
 * exercised these routes before (only Unit/Model + Unit/Policy tests existed); `InvoiceServiceTest.php`
 * covers the business rules directly against the Service. Mirrors `TreatmentPlanItemTest.php`'s
 * identical split for the sibling module.
 */
class InvoiceItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_add_an_item(): void
    {
        $invoice = Invoice::factory()->create();

        $response = $this->postJson("/api/invoices/{$invoice->id}/items", [
            'kind' => 'charge',
            'description' => 'Consultation',
            'unit_amount' => 50,
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_add_a_manual_charge_item(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/items", [
            'kind' => 'charge',
            'description' => 'Consultation',
            'unit_amount' => 50,
            'quantity' => 2,
        ]);

        $response->assertCreated();
        $items = $response->json('items');
        $this->assertCount(1, $items);
        $this->assertSame('Consultation', $items[0]['description']);
        $this->assertSame('100.00', $response->json('total'));
    }

    public function test_add_item_snapshots_from_a_completed_treatment_plan_item(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();
        $treatmentPlanItem = TreatmentPlanItem::factory()->create([
            'status' => TreatmentPlanItemStatus::Completed,
        ]);
        $treatmentPlanItem->loadMissing('treatmentPlan');
        $treatmentPlanItem->treatmentPlan()->update(['patient_id' => $invoice->patient_id]);

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/items", [
            'kind' => 'charge',
            'treatment_plan_item_id' => $treatmentPlanItem->id,
        ]);

        $response->assertCreated();
        $item = $response->json('items')[0];
        $this->assertSame($treatmentPlanItem->procedure_name, $item['description']);
    }

    public function test_add_item_rejects_a_treatment_plan_item_belonging_to_a_different_patient(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();
        $treatmentPlanItem = TreatmentPlanItem::factory()->create(); // different, unrelated patient

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/items", [
            'kind' => 'charge',
            'treatment_plan_item_id' => $treatmentPlanItem->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['treatment_plan_item_id']);
    }

    public function test_dentist_cannot_add_an_item(): void
    {
        $actor = User::factory()->dentist()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/items", [
            'kind' => 'charge',
            'description' => 'Consultation',
            'unit_amount' => 50,
        ]);

        $response->assertForbidden();
    }

    public function test_adding_an_item_to_an_issued_invoice_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/items", [
            'kind' => 'charge',
            'description' => 'Consultation',
            'unit_amount' => 50,
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'invoice_item_locked']);
    }

    public function test_add_item_requires_a_description_or_unit_amount(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/items", [
            'kind' => 'charge',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['description', 'unit_amount']);
    }

    public function test_admin_can_update_an_items_description(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($actor)->putJson("/api/invoice-items/{$item->id}", [
            'description' => 'Updated description',
        ]);

        $response->assertOk();
        $this->assertSame('Updated description', $response->json('items.0.description'));
    }

    public function test_dentist_cannot_update_an_item(): void
    {
        $actor = User::factory()->dentist()->create();
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($actor)->putJson("/api/invoice-items/{$item->id}", ['description' => 'x']);

        $response->assertForbidden();
    }

    public function test_updating_an_item_on_an_issued_invoice_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($actor)->putJson("/api/invoice-items/{$item->id}", ['description' => 'x']);

        $response->assertStatus(422)->assertJson(['code' => 'invoice_item_locked']);
    }

    public function test_admin_can_delete_an_item(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($actor)->deleteJson("/api/invoice-items/{$item->id}");

        $response->assertOk();
        $this->assertCount(0, $response->json('items'));
        $this->assertSoftDeleted('invoice_items', ['id' => $item->id]);
    }

    public function test_dentist_cannot_delete_an_item(): void
    {
        $actor = User::factory()->dentist()->create();
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($actor)->deleteJson("/api/invoice-items/{$item->id}");

        $response->assertForbidden();
    }

    public function test_deleting_an_item_on_an_issued_invoice_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create();
        $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($actor)->deleteJson("/api/invoice-items/{$item->id}");

        $response->assertStatus(422)->assertJson(['code' => 'invoice_item_locked']);
    }
}
