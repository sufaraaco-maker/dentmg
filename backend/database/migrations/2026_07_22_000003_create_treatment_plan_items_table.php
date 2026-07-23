<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_plan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('treatment_plan_id')->constrained('treatment_plans');

            // The procedure. Kept as a live FK purely for catalog/category linkage and future
            // reporting joins — NEVER used to resolve display name/description/price once the
            // parent plan leaves draft; those always read from the snapshot columns below
            // (design doc §6/§8, Decision 2). Application-level validation enforces
            // category = procedure (no DB-level enum-subset constraint, same precedent as the
            // rest of the codebase).
            $table->foreignUuid('dental_condition_id')->constrained('dental_conditions');

            // Snapshot of dental_conditions.name/description at creation time (or last edit while
            // still draft) — what the patient was actually shown. Frozen, together with
            // unit_cost/quantity, the moment the parent plan leaves draft (design doc §5/§6/§8,
            // Decision 2) — a catalog rename/re-description must never retroactively change an
            // already-presented plan.
            $table->string('procedure_name');
            $table->text('procedure_description')->nullable();

            // Traceability link to the Dental Chart finding this item addresses — read-only
            // reference, never mutated by this module (design doc §7, Decision 1). One-way: Dental
            // Chart has zero knowledge of Treatment Plans.
            $table->foreignUuid('diagnosis_entry_id')->nullable()->constrained('dental_chart_entries')->nullOnDelete();

            // FDI tooth code, validated against App\Support\ToothChart when present — NOT a DB
            // foreign key (same reasoning as dental_chart_entries.tooth_number). Nullable, unlike
            // dental_chart_entries (always required): not every planned procedure is
            // tooth-specific (e.g. full-mouth debridement, orthodontic consult, whitening).
            $table->string('tooth_number', 2)->nullable();

            // Same shape/validation as dental_chart_entries.surfaces; only meaningful when
            // tooth_number is present.
            $table->json('surfaces')->nullable();

            $table->unsignedSmallInteger('quantity')->default(1);

            // Snapshotted from dental_conditions.default_cost at creation (or manually
            // entered/overridden); frozen (read-only) once the parent plan leaves draft.
            $table->decimal('unit_cost', 10, 2);

            // Lightweight CareStack-style grouping (design doc §0/§6) — no phase-name table in V1.
            $table->unsignedSmallInteger('phase')->nullable()->default(1);

            // Manual ordering within a phase.
            $table->unsignedSmallInteger('sequence')->nullable();

            // Cast to App\Enums\TreatmentPlanItemStatus. Lifecycle: planned -> completed/cancelled
            // (design doc §5). Deliberately no stored "scheduled" state — derived from
            // appointment_id being set to a non-cancelled Appointment instead.
            $table->string('status');

            // Set once the patient books this specific item (design doc §3/§7) — one-way
            // reference, no cascade in either direction beyond the plan-level cancel cascade.
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Mirrors dental_chart_entries' create/update actor split.
            $table->foreignUuid('created_by_id')->constrained('users');
            $table->foreignUuid('updated_by_id')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index('treatment_plan_id');
            $table->index(['treatment_plan_id', 'status']);
            $table->index('dental_condition_id');
            $table->index('appointment_id');
            $table->index('diagnosis_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_items');
    }
};
