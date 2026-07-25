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
            // Column only here — the foreign key itself is added by a separate, later migration
            // (`2026_07_26_000001_add_refunded_payment_id_foreign_to_payments_table.php`). A
            // same-migration self-referencing FK (whether via `->constrained()` or an explicit
            // `->foreign()` call in this same `Schema::create()`) fails on Postgres here: Laravel's
            // Blueprint defers the `.primary()` column modifier into an `alter table add primary
            // key` statement appended after every explicit index/foreign-key command in the same
            // blueprint (`addFluentIndexes()`, called once at compile time, after the whole closure
            // has already run) — so the self-referencing FK is always compiled *before* this table's
            // own primary key exists yet, and Postgres rejects it ("no unique constraint matching
            // given keys for referenced table"). Confirmed by direct reproduction against a fresh
            // Postgres database (`migrate --pretend`/a real fresh `migrate` both showed this). A
            // separate follow-up migration sidesteps it entirely: by the time it runs, this
            // migration's `CREATE TABLE` (primary key included) is already fully committed.
            $table->uuid('refunded_payment_id')->nullable();

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
