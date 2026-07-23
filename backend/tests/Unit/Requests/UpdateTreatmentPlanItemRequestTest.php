<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\TreatmentPlanItem\UpdateTreatmentPlanItemRequest;
use App\Models\Appointment;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateTreatmentPlanItemRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(?TreatmentPlanItem $item): array
    {
        $request = new UpdateTreatmentPlanItemRequest;
        $request->setRouteResolver(fn () => new class($item)
        {
            public function __construct(private ?TreatmentPlanItem $item) {}

            public function parameter($name, $default = null)
            {
                return $name === 'treatment_plan_item' ? $this->item : $default;
            }
        });

        return $request->rules();
    }

    public function test_empty_payload_passes_since_every_field_is_optional(): void
    {
        $item = TreatmentPlanItem::factory()->create();

        $this->assertTrue(Validator::make([], $this->rules($item))->passes());
    }

    public function test_dental_condition_must_be_a_procedure_when_provided(): void
    {
        $item = TreatmentPlanItem::factory()->create();
        $finding = DentalCondition::factory()->finding()->create();
        $procedure = DentalCondition::factory()->procedure()->create(['applies_to_surface' => false]);

        $this->assertTrue(Validator::make(['dental_condition_id' => $finding->id], $this->rules($item))->fails());
        $this->assertTrue(Validator::make(['dental_condition_id' => $procedure->id], $this->rules($item))->passes());
    }

    public function test_diagnosis_entry_must_belong_to_the_items_plan_patient(): void
    {
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $plan = TreatmentPlan::factory()->create(['patient_id' => $patient->id]);
        $item = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);

        $samePatientEntry = DentalChartEntry::factory()->create(['patient_id' => $patient->id]);
        $otherPatientEntry = DentalChartEntry::factory()->create(['patient_id' => $otherPatient->id]);

        $this->assertTrue(Validator::make(['diagnosis_entry_id' => $samePatientEntry->id], $this->rules($item))->passes());
        $this->assertTrue(Validator::make(['diagnosis_entry_id' => $otherPatientEntry->id], $this->rules($item))->fails());
    }

    public function test_appointment_must_belong_to_the_items_plan_patient(): void
    {
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $plan = TreatmentPlan::factory()->create(['patient_id' => $patient->id]);
        $item = TreatmentPlanItem::factory()->create(['treatment_plan_id' => $plan->id]);

        $samePatientAppointment = Appointment::factory()->create(['patient_id' => $patient->id]);
        $otherPatientAppointment = Appointment::factory()->create(['patient_id' => $otherPatient->id]);

        $this->assertTrue(Validator::make(['appointment_id' => $samePatientAppointment->id], $this->rules($item))->passes());
        $this->assertTrue(Validator::make(['appointment_id' => $otherPatientAppointment->id], $this->rules($item))->fails());
    }

    public function test_appointment_id_can_be_cleared_to_null_to_unschedule(): void
    {
        $item = TreatmentPlanItem::factory()->create();

        $this->assertTrue(Validator::make(['appointment_id' => null], $this->rules($item))->passes());
    }

    public function test_tooth_number_must_be_a_valid_fdi_code_when_provided(): void
    {
        $item = TreatmentPlanItem::factory()->create();

        $this->assertTrue(Validator::make(['tooth_number' => '19'], $this->rules($item))->fails());
        $this->assertTrue(Validator::make(['tooth_number' => '16'], $this->rules($item))->passes());
    }

    public function test_surfaces_conditional_rule_uses_the_items_own_values_as_fallback(): void
    {
        $condition = DentalCondition::factory()->procedure()->create(['applies_to_surface' => true]);
        $item = TreatmentPlanItem::factory()->create([
            'dental_condition_id' => $condition->id,
            'tooth_number' => '16',
        ]);

        // Neither dental_condition_id nor tooth_number is re-sent — the rule must fall back to the
        // route-bound item's own values (mirrors UpdateDentalChartEntryRequest's identical pattern).
        $this->assertTrue(Validator::make(['surfaces' => []], $this->rules($item))->fails());
        $this->assertTrue(Validator::make(['surfaces' => ['O']], $this->rules($item))->passes());
    }

    public function test_status_and_treatment_plan_id_are_not_accepted_fields(): void
    {
        $item = TreatmentPlanItem::factory()->create();
        $rules = $this->rules($item);

        $this->assertArrayNotHasKey('status', $rules);
        $this->assertArrayNotHasKey('treatment_plan_id', $rules);
        $this->assertArrayNotHasKey('procedure_name', $rules);
        $this->assertArrayNotHasKey('procedure_description', $rules);
    }

    public function test_unit_cost_cannot_be_negative_when_provided(): void
    {
        $item = TreatmentPlanItem::factory()->create();

        $this->assertTrue(Validator::make(['unit_cost' => -5], $this->rules($item))->fails());
        $this->assertTrue(Validator::make(['unit_cost' => 100], $this->rules($item))->passes());
    }
}
