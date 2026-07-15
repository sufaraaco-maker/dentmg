<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\DentistTimeOff\StoreDentistTimeOffRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreDentistTimeOffRequestTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'start_at' => now()->addWeek()->toDateTimeString(),
            'end_at' => now()->addWeek()->addDay()->toDateTimeString(),
        ], $overrides);
    }

    private function rules(): array
    {
        return (new StoreDentistTimeOffRequest)->rules();
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(), $this->rules())->passes());
    }

    public function test_end_at_must_be_after_start_at(): void
    {
        $validator = Validator::make($this->validPayload([
            'start_at' => now()->addWeek()->toDateTimeString(),
            'end_at' => now()->toDateTimeString(),
        ]), $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_reason_is_optional_and_length_capped(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(), $this->rules())->passes());
        $this->assertTrue(Validator::make($this->validPayload(['reason' => str_repeat('a', 256)]), $this->rules())->fails());
    }
}
