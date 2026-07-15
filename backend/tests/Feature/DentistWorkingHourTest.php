<?php

namespace Tests\Feature;

use App\Models\DentistWorkingHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentistWorkingHourTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ], $overrides);
    }

    public function test_guest_cannot_list_working_hours(): void
    {
        $dentist = User::factory()->dentist()->create();

        $response = $this->getJson("/api/dentists/{$dentist->id}/working-hours");

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_a_dentists_working_hours(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();
        DentistWorkingHour::factory()->count(2)->create(['user_id' => $dentist->id]);

        $response = $this->actingAs($actor)->getJson("/api/dentists/{$dentist->id}/working-hours");

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_admin_can_create_a_working_hour_for_any_dentist(): void
    {
        $actor = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson("/api/dentists/{$dentist->id}/working-hours", $this->validPayload());

        $response->assertCreated()->assertJson(['user_id' => $dentist->id, 'day_of_week' => 1]);
        $this->assertDatabaseHas('dentist_working_hours', ['user_id' => $dentist->id, 'day_of_week' => 1]);
    }

    public function test_receptionist_cannot_create_a_working_hour(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson("/api/dentists/{$dentist->id}/working-hours", $this->validPayload());

        $response->assertForbidden();
    }

    public function test_a_dentist_cannot_self_service_their_own_working_hours(): void
    {
        $dentist = User::factory()->dentist()->create();

        $response = $this->actingAs($dentist)->postJson("/api/dentists/{$dentist->id}/working-hours", $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseMissing('dentist_working_hours', ['user_id' => $dentist->id]);
    }

    public function test_create_working_hour_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson("/api/dentists/{$dentist->id}/working-hours", [
            'day_of_week' => 9,
            'start_time' => '17:00',
            'end_time' => '09:00',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['day_of_week', 'end_time']);
    }

    public function test_admin_can_delete_a_working_hour(): void
    {
        $actor = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $workingHour = DentistWorkingHour::factory()->create(['user_id' => $dentist->id]);

        $response = $this->actingAs($actor)->deleteJson("/api/dentists/{$dentist->id}/working-hours/{$workingHour->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('dentist_working_hours', ['id' => $workingHour->id]);
    }

    public function test_dentist_cannot_delete_their_own_working_hour(): void
    {
        $dentist = User::factory()->dentist()->create();
        $workingHour = DentistWorkingHour::factory()->create(['user_id' => $dentist->id]);

        $response = $this->actingAs($dentist)->deleteJson("/api/dentists/{$dentist->id}/working-hours/{$workingHour->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('dentist_working_hours', ['id' => $workingHour->id]);
    }
}
