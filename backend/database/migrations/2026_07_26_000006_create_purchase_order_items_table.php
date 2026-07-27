<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders');
            $table->foreignUuid('supply_id')->constrained('supplies');

            // Snapshot of the Supply's name/SKU at order time (design doc §6) — same snapshot
            // convention as invoice_items.description/treatment_plan_items.procedure_name, so
            // renaming a Supply later never rewrites order history.
            $table->string('description');

            $table->unsignedInteger('quantity_ordered');

            // Running total, updated only via the Receive action (design doc §8); hard-capped so it
            // can never exceed quantity_ordered (design doc §15 Decision 5).
            $table->unsignedInteger('quantity_received')->default(0);

            // Snapshot at order time — same reasoning as invoice_items.unit_amount.
            $table->decimal('unit_cost', 10, 2);

            $table->timestamps();

            // No soft delete — owned entirely by its parent purchase_order (design doc §6), same as
            // invoice_items being deleted only via cascading its own restricted lifecycle.

            $table->index('purchase_order_id');
            $table->index('supply_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
