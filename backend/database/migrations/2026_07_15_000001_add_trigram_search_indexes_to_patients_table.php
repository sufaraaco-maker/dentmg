<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PatientService searches first_name/last_name/phone/national_id/email with a leading-wildcard
 * LIKE/ILIKE ("%term%"), which a standard B-tree index cannot serve — Postgres would fall back to
 * a sequential scan as the table grows. Trigram (pg_trgm) GIN indexes let the planner use an index
 * for these patterns. Postgres-only: SQLite (used in tests) has no equivalent and doesn't need it
 * at the data volumes the test suite runs at.
 */
return new class extends Migration
{
    private const COLUMNS = ['first_name', 'last_name', 'phone', 'national_id', 'email'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach (self::COLUMNS as $column) {
            DB::statement("CREATE INDEX patients_{$column}_trgm_idx ON patients USING gin ({$column} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $column) {
            DB::statement("DROP INDEX IF EXISTS patients_{$column}_trgm_idx");
        }
    }
};
