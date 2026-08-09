<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * device_readings — der Eingang / die Inbox.
 *
 * Eine eingegangene GDT-Messung. Enthält TRANSIENT PII (raw, patient_name, patient_number) —
 * nur bis der Arzt bestätigt. Beim Forward an den Core wird gestrippt: PII-Felder werden
 * geleert, es bleibt die Referenz (observation_uuid + matched_patient_id + measured_at).
 * So bleibt Single-Storage: der Wert lebt kanonisch im Core, nodera hält nur die Referenz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_readings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('medical_device_id')->index();

            // received → matched|unmatched → confirmed → forwarded | rejected
            $table->string('status', 16)->default('received')->index();

            // Dedupe: Hash der Rohdatei (ein Neustart des Agents schickt nichts doppelt).
            $table->string('content_hash', 64)->nullable()->index();

            // TRANSIENTE PII (verschlüsselt) — wird beim Forward gestrippt.
            $table->text('raw')->nullable();            // rohe GDT
            $table->text('patient_name')->nullable();   // Anzeige in der Inbox
            $table->string('patient_number')->nullable(); // Matching-Key (Geräte-/Patientennummer)

            // Ergebnis der Zuordnung
            $table->unsignedBigInteger('matched_patient_id')->nullable()->index();
            $table->uuid('subject_uuid')->nullable();   // nach Provisionierung/Forward

            // Geparste, entkontextualisierte Werte (Komponenten) — Referenz/Anzeige.
            $table->json('parsed')->nullable();
            $table->timestamp('measured_at')->nullable();

            // Referenz nach erfolgreichem Forward
            $table->uuid('observation_uuid')->nullable();
            $table->timestamp('forwarded_at')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_readings');
    }
};
