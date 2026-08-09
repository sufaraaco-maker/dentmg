<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Stable, backend-owned identifier (e.g. "patients.delete") — not admin-editable.
            // Only the role<->permission assignment (role_permissions) is admin-editable, not the
            // catalog itself (Phase 4 design doc §1.1).
            $table->string('key')->unique();

            // UI grouping only (e.g. "appointments", "patients") — frontend renders translated
            // labels keyed by `key`/`group` via i18n, this column is not shown to end users as-is.
            $table->string('group');

            $table->string('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
