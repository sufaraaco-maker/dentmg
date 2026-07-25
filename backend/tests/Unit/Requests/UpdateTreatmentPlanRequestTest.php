<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\TreatmentPlan\UpdateTreatmentPlanRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateTreatmentPlanRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new UpdateTreatmentPlanRequest)->rules();
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

    public function test_status_and_patient_id_are_not_accepted_fields(): void
    {
        // Transitions only move through the dedicated /present, /accept, /reject, /start,
        // /complete, /cancel endpoints (design doc §5/§9) — an errant `status` key is silently
        // dropped by FormRequest::validated() rather than accepted as a status change.
        $this->assertArrayNotHasKey('status', $this->rules());
        $this->assertArrayNotHasKey('patient_id', $this->rules());
    }

    public function test_title_can_be_cleared_to_null(): void
    {
        $this->assertTrue(Validator::make(['title' => null], $this->rules())->passes());
    }
}
