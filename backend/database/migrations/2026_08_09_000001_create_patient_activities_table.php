<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();

            // Plain string columns (not native DB enum types) — design doc §6: adding a future
            // event type or category is a one-line PHP const change, never an ALTER TYPE migration,
            // matching every other status-string column in this codebase.
            $table->string('event_type');
            $table->string('category');

            // Polymorphic — the underlying Appointment/TreatmentPlan/ClinicalNote/Invoice/Payment/
            // PatientAllergy/PatientMedicalCondition/PatientMedication/LabCase/PatientImage/
            // PatientDocument row this activity describes.
            $table->uuidMorphs('subject');

            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();

            // Precomputed human-readable text, produced at dispatch time by the code that knows
            // the subject's shape — Timeline never joins back to the subject table to render a
            // list (design doc §4/§6).
            $table->string('summary');

            $table->json('metadata')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('created_at');

            // No updated_at/deleted_at — activity rows are immutable, append-only facts, never
            // edited or soft-deleted (design doc §6).

            $table->index(['patient_id', 'occurred_at']);
            $table->index(['patient_id', 'category', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_activities');
    }
};
