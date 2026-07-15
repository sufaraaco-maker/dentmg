<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-compatible schema only — not wired into any Service, Job, or API endpoint yet.
 * Included now (per explicit approval of the Appointments design) so the future Notifications
 * module has a table to query/write to without an Appointments-module migration later.
 * `channel` is a plain string, not a backed enum: the Notifications module hasn't been designed
 * yet, so the final channel set (email/SMS/WhatsApp/...) isn't decided here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();

            $table->string('channel')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
