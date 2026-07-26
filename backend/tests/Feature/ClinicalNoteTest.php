<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Written and CI-verified as part of this module's own implementation sequence, per explicit
 * Step 2/Step 3 direction — closing the same "no Feature-test suite" gap Treatment Plans/Billing
 * shipped without (see TECH_DEBT.md), rather than deferring it again.
 */
class ClinicalNoteTest extends TestCase
{
    use RefreshDatabase;

    // ---- index --------------------------------------------------------------------------------

    public function test_guest_cannot_list_clinical_notes(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/patients/{$patient->id}/clinical-notes");

        $response->assertUnauthorized();
    }

    public function test_admin_and_dentist_can_list_a_patients_clinical_notes(): void
    {
        $patient = Patient::factory()->create();
        ClinicalNote::factory()->count(2)->create(['patient_id' => $patient->id]);
        ClinicalNote::factory()->create(); // different patient, excluded

        foreach ([User::factory()->admin()->create(), User::factory()->dentist()->create()] as $actor) {
            $response = $this->actingAs($actor)->getJson("/api/patients/{$patient->id}/clinical-notes");

            $response->assertOk();
            $this->assertCount(2, $response->json());
        }
    }

    public function test_receptionist_cannot_list_clinical_notes(): void
    {
        $patient = Patient::factory()->create();
        $receptionist = User::factory()->create(['role' => 'receptionist']);

        $response = $this->actingAs($receptionist)->getJson("/api/patients/{$patient->id}/clinical-notes");

        $response->assertForbidden();
    }

    // ---- store ----------------------------------------------------------------------------------

    public function test_guest_cannot_create_a_clinical_note(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->postJson("/api/patients/{$patient->id}/clinical-notes", [
            'dentist_id' => User::factory()->dentist()->create()->id,
            'note_type' => 'progress',
        ]);

        $response->assertUnauthorized();
    }

