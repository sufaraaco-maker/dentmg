<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedSmallInteger('default_duration_minutes');
            $table->string('color');

            // Soft-disable so historical appointments keep a valid FK instead of a hard delete.
            // No soft delete on this table — it's a lookup table, the exception to the project's
            // default soft-delete rule (see docs/database-design.md).
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_types');
    }
};
