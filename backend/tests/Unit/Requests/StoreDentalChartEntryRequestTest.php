<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\DentalChartEntry\StoreDentalChartEntryRequest;
use App\Models\DentalCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreDentalChartEntryRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new StoreDentalChartEntryRequest)->rules();
    }

    private function validPayload(array $overrides = []): array
    {
        $condition = $overrides['dental_condition_id'] ?? null
            ? null
            : DentalCondition::factory()->create(['applies_to_surface' => false]);

        $dentist = $overrides['dentist_id'] ?? null
            ? null
            : User::factory()->dentist()->create();

        return array_merge([
            'dental_condition_id' => $condition?->id,
            'dentist_id' => $dentist?->id,
            'tooth_number' => '16',
            'status' => 'active',
        ], $overrides);
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(), $this->rules())->passes());
    }

    public function test_required_fields_are_enforced(): void
    {
        $validator = Validator::make([], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertEqualsCanonicalizing(
            ['dental_condition_id', 'dentist_id', 'tooth_number', 'status'],
            array_keys($validator->errors()->toArray())
        );
    }

    public function test_dental_condition_must_exist(): void
    {
        $payload = $this->validPayload(['dental_condition_id' => (string) Str::uuid()]);

        $this->assertTrue(Validator::make($payload, $this->rules())->fails());
    }

    public function test_dentist_id_must_reference_a_user_with_the_dentist_role(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $payload = $this->validPayload(['dentist_id' => $receptionist->id]);

        $this->assertTrue(Validator::make($payload, $this->rules())->fails());
    }

    public function test_tooth_number_must_be_a_valid_fdi_code(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(['tooth_number' => '19']), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['tooth_number' => '91']), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['tooth_number' => '00']), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['tooth_number' => '16']), $this->rules())->passes());
    }

    public function test_status_cannot_be_set_to_cancelled_on_create(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(['status' => 'cancelled']), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['status' => 'planned']), $this->rules())->passes());
    }

    public function test_surfaces_are_required_when_the_condition_applies_to_a_surface(): void
    {
        $condition = DentalCondition::factory()->create(['applies_to_surface' => true]);

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'surfaces' => []]);
        $this->assertTrue(Validator::make($payload, $this->rules())->fails());

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'surfaces' => ['M']]);
        $this->assertTrue(Validator::make($payload, $this->rules())->passes());
    }

    public function test_surfaces_are_rejected_when_the_condition_does_not_apply_to_a_surface(): void
    {
        $condition = DentalCondition::factory()->create(['applies_to_surface' => false]);

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'surfaces' => ['M']]);
        $this->assertTrue(Validator::make($payload, $this->rules())->fails());

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'surfaces' => []]);
        $this->assertTrue(Validator::make($payload, $this->rules())->passes());
    }

    public function test_occlusal_surface_is_only_valid_on_a_posterior_tooth(): void
    {
        $condition = DentalCondition::factory()->create(['applies_to_surface' => true]);

        // Tooth 11 (upper right central incisor) is anterior — O is invalid there.
        $anterior = $this->validPayload(['dental_condition_id' => $condition->id, 'tooth_number' => '11', 'surfaces' => ['O']]);
        $this->assertTrue(Validator::make($anterior, $this->rules())->fails());

        // Tooth 16 (upper right first molar) is posterior — O is valid there.
        $posterior = $this->validPayload(['dental_condition_id' => $condition->id, 'tooth_number' => '16', 'surfaces' => ['O']]);
        $this->assertTrue(Validator::make($posterior, $this->rules())->passes());
    }

    public function test_incisal_surface_is_only_valid_on_an_anterior_tooth(): void
    {
        $condition = DentalCondition::factory()->create(['applies_to_surface' => true]);

        // Tooth 16 (upper right first molar) is posterior — I is invalid there.
        $posterior = $this->validPayload(['dental_condition_id' => $condition->id, 'tooth_number' => '16', 'surfaces' => ['I']]);
        $this->assertTrue(Validator::make($posterior, $this->rules())->fails());

        // Tooth 11 (upper right central incisor) is anterior — I is valid there.
        $anterior = $this->validPayload(['dental_condition_id' => $condition->id, 'tooth_number' => '11', 'surfaces' => ['I']]);
        $this->assertTrue(Validator::make($anterior, $this->rules())->passes());
    }

    public function test_surface_values_are_restricted_to_the_fixed_set(): void
    {
        $condition = DentalCondition::factory()->create(['applies_to_surface' => true]);

        $payload = $this->validPayload(['dental_condition_id' => $condition->id, 'surfaces' => ['X']]);
        $this->assertTrue(Validator::make($payload, $this->rules())->fails());
    }
}
