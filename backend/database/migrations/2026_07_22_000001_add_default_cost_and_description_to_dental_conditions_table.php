<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dental_conditions', function (Blueprint $table) {
            // Prefills a new treatment_plan_items.unit_cost (editable per item, then frozen once
            // the parent plan leaves draft — see docs/modules/treatment-plans-design.md §6/§8).
            // Nullable: not every clinic prices every procedure, and null cleanly means "no
            // default — dentist must enter one" (mirrors Open Dental's ExistCurProv/ExistOther
            // "no fee" precedent, design doc §0).
            $table->decimal('default_cost', 10, 2)->nullable()->after('icon_key');

            // Snapshotted onto treatment_plan_items.procedure_description at item-creation time
            // (design doc §6) — also the source text for a future printable treatment proposal
            // (design doc §23). Nullable: existing seeded rows have none until backfilled.
            $table->text('description')->nullable()->after('default_cost');
        });
    }

    public function down(): void
    {
        Schema::table('dental_conditions', function (Blueprint $table) {
            $table->dropColumn(['default_cost', 'description']);
        });
    }
};
