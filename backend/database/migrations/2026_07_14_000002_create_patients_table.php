<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Internal counter used to derive the human-readable patient_code (e.g. P-00001).
            // Kept separate from the UUID primary key so front-desk staff have something short to reference.
            $table->unsignedBigInteger('sequence_number')->unique();

            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('gender');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('national_id')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('blood_type')->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_history')->nullable();
            $table->string('insurance_provider')->nullable();
            $table->string('insurance_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['first_name', 'last_name']);
            $table->index('phone');
            $table->unique('national_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
