<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * medical_devices — Registry der medizinischen Messgeräte einer Praxis.
 * Jedes Gerät bekommt einen (gehashten) Token; damit authentifiziert sich der lokale
 * Windows-Agent je Geräte-Ordner am GDT-Eingang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();

            $table->string('name');                          // z.B. "Audiometer Raum 2"
            $table->string('kind', 64)->nullable();          // z.B. "audiometry" (fachliche Gattung)
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();

            // Welche Core-Definition (LOINC/lokaler Code) speist dieses Gerät?
            $table->string('definition_code', 64)->nullable();

            // Geräte-Token: nur Hash + Prefix persistiert (Klartext wird einmalig angezeigt).
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('token_prefix', 16)->nullable();

            $table->string('status', 16)->default('active'); // active|disabled
            $table->timestamp('last_seen_at')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_devices');
    }
};
