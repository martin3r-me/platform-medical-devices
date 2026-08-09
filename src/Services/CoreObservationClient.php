<?php

namespace Platform\MedicalDevices\Services;

use Illuminate\Support\Facades\Http;
use Platform\MedicalDevices\Models\DeviceReading;

/**
 * Die Bridge zum sovra-Core.
 *
 * Schiebt eine bestätigte Messung PII-frei an den Core (POST /api/observation, Consumer-Token).
 * Provisionierung: hat der Patient noch keine subject_uuid, wird OHNE subject_uuid gepostet →
 * der Core legt ein Subject an und gibt die uuid zurück → wir speichern sie auf dem Patienten.
 *
 * Cross-Modul-Zugriff auf Patient bewusst lose (FQCN + class_exists).
 */
class CoreObservationClient
{
    public function __construct(
        protected string $base,
        protected string $token,
        protected int $timeout = 8,
    ) {}

    public function isConfigured(): bool
    {
        return $this->base !== '' && $this->token !== '';
    }

    /**
     * @return array{ok:bool, observation_uuid?:string, subject_uuid?:string, error?:string}
     */
    public function forward(DeviceReading $reading): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'core_not_configured'];
        }

        $device = $reading->device;
        $definition = $device?->definition_code;
        if (!$definition) {
            return ['ok' => false, 'error' => 'device_without_definition'];
        }

        $patient = $this->resolvePatient($reading->matched_patient_id);
        if (!$patient) {
            return ['ok' => false, 'error' => 'no_matched_patient'];
        }

        $payload = [
            'definition'    => $definition,
            'effective_at'  => optional($reading->measured_at)->toIso8601String() ?? now()->toIso8601String(),
            'source_device' => $device->kind ?: 'medical-device',   // neutral, keine PII
            'components'    => $this->components($reading),
        ];

        $subjectUuid = $patient->subject_uuid ?? null;
        if ($subjectUuid) {
            $payload['subject_uuid'] = $subjectUuid;
        }

        try {
            $res = Http::withToken($this->token)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->base, '/') . '/api/observation', $payload);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'core_unreachable'];
        }

        if (!$res->successful()) {
            return ['ok' => false, 'error' => 'core_' . $res->status()];
        }

        $body = $res->json();
        $returnedSubject = $body['subject_uuid'] ?? $subjectUuid;

        // Provisionierte subject_uuid am Patienten festhalten (einmalig).
        if (!$subjectUuid && $returnedSubject) {
            $patient->forceFill(['subject_uuid' => $returnedSubject])->save();
        }

        return [
            'ok'               => true,
            'observation_uuid' => $body['observation_uuid'] ?? null,
            'subject_uuid'     => $returnedSubject,
        ];
    }

    /**
     * Liest die patient-owned Observations eines Subjects aus dem Core zurück (Read-back).
     * Kurzer Timeout — läuft im Seiten-Render der Akte. Fehler/Timeout → leere Liste.
     *
     * @return array<int,array>
     */
    public function observationsForSubject(?string $subjectUuid): array
    {
        if (!$this->isConfigured() || !$subjectUuid) {
            return [];
        }
        try {
            $res = Http::withToken($this->token)
                ->timeout(min($this->timeout, 4))
                ->acceptJson()
                ->get(rtrim($this->base, '/') . '/api/observation', ['subject_uuid' => $subjectUuid]);
        } catch (\Throwable $e) {
            return [];
        }
        if (!$res->successful()) {
            return [];
        }
        return (array) ($res->json('observations') ?? []);
    }

    /** Geparste GDT-Komponenten → Core-Komponenten (Wert numerisch wenn möglich). */
    protected function components(DeviceReading $reading): array
    {
        $out = [];
        foreach ((array) ($reading->parsed['components'] ?? []) as $c) {
            $value = $c['value'] ?? null;
            $isNumeric = is_numeric(is_string($value) ? str_replace(',', '.', $value) : $value);
            $out[] = array_filter([
                'name'          => $c['name'] ?? $c['test'] ?? null,
                'value_numeric' => $isNumeric ? (float) str_replace(',', '.', (string) $value) : null,
                'value_text'    => $isNumeric ? null : ($value !== null ? (string) $value : null),
                'unit'          => $c['unit'] ?? null,
            ], fn ($v) => $v !== null);
        }
        return $out;
    }

    protected function resolvePatient(?int $id)
    {
        if (!$id) {
            return null;
        }
        $cls = '\Platform\Patient\Models\Patient';
        if (!class_exists($cls)) {
            return null;
        }
        return $cls::find($id);
    }
}
