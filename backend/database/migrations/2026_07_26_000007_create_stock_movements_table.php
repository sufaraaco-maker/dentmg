<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('supply_id')->constrained('supplies');

            // Signed: positive = increase, negative = decrease (design doc §4/§6). A Supply's
            // quantity_on_hand is always SUM(quantity_delta) for that supply — never stored, never
            // drifts (design doc §0's deliberate deviation from Open Dental's mutable on-hand field).
            $table->integer('quantity_delta');

            // Cast to App\Enums\StockMovementReason.
            $table->string('reason');

            // Set only for reason = received; traces back to the Purchase Order line that generated
            // it (design doc §6/§8). nullOnDelete: a historical ledger row must survive its
            // originating (draft-only-deletable) Purchase Order being deleted.
            $table->foreignUuid('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();

            // Lightweight, optional expiration tracking (design doc §6a, §15 Decision 2) — only
            // meaningful on an incoming (received/initial_stock) row. Deliberately a plain column on
            // this ledger row, not a separate stock_lots table: see §6a for the additive path a
            // future V2 lot/batch layer could take without a breaking migration.
            $table->date('expiration_date')->nullable();

            $table->text('notes')->nullable();

            $table->foreignUuid('performed_by_id')->constrained('users');

            // Staff-editable (e.g. backdating a receipt), mirrors payments.received_at.
            $table->timestamp('occurred_at');

            $table->timestamps();

            // No soft delete — immutable, append-only ledger (design doc §5/§8). A correction is
            // always a new offsetting row, never an edit or delete of an existing one.

            $table->index('supply_id');
            $table->index('purchase_order_item_id');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
