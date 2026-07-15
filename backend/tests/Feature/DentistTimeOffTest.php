<?php

namespace Tests\Feature;

use App\Models\DentistTimeOff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentistTimeOffTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'start_at' => now()->addWeek()->toIso8601String(),
            'end_at' => now()->addWeek()->addDay()->toIso8601String(),
            'reason' => 'Vacation',
        ], $overrides);
    }

    public function test_guest_cannot_list_time_off(): void
    {
        $dentist = User::factory()->dentist()->create();

        $response = $this->getJson("/api/dentists/{$dentist->id}/time-off");

        $response->assertUnauthorized();
    }

    public function test_any_authenticated_role_can_list_a_dentists_time_off(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();
        DentistTimeOff::factory()->count(2)->create(['user_id' => $dentist->id]);

        $response = $this->actingAs($actor)->getJson("/api/dentists/{$dentist->id}/time-off");

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_admin_can_create_time_off_for_any_dentist(): void
    {
        $actor = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson("/api/dentists/{$dentist->id}/time-off", $this->validPayload());

        $response->assertCreated()->assertJson(['user_id' => $dentist->id, 'reason' => 'Vacation']);
        $this->assertDatabaseHas('dentist_time_off', ['user_id' => $dentist->id, 'reason' => 'Vacation']);
    }

    public function test_dentist_can_create_their_own_time_off(): void
    {
        $dentist = User::factory()->dentist()->create();

        $response = $this->actingAs($dentist)->postJson("/api/dentists/{$dentist->id}/time-off", $this->validPayload());

        $response->assertCreated()->assertJson(['user_id' => $dentist->id]);
    }

    public function test_dentist_cannot_create_time_off_for_another_dentist(): void
    {
        $dentist = User::factory()->dentist()->create();
        $otherDentist = User::factory()->dentist()->create();

        $response = $this->actingAs($dentist)->postJson("/api/dentists/{$otherDentist->id}/time-off", $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseMissing('dentist_time_off', ['user_id' => $otherDentist->id]);
    }

    public function test_receptionist_cannot_create_time_off(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson("/api/dentists/{$dentist->id}/time-off", $this->validPayload());

        $response->assertForbidden();
    }

    public function test_create_time_off_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $response = $this->actingAs($actor)->postJson("/api/dentists/{$dentist->id}/time-off", [
            'start_at' => 'not-a-date',
            'end_at' => 'not-a-date',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['start_at', 'end_at']);
    }

    public function test_admin_can_delete_any_dentists_time_off(): void
    {
        $actor = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $timeOff = DentistTimeOff::factory()->create(['user_id' => $dentist->id]);

        $response = $this->actingAs($actor)->deleteJson("/api/dentists/{$dentist->id}/time-off/{$timeOff->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('dentist_time_off', ['id' => $timeOff->id]);
    }

    public function test_dentist_can_delete_their_own_time_off(): void
    {
        $dentist = User::factory()->dentist()->create();
        $timeOff = DentistTimeOff::factory()->create(['user_id' => $dentist->id]);

        $response = $this->actingAs($dentist)->deleteJson("/api/dentists/{$dentist->id}/time-off/{$timeOff->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('dentist_time_off', ['id' => $timeOff->id]);
    }

    public function test_dentist_cannot_delete_another_dentists_time_off(): void
    {
        $dentist = User::factory()->dentist()->create();
        $otherDentist = User::factory()->dentist()->create();
        $timeOff = DentistTimeOff::factory()->create(['user_id' => $otherDentist->id]);

        $response = $this->actingAs($dentist)->deleteJson("/api/dentists/{$otherDentist->id}/time-off/{$timeOff->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('dentist_time_off', ['id' => $timeOff->id]);
    }
}
