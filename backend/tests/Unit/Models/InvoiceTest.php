<?php

namespace Tests\Unit\Models;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_patient_and_created_by(): void
    {
        $patient = Patient::factory()->create();
        $admin = User::factory()->admin()->create();

        $invoice = Invoice::factory()->create([
            'patient_id' => $patient->id,
            'created_by_id' => $admin->id,
        ]);

        $this->assertTrue($invoice->patient->is($patient));
        $this->assertTrue($invoice->createdBy->is($admin));
    }

    public function test_patient_has_many_invoices(): void
    {
        $patient = Patient::factory()->create();
        Invoice::factory()->count(3)->create(['patient_id' => $patient->id]);

        $this->assertCount(3, $patient->invoices);
    }

    public function test_has_many_items(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);

        $this->assertCount(2, $invoice->items);
    }

    public function test_has_many_payments(): void
    {
        $invoice = Invoice::factory()->issued()->create();
        Payment::factory()->count(2)->create(['invoice_id' => $invoice->id]);

        $this->assertCount(2, $invoice->payments);
    }

    public function test_status_is_cast_to_the_backed_enum(): void
    {
        $invoice = Invoice::factory()->issued()->create();

        $this->assertInstanceOf(InvoiceStatus::class, $invoice->status);
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
    }

    public function test_issued_at_and_voided_at_are_cast_to_datetime(): void
    {
        $invoice = Invoice::factory()->void()->create();

        $this->assertInstanceOf(Carbon::class, $invoice->issued_at);
        $this->assertInstanceOf(Carbon::class, $invoice->voided_at);
    }

    public function test_draft_invoice_has_no_sequence_or_invoice_number(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertNull($invoice->sequence_number);
        $this->assertNull($invoice->invoice_number);
    }

    public function test_scope_for_patient_and_with_status(): void
    {
        $patientA = Patient::factory()->create();
        $patientB = Patient::factory()->create();

        Invoice::factory()->create(['patient_id' => $patientA->id, 'status' => InvoiceStatus::Draft]);
        Invoice::factory()->issued()->create(['patient_id' => $patientA->id]);
        Invoice::factory()->create(['patient_id' => $patientB->id, 'status' => InvoiceStatus::Draft]);

        $this->assertCount(2, Invoice::forPatient($patientA->id)->get());
        $this->assertCount(2, Invoice::withStatus(InvoiceStatus::Draft)->get());
        $this->assertCount(1, Invoice::forPatient($patientA->id)->withStatus(InvoiceStatus::Draft)->get());
    }

    public function test_soft_delete_does_not_remove_the_row(): void
    {
        $invoice = Invoice::factory()->create();

        $invoice->delete();

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_uses_auditable_and_records_creation_in_audit_logs(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);

        $invoice = Invoice::factory()->create(['created_by_id' => $actor->id]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }
}
