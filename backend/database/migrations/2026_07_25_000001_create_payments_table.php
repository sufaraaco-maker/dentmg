<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('patient_id')->constrained('patients');

            // Set at creation (applied) or later via /apply (design doc §3/§8); null = unapplied
            // credit sitting on the patient's account (§4). nullOnDelete mirrors invoice_items'
            // treatment_plan_item_id precedent — a payment record must survive its invoice being
            // deleted (draft-only deletion, but still).
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // Set only on a refund row; points back at the payment it reverses (design doc §4/§6).
            // Self-referencing FK, so no circular-dependency ordering issue within this migration.
            $table->foreignUuid('refunded_payment_id')->nullable()->constrained('payments')->nullOnDelete();

            // Cast to App\Enums\PaymentMethod. Recording only — no processor integration (design doc §2).
            $table->string('method');

            // Positive for an ordinary payment, negative for a refund row (design doc §6) — the
            // only signed stored amount in this codebase's financial tables, because a refund's
            // entire meaning is "money moving the other way" and there's no "kind" enum here to
            // carry the sign instead (unlike invoice_items.unit_amount).
            $table->decimal('amount', 10, 2);

            // Snapshotted from billing_settings at creation, same reasoning as invoices.currency_code
            // (design doc §6) — never assume a single global currency.
            $table->string('currency_code', 3)->default('USD');

            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            // When the payment was actually collected/returned — staff-editable (e.g. backdating),
            // mirrors invoices.issue_date.
            $table->date('received_at');

            $table->foreignUuid('created_by_id')->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_id');
            $table->index('invoice_id');
            $table->index('refunded_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
