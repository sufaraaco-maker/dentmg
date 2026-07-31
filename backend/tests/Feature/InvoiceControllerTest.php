<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers only `GET /invoices` (frontend-ux-redesign design doc §5.1/§11) — every other
 * `InvoiceController` action is patient-scoped and stays Unit-tested only (`InvoiceServiceTest`/
 * `InvoicePolicyTest`/`InvoiceResourceTest`), a pre-existing gap this file doesn't attempt to
 * backfill (out of scope for this UI-navigation initiative).
 */
class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

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
