<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_labs(): void
    {
        $response = $this->getJson('/api/labs');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_labs(): void
    {
        $actor = User::factory()->dentist()->create();
        Lab::factory()->count(2)->create();

        $response = $this->actingAs($actor)->getJson('/api/labs');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_admin_can_create_a_lab(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/labs', [
            'name' => 'Precision Dental Lab',
            'default_turnaround_days' => 7,
        ]);

        $response->assertCreated();
        $this->assertSame('Precision Dental Lab', $response->json('name'));
        $this->assertSame(7, $response->json('default_turnaround_days'));
        $this->assertTrue($response->json('is_active'));
    }

    public function test_receptionist_cannot_create_a_lab(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->postJson('/api/labs', ['name' => 'Patterson Lab']);

        $response->assertForbidden();
    }

    public function test_dentist_cannot_create_a_lab(): void
    {
        $actor = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson('/api/labs', ['name' => 'Patterson Lab']);

        $response->assertForbidden();
    }

    public function test_admin_can_deactivate_a_lab(): void
    {
        $actor = User::factory()->admin()->create();
        $lab = Lab::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/labs/{$lab->id}");

        $response->assertNoContent();
        $this->assertFalse($lab->fresh()->is_active);
        $this->assertDatabaseHas('labs', ['id' => $lab->id]);
    }

    public function test_receptionist_cannot_deactivate_a_lab(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $lab = Lab::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/labs/{$lab->id}");

        $response->assertForbidden();
    }
}
