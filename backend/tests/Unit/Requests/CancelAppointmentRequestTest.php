<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Appointment\CancelAppointmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CancelAppointmentRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new CancelAppointmentRequest)->rules();
    }

    public function test_cancellation_reason_is_optional(): void
    {
        $this->assertTrue(Validator::make([], $this->rules())->passes());
    }

    public function test_cancellation_reason_has_a_length_cap(): void
    {
        $this->assertTrue(Validator::make(['cancellation_reason' => str_repeat('a', 1001)], $this->rules())->fails());
        $this->assertTrue(Validator::make(['cancellation_reason' => str_repeat('a', 1000)], $this->rules())->passes());
    }
}
