<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('patient_id')->constrained('patients');

            // Not every note is visit-scoped (e.g. a phone consult, a referral letter copy) —
            // design doc §2/§6.
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            // App-level check that the referenced user has role `dentist` — same approach used
            // everywhere else roles are checked in this codebase (e.g. dental_chart_entries.dentist_id).
            // Clinical attribution: which dentist authored this note.
            $table->foreignUuid('dentist_id')->constrained('users');

            // Cast to App\Enums\ClinicalNoteType at the model layer.
            $table->string('note_type');

            $table->text('chief_complaint')->nullable();
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();

            // Cast to App\Enums\ClinicalNoteStatus. Lifecycle: draft -> signed (terminal) — design
            // doc §5. No `cancelled` state: a mistaken draft is soft-deleted instead.
            $table->string('status')->default('draft');

            $table->timestamp('signed_at')->nullable();

            // Who actually signed — recorded independently of dentist_id (design doc §12) so a
            // future re-authentication/e-signature step can be inserted without a schema change.
            // No nullOnDelete: users are soft-deleted, not hard-deleted, same precedent as
            // created_by_id/updated_by_id below.
            $table->foreignUuid('signed_by_id')->nullable()->constrained('users');

            // System/actor attribution, distinct from dentist_id: which authenticated account
            // performed the data entry (usually the same person, but an admin may author on a
            // dentist's behalf) — mirrors dental_chart_entries'/treatment_plan_items' identical split.
            $table->foreignUuid('created_by_id')->constrained('users');
            $table->foreignUuid('updated_by_id')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'created_at']);
            $table->index(['patient_id', 'status']);
            $table->index('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_notes');
    }
};
