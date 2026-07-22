<?php

namespace Tests\Feature;

use App\Models\DentalCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentalConditionTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dental Caries',
            'category' => 'finding',
            'applies_to_surface' => true,
            'default_color' => '#DC2626',
        ], $overrides);
    }

    public function test_guest_cannot_list_dental_conditions(): void
    {
        $response = $this->getJson('/api/dental-conditions');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_dental_conditions(): void
    {
        $actor = User::factory()->dentist()->create();
        DentalCondition::factory()->count(3)->create();

        $response = $this->actingAs($actor)->getJson('/api/dental-conditions');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_any_authenticated_role_can_view_a_dental_condition(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $condition = DentalCondition::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/dental-conditions/{$condition->id}");

        $response->assertOk()->assertJson(['id' => $condition->id]);
    }

    public function test_admin_can_create_a_dental_condition(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/dental-conditions', $this->validPayload());

        $response->assertCreated()->assertJson([
            'name' => 'Dental Caries',
            'category' => 'finding',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('dental_conditions', ['name' => 'Dental Caries']);
    }

    public function test_non_admin_cannot_create_a_dental_condition(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson('/api/dental-conditions', $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseMissing('dental_conditions', ['name' => 'Dental Caries']);
    }

    public function test_create_dental_condition_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/dental-conditions', [
            'name' => '',
            'category' => 'not-a-category',
            'applies_to_surface' => 'not-a-bool',
            'default_color' => 'not-a-color',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'name', 'category', 'applies_to_surface', 'default_color',
        ]);
    }

    public function test_admin_can_update_a_dental_condition(): void
    {
        $actor = User::factory()->admin()->create();
        $condition = DentalCondition::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($actor)->putJson("/api/dental-conditions/{$condition->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJson(['name' => 'New Name']);
    }

    public function test_non_admin_cannot_update_a_dental_condition(): void
    {
        $actor = User::factory()->dentist()->create();
        $condition = DentalCondition::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($actor)->putJson("/api/dental-conditions/{$condition->id}", [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('dental_conditions', ['id' => $condition->id, 'name' => 'Old Name']);
    }

    public function test_admin_can_deactivate_a_dental_condition(): void
    {
        $actor = User::factory()->admin()->create();
        $condition = DentalCondition::factory()->create(['is_active' => true]);

        $response = $this->actingAs($actor)->putJson("/api/dental-conditions/{$condition->id}", [
            'is_active' => false,
        ]);

        $response->assertOk()->assertJson(['is_active' => false]);
    }

    public function test_admin_can_delete_a_dental_condition(): void
    {
        $actor = User::factory()->admin()->create();
        $condition = DentalCondition::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/dental-conditions/{$condition->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('dental_conditions', ['id' => $condition->id]);
    }

    public function test_non_admin_cannot_delete_a_dental_condition(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $condition = DentalCondition::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/dental-conditions/{$condition->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('dental_conditions', ['id' => $condition->id]);
    }
}
