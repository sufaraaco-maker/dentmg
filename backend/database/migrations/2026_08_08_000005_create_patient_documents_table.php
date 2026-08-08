<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // Cast to App\Enums\DocumentCategory. Plain string column (not a native DB enum type) —
            // same convention as patient_images.image_type / lab_cases.status: adding a future case
            // is a one-line PHP enum change, never an ALTER TYPE migration (design doc §5).
            $table->string('category');

            $table->string('title');
            $table->string('original_filename');

            // Which Storage facade disk the file lives on — stored explicitly per row, exact
            // convention as patient_images.disk (design doc §6 / §16 decision 5).
            $table->string('disk');
            $table->string('path');

            $table->string('mime_type');
            $table->unsignedInteger('file_size');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Soft delete — admin-only (design doc §9), documents are part of the clinical/
            // administrative record, same rationale as patient_images.
            $table->softDeletes();

            $table->index('patient_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_documents');
    }
};
