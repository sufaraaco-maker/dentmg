<?php

use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * AI Assistant removal (PR #52, 2026-08-14) deleted the `ai_interaction_log.*` catalog rows
     * from `PermissionSeeder`'s *code*, but that only affects a fresh seed — any environment that
     * already ran the pre-removal seeder still has those rows live in `permissions` (and, via
     * `role_permissions.permission_key`'s `cascadeOnDelete()` FK, transitively in
     * `role_permissions` too). See TECH_DEBT.md's "Orphaned `ai_interaction_log`..." entry.
     */
    public function up(): void
    {
        DB::table('permissions')->where('group', 'ai_interaction_log')->delete();

        RolePermission::flushCache();
    }

    public function down(): void
    {
        // Deliberately irreversible — the removed AI Assistant feature these permissions gated no
        // longer exists, so there is nothing left for a restored permission to authorize.
    }
};
