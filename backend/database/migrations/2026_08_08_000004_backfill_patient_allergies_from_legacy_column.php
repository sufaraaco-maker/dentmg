<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Best-effort backfill (design doc §7): one `patient_allergies` row per patient whose legacy
     * free-text `patients.allergies` is non-empty, `allergen` set to the raw text as-is — an admin
     * can split it into multiple structured rows manually afterward. `patients.allergies` itself
     * is deprecated by this but deliberately not dropped this phase (see the same section).
     */
    private const BACKFILL_NOTE = 'Migrated automatically from the legacy patients.allergies free-text field.';

    public function up(): void
    {
        DB::table('patients')
            ->whereNotNull('allergies')
            ->where('allergies', '!=', '')
            ->orderBy('id')
            ->select(['id', 'allergies'])
            ->chunkById(200, function ($patients) {
                $now = now();

                $rows = $patients->map(fn ($patient) => [
                    'id' => (string) Str::uuid(),
                    'patient_id' => $patient->id,
                    'allergen' => $patient->allergies,
                    'severity' => null,
                    'reaction' => null,
                    'notes' => self::BACKFILL_NOTE,
                    'created_by_id' => null,
                    'updated_by_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ])->all();

                DB::table('patient_allergies')->insert($rows);
            });
    }

    public function down(): void
    {
        DB::table('patient_allergies')->where('notes', self::BACKFILL_NOTE)->delete();
    }
};
