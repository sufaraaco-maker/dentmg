<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Immutable after creation, mirrors every prior module's own "the parent/party of a
            // financial-adjacent record never changes" rule.
            $table->foreignUuid('supplier_id')->constrained('suppliers');

            // Human-readable sequential numbering (e.g. PO-000001), mirrors patients.patient_code /
            // invoices.invoice_number — assigned via PurchaseOrderService's own
            // lock-highest-row-and-increment helper (design doc, mirrors PatientService::create()'s
            // identical pattern; this is an operational, not a legal/financial, sequence, so the
            // heavier InvoiceService::reserveNextSequenceNumber()-style dedicated settings row isn't
            // warranted here).
            $table->unsignedInteger('sequence_number');
            $table->string('order_number')->unique();

            // Cast to App\Enums\PurchaseOrderStatus (design doc §5).
            $table->string('status')->default('draft');

            $table->text('notes')->nullable();

            // Null while draft; set the moment the order is placed (design doc §5).
            $table->date('ordered_at')->nullable();
            $table->date('expected_at')->nullable();

            $table->foreignUuid('created_by_id')->constrained('users');

            $table->timestamps();

            // Soft delete — genuine data-entry-error correction only, admin-only, draft-only (design
            // doc §6/§8), mirrors invoices.deleted_at.
            $table->softDeletes();

            $table->index('supplier_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
