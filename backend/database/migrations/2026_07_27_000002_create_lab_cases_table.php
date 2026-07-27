<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Human-readable sequential numbering (e.g. LC-000001), mirrors
            // purchase_orders.order_number — assigned via LabCaseService's own
            // lock-highest-row-and-increment helper (design doc §3a).
            $table->unsignedInteger('sequence_number');
            $table->string('case_number')->unique();

            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('lab_id')->constrained('labs');

            // The responsible provider (design doc §3, mirrors Open Dental's "Provider" field).
            // Nullable — not every case has a single named dentist at creation time.
            $table->foreignUuid('dentist_id')->nullable()->constrained('users')->nullOnDelete();

            // Traceability only, one-way, never mutated by this module — exact convention as
            // treatment_plan_items.diagnosis_entry_id (design doc §3).
            $table->foreignUuid('treatment_plan_item_id')->nullable()->constrained('treatment_plan_items')->nullOnDelete();

            // Traceability only, one-way — the intended delivery/seat appointment, if scheduled at
            // case-creation time (design doc §3).
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            // Array of FDI tooth codes, validated against App\Support\ToothChart at the Form Request
            // layer — NOT a DB foreign key (same reasoning as treatment_plan_items.tooth_number).
            // Plural/array (unlike Dental Chart's/Treatment Plan's single tooth_number): a single lab
            // case commonly spans multiple teeth (e.g. a 3-unit bridge) — design doc §7 decision 7.
            $table->json('tooth_numbers')->nullable();

            // Free text in V1, not a catalog table or backed enum — design doc §7 decision 3 /
            // Approval Log item 3.
            $table->string('case_type')->nullable();

            $table->string('shade')->nullable();
            $table->string('material')->nullable();
            $table->text('instructions')->nullable();

            // Tracking-only (design doc §2/§8) — never read by Billing/Payments, never becomes an
            // InvoiceItem.
            $table->decimal('fee', 10, 2)->nullable();

            $table->string('tracking_number')->nullable();

            // Cast to App\Enums\LabCaseStatus (design doc §4).
            $table->string('status')->default('draft');

            // Set by LabCaseService on each transition, never by the controller directly (design doc
            // §4, mirrors PurchaseOrderService's identical transition-timestamp discipline).
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('quality_checked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Soft delete — admin-only, draft-only correction, mirrors purchase_orders.deleted_at.
            $table->softDeletes();

            $table->index('patient_id');
            $table->index('lab_id');
            $table->index('status');
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_cases');
    }
};
