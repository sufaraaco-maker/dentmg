<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
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
            'unscheduled_accepted_treatment' => ['count', 'items'],
        ]);
    }

    /**
     * Dashboard 2.0 (`docs/modules/dashboard-2.0-design.md` §0/§2): `/dashboard/summary` used to
     * return `monthly_revenue` with zero authorization, the same figure `reports/collections`
     * already gated admin-only — a real leak, now fixed by moving it to the separately-gated
     * `/dashboard/financial-summary`. This is the regression test for that fix, run for every role
     * including admin: financial data must never appear on this endpoint, period.
     */
    public function test_summary_never_includes_financial_data_for_any_role(): void
    {
        $admin = User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $receptionist = User::factory()->create();

        foreach ([$admin, $dentist, $receptionist] as $user) {
            $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

            $response->assertStatus(200)->assertJsonMissing(['monthly_revenue']);
        }
    }

    public function test_summary_defaults_to_zero_when_no_records_exist_yet(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson([
            'total_patients' => 0,
            'today_appointments' => 0,
            'unscheduled_accepted_treatment' => ['count' => 0, 'items' => []],
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

    public function test_summary_today_appointments_is_scoped_to_today_not_the_whole_table(): void
    {
        $user = User::factory()->create();
        Appointment::factory()->count(2)->create(['start_at' => Date::today()->copy()->setTime(9, 0)]);
        Appointment::factory()->create(['start_at' => Date::today()->copy()->setTime(23, 59)]);
        // Same-table rows outside today — must not be counted (this is the exact bug PR #14 logged:
        // an unscoped COUNT(*) over the whole appointments table).
        Appointment::factory()->create(['start_at' => Date::today()->copy()->subDay()->setTime(9, 0)]);
        Appointment::factory()->create(['start_at' => Date::today()->copy()->addDay()->setTime(9, 0)]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson(['today_appointments' => 3]);
    }

    public function test_unscheduled_accepted_treatment_counts_item_with_no_appointment_linked(): void
    {
        $user = User::factory()->create();
        $plan = TreatmentPlan::factory()->accepted()->create();
        TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'appointment_id' => null]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson(['unscheduled_accepted_treatment' => ['count' => 1]]);
    }

    public function test_unscheduled_accepted_treatment_counts_item_whose_appointment_was_cancelled(): void
    {
        $user = User::factory()->create();
        $plan = TreatmentPlan::factory()->accepted()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Cancelled]);
        TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'appointment_id' => $appointment->id]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson(['unscheduled_accepted_treatment' => ['count' => 1]]);
    }

    public function test_unscheduled_accepted_treatment_excludes_item_with_an_active_appointment(): void
    {
        $user = User::factory()->create();
        $plan = TreatmentPlan::factory()->accepted()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);
        TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'appointment_id' => $appointment->id]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson(['unscheduled_accepted_treatment' => ['count' => 0]]);
    }

    public function test_unscheduled_accepted_treatment_excludes_items_from_a_plan_that_is_not_accepted(): void
    {
        $user = User::factory()->create();
        $plan = TreatmentPlan::factory()->presented()->create();
        TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id, 'appointment_id' => null]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson(['unscheduled_accepted_treatment' => ['count' => 0]]);
    }

    public function test_unscheduled_accepted_treatment_excludes_items_that_are_already_completed(): void
    {
        $user = User::factory()->create();
        $plan = TreatmentPlan::factory()->accepted()->create();
        TreatmentPlanItem::factory()->completed()->create(['treatment_plan_id' => $plan->id, 'appointment_id' => null]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson(['unscheduled_accepted_treatment' => ['count' => 0]]);
    }

    public function test_unscheduled_accepted_treatment_items_include_patient_and_plan_identifiers(): void
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['first_name' => 'Layla', 'last_name' => 'Hassan']);
        $plan = TreatmentPlan::factory()->accepted()->create(['patient_id' => $patient->id]);
        $item = TreatmentPlanItem::factory()->create([
            'treatment_plan_id' => $plan->id,
            'appointment_id' => null,
            'procedure_name' => 'Root Canal Treatment',
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        $response->assertJson([
            'unscheduled_accepted_treatment' => [
                'count' => 1,
                'items' => [
                    [
                        'patient' => 'Layla Hassan',
                        'patient_id' => $patient->id,
                        'treatment_plan_id' => $plan->id,
                        'item_description' => 'Root Canal Treatment',
                    ],
                ],
            ],
        ]);
    }
}
