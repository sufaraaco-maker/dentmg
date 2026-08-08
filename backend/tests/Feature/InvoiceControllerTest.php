<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `GET /invoices` (frontend-ux-redesign design doc §5.1/§11) plus the patient-scoped
 * `GET /patients/{patient}/invoices` (paginated, Phase 2.2 — see TECH_DEBT.md's now-resolved
 * entry). Every other `InvoiceController` action stays Unit-tested only (`InvoiceServiceTest`/
 * `InvoicePolicyTest`/`InvoiceResourceTest`), a pre-existing gap this file doesn't attempt to
 * backfill.
 */
class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- patient-scoped index (Phase 2.2) ------------------------------------------------------

    public function test_guest_cannot_list_a_patients_invoices(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/patients/{$patient->id}/invoices");

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_a_patients_invoices(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $other = Patient::factory()->create();
        Invoice::factory()->count(2)->create(['patient_id' => $patient->id]);
        Invoice::factory()->create(['patient_id' => $other->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/invoices");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_a_patients_invoice_list_is_paginated(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        Invoice::factory()->count(20)->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/invoices");

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    public function test_a_patients_invoice_list_can_be_filtered_by_status(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        Invoice::factory()->count(2)->issued()->create(['patient_id' => $patient->id]);
        Invoice::factory()->create(['patient_id' => $patient->id]); // draft, excluded

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/invoices?status=issued");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_a_patients_invoice_status_filter_returns_more_than_one_page_worth(): void
    {
        // ApplyPaymentDialog.vue's invoice picker needs every issued invoice for a patient, not
        // just the first 15 — the status-filtered branch uses a higher per_page than the default
        // browsing view (see InvoiceController::index()'s doc comment).
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        Invoice::factory()->count(20)->issued()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/invoices?status=issued");

        $response->assertOk();
        $this->assertCount(20, $response->json('data'));
    }

    public function test_an_unrecognized_status_filter_on_the_patient_scoped_list_is_ignored(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        Invoice::factory()->count(2)->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/invoices?status=not-a-real-status");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    // ---- clinic-wide index ----------------------------------------------------------------------

    public function test_guest_cannot_list_invoices(): void
    {
        $response = $this->getJson('/api/invoices');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_invoices_across_every_patient(): void
    {
        $actor = User::factory()->dentist()->create();
        Invoice::factory()->count(3)->create();

        $response = $this->actingAs($actor)->getJson('/api/invoices');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_is_paginated(): void
    {
        $actor = User::factory()->admin()->create();
        Invoice::factory()->count(20)->create();

        $response = $this->actingAs($actor)->getJson('/api/invoices');

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    public function test_list_can_be_filtered_by_status(): void
    {
        $actor = User::factory()->admin()->create();
        Invoice::factory()->count(2)->issued()->create();
        Invoice::factory()->create(); // draft, excluded

        $response = $this->actingAs($actor)->getJson('/api/invoices?status=issued');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_an_unrecognized_status_filter_is_ignored_rather_than_erroring(): void
    {
        $actor = User::factory()->admin()->create();
        Invoice::factory()->count(2)->create();

        $response = $this->actingAs($actor)->getJson('/api/invoices?status=not-a-real-status');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_can_be_searched_by_invoice_number(): void
    {
        $actor = User::factory()->admin()->create();
        $match = Invoice::factory()->issued()->create();
        Invoice::factory()->issued()->create();

        $response = $this->actingAs($actor)->getJson('/api/invoices?search='.$match->invoice_number);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($match->id, $response->json('data.0.id'));
    }

    public function test_list_can_be_searched_by_patient_name(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Uniquename']);
        $match = Invoice::factory()->create(['patient_id' => $patient->id]);
        Invoice::factory()->create();

        $response = $this->actingAs($actor)->getJson('/api/invoices?search=Uniquename');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($match->id, $response->json('data.0.id'));
    }

    public function test_each_row_includes_its_patient(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        Invoice::factory()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson('/api/invoices');

        $response->assertOk();
        $this->assertSame('Jane', $response->json('data.0.patient.first_name'));
        $this->assertSame('Doe', $response->json('data.0.patient.last_name'));
    }
}
