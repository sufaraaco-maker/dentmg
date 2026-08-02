<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers only `GET /treatment-plans` (mirrors `InvoiceControllerTest`'s exact scope/reasoning) —
 * every other `TreatmentPlanController` action is patient-scoped and stays Unit-tested only
 * (`TreatmentPlanServiceTest`/`TreatmentPlanPolicyTest`/`TreatmentPlanResourceTest`), a
 * pre-existing gap this file doesn't attempt to backfill (out of scope for this UI-navigation fix).
 */
class TreatmentPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_treatment_plans(): void
    {
        $response = $this->getJson('/api/treatment-plans');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_treatment_plans_across_every_patient(): void
    {
        $actor = User::factory()->create(); // default role: Receptionist
        TreatmentPlan::factory()->count(3)->create();

        $response = $this->actingAs($actor)->getJson('/api/treatment-plans');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_list_is_paginated(): void
    {
        $actor = User::factory()->admin()->create();
        TreatmentPlan::factory()->count(20)->create();

        $response = $this->actingAs($actor)->getJson('/api/treatment-plans');

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('meta.total'));
    }

    public function test_list_can_be_filtered_by_status(): void
    {
        $actor = User::factory()->admin()->create();
        TreatmentPlan::factory()->count(2)->accepted()->create();
        TreatmentPlan::factory()->create(); // draft, excluded

        $response = $this->actingAs($actor)->getJson('/api/treatment-plans?status=accepted');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_an_unrecognized_status_filter_is_ignored_rather_than_erroring(): void
    {
        $actor = User::factory()->admin()->create();
        TreatmentPlan::factory()->count(2)->create();

        $response = $this->actingAs($actor)->getJson('/api/treatment-plans?status=not-a-real-status');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_can_be_searched_by_title(): void
    {
        $actor = User::factory()->admin()->create();
        $match = TreatmentPlan::factory()->create(['title' => 'Option A — Full Mouth Implants']);
        TreatmentPlan::factory()->create(['title' => 'Something else entirely']);

        $response = $this->actingAs($actor)->getJson('/api/treatment-plans?search=Implants');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($match->id, $response->json('data.0.id'));
    }

    public function test_list_can_be_searched_by_patient_name(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Uniquename']);
        $match = TreatmentPlan::factory()->create(['patient_id' => $patient->id]);
        TreatmentPlan::factory()->create();

        $response = $this->actingAs($actor)->getJson('/api/treatment-plans?search=Uniquename');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($match->id, $response->json('data.0.id'));
    }

    public function test_each_row_includes_its_patient(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        TreatmentPlan::factory()->create(['patient_id' => $patient->id]);

        $response = $this->actingAs($actor)->getJson('/api/treatment-plans');

        $response->assertOk();
        $this->assertSame('Jane', $response->json('data.0.patient.first_name'));
        $this->assertSame('Doe', $response->json('data.0.patient.last_name'));
    }
}
