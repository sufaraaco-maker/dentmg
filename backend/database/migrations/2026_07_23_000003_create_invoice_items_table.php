<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('invoice_id')->constrained('invoices');

            // Traceability only — NEVER used to resolve description/amount once written (design doc
            // §7, mirrors treatment_plan_items.dental_condition_id's identical "live FK, never the
            // display source" precedent). Null for manual/direct charges and always for
            // discount/tax-kind items (Form Request validation, Step 2). No reverse column exists on
            // treatment_plan_items and no uniqueness constraint here — "already invoiced" is answered
            // by querying this table, not stored anywhere (design doc §7 Decision Log item 6).
            $table->foreignUuid('treatment_plan_item_id')->nullable()->constrained('treatment_plan_items')->nullOnDelete();

            // Cast to App\Enums\InvoiceItemKind (charge, discount, tax). Determines the item's sign
            // in the invoice total formula — unit_amount itself is always stored positive (design
            // doc §6), avoiding double-negative bugs.
            $table->string('kind');

            // Snapshot — from treatment_plan_item.procedure_name when sourced, freeform when
            // manual, or a fixed label ("Tax"/"Discount") for tax/discount lines. Frozen the moment
            // the parent invoice leaves draft (design doc §8, Decision Log item 2) — never re-read
            // from dental_conditions/treatment_plan_items once written.
            $table->string('description');

            $table->unsignedSmallInteger('quantity')->default(1);

            $table->decimal('unit_amount', 10, 2);

            $table->unsignedSmallInteger('sequence')->nullable();

            $table->text('notes')->nullable();

            $table->foreignUuid('created_by_id')->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index('invoice_id');
            $table->index('treatment_plan_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
