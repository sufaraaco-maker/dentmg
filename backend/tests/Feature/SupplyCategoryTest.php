<?php

namespace Tests\Feature;

use App\Models\SupplyCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_role_can_list_categories(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        SupplyCategory::factory()->count(3)->create();

        $response = $this->actingAs($actor)->getJson('/api/supply-categories');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_admin_can_create_a_category(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/supply-categories', ['name' => 'PPE']);

        $response->assertCreated();
        $this->assertSame('PPE', $response->json('name'));
    }

    public function test_category_name_must_be_unique(): void
    {
        $actor = User::factory()->admin()->create();
        SupplyCategory::factory()->create(['name' => 'PPE']);

        $response = $this->actingAs($actor)->postJson('/api/supply-categories', ['name' => 'PPE']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_dentist_cannot_create_a_category(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson('/api/supply-categories', ['name' => 'Anesthetics']);

        $response->assertForbidden();
    }

    public function test_receptionist_cannot_deactivate_a_category(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $category = SupplyCategory::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/supply-categories/{$category->id}");

        $response->assertForbidden();
    }
}
