<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\AppointmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateAppointmentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new UpdateAppointmentRequest)->rules();
    }

    public function test_empty_payload_is_valid_since_every_field_is_optional_on_update(): void
    {
        $this->assertTrue(Validator::make([], $this->rules())->passes());
    }

    public function test_patient_id_is_not_an_accepted_field(): void
    {
        // Business rule (design doc §4): booking the wrong patient is a soft-delete case,
        // not an update — patient_id has no validation rule and stays out of $fillable use here.
        $this->assertArrayNotHasKey('patient_id', $this->rules());
    }

    public function test_status_is_not_an_accepted_field(): void
    {
        // Status only moves through the dedicated transition endpoints (confirm/cancel/etc.).
        $this->assertArrayNotHasKey('status', $this->rules());
    }

    public function test_provided_fields_are_still_validated(): void
    {
        $this->assertTrue(Validator::make(['duration_minutes' => 999], $this->rules())->fails());
        $this->assertTrue(Validator::make(['start_at' => 'not-a-date'], $this->rules())->fails());
    }

    public function test_dentist_id_when_provided_must_reference_a_dentist(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $validator = Validator::make(['dentist_id' => $receptionist->id], $this->rules());

        $this->assertTrue($validator->fails());
    }

    public function test_appointment_type_id_when_provided_must_be_active(): void
    {
        $inactiveType = AppointmentType::factory()->create(['is_active' => false]);

        $validator = Validator::make(['appointment_type_id' => $inactiveType->id], $this->rules());

        $this->assertTrue($validator->fails());
    }
}
