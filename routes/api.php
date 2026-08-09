<?php

/**
 * Medizinische Messgeräte — Geräte-Token-API (Prefix /api/medical-devices).
 * Bearer = Geräte-Token (medical-devices.device.token-Middleware löst Gerät + Team auf).
 *
 * Ziel des lokalen Windows-Agents: er beobachtet je Geräte-Ordner GDT-Dateien und POSTet
 * die ROHE Datei hierher. Alles Weitere (parsen, matchen, strippen, bestätigen) macht nodera.
 */

use Platform\MedicalDevices\Http\Controllers\IngestController;

Route::post('/ingest', [IngestController::class, 'ingest'])->name('medical-devices.api.ingest');
Route::get('/ping', [IngestController::class, 'ping'])->name('medical-devices.api.ping');
