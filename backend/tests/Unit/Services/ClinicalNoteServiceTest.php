<?php

namespace Tests\Unit\Services;

use App\Enums\ClinicalNoteStatus;
use App\Exceptions\ClinicalNote\ClinicalNoteLockedException;
use App\Exceptions\ClinicalNote\InvalidClinicalNoteOperationException;
use App\Models\ClinicalNote;
use App\Models\ClinicalNoteAddendum;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicalNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalNoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClinicalNoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ClinicalNoteService;
    }

    // --- create -----------------------------------------------------------------------------------

    public function test_create_starts_a_note_as_draft(): void
    {
        $patient = Patient::factory()->create();
        $dentist = User::factory()->dentist()->create();
        $actor = User::factory()->admin()->create();

        $note = $this->service->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'note_type' => 'progress',
            'subjective' => 'Patient reports mild pain.',
        ], $actor);

        $this->assertSame(ClinicalNoteStatus::Draft, $note->status);
        $this->assertSame($actor->id, $note->created_by_id);
        $this->assertNull($note->signed_at);
    }

    // --- update -----------------------------------------------------------------------------------

    public function test_update_edits_content_fields_while_draft(): void
    {
        $note = ClinicalNote::factory()->create(['subjective' => 'Old']);
        $actor = User::factory()->admin()->create();

        $updated = $this->service->update($note, ['subjective' => 'New'], $actor);

        $this->assertSame('New', $updated->subjective);
        $this->assertSame($actor->id, $updated->updated_by_id);
    }

    public function test_update_ignores_dentist_id_and_patient_id(): void
    {
        $originalDentist = User::factory()->dentist()->create();
        $note = ClinicalNote::factory()->create(['dentist_id' => $originalDentist->id]);
        $actor = User::factory()->admin()->create();
        $otherDentist = User::factory()->dentist()->create();
        $otherPatient = Patient::factory()->create();

        $updated = $this->service->update($note, [
            'dentist_id' => $otherDentist->id,
            'patient_id' => $otherPatient->id,
        ], $actor);

        $this->assertSame($originalDentist->id, $updated->dentist_id);
        $this->assertSame($note->patient_id, $updated->patient_id);
    }

    public function test_update_throws_locked_exception_once_signed(): void
    {
        $note = ClinicalNote::factory()->signed()->create();
        $actor = User::factory()->admin()->create();

        $this->expectException(ClinicalNoteLockedException::class);

        $this->service->update($note, ['subjective' => 'changed'], $actor);
    }

    // --- sign -------------------------------------------------------------------------------------

    public function test_sign_sets_status_signed_at_and_signed_by(): void
    {
        $note = ClinicalNote::factory()->create(['subjective' => 'Patient reports mild pain.']);
        $actor = User::factory()->admin()->create();

        $signed = $this->service->sign($note, $actor);

        $this->assertSame(ClinicalNoteStatus::Signed, $signed->status);
        $this->assertNotNull($signed->signed_at);
        $this->assertSame($actor->id, $signed->signed_by_id);
    }

    public function test_sign_rejects_a_completely_blank_note(): void
    {
        $note = ClinicalNote::factory()->create([
            'chief_complaint' => null,
            'subjective' => null,
            'objective' => null,
            'assessment' => null,
            'plan' => null,
        ]);
        $actor = User::factory()->admin()->create();

        $this->expectException(InvalidClinicalNoteOperationException::class);

        $this->service->sign($note, $actor);
    }

    public function test_sign_is_idempotent_when_called_twice(): void
    {
        $note = ClinicalNote::factory()->create(['subjective' => 'Patient reports mild pain.']);
        $actor = User::factory()->admin()->create();

        $first = $this->service->sign($note, $actor);
        $secondActor = User::factory()->admin()->create();
        $second = $this->service->sign($note->fresh(), $secondActor);

        $this->assertTrue($first->signed_at->equalTo($second->signed_at));
        $this->assertSame($first->signed_by_id, $second->signed_by_id);
        $this->assertNotSame($secondActor->id, $second->signed_by_id);
    }

    // --- addAddendum --------------------------------------------------------------------------------

    public function test_add_addendum_creates_a_row_linked_to_the_note(): void
    {
        $note = ClinicalNote::factory()->signed()->create();
        $actor = User::factory()->dentist()->create();

        $addendum = $this->service->addAddendum($note, 'Follow-up call: pain resolved.', $actor);

        $this->assertSame($note->id, $addendum->clinical_note_id);
        $this->assertSame($actor->id, $addendum->author_id);
        $this->assertSame('Follow-up call: pain resolved.', $addendum->body);
    }

    public function test_add_addendum_rejects_a_draft_note(): void
    {
        $note = ClinicalNote::factory()->create();
        $actor = User::factory()->dentist()->create();

        $this->expectException(InvalidClinicalNoteOperationException::class);

        $this->service->addAddendum($note, 'too early', $actor);
    }

    public function test_add_addendum_never_touches_the_parent_note(): void
    {
        $note = ClinicalNote::factory()->signed()->create();
        $beforeUpdatedAt = $note->fresh()->updated_at;
        $actor = User::factory()->dentist()->create();

        sleep(1);
        $this->service->addAddendum($note, 'Follow-up.', $actor);

        $this->assertTrue($beforeUpdatedAt->equalTo($note->fresh()->updated_at));
    }

    public function test_addendums_accumulate_in_chronological_order(): void
    {
        $note = ClinicalNote::factory()->signed()->create();
        $actor = User::factory()->dentist()->create();

        $this->service->addAddendum($note, 'First.', $actor);
        $this->service->addAddendum($note, 'Second.', $actor);

        $this->assertSame(['First.', 'Second.'], $note->addendums()->pluck('body')->all());
    }

    // --- delete -----------------------------------------------------------------------------------

    public function test_delete_soft_deletes_a_draft_note(): void
    {
        $note = ClinicalNote::factory()->create();

        $this->service->delete($note);

        $this->assertSoftDeleted('clinical_notes', ['id' => $note->id]);
    }

    public function test_delete_soft_deletes_a_signed_note_too(): void
    {
        $note = ClinicalNote::factory()->signed()->create();

        $this->service->delete($note);

        $this->assertSoftDeleted('clinical_notes', ['id' => $note->id]);
    }

    // --- Audit ------------------------------------------------------------------------------------

    public function test_create_records_an_audit_log_entry(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $patient = Patient::factory()->create();
        $dentist = User::factory()->dentist()->create();

        $note = $this->service->create([
            'patient_id' => $patient->id,
            'dentist_id' => $dentist->id,
            'note_type' => 'progress',
        ], $actor);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ClinicalNote::class,
            'auditable_id' => $note->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }

    public function test_add_addendum_records_its_own_audit_log_entry(): void
    {
        $actor = User::factory()->dentist()->create();
        $this->actingAs($actor);
        $note = ClinicalNote::factory()->signed()->create();

        $addendum = $this->service->addAddendum($note, 'Follow-up.', $actor);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ClinicalNoteAddendum::class,
            'auditable_id' => $addendum->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }
}
