<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The App\Enums\UserRole string value — not a foreign key, since roles are a fixed
            // enum, not their own table (Phase 4 design doc §1.2/§12: full role-hierarchy stays
            // deferred).
            $table->string('role');

            $table->string('permission_key');
            $table->foreign('permission_key')->references('key')->on('permissions')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['role', 'permission_key']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
