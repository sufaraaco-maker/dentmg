<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
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
            'monthly_revenue' => '0.00',
        ]);
    }

    public function test_summary_monthly_revenue_reflects_this_months_payments_via_report_service(): void
    {
        $user = User::factory()->create();
        Payment::factory()->create(['amount' => '150.00', 'received_at' => Date::today()->startOfMonth()]);
        Payment::factory()->create(['amount' => '75.50', 'received_at' => Date::today()->endOfMonth()]);
        // Outside the current month — must not be counted.
        Payment::factory()->create(['amount' => '999.00', 'received_at' => Date::today()->copy()->subMonths(2)]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson(['monthly_revenue' => '225.50']);
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
