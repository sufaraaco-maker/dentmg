<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreAppointmentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'dentist_id' => User::factory()->dentist()->create()->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'start_at' => now()->addDay()->toDateTimeString(),
            'duration_minutes' => 30,
        ], $overrides);
    }

    private function rules(): array
    {
        return (new StoreAppointmentRequest)->rules();
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
            ['patient_id', 'dentist_id', 'appointment_type_id', 'start_at', 'duration_minutes'],
            array_keys($validator->errors()->toArray())
        );
    }

    public function test_dentist_id_must_reference_a_user_with_dentist_role(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $validator = Validator::make($this->validPayload(['dentist_id' => $receptionist->id]), $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('dentist_id', $validator->errors()->toArray());
    }

    public function test_patient_id_must_exist_and_not_be_soft_deleted(): void
    {
        $patient = Patient::factory()->create();
        $patient->delete();

        $validator = Validator::make($this->validPayload(['patient_id' => $patient->id]), $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('patient_id', $validator->errors()->toArray());
    }

    public function test_appointment_type_must_be_active(): void
    {
        $inactiveType = AppointmentType::factory()->create(['is_active' => false]);

        $validator = Validator::make($this->validPayload(['appointment_type_id' => $inactiveType->id]), $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('appointment_type_id', $validator->errors()->toArray());
    }

    public function test_duration_minutes_must_be_within_the_sane_bound(): void
    {
        $this->assertTrue(Validator::make($this->validPayload(['duration_minutes' => 1]), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['duration_minutes' => 481]), $this->rules())->fails());
        $this->assertTrue(Validator::make($this->validPayload(['duration_minutes' => 480]), $this->rules())->passes());
    }

    public function test_start_at_in_the_past_is_allowed(): void
    {
        // Business rule §5.7: no restriction on booking in the past (same-day walk-ins, backfill).
        $validator = Validator::make($this->validPayload(['start_at' => now()->subDay()->toDateTimeString()]), $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_override_flags_must_be_boolean_when_present(): void
    {
        $this->assertTrue(Validator::make(
            $this->validPayload(['override_patient_conflict' => true, 'override_outside_working_hours' => false]),
            $this->rules()
        )->passes());

        $this->assertTrue(Validator::make(
            $this->validPayload(['override_patient_conflict' => 'not-a-bool']),
            $this->rules()
        )->fails());
    }
}
