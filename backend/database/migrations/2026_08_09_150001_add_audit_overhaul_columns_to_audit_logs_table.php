<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Phase 4 (Advanced Permissions & Audit) Step 3 design doc §2.3/§2.4/§2.7 — additive
            // only, no rename, so every existing consumer of `changes` (the "new values" column)
            // keeps working unchanged.
            $table->json('old_values')->nullable()->after('changes');
            $table->string('ip_address')->nullable()->after('old_values');
            $table->string('user_agent')->nullable()->after('ip_address');

            // Non-model context (e.g. the attempted email on a login_failed row with no
            // resolvable user) — kept separate from `changes`/`old_values` so it's never
            // mistaken for model field data, and goes through the same redaction as both.
            $table->json('context')->nullable()->after('user_agent');
        });

        // auth events (login_failed with an unknown email) have no resolvable target — relax
        // the previously-required auditable_id to allow it.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('auditable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('auditable_id')->nullable(false)->change();
            $table->dropColumn(['old_values', 'ip_address', 'user_agent', 'context']);
        });
    }
};
