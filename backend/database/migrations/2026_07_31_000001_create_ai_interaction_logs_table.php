<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit trail (design doc §3) — no `updated_at`, same immutability treatment
        // as `clinical_note_addendums`: what the user asked and what Claude answered is a permanent
        // record from the moment it's written. `accepted` is the one mutable-in-spirit field (an
        // informational log row gets its accept/reject decision recorded after the fact), so it's a
        // plain nullable boolean rather than a second append-only row.
        Schema::create('ai_interaction_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained('users');

            // String-backed enum column, mirrors every other module's convention (e.g. Payment.method).
            $table->string('feature');

            // Set only for the two PHI-bearing, patient-scoped features (§3) — null for Dashboard
            // Insights/Smart Search/report narratives, which never touch one specific patient.
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();

            // The user's own typed input only — never the assembled system prompt or the
            // patient-record content interpolated into it (design doc §5's PHI-minimization rule
            // for this table itself).
            $table->text('prompt_summary');
            $table->text('response_summary');

            // null = informational only (nothing to accept/reject); true/false once a Clinical
            // Notes draft or Treatment Suggestion is reviewed.
            $table->boolean('accepted')->nullable();

            $table->string('model');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index('feature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interaction_logs');
    }
};
