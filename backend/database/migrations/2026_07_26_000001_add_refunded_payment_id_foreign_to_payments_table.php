<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split out from `2026_07_25_000001_create_payments_table.php` (see that file's comment on
 * `refunded_payment_id`): a self-referencing foreign key declared in the same `Schema::create()`
 * as its own table's `.primary()` column fails on Postgres, because Laravel's Blueprint always
 * appends the primary-key command after every explicit index/foreign-key command in that same
 * blueprint — so the FK is compiled before the table's own primary key exists. Adding it here, in
 * its own migration against the already-fully-created `payments` table, avoids that ordering
 * entirely. No data migration needed: this table has no rows yet in any environment that has run
 * `2026_07_25_000001` (the self-referencing FK's absence there means that migration never
 * successfully completed against Postgres before this fix).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('refunded_payment_id')->references('id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['refunded_payment_id']);
        });
    }
};
