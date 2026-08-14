<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI Assistant module removal — drops the append-only interaction-log table created by
        // 2026_07_31_000001_create_ai_interaction_logs_table.php. Never edit that migration in
        // place; this reverses it going forward instead.
        Schema::dropIfExists('ai_interaction_logs');
    }

    public function down(): void
    {
        Schema::create('ai_interaction_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained('users');
            $table->string('feature');
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->text('prompt_summary');
            $table->text('response_summary');
            $table->boolean('accepted')->nullable();
            $table->string('model');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['patient_id', 'created_at']);
            $table->index('feature');
        });
    }
};
