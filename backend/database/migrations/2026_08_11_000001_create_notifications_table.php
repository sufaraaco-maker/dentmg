<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (Notification System) Step A, design doc §6.1 / Decision D1.
 *
 * Laravel's own stock `notifications` table (so `Notifiable`, `DatabaseNotification`,
 * `markAsRead()`, `unreadNotifications`, and `Prunable` all work natively) plus four additive
 * columns. This is the same additive-columns-on-a-framework-table move Phase 4 Step 3 made on
 * `audit_logs`, not a fork of Laravel's schema.
 *
 * Why the four extra columns exist rather than living in `data` (design doc §3.1):
 *  - `category`      — the read-time authorization re-check (design doc §8.2) is a
 *                      `whereIn('category', ...)`. On a real indexed column that is an index scan;
 *                      as `data->>'category'` it is a JSON probe that cannot share the index.
 *  - `subject_*`     — the deep-link target ("open the notification, go to the resource").
 *  - `patient_id`    — nullable on purpose: Phase C's low-stock and security notifications have no
 *                      patient. This is the one deliberate divergence from `patient_activities`,
 *                      which requires one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            // --- Laravel's stock notifications schema ---
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // --- Additive (design doc §6.1) ---
            // Plain string, not a native DB enum — adding a category in Phase C is a one-line PHP
            // const change, never an ALTER TYPE. Matches every other status/category string column
            // in this codebase (see the patient_activities migration's own note).
            $table->string('category')->index();

            $table->uuidMorphs('subject');

            $table->foreignUuid('patient_id')->nullable()->constrained()->nullOnDelete();

            // The paginated Notification Center list.
            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
        });

        // Decision D2 — the unread badge is polled every 60s per open session, making this the
        // single hottest query in the module. A partial index keeps it proportional to the number
        // of *unread* rows rather than to the table, which matters because read rows accumulate
        // until Phase B's pruning runs. Raw SQL because Laravel's schema builder has no portable
        // partial-index API; guarded on the driver so the SQLite-backed test suite still migrates.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE INDEX notifications_unread_idx ON notifications '
                .'(notifiable_type, notifiable_id) WHERE read_at IS NULL'
            );
        } else {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
