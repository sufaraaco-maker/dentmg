<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_summary(): void
    {
        $response = $this->getJson('/api/dashboard/summary');

        $response->assertUnauthorized();
    }

    public function test_summary_returns_expected_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertStatus(200)->assertJsonStructure([
            'total_patients',
            'today_appointments',
            'monthly_revenue',
        ]);
    }

    public function test_summary_defaults_to_zero_when_no_records_exist_yet(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson([
            'total_patients' => 0,
            'today_appointments' => 0,
            'monthly_revenue' => 0,
        ]);
    }

    public function test_summary_reflects_real_patient_count_now_that_patients_exist(): void
    {
        $user = User::factory()->create();
        Patient::factory()->count(2)->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson(['total_patients' => 2]);
    }

    public function test_summary_still_defaults_appointments_to_zero_until_that_module_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson(['today_appointments' => 0]);
    }
}
