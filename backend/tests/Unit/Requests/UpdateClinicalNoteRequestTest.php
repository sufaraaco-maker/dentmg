<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\ClinicalNote\UpdateClinicalNoteRequest;
use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateClinicalNoteRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(?ClinicalNote $note): array
    {
        $request = new UpdateClinicalNoteRequest;
        $request->setRouteResolver(fn () => new class($note)
        {
            public function __construct(private ?ClinicalNote $note) {}

            public function parameter($name, $default = null)
            {
                return $name === 'clinical_note' ? $this->note : $default;
            }
        });

        return $request->rules();
    }

    public function test_empty_payload_passes_since_every_field_is_optional(): void
    {
        $note = ClinicalNote::factory()->create();

        $this->assertTrue(Validator::make([], $this->rules($note))->passes());
    }

    public function test_note_type_must_be_a_valid_enum_value_when_provided(): void
    {
        $note = ClinicalNote::factory()->create();

        $this->assertTrue(Validator::make(['note_type' => 'not_a_type'], $this->rules($note))->fails());
        $this->assertTrue(Validator::make(['note_type' => 'consultation'], $this->rules($note))->passes());
    }

    public function test_appointment_id_must_belong_to_the_notes_patient(): void
    {
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $note = ClinicalNote::factory()->create(['patient_id' => $patient->id]);

        $sameAppointment = Appointment::factory()->create(['patient_id' => $patient->id]);
        $otherAppointment = Appointment::factory()->create(['patient_id' => $otherPatient->id]);

        $this->assertTrue(Validator::make(['appointment_id' => $sameAppointment->id], $this->rules($note))->passes());
        $this->assertTrue(Validator::make(['appointment_id' => $otherAppointment->id], $this->rules($note))->fails());
    }

    public function test_appointment_id_can_be_cleared_to_null(): void
    {
        $note = ClinicalNote::factory()->create();

        $this->assertTrue(Validator::make(['appointment_id' => null], $this->rules($note))->passes());
    }

    public function test_dentist_id_patient_id_status_signed_at_and_signed_by_id_are_not_accepted_fields(): void
    {
        // Author of record and patient are fixed at creation; status only ever moves through the
        // dedicated /sign endpoint (design doc §7/§8/§9).
        $rules = $this->rules(ClinicalNote::factory()->create());

        $this->assertArrayNotHasKey('dentist_id', $rules);
        $this->assertArrayNotHasKey('patient_id', $rules);
        $this->assertArrayNotHasKey('status', $rules);
        $this->assertArrayNotHasKey('signed_at', $rules);
        $this->assertArrayNotHasKey('signed_by_id', $rules);
    }
}
