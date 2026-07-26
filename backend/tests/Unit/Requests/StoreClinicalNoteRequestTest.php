<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ClinicalNote\StoreClinicalNoteRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreClinicalNoteRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(?Patient $patient): array
    {
        $request = new StoreClinicalNoteRequest;
        $request->setRouteResolver(fn () => new class($patient)
        {
            public function __construct(private ?Patient $patient) {}

            public function parameter($name, $default = null)
            {
                return $name === 'patient' ? $this->patient : $default;
            }
        });

        return $request->rules();
    }

    private function validPayload(array $overrides = []): array
    {
        $dentist = array_key_exists('dentist_id', $overrides) ? null : User::factory()->dentist()->create();

        return array_merge([
            'dentist_id' => $dentist?->id,
            'note_type' => 'progress',
            'chief_complaint' => 'Sensitivity on lower left molar.',
            'subjective' => 'Patient reports mild pain when chewing.',
        ], $overrides);
    }

    public function test_valid_payload_passes(): void
    {
        $patient = Patient::factory()->create();

        $this->assertTrue(Validator::make($this->validPayload(), $this->rules($patient))->passes());
    }

    public function test_dentist_id_is_required(): void
    {
        $patient = Patient::factory()->create();

        $this->assertTrue(Validator::make([], $this->rules($patient))->fails());
    }

    public function test_dentist_id_must_reference_a_user_with_the_dentist_role(): void
    {
        $patient = Patient::factory()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $this->assertTrue(Validator::make($this->validPayload(['dentist_id' => $receptionist->id]), $this->rules($patient))->fails());
    }

    public function test_dentist_id_must_exist(): void
    {
        $patient = Patient::factory()->create();

        $this->assertTrue(Validator::make($this->validPayload(['dentist_id' => (string) Str::uuid()]), $this->rules($patient))->fails());
    }

    public function test_note_type_is_required_and_must_be_a_valid_enum_value(): void
    {
        $patient = Patient::factory()->create();

        $this->assertTrue(Validator::make($this->validPayload(['note_type' => null]), $this->rules($patient))->fails());
        $this->assertTrue(Validator::make($this->validPayload(['note_type' => 'not_a_type']), $this->rules($patient))->fails());
        $this->assertTrue(Validator::make($this->validPayload(['note_type' => 'phone']), $this->rules($patient))->passes());
    }

    public function test_soap_fields_are_all_optional(): void
    {
        $patient = Patient::factory()->create();
        $payload = $this->validPayload(['chief_complaint' => null, 'subjective' => null]);

        $this->assertTrue(Validator::make($payload, $this->rules($patient))->passes());
    }

    public function test_appointment_id_must_belong_to_the_same_patient_when_given(): void
    {
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $sameAppointment = Appointment::factory()->create(['patient_id' => $patient->id]);
        $otherAppointment = Appointment::factory()->create(['patient_id' => $otherPatient->id]);

        $this->assertTrue(Validator::make(
            $this->validPayload(['appointment_id' => $sameAppointment->id]),
            $this->rules($patient)
        )->passes());

        $this->assertTrue(Validator::make(
            $this->validPayload(['appointment_id' => $otherAppointment->id]),
            $this->rules($patient)
        )->fails());
    }

    public function test_appointment_id_is_optional(): void
    {
        $patient = Patient::factory()->create();

        $this->assertTrue(Validator::make($this->validPayload(['appointment_id' => null]), $this->rules($patient))->passes());
    }

    public function test_status_and_patient_id_are_not_accepted_fields(): void
    {
        // A new note always starts draft, and patient_id comes from the nested route — neither is
        // client-controllable (design doc §9).
        $rules = $this->rules(Patient::factory()->create());

        $this->assertArrayNotHasKey('status', $rules);
        $this->assertArrayNotHasKey('patient_id', $rules);
        $this->assertArrayNotHasKey('signed_at', $rules);
        $this->assertArrayNotHasKey('signed_by_id', $rules);
    }
}
