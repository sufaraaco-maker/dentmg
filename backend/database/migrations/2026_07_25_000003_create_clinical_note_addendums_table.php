<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately no `updated_at`/`deleted_at`/soft-delete column (design doc §6, §15
        // Decision A, approved 2026-07-25): an addendum is permanent from the moment it's created.
        // Immutability is enforced at the schema level, not just the policy/controller layer — no
        // future code change can accidentally expose an edit/delete path for a column that doesn't
        // exist.
        Schema::create('clinical_note_addendums', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('clinical_note_id')->constrained('clinical_notes');
            $table->foreignUuid('author_id')->constrained('users');

            $table->text('body');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['clinical_note_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_note_addendums');
    }
};
