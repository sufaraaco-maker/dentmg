<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_images', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // Cast to App\Enums\ImageType. Plain string column (not a native DB enum type) — design
            // doc §15: adding a future case (e.g. a document/model category) is a one-line PHP enum
            // change, never an ALTER TYPE migration, mirroring lab_cases.status's identical choice.
            $table->string('image_type');

            // FDI code, validated against App\Support\ToothChart at the Form Request layer — NOT a DB
            // foreign key, exact convention as dental_chart_entries.tooth_number. Optional (design doc
            // §0 Eaglesoft finding: tagging must never block upload).
            $table->string('tooth_number', 2)->nullable();

            // Subset of M/D/F/L/O/I, exact convention as dental_chart_entries.surfaces.
            $table->json('surfaces')->nullable();

            // When the image was captured, distinct from created_at (upload time) — design doc §2.
            $table->date('taken_at');

            // Traceability only, one-way, never mutated by this module — exact convention as
            // lab_cases.treatment_plan_item_id / lab_cases.appointment_id (design doc §3).
            $table->foreignUuid('treatment_plan_item_id')->nullable()->constrained('treatment_plan_items')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            // Which Storage facade disk the file lives on — stored explicitly per row so a future
            // disk migration (local -> s3) never silently breaks already-uploaded rows (design doc §7
            // decision 5 / Approval Log item 5).
            $table->string('disk');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();

            $table->string('mime_type');
            $table->unsignedInteger('file_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Soft delete — admin-only (design doc §5), images are part of the clinical record.
            $table->softDeletes();

            $table->index('patient_id');
            $table->index('image_type');
            $table->index('tooth_number');
            $table->index('taken_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_images');
    }
};
