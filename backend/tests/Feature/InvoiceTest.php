<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP-layer coverage for the invoice lifecycle endpoints (store/update/issue/void/destroy) --
 * `InvoiceControllerTest.php` only covers the index/list endpoints, and `InvoiceServiceTest.php`
 * (Unit) already covers every business rule at the Service layer, but neither exercises routing,
 * Policy-driven 403s, or FormRequest validation through a real HTTP request. Mirrors
 * `TreatmentPlanTest.php`'s identical split (Service Unit test + Controller Feature test).
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    // ---- store ------------------------------------------------------------------------------

    public function test_guest_cannot_create_an_invoice(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->postJson("/api/patients/{$patient->id}/invoices", []);

        $response->assertUnauthorized();
    }

    public function test_admin_can_create_a_draft_invoice(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/invoices", [
            'notes' => 'First visit',
        ]);

        $response->assertCreated();
        $this->assertSame('draft', $response->json('status'));
        $this->assertNull($response->json('invoice_number'));
    }

    public function test_receptionist_can_create_a_draft_invoice(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/invoices", []);

        $response->assertCreated();
    }

    public function test_dentist_cannot_create_an_invoice(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/invoices", []);

        $response->assertForbidden();
    }

    public function test_create_rejects_a_due_date_before_the_issue_date(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/invoices", [
            'issue_date' => '2026-01-10',
            'due_date' => '2026-01-01',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['due_date']);
    }

    // ---- update -----------------------------------------------------------------------------

    public function test_admin_can_update_a_draft_invoices_notes(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/invoices/{$invoice->id}", [
            'notes' => 'Updated notes',
        ]);

        $response->assertOk();
        $this->assertSame('Updated notes', $response->json('notes'));
    }

    public function test_dentist_cannot_update_an_invoice(): void
    {
        $actor = User::factory()->dentist()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->putJson("/api/invoices/{$invoice->id}", ['notes' => 'x']);

        $response->assertForbidden();
    }

    public function test_updating_an_issued_invoice_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create();

        $response = $this->actingAs($actor)->putJson("/api/invoices/{$invoice->id}", ['notes' => 'x']);

        $response->assertStatus(422)->assertJson(['code' => 'invoice_item_locked']);
    }

    // ---- issue ------------------------------------------------------------------------------

    public function test_admin_can_issue_a_draft_invoice(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/issue");

        $response->assertOk();
        $this->assertSame('issued', $response->json('status'));
        $this->assertNotNull($response->json('invoice_number'));
    }

    public function test_dentist_cannot_issue_an_invoice(): void
    {
        $actor = User::factory()->dentist()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/issue");

        $response->assertForbidden();
    }

    public function test_issuing_an_already_issued_invoice_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/issue");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_invoice_status_transition']);
    }

    // ---- void -------------------------------------------------------------------------------

    public function test_admin_can_void_an_issued_invoice(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/void");

        $response->assertOk();
        $this->assertSame('void', $response->json('status'));
        // The invoice number is preserved, not reissued -- a void is a cancellation, not a delete.
        $this->assertSame($invoice->invoice_number, $response->json('invoice_number'));
    }

    public function test_receptionist_can_void_an_issued_invoice(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $invoice = Invoice::factory()->issued()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/void");

        $response->assertOk();
    }

    public function test_dentist_cannot_void_an_invoice(): void
    {
        $actor = User::factory()->dentist()->create();
        $invoice = Invoice::factory()->issued()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/void");

        $response->assertForbidden();
    }

    public function test_voiding_a_draft_invoice_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/invoices/{$invoice->id}/void");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_invoice_status_transition']);
    }

    // ---- destroy ----------------------------------------------------------------------------

    public function test_admin_can_delete_a_draft_invoice(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/invoices/{$invoice->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_receptionist_cannot_delete_an_invoice(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $invoice = Invoice::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/invoices/{$invoice->id}");

        $response->assertForbidden();
    }

    public function test_deleting_an_issued_invoice_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $invoice = Invoice::factory()->issued()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(422)->assertJson(['code' => 'invoice_item_locked']);
    }
}
