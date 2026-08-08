<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientAllergy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `RefreshDatabase` runs every migration (including the backfill itself, design doc §7) against an
 * empty `patients` table, so it's a no-op by the time any test creates data. This test re-runs the
 * migration's own `up()`/`down()` directly, after seeding legacy `patients.allergies` text, to
 * exercise the actual backfill logic.
 */
class BackfillPatientAllergiesTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_08_000004_backfill_patient_allergies_from_legacy_column.php');
    }

    public function test_backfill_creates_one_allergy_row_per_patient_with_legacy_text(): void
    {
        $withAllergies = Patient::factory()->create(['allergies' => 'Penicillin, Latex']);
        $withoutAllergies = Patient::factory()->create(['allergies' => null]);
        $withEmptyAllergies = Patient::factory()->create(['allergies' => '']);

        $this->migration()->up();

        $this->assertDatabaseHas('patient_allergies', [
            'patient_id' => $withAllergies->id,
            'allergen' => 'Penicillin, Latex',
        ]);
        $this->assertSame(0, PatientAllergy::query()->where('patient_id', $withoutAllergies->id)->count());
        $this->assertSame(0, PatientAllergy::query()->where('patient_id', $withEmptyAllergies->id)->count());
        $this->assertSame(1, PatientAllergy::count());
    }

    public function test_backfilled_rows_are_marked_as_migrated_and_have_no_severity_or_reaction(): void
    {
        Patient::factory()->create(['allergies' => 'Sulfa drugs']);

        $this->migration()->up();

        $allergy = PatientAllergy::first();

        $this->assertNull($allergy->severity);
        $this->assertNull($allergy->reaction);
        $this->assertNull($allergy->created_by_id);
        $this->assertStringContainsString('Migrated automatically', $allergy->notes);
    }

    public function test_down_removes_only_the_backfilled_rows(): void
    {
        $patient = Patient::factory()->create(['allergies' => 'Penicillin']);
        $manualEntry = PatientAllergy::factory()->create([
            'patient_id' => $patient->id,
            'notes' => 'Entered manually by a dentist',
        ]);

        $this->migration()->up();
        $this->assertSame(2, PatientAllergy::count());

        $this->migration()->down();

        $this->assertSame(1, PatientAllergy::count());
        $this->assertDatabaseHas('patient_allergies', ['id' => $manualEntry->id]);
    }
}
