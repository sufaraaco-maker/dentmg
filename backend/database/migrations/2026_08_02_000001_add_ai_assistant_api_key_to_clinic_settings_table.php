<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable, encrypted-at-rest (`ClinicSetting`'s `encrypted` cast) — lets an admin manage
        // the Anthropic API key from Settings instead of only `.env`'s `ANTHROPIC_API_KEY`
        // (docs/modules/ai-assistant-settings-api-key-design.md §4). `text`, not `string`, since an
        // encrypted value is a much longer opaque payload than the plain key itself.
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->text('ai_assistant_api_key')->nullable()->after('ai_assistant_phi_features_acknowledged');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn('ai_assistant_api_key');
        });
    }
};
