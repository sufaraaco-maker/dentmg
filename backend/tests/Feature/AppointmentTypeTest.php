<?php

namespace Tests\Feature;

use App\Models\AppointmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTypeTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Consultation',
            'default_duration_minutes' => 30,
            'color' => '#3B82F6',
        ], $overrides);
    }

    public function test_guest_cannot_list_appointment_types(): void
    {
        $response = $this->getJson('/api/appointment-types');

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_appointment_types(): void
    {
        $actor = User::factory()->dentist()->create();
        AppointmentType::factory()->count(3)->create();

        $response = $this->actingAs($actor)->getJson('/api/appointment-types');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_any_authenticated_role_can_view_an_appointment_type(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $type = AppointmentType::factory()->create();

        $response = $this->actingAs($actor)->getJson("/api/appointment-types/{$type->id}");

        $response->assertOk()->assertJson(['id' => $type->id]);
    }

    public function test_admin_can_create_an_appointment_type(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/appointment-types', $this->validPayload());

        $response->assertCreated()->assertJson(['name' => 'Consultation', 'is_active' => true]);
        $this->assertDatabaseHas('appointment_types', ['name' => 'Consultation']);
    }

    public function test_non_admin_cannot_create_an_appointment_type(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($actor)->postJson('/api/appointment-types', $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseMissing('appointment_types', ['name' => 'Consultation']);
    }

    public function test_create_appointment_type_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();

        $response = $this->actingAs($actor)->postJson('/api/appointment-types', [
            'name' => '',
            'default_duration_minutes' => 0,
            'color' => 'not-a-color',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'default_duration_minutes', 'color']);
    }

    public function test_admin_can_update_an_appointment_type(): void
    {
        $actor = User::factory()->admin()->create();
        $type = AppointmentType::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($actor)->putJson("/api/appointment-types/{$type->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()->assertJson(['name' => 'New Name']);
    }

    public function test_non_admin_cannot_update_an_appointment_type(): void
    {
        $actor = User::factory()->dentist()->create();
        $type = AppointmentType::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($actor)->putJson("/api/appointment-types/{$type->id}", [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('appointment_types', ['id' => $type->id, 'name' => 'Old Name']);
    }

    public function test_admin_can_deactivate_an_appointment_type(): void
    {
        $actor = User::factory()->admin()->create();
        $type = AppointmentType::factory()->create(['is_active' => true]);

        $response = $this->actingAs($actor)->putJson("/api/appointment-types/{$type->id}", [
            'is_active' => false,
        ]);

        $response->assertOk()->assertJson(['is_active' => false]);
    }

    public function test_admin_can_delete_an_appointment_type(): void
    {
        $actor = User::factory()->admin()->create();
        $type = AppointmentType::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/appointment-types/{$type->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('appointment_types', ['id' => $type->id]);
    }

    public function test_non_admin_cannot_delete_an_appointment_type(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $type = AppointmentType::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/appointment-types/{$type->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('appointment_types', ['id' => $type->id]);
    }
}
