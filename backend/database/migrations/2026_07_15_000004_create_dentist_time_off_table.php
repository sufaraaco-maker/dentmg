<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dentist_time_off', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dentist_time_off');
    }
};
