<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\DentistWorkingHour\StoreDentistWorkingHourRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreDentistWorkingHourRequestTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ], $overrides);
    }

    private function rules(): array
    {
        return (new StoreDentistWorkingHourRequest)->rules();
    }

    public function test_valid_payload_passes(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(), $this->rules())->passes());
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $validator = Validator::make($this->validPayload(['start_time' => '17:00', 'end_time' => '09:00']), $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_day_of_week_must_be_between_0_and_6(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(['day_of_week' => 7]), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['day_of_week' => -1]), $this->rules())->fails());
    }

    public function test_multiple_rows_on_the_same_day_are_a_valid_shape(): void
    {
        // Split shifts/lunch breaks (design doc §6) — no uniqueness rule on day_of_week here.
        $morning = Validator::make($this->validPayload(['start_time' => '09:00', 'end_time' => '13:00']), $this->rules());
        $afternoon = Validator::make($this->validPayload(['start_time' => '15:00', 'end_time' => '19:00']), $this->rules());

        $this->assertTrue($morning->passes());
        $this->assertTrue($afternoon->passes());
    }
}
