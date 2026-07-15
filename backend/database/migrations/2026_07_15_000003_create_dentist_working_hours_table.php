<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dentist_working_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // 0 = Sunday .. 6 = Saturday.
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');

            // A dentist can have multiple rows for the same day_of_week (e.g. a lunch-break
            // split shift) — this is a table of ranges, not one start/end pair per day.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dentist_working_hours');
    }
};
