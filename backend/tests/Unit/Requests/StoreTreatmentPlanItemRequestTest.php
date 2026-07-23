<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\TreatmentPlanItem\StoreTreatmentPlanItemRequest;
use App\Models\Appointment;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use App\Models\Patient;
use App\Models\TreatmentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTreatmentPlanItemRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `rules()` reads the parent plan's patient via `$this->route('treatment_plan')` (design doc
     * §9's nested route) — fakes the route resolver Laravel would normally provide from the real
     * `treatment-plans/{treatment_plan}/items` route, so `BelongsToPatient` can be exercised the
     * same way `Validator::make($payload, $rules)` already exercises every other Form Request in
     * this codebase.
     */
    private function rules(?TreatmentPlan $plan): array
    {
        $request = new StoreTreatmentPlanItemRequest;
        $request->setRouteResolver(fn () => new class($plan)
        {
            public function __construct(private ?TreatmentPlan $plan) {}

            public function parameter($name, $default = null)
            {
                return $name === 'treatment_plan' ? $this->plan : $default;
            }
        });

        return $request->rules();
    }

    private function validPayload(array $overrides = []): array
    {
        $condition = array_key_exists('dental_condition_id', $overrides)
            ? null
            : DentalCondition::factory()->procedure()->create(['applies_to_surface' => false, 'default_cost' => 100]);

        return array_merge([
            'dental_condition_id' => $condition?->id,
        ], $overrides);
    }

    public function test_valid_payload_passes(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $this->assertTrue(Validator::make($this->validPayload(), $this->rules($plan))->passes());
    }

    public function test_dental_condition_id_is_required(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $validator = Validator::make([], $this->rules($plan));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('dental_condition_id', $validator->errors()->toArray());
    }

    public function test_dental_condition_must_be_a_procedure_not_a_finding(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $finding = DentalCondition::factory()->finding()->create();

        $payload = $this->validPayload(['dental_condition_id' => $finding->id]);

        $this->assertTrue(Validator::make($payload, $this->rules($plan))->fails());
    }

    public function test_dental_condition_must_exist(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $payload = $this->validPayload(['dental_condition_id' => (string) Str::uuid()]);

        $this->assertTrue(Validator::make($payload, $this->rules($plan))->fails());
    }

    public function test_diagnosis_entry_must_belong_to_the_same_patient_as_the_plan(): void
    {
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $plan = TreatmentPlan::factory()->create(['patient_id' => $patient->id]);

        $samePatientEntry = DentalChartEntry::factory()->create(['patient_id' => $patient->id]);
        $otherPatientEntry = DentalChartEntry::factory()->create(['patient_id' => $otherPatient->id]);

        $this->assertTrue(
            Validator::make($this->validPayload(['diagnosis_entry_id' => $samePatientEntry->id]), $this->rules($plan))->passes()
        );
        $this->assertTrue(
            Validator::make($this->validPayload(['diagnosis_entry_id' => $otherPatientEntry->id]), $this->rules($plan))->fails()
        );
    }

    public function test_appointment_must_belong_to_the_same_patient_as_the_plan(): void
    {
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $plan = TreatmentPlan::factory()->create(['patient_id' => $patient->id]);

        $samePatientAppointment = Appointment::factory()->create(['patient_id' => $patient->id]);
        $otherPatientAppointment = Appointment::factory()->create(['patient_id' => $otherPatient->id]);

        $this->assertTrue(
            Validator::make($this->validPayload(['appointment_id' => $samePatientAppointment->id]), $this->rules($plan))->passes()
        );
        $this->assertTrue(
            Validator::make($this->validPayload(['appointment_id' => $otherPatientAppointment->id]), $this->rules($plan))->fails()
        );
    }

    public function test_tooth_number_must_be_a_valid_fdi_code_when_provided(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $this->assertTrue(Validator::make($this->validPayload(['tooth_number' => '19']), $this->rules($plan))->fails());
        $this->assertTrue(Validator::make($this->validPayload(['tooth_number' => '16']), $this->rules($plan))->passes());
    }

    public function test_tooth_number_is_optional_for_non_tooth_specific_procedures(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $this->assertTrue(Validator::make($this->validPayload(['tooth_number' => null]), $this->rules($plan))->passes());
    }

    public function test_tooth_number_is_required_when_surfaces_are_given(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $condition = DentalCondition::factory()->procedure()->create(['applies_to_surface' => true]);

        $payload = $this->validPayload([
            'dental_condition_id' => $condition->id,
            'tooth_number' => null,
            'surfaces' => ['M'],
        ]);

        $this->assertTrue(Validator::make($payload, $this->rules($plan))->fails());
    }

    public function test_surfaces_conditional_rule_applies(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $condition = DentalCondition::factory()->procedure()->create(['applies_to_surface' => true]);

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'tooth_number' => '16', 'surfaces' => []]);
        $this->assertTrue(Validator::make($payload, $this->rules($plan))->fails());

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'tooth_number' => '16', 'surfaces' => ['O']]);
        $this->assertTrue(Validator::make($payload, $this->rules($plan))->passes());
    }

    public function test_unit_cost_is_required_when_the_condition_has_no_default_price(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $condition = DentalCondition::factory()->procedure()->create(['default_cost' => null]);

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'unit_cost' => null]);
        $this->assertTrue(Validator::make($payload, $this->rules($plan))->fails());

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'unit_cost' => 250]);
        $this->assertTrue(Validator::make($payload, $this->rules($plan))->passes());
    }

    public function test_unit_cost_is_optional_when_the_condition_has_a_default_price(): void
    {
        $plan = TreatmentPlan::factory()->create();
        $condition = DentalCondition::factory()->procedure()->create(['default_cost' => 500]);

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'unit_cost' => null]);
        $this->assertTrue(Validator::make($payload, $this->rules($plan))->passes());
    }

    public function test_unit_cost_cannot_be_negative(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $this->assertTrue(Validator::make($this->validPayload(['unit_cost' => -10]), $this->rules($plan))->fails());
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $plan = TreatmentPlan::factory()->create();

        $this->assertTrue(Validator::make($this->validPayload(['quantity' => 0]), $this->rules($plan))->fails());
        $this->assertTrue(Validator::make($this->validPayload(['quantity' => 1]), $this->rules($plan))->passes());
    }

    public function test_procedure_name_status_and_treatment_plan_id_are_not_accepted_fields(): void
    {
        // Never client-writable — computed server-side (procedure_name/description) or derived
        // from the nested route / a fixed initial value (treatment_plan_id/status), design doc
        // §6/§8/§9, Decision 2.
        $rules = $this->rules(TreatmentPlan::factory()->create());

        $this->assertArrayNotHasKey('procedure_name', $rules);
        $this->assertArrayNotHasKey('procedure_description', $rules);
        $this->assertArrayNotHasKey('status', $rules);
        $this->assertArrayNotHasKey('treatment_plan_id', $rules);
    }
}
