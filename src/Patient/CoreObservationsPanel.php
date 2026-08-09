<?php

namespace Platform\MedicalDevices\Patient;

use Illuminate\Support\Carbon;
use Platform\MedicalDevices\Services\CoreObservationClient;
use Platform\Patient\Contracts\PatientPanelProvider;

/**
 * Read-back-Panel: zeigt die patient-owned Messwerte eines Patienten AUS DEM CORE in der Akte.
 * nodera hält den Wert nicht doppelt — hier wird live aus dem Core gelesen (Single-Storage).
 * Kein subject_uuid → kein Panel (Patient hat noch nichts im Core).
 */
class CoreObservationsPanel implements PatientPanelProvider
{
    public function panel(int $patientId, int $teamId): ?array
    {
        $cls = '\Platform\Patient\Models\Patient';
        if (!class_exists($cls)) {
            return null;
        }
        $patient = $cls::find($patientId);
        $subjectUuid = $patient?->subject_uuid;
        if (!$subjectUuid) {
            return null;
        }

        $observations = app(CoreObservationClient::class)->observationsForSubject($subjectUuid);

        $items = [];
        foreach ($observations as $o) {
            $value = $o['value'] ?? null;
            $comps = $o['components'] ?? [];
            if ($value !== null && $value !== '') {
                $subtitle = trim($value . ' ' . ($o['unit'] ?? ''));
            } else {
                $subtitle = count($comps) . ' ' . (count($comps) === 1 ? 'Komponente' : 'Komponenten');
            }
            $when = !empty($o['effective_at']) ? Carbon::parse($o['effective_at'])->format('d.m.Y') : null;

            $items[] = [
                'title'    => $o['name'] ?? $o['type'] ?? ($o['loinc'] ?? 'Messwert'),
                'subtitle' => $subtitle,
                'meta'     => $when,
                'url'      => null,
            ];
        }

        return [
            'key'   => 'sovra-observations',
            'title' => 'Messwerte (sovra-Core)',
            'icon'  => 'signal',
            'order' => 40,
            'items' => $items,
            'empty' => 'Noch keine patient-owned Messwerte im Core.',
        ];
    }
}
