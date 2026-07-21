<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\DentalChartEntry\UpdateDentalChartEntryRequest;
use App\Models\DentalCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateDentalChartEntryRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new UpdateDentalChartEntryRequest)->rules();
    }

    public function test_empty_payload_passes_since_every_field_is_optional(): void
    {
        $this->assertTrue(Validator::make([], $this->rules())->passes());
    }

    public function test_dentist_id_must_reference_a_user_with_the_dentist_role_when_provided(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $dentist = User::factory()->dentist()->create();

        $this->assertTrue(Validator::make(['dentist_id' => $receptionist->id], $this->rules())->fails());
        $this->assertTrue(Validator::make(['dentist_id' => $dentist->id], $this->rules())->passes());
    }

    public function test_tooth_number_must_be_a_valid_fdi_code_when_provided(): void
    {
        $this->assertTrue(Validator::make(['tooth_number' => '19'], $this->rules())->fails());
        $this->assertTrue(Validator::make(['tooth_number' => '16'], $this->rules())->passes());
    }

    public function test_status_is_not_an_accepted_field(): void
    {
        // Transitions only move through the dedicated /complete and /cancel endpoints — this
        // field simply isn't validated here, so an errant `status` key is silently dropped by
        // FormRequest::validated() rather than accepted as a status change.
        $this->assertArrayNotHasKey('status', $this->rules());
    }

    public function test_surfaces_conditional_rule_still_applies_when_both_fields_are_present(): void
    {
        $condition = DentalCondition::factory()->create(['applies_to_surface' => true]);

        $payload = ['dental_condition_id' => $condition->id, 'tooth_number' => '16', 'surfaces' => []];
        $this->assertTrue(Validator::make($payload, $this->rules())->fails());

        $payload = ['dental_condition_id' => $condition->id, 'tooth_number' => '16', 'surfaces' => ['O']];
        $this->assertTrue(Validator::make($payload, $this->rules())->passes());
    }

    public function test_occlusal_incisal_rule_still_applies_when_both_fields_are_present(): void
    {
        $condition = DentalCondition::factory()->create(['applies_to_surface' => true]);

        $payload = ['dental_condition_id' => $condition->id, 'tooth_number' => '11', 'surfaces' => ['O']];
        $this->assertTrue(Validator::make($payload, $this->rules())->fails());
    }
}
