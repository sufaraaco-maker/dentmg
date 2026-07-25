<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('patient_id')->constrained('patients');
            $table->foreignUuid('created_by_id')->constrained('users');

            // Assigned exactly once, at the draft -> issued transition (design doc §6/§8) — never
            // set at draft creation, so an abandoned draft never burns a real invoice number.
            // Nullable-safe unique index (Postgres and SQLite both allow multiple NULLs under a
            // unique index). The raw numeric counter, used for InvoiceService's row-locked
            // generation and for ordering — the human-readable display value is invoice_number
            // below, not this column formatted on the fly.
            $table->unsignedInteger('sequence_number')->nullable()->unique();

            // Frozen snapshot of the fully-formatted invoice number (prefix + zero-padded
            // sequence_number), assigned exactly once at the draft -> issued transition, alongside
            // sequence_number — fully independent of the uuid primary key (design doc Decision Log
            // item 3). Stored as a real column rather than computed live from
            // billing_settings.invoice_number_prefix on every read: that setting is mutable and
            // admin-editable, so a live lookup would let a later prefix change retroactively alter
            // every already-issued invoice's displayed number — violating this module's "no future
            // changes to billing settings affect an issued invoice" rule just as surely as editing
            // a line item would. Caught and fixed during Step 2 implementation (2026-07-25), before
            // Step 1 was committed — see design doc §6.
            $table->string('invoice_number')->nullable()->unique();

            // Snapshot of billing_settings.currency_code at the moment the invoice is *created*
            // (not issued — currency context applies from the first item entered, not just once
            // finalized). Same "don't let a later settings change retroactively affect an existing
            // invoice" reasoning as invoice_number above. Always set, unlike sequence_number/
            // invoice_number (which stay null through draft) — a draft still has a currency
            // context even before issuance.
            $table->string('currency_code', 3)->default('USD');

            // Cast to App\Enums\InvoiceStatus. Lifecycle: draft -> issued -> void (design doc §5/§8
            // — void is reachable only from issued, never from draft; an abandoned draft is deleted
            // instead of voided).
            $table->string('status');

            $table->text('notes')->nullable();

            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
