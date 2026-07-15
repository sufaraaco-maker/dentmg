<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('patient_id')->constrained('patients');

            // App-level check that the referenced user has role `dentist` — not a DB constraint,
            // same approach used everywhere else roles are checked in this codebase.
            $table->foreignUuid('dentist_id')->constrained('users');

            $table->foreignUuid('appointment_type_id')->constrained('appointment_types');

            $table->timestamp('start_at');

            // Always server-computed as start_at + duration_minutes — never set directly by the
            // client. Stored (not derived at query time) so overlap queries and the Postgres
            // EXCLUDE constraint (see the following migration) can use it directly.
            $table->timestamp('end_at');
            $table->unsignedSmallInteger('duration_minutes');

            // Cast to App\Enums\AppointmentStatus at the model layer (next phase).
            $table->string('status')->default('scheduled');

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            // Purpose-built reporting columns (wait time, visit duration, no-show rate) —
            // deliberately duplicate what the generic Auditable/AuditLog trail already captures,
            // since that trail is JSON and not efficient for KPI queries. See design doc §4.
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('no_show_at')->nullable();

            $table->unsignedInteger('reschedule_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dentist_id', 'start_at']);
            $table->index(['patient_id', 'start_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