    public function test_dentist_can_create_a_draft_clinical_note(): void
    {
        $actor = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/clinical-notes", [
            'dentist_id' => $actor->id,
            'note_type' => 'progress',
            'subjective' => 'Patient reports mild pain.',
        ]);

        $response->assertCreated();
        $this->assertSame('draft', $response->json('status'));
        $this->assertSame($patient->id, $response->json('patient_id'));
    }

    public function test_receptionist_cannot_create_a_clinical_note(): void
    {
        $actor = User::factory()->create(['role' => 'receptionist']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/clinical-notes", [
            'dentist_id' => User::factory()->dentist()->create()->id,
            'note_type' => 'progress',
        ]);

        $response->assertForbidden();
    }

    public function test_store_requires_valid_data(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/clinical-notes", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['dentist_id', 'note_type']);
    }

    public function test_appointment_id_must_belong_to_the_same_patient(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $otherAppointment = Appointment::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/patients/{$patient->id}/clinical-notes", [
            'dentist_id' => User::factory()->dentist()->create()->id,
            'note_type' => 'progress',
            'appointment_id' => $otherAppointment->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['appointment_id']);
    }

    // ---- show / update ----------------------------------------------------------------------------

    public function test_admin_can_update_a_draft_notes_content(): void
    {
        $actor = User::factory()->admin()->create();
        $note = ClinicalNote::factory()->create(['subjective' => 'Old']);

        $response = $this->actingAs($actor)->putJson("/api/clinical-notes/{$note->id}", [
            'subjective' => 'New',
        ]);

        $response->assertOk();
        $this->assertSame('New', $response->json('subjective'));
    }

    public function test_updating_a_signed_note_is_rejected(): void
    {
        $actor = User::factory()->admin()->create();
        $note = ClinicalNote::factory()->signed()->create();

        $response = $this->actingAs($actor)->putJson("/api/clinical-notes/{$note->id}", [
            'subjective' => 'changed',
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'clinical_note_locked']);
    }

    public function test_receptionist_cannot_view_or_update_a_clinical_note(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $note = ClinicalNote::factory()->create();

        $this->actingAs($receptionist)->getJson("/api/clinical-notes/{$note->id}")->assertForbidden();
        $this->actingAs($receptionist)->putJson("/api/clinical-notes/{$note->id}", ['subjective' => 'x'])->assertForbidden();
    }

    // ---- sign -------------------------------------------------------------------------------------

    public function test_dentist_can_sign_a_note_with_content(): void
    {
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->create(['subjective' => 'Patient reports mild pain.']);

        $response = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/sign");

        $response->assertOk();
        $this->assertSame('signed', $response->json('status'));
        $this->assertSame($actor->id, $response->json('signed_by_id'));
        $this->assertNotNull($response->json('signed_at'));
    }

    public function test_signing_a_blank_note_is_rejected(): void
    {
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->create([
            'chief_complaint' => null,
            'subjective' => null,
            'objective' => null,
            'assessment' => null,
            'plan' => null,
        ]);

        $response = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/sign");

        $response->assertStatus(422)->assertJson(['code' => 'invalid_clinical_note_operation']);
    }

    public function test_signing_twice_is_idempotent_not_an_error(): void
    {
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->create(['subjective' => 'Patient reports mild pain.']);

        $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/sign")->assertOk();
        $second = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/sign");

        $second->assertOk();
        $this->assertSame('signed', $second->json('status'));
    }

    public function test_receptionist_cannot_sign_a_note(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $note = ClinicalNote::factory()->create(['subjective' => 'x']);

        $response = $this->actingAs($receptionist)->postJson("/api/clinical-notes/{$note->id}/sign");

        $response->assertForbidden();
    }

    // ---- addendums ----------------------------------------------------------------------------------

    public function test_dentist_can_add_an_addendum_to_a_signed_note(): void
    {
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->signed()->create();

        $response = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/addendums", [
            'body' => 'Follow-up call: pain resolved.',
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->json('addendums'));
        $this->assertSame('Follow-up call: pain resolved.', $response->json('addendums.0.body'));
    }

    public function test_adding_an_addendum_to_a_draft_note_is_rejected(): void
    {
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->create();

        $response = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/addendums", [
            'body' => 'too early',
        ]);

        $response->assertStatus(422)->assertJson(['code' => 'invalid_clinical_note_operation']);
    }

    public function test_addendum_requires_a_body(): void
    {
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->signed()->create();

        $response = $this->actingAs($actor)->postJson("/api/clinical-notes/{$note->id}/addendums", []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['body']);
    }

    public function test_receptionist_cannot_add_an_addendum(): void
    {
        $receptionist = User::factory()->create(['role' => 'receptionist']);
        $note = ClinicalNote::factory()->signed()->create();

        $response = $this->actingAs($receptionist)->postJson("/api/clinical-notes/{$note->id}/addendums", [
            'body' => 'x',
        ]);

        $response->assertForbidden();
    }

    public function test_no_route_exists_to_update_or_delete_a_single_addendum(): void
    {
        $note = ClinicalNote::factory()->signed()->create();
        $addendum = $note->addendums()->create(['author_id' => $note->dentist_id, 'body' => 'x']);
        $actor = User::factory()->admin()->create();

        $this->actingAs($actor)->putJson("/api/clinical-note-addendums/{$addendum->id}", ['body' => 'y'])->assertNotFound();
        $this->actingAs($actor)->deleteJson("/api/clinical-note-addendums/{$addendum->id}")->assertNotFound();
    }

    // ---- destroy ----------------------------------------------------------------------------------

    public function test_admin_can_delete_a_draft_note(): void
    {
        $actor = User::factory()->admin()->create();
        $note = ClinicalNote::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/clinical-notes/{$note->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('clinical_notes', ['id' => $note->id]);
    }

    public function test_admin_can_delete_a_signed_note(): void
    {
        $actor = User::factory()->admin()->create();
        $note = ClinicalNote::factory()->signed()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/clinical-notes/{$note->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('clinical_notes', ['id' => $note->id]);
    }

    public function test_dentist_cannot_delete_a_clinical_note(): void
    {
        $actor = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->create();

        $response = $this->actingAs($actor)->deleteJson("/api/clinical-notes/{$note->id}");

        $response->assertForbidden();
    }
}
