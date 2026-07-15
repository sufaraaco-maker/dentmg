<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Belt-and-suspenders guarantee against a dentist double-booking race condition: two receptionists
 * submitting overlapping bookings for the same dentist within milliseconds of each other would both
 * pass the application-level overlap check (in AppointmentService, next phase) before either commits.
 * This EXCLUDE constraint makes Postgres itself reject the second write. Postgres-only, guarded by
 * driver check exactly like the pg_trgm trigram-index migration added for Patients search — SQLite
 * (used in tests) has no equivalent and the application-level check alone is sufficient there.
 *
 * Status list mirrors the "active" set from the Appointments design doc §5 rule 3: scheduled,
 * confirmed, checked_in, and in_progress occupy the dentist's time; cancelled, no_show, and
 * completed do not.
 */
return new class extends Migration
{
    private const ACTIVE_STATUSES = ['scheduled', 'confirmed', 'checked_in', 'in_progress'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        $statusList = implode(', ', array_map(fn (string $status) => "'{$status}'", self::ACTIVE_STATUSES));

        DB::statement(<<<SQL
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_no_overlapping_dentist_slots
            EXCLUDE USING gist (
                dentist_id WITH =,
                tsrange(start_at, end_at) WITH &&
            )
            WHERE (status IN ({$statusList}) AND deleted_at IS NULL)
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_no_overlapping_dentist_slots');
    }
};
