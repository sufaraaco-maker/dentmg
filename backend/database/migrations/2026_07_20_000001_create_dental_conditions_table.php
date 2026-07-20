<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');

            // Cast to App\Enums\DentalConditionCategory ('finding' | 'procedure') at the model layer.
            // Drives the Diagnosis/Procedure tab split in the chart-entry picker UI.
            $table->string('category');

            // Whether an entry referencing this condition requires >=1 tooth surface (e.g. caries,
            // filling) vs. applying to the whole tooth (e.g. missing, extraction, implant).
            $table->boolean('applies_to_surface')->default(false);

            $table->string('default_color', 7);
            $table->string('icon_key')->nullable();

            // Deactivate, don't hard-delete, so historical chart entries keep a valid FK — same
            // pattern and same reasoning as appointment_types.is_active.
            $table->boolean('is_active')->default(true);

            $table->unsignedSmallInteger('sort_order')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_conditions');
    }
};
