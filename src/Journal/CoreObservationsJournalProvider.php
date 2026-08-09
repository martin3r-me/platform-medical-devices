<?php

namespace Platform\MedicalDevices\Journal;

use Illuminate\Support\Carbon;
use Platform\Encounter\Contracts\JournalEntryProvider;
use Platform\MedicalDevices\Services\CoreObservationClient;

/**
 * Speist die patient-owned Messwerte AUS DEM CORE als datierte Einträge in den Akte-Verlauf
 * (encounter). Live gelesen — kein Doppelspeicher. Kein subject_uuid → keine Einträge.
 */
class CoreObservationsJournalProvider implements JournalEntryProvider
{
    public function entriesFor(int $patientId, int $teamId): array
    {
        $cls = '\Platform\Patient\Models\Patient';
        if (!class_exists($cls)) {
            return [];
        }
        $patient = $cls::find($patientId);
        $subjectUuid = $patient?->subject_uuid;
        if (!$subjectUuid) {
            return [];
        }

        $observations = app(CoreObservationClient::class)->observationsForSubject($subjectUuid);

        $entries = [];
        foreach ($observations as $o) {
            $lines = [];
            foreach (($o['components'] ?? []) as $c) {
                $v = trim(($c['value'] ?? '') . ' ' . ($c['unit'] ?? ''));
                $lines[] = ($c['name'] ?? $c['loinc'] ?? '?') . ($v !== '' ? ': ' . $v : '');
            }

            $value = $o['value'] ?? null;
            $subtitle = ($value !== null && $value !== '')
                ? trim($value . ' ' . ($o['unit'] ?? ''))
                : (count($o['components'] ?? []) . ' Komponenten');

            $entries[] = [
                'date'     => !empty($o['effective_at']) ? Carbon::parse($o['effective_at']) : Carbon::now(),
                'anchor'   => 'sovra-obs-' . ($o['uuid'] ?? ''),
                'type'     => 'measurement',
                'icon'     => 'heroicon-o-signal',
                'title'    => $o['name'] ?? $o['type'] ?? 'Messwert',
                'subtitle' => $subtitle,
                'badge'    => ['label' => 'sovra-Core', 'variant' => 'success'],
                'lines'    => $lines,
                'url'      => null,
            ];
        }

        return $entries;
    }
}
