<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Appointment\MarkNoShowAppointmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MarkNoShowAppointmentRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new MarkNoShowAppointmentRequest)->rules();
    }

    public function test_override_flag_is_optional(): void
    {
        $this->assertTrue(Validator::make([], $this->rules())->passes());
    }

    public function test_override_flag_must_be_boolean(): void
    {
        $this->assertTrue(Validator::make(['override_early_no_show' => true], $this->rules())->passes());
        $this->assertTrue(Validator::make(['override_early_no_show' => 'not-a-bool'], $this->rules())->fails());
    }
}
