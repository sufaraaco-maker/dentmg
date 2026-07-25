<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\TreatmentPlan\StoreTreatmentPlanRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTreatmentPlanRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new StoreTreatmentPlanRequest)->rules();
    }

    private function validPayload(array $overrides = []): array
    {
        $dentist = array_key_exists('dentist_id', $overrides) ? null : User::factory()->dentist()->create();

        return array_merge([
            'dentist_id' => $dentist?->id,
            'title' => 'Option A',
            'notes' => 'Discussed with patient.',
        ], $overrides);
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(), $this->rules())->passes());
    }

    public function test_dentist_id_is_required(): void
    {
        $validator = Validator::make([], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('dentist_id', $validator->errors()->toArray());
    }

    public function test_dentist_id_must_reference_a_user_with_the_dentist_role(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue(Validator::make($this->validPayload(['dentist_id' => $receptionist->id]), $this->rules())->fails());
    }

    public function test_dentist_id_must_exist(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(['dentist_id' => (string) Str::uuid()]), $this->rules())->fails());
    }

    public function test_title_and_notes_are_optional(): void
    {
        $payload = $this->validPayload(['title' => null, 'notes' => null]);

        $this->assertTrue(Validator::make($payload, $this->rules())->passes());
    }

    public function test_status_and_patient_id_are_not_accepted_fields(): void
    {
        // A new plan always starts draft, and patient_id comes from the nested route — neither is
        // client-controllable (design doc §9); confirmed absent from the rule set entirely rather
        // than merely ignored, so FormRequest::validated() can never surface either key.
        $this->assertArrayNotHasKey('status', $this->rules());
        $this->assertArrayNotHasKey('patient_id', $this->rules());
    }
}
