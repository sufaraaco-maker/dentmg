<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\DentalCondition\StoreDentalConditionRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreDentalConditionRequestTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dental Caries',
            'category' => 'finding',
            'default_color' => '#EF4444',
            'applies_to_surface' => true,
        ], $overrides);
    }

    private function rules(): array
    {
        return (new StoreDentalConditionRequest)->rules();
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
            ['name', 'category', 'default_color', 'applies_to_surface'],
            array_keys($validator->errors()->toArray())
        );
    }

    public function test_default_color_must_be_a_hex_string(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(['default_color' => 'red']), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['default_color' => '#ZZZZZZ']), $this->rules())->fails());
    }

    public function test_category_must_be_a_valid_enum_value(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(['category' => 'diagnosis']), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['category' => 'procedure']), $this->rules())->passes());
    }
}
