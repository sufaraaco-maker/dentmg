<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Same `disk`/`path` pair and same reasoning as clinic_settings.logo_disk/logo_path
        // (2026_08_13_000001) — always the `public` disk, rendered as a plain <img> (header avatar,
        // Users list), never the auth-gated streaming pattern patient-data uploads use.
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_disk')->nullable();
            $table->string('avatar_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_disk', 'avatar_path']);
        });
    }
};
