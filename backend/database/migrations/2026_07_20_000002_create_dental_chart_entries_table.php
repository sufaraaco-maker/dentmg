<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_chart_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('dental_condition_id')->constrained('dental_conditions');

            // App-level check that the referenced user has role `dentist` — same approach used
            // everywhere else roles are checked in this codebase (e.g. appointments.dentist_id).
            // Clinical attribution: which dentist this finding/procedure is attributed to.
            $table->foreignUuid('dentist_id')->constrained('users');

            // System/actor attribution, distinct from dentist_id: which authenticated account
            // performed the data entry (usually the same person, but an admin may chart on a
            // dentist's behalf). Display-only denormalization — audit_logs (via the Auditable
            // trait below) remains the authoritative, complete change history; these two columns
            // only avoid an extra audit_logs query for routine chart-entry display.
            $table->foreignUuid('created_by_id')->constrained('users');
            $table->foreignUuid('updated_by_id')->nullable()->constrained('users');

            // FDI (ISO 3950) two-digit tooth code, validated against App\Support\ToothChart —
            // deliberately not a foreign key: teeth are fixed reference data (52 codes), not a
            // database record set. dentition_type (permanent/primary) is derived from this value
            // at the model layer, never stored, so it can never drift out of sync.
            $table->string('tooth_number', 2);

            // e.g. ["M","O"]. Null/empty when dental_condition.applies_to_surface is false.
            $table->json('surfaces')->nullable();

            // Cast to App\Enums\DentalChartEntryStatus. Lifecycle: existing|active -> planned ->
            // completed|cancelled (see DentalChartEntryStatus::transitionsFrom()).
            $table->string('status');

            $table->text('notes')->nullable();

            $table->timestamp('recorded_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index(['patient_id', 'tooth_number']);
            $table->index(['patient_id', 'status']);
            $table->index('dental_condition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_chart_entries');
    }
};
