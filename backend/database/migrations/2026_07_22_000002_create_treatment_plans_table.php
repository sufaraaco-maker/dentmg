<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('patient_id')->constrained('patients');

            // The responsible/treating dentist for this plan — app-level role check, not a DB
            // constraint, same approach as dental_chart_entries.dentist_id.
            $table->foreignUuid('dentist_id')->constrained('users');

            // Who authored the plan (may differ from dentist_id — an admin may draft on a
            // dentist's behalf), mirroring dental_chart_entries' dentist_id/created_by_id split.
            $table->foreignUuid('created_by_id')->constrained('users');

            // e.g. "Option A — Implant" — useful once multiple concurrent plans exist for one
            // patient (design doc §0/§3). Optional: not required for a single straightforward plan.
            $table->string('title')->nullable();

            // Cast to App\Enums\TreatmentPlanStatus. Lifecycle: draft -> presented ->
            // accepted/rejected -> in_progress -> completed, plus cancelled from any non-terminal
            // status (design doc §5).
            $table->string('status');

            $table->text('notes')->nullable();

            $table->timestamp('presented_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Set when a replacement plan is created for this one (design doc §15 Q4) — models
            // "revision" as plan-level lineage instead of a separate versioning table. The FK
            // constraint itself is added below, in a separate Schema::table() call — Postgres
            // rejects a self-referencing foreign key declared inline in the same Schema::create()
            // statement ("no unique constraint matching given keys for referenced table
            // treatment_plans", confirmed by trying it directly against the real dev database)
            // because the table's own primary key isn't visible yet at that point.
            $table->uuid('superseded_by_plan_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index(['patient_id', 'status']);
            $table->index('dentist_id');
        });

        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->foreign('superseded_by_plan_id')->references('id')->on('treatment_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plans');
    }
};
