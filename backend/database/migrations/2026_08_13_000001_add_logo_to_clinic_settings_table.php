<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `disk`/`path` pair — same convention as patient_images.disk/path and
        // patient_documents.disk/path, except this always resolves to the `public` disk (never
        // `local`): a clinic logo has to render as a plain <img> in the Practice Settings preview
        // with no consumer needing the auth-gated streaming pattern the patient-data tables use.
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->string('logo_disk')->nullable();
            $table->string('logo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_disk', 'logo_path']);
        });
    }
};
