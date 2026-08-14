<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI Assistant module removal — drops the 3 columns added by
        // 2026_07_31_000002_add_ai_assistant_columns_to_clinic_settings_table.php and
        // 2026_08_02_000001_add_ai_assistant_api_key_to_clinic_settings_table.php. Never edit
        // those migrations in place; this reverses them going forward instead. The rest of
        // `clinic_settings` (name/phone/address/email/logo) is untouched.
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ai_assistant_enabled',
                'ai_assistant_phi_features_acknowledged',
                'ai_assistant_api_key',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->boolean('ai_assistant_enabled')->default(false);
            $table->boolean('ai_assistant_phi_features_acknowledged')->default(false);
            $table->text('ai_assistant_api_key')->nullable();
        });
    }
};
