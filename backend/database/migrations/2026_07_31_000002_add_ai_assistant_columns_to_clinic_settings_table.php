<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two additive nullable-default-false columns on the existing singleton (design doc §3).
        // Kept separate so an admin can enable the zero-PHI features without also acknowledging
        // the PHI-bearing ones' BAA-with-Anthropic prerequisite (Approval decision 1, 2026-07-31).
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->boolean('ai_assistant_enabled')->default(false);
            $table->boolean('ai_assistant_phi_features_acknowledged')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_assistant_enabled', 'ai_assistant_phi_features_acknowledged']);
        });
    }
};
