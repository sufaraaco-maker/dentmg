<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            // Powers LabCaseService::send()'s due_at auto-suggestion (design doc §3/§4) — nullable,
            // not every lab publishes a single turnaround time.
            $table->unsignedSmallInteger('default_turnaround_days')->nullable();

            $table->text('notes')->nullable();

            // Soft-disable, not a delete, so historical Lab Cases keep a valid FK — same pattern and
            // reasoning as suppliers.is_active/appointment_types.is_active (design doc §3a).
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labs');
    }
};
