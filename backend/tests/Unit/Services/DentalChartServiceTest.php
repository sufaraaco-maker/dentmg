<?php

namespace Tests\Unit\Services;

use App\Enums\DentalChartEntryStatus;
use App\Exceptions\DentalChart\EntryLockedException;
use App\Exceptions\DentalChart\InvalidStatusTransitionException;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use App\Models\Patient;
use App\Models\User;
use App\Services\DentalChartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DentalChartServiceTest extends TestCase
{
    use RefreshDatabase;

    private DentalChartService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DentalChartService;
    }

    public function test_list_for_patient_only_returns_that_patients_entries(): void
    {
        $patient = Patient::factory()->create();
        $other = Patient::factory()->create();

        DentalChartEntry::factory()->create(['patient_id' => $patient->id]);
        DentalChartEntry::factory()->create(['patient_id' => $other->id]);

        $entries = $this->service->listForPatient($patient->id, []);

        $this->assertCount(1, $entries);
        $this->assertSame($patient->id, $entries->first()->patient_id);
    }

    public function test_list_for_patient_filters_by_tooth_number_and_status(): void
    {
        $patient = Patient::factory()->create();

        DentalChartEntry::factory()->create(['patient_id' => $patient->id, 'tooth_number' => '16', 'status' => DentalChartEntryStatus::Active]);
        DentalChartEntry::factory()->create(['patient_id' => $patient->id, 'tooth_number' => '11', 'status' => DentalChartEntryStatus::Planned]);

        $byTooth = $this->service->listForPatient($patient->id, ['tooth_number' => '16']);
        $this->assertCount(1, $byTooth);
        $this->assertSame('16', $byTooth->first()->tooth_number);

        $byStatus = $this->service->listForPatient($patient->id, ['status' => 'planned']);
        $this->assertCount(1, $byStatus);
        $this->assertSame(DentalChartEntryStatus::Planned, $byStatus->first()->status);
    }

    public function test_list_for_patient_filters_by_condition_category(): void
    {
        $patient = Patient::factory()->create();
        $finding = DentalCondition::factory()->finding()->create();
        $procedure = DentalCondition::factory()->procedure()->create();

        DentalChartEntry::factory()->create(['patient_id' => $patient->id, 'dental_condition_id' => $finding->id]);
        DentalChartEntry::factory()->create(['patient_id' => $patient->id, 'dental_condition_id' => $procedure->id]);

        $entries = $this->service->listForPatient($patient->id, ['category' => 'procedure']);

        $this->assertCount(1, $entries);
        $this->assertSame($procedure->id, $entries->first()->dental_condition_id);
    }

    public function test_create_sets_created_by_id_and_defaults_recorded_at(): void
    {
        $patient = Patient::factory()->create();
        $condition = DentalCondition::factory()->create(['applies_to_surface' => false]);
        $dentist = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();

        $entry = $this->service->create([
            'patient_id' => $patient->id,
            'dental_condition_id' => $condition->id,
            'dentist_id' => $dentist->id,
            'tooth_number' => '16',
            'status' => DentalChartEntryStatus::Active->value,
        ], $admin);

        $this->assertSame($admin->id, $entry->created_by_id);
        $this->assertNull($entry->updated_by_id);
        $this->assertNotNull($entry->recorded_at);
    }

    public function test_update_sets_updated_by_id(): void
    {
        $entry = DentalChartEntry::factory()->create(['status' => DentalChartEntryStatus::Active]);
        $editor = User::factory()->dentist()->create();

        $updated = $this->service->update($entry, ['notes' => 'Follow-up needed'], $editor);

        $this->assertSame($editor->id, $updated->updated_by_id);
        $this->assertSame('Follow-up needed', $updated->notes);
    }

    public function test_update_rejects_edits_to_locked_fields_on_a_terminal_entry(): void
    {
        $entry = DentalChartEntry::factory()->completed()->create();
        $editor = User::factory()->dentist()->create();

        $this->expectException(EntryLockedException::class);

        $this->service->update($entry, ['tooth_number' => '11'], $editor);
    }

    public function test_update_still_allows_notes_on_a_terminal_entry(): void
    {
        $entry = DentalChartEntry::factory()->cancelled()->create();
        $editor = User::factory()->dentist()->create();

        $updated = $this->service->update($entry, ['notes' => 'Patient declined treatment'], $editor);

        $this->assertSame('Patient declined treatment', $updated->notes);
    }

    public function test_complete_transitions_a_planned_entry(): void
    {
        $entry = DentalChartEntry::factory()->planned()->create();
        $actor = User::factory()->dentist()->create();

        $completed = $this->service->complete($entry, $actor);

        $this->assertSame(DentalChartEntryStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame($actor->id, $completed->updated_by_id);
    }

    public function test_complete_rejects_a_non_planned_entry(): void
    {
        $entry = DentalChartEntry::factory()->create(['status' => DentalChartEntryStatus::Active]);
        $actor = User::factory()->dentist()->create();

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->complete($entry, $actor);
    }

    public function test_cancel_transitions_a_planned_entry(): void
    {
        $entry = DentalChartEntry::factory()->planned()->create();
        $actor = User::factory()->dentist()->create();

        $cancelled = $this->service->cancel($entry, $actor);

        $this->assertSame(DentalChartEntryStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_cancel_rejects_an_already_terminal_entry(): void
    {
        $entry = DentalChartEntry::factory()->completed()->create();
        $actor = User::factory()->dentist()->create();

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->cancel($entry, $actor);
    }

    public function test_delete_soft_deletes_the_entry(): void
    {
        $entry = DentalChartEntry::factory()->create();

        $this->service->delete($entry);

        $this->assertSoftDeleted('dental_chart_entries', ['id' => $entry->id]);
    }
}
