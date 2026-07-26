<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('category_id')->constrained('supply_categories');

            // Pre-fills a new Purchase Order item's supplier (design doc §6) — nullable, a Supply
            // isn't required to have a default vendor picked yet.
            $table->foreignUuid('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->string('name');
            $table->string('sku')->nullable();

            // Free text ("box", "each", "pack", ...) — too varied across dental supplies for a
            // fixed enum (design doc §6).
            $table->string('unit_of_measure');

            // Default/last-known cost; snapshotted onto purchase_order_items.unit_cost at order
            // time (design doc §6), same convention as dental_conditions.default_cost ->
            // treatment_plan_items.unit_cost. Nullable: not every supply has a known cost yet.
            $table->decimal('unit_cost', 10, 2)->nullable();

            // The reorder threshold (design doc §4 — Open Dental's "Stock Level", Curve's "par
            // level"). A Supply at or below this is "low stock" — always computed at read time
            // against the live stock_movements ledger, never a stored flag.
            $table->integer('reorder_level')->default(0);

            // Default quantity to pre-fill when adding this Supply to a new Purchase Order item.
            $table->integer('reorder_quantity')->nullable();

            // Soft-disable, not a delete — same reasoning as suppliers.is_active (design doc §5).
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('category_id');
            $table->index('default_supplier_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplies');
    }
};
