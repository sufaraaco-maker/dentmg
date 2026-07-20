<?php

namespace Tests\Unit\Models;

use App\Enums\DentalChartEntryStatus;
use App\Models\DentalChartEntry;
use App\Models\DentalCondition;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DentalChartEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_patient_dental_condition_dentist_created_by_and_updated_by(): void
    {
        $patient = Patient::factory()->create();
        $condition = DentalCondition::factory()->create();
        $dentist = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();

        $entry = DentalChartEntry::factory()->create([
            'patient_id' => $patient->id,
            'dental_condition_id' => $condition->id,
            'dentist_id' => $dentist->id,
            'created_by_id' => $dentist->id,
            'updated_by_id' => $admin->id,
        ]);

        $this->assertTrue($entry->patient->is($patient));
        $this->assertTrue($entry->dentalCondition->is($condition));
        $this->assertTrue($entry->dentist->is($dentist));
        $this->assertTrue($entry->createdBy->is($dentist));
        $this->assertTrue($entry->updatedBy->is($admin));
    }

    public function test_updated_by_is_nullable(): void
    {
        $entry = DentalChartEntry::factory()->create(['updated_by_id' => null]);

        $this->assertNull($entry->updated_by_id);
        $this->assertNull($entry->updatedBy);
    }

    public function test_patient_has_many_dental_chart_entries(): void
    {
        $patient = Patient::factory()->create();
        DentalChartEntry::factory()->count(3)->create(['patient_id' => $patient->id]);

        $this->assertCount(3, $patient->dentalChartEntries);
    }

    public function test_surfaces_is_cast_to_array(): void
    {
        $entry = DentalChartEntry::factory()->withSurfaces(['M', 'O'])->create();

        $this->assertIsArray($entry->surfaces);
        $this->assertSame(['M', 'O'], $entry->fresh()->surfaces);
    }

    public function test_status_is_cast_to_the_backed_enum(): void
    {
        $entry = DentalChartEntry::factory()->planned()->create();

        $this->assertInstanceOf(DentalChartEntryStatus::class, $entry->status);
        $this->assertSame(DentalChartEntryStatus::Planned, $entry->fresh()->status);
    }

    public function test_recorded_completed_and_cancelled_at_are_cast_to_datetime(): void
    {
        $entry = DentalChartEntry::factory()->completed()->create();

        $this->assertInstanceOf(Carbon::class, $entry->recorded_at);
        $this->assertInstanceOf(Carbon::class, $entry->completed_at);
    }

    public function test_dentition_type_is_derived_from_tooth_number_and_never_stored(): void
    {
        $permanent = DentalChartEntry::factory()->create(['tooth_number' => '16']);
        $primary = DentalChartEntry::factory()->create(['tooth_number' => '55']);

        $this->assertSame('permanent', $permanent->dentition_type);
        $this->assertSame('primary', $primary->dentition_type);

        // Not a real column — confirmed absent from the raw database attributes.
        $this->assertArrayNotHasKey('dentition_type', $permanent->getAttributes());
    }

    public function test_scope_for_patient_for_tooth_and_with_status(): void
    {
        $patientA = Patient::factory()->create();
        $patientB = Patient::factory()->create();

        DentalChartEntry::factory()->create(['patient_id' => $patientA->id, 'tooth_number' => '16', 'status' => DentalChartEntryStatus::Active]);
        DentalChartEntry::factory()->create(['patient_id' => $patientA->id, 'tooth_number' => '17', 'status' => DentalChartEntryStatus::Planned]);
        DentalChartEntry::factory()->create(['patient_id' => $patientB->id, 'tooth_number' => '16', 'status' => DentalChartEntryStatus::Active]);

        $this->assertCount(2, DentalChartEntry::forPatient($patientA->id)->get());
        $this->assertCount(2, DentalChartEntry::forTooth('16')->get());
        $this->assertCount(2, DentalChartEntry::withStatus(DentalChartEntryStatus::Active)->get());
        $this->assertCount(1, DentalChartEntry::forPatient($patientA->id)->forTooth('16')->get());
    }

    public function test_soft_delete_does_not_remove_the_row(): void
    {
        $entry = DentalChartEntry::factory()->create();

        $entry->delete();

        $this->assertSoftDeleted('dental_chart_entries', ['id' => $entry->id]);
    }

    public function test_uses_auditable_and_records_creation_in_audit_logs(): void
    {
        $actor = User::factory()->dentist()->create();
        $this->actingAs($actor);

        $entry = DentalChartEntry::factory()->create(['created_by_id' => $actor->id]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => DentalChartEntry::class,
            'auditable_id' => $entry->id,
            'action' => 'created',
            'user_id' => $actor->id,
        ]);
    }
}
