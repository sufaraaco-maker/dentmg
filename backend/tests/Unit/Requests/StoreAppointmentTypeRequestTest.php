<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\AppointmentType\StoreAppointmentTypeRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreAppointmentTypeRequestTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Consultation',
            'default_duration_minutes' => 30,
            'color' => '#3B82F6',
        ], $overrides);
    }

    private function rules(): array
    {
        return (new StoreAppointmentTypeRequest)->rules();
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
            ['name', 'default_duration_minutes', 'color'],
            array_keys($validator->errors()->toArray())
        );
    }

    public function test_color_must_be_a_hex_string(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(['color' => 'blue']), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['color' => '#ZZZZZZ']), $this->rules())->fails());
    }

    public function test_default_duration_minutes_must_be_within_the_sane_bound(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(['default_duration_minutes' => 0]), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['default_duration_minutes' => 481]), $this->rules())->fails());
    }
}
