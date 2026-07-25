<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ISO 4217 code, display-only in V1 — no currency conversion logic anywhere in the
            // system (design doc §6/§14).
            $table->string('currency_code', 3)->default('USD');

            // Percentage (e.g. 5.00 = 5%), used only to prefill a new invoice's tax line — never a
            // live-applied rule (design doc §2/§6).
            $table->decimal('tax_rate', 5, 2)->nullable();

            $table->string('invoice_number_prefix')->default('INV');

            // The running counter InvoiceService locks-and-increments-in-place to reserve each
            // new invoice's sequence_number (design doc §6, requirement 2 — added during Step 2
            // implementation, 2026-07-25, before Step 1 was committed). Deliberately NOT derived
            // from MAX(invoices.sequence_number): that "lock whichever invoice row is currently
            // the max" approach doesn't actually serialize concurrent issue() calls, because the
            // row being locked (some other, already-issued invoice) is never the row being
            // updated (the invoice being issued) — its value never changes, so two concurrent
            // callers can both lock it, both unblock reading the same unchanged value, and both
            // compute the same "next" number. Locking this row and incrementing it in place fixes
            // that: the locked row IS the row being modified, so a second concurrent caller blocks
            // on this exact row and re-reads the already-incremented value once unblocked.
            $table->unsignedInteger('next_invoice_sequence')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
