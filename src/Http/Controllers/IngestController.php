<?php

namespace Platform\MedicalDevices\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\MedicalDevices\Models\DeviceReading;
use Platform\MedicalDevices\Models\MedicalDevice;
use Platform\MedicalDevices\Services\GdtParser;

/**
 * GDT-Eingang. Der lokale Windows-Agent POSTet die ROHE GDT-Datei hierher (Bearer-Geräte-Token).
 * Hier: parsen → auf Patienten matchen → als (pending) Reading in die Inbox. KEIN Auto-Forward:
 * erst der Arzt bestätigt (dann wird gestrippt + an den Core geschoben).
 */
class IngestController extends Controller
{
    public function ping(Request $request)
    {
        $device = $request->attributes->get('medical_device');
        return response()->json(['ok' => true, 'device' => $device?->name]);
    }

    public function ingest(Request $request, GdtParser $parser)
    {
        /** @var MedicalDevice $device */
        $device = $request->attributes->get('medical_device');

        $raw = $request->getContent();
        if ($raw === '' || $raw === null) {
            $raw = (string) $request->input('gdt', '');
        }
        if (trim($raw) === '') {
            return response()->json(['error' => 'empty_payload'], 422);
        }

        $hash = hash('sha256', $raw);

        // Idempotenz: gleiche Datei zweimal (Agent-Neustart) → nichts doppelt.
        $existing = DeviceReading::where('medical_device_id', $device->id)
            ->where('content_hash', $hash)
            ->first();
        if ($existing) {
            return response()->json(['ok' => true, 'reading' => $existing->uuid, 'status' => $existing->status, 'duplicate' => true], 200);
        }

        $parsed = $parser->parse($raw);
        $number = $parsed['patient_number'] ?? null;
        $matched = $this->matchPatient($device->team_id, $number);

        $reading = DeviceReading::create([
            'team_id'            => $device->team_id,
            'medical_device_id'  => $device->id,
            'status'             => $matched ? DeviceReading::S_MATCHED : DeviceReading::S_UNMATCHED,
            'content_hash'       => $hash,
            'raw'                => $raw,
            'patient_name'       => $parser->displayName($parsed),
            'patient_number'     => $number,
            'matched_patient_id' => $matched?->id,
            'parsed'             => ['components' => $parsed['components'] ?? []],
            'measured_at'        => $parsed['measured_at'] ?? null,
        ]);

        return response()->json([
            'ok'      => true,
            'reading' => $reading->uuid,
            'status'  => $reading->status,
        ], 202);
    }

    /** Matching-Key: die vom Gerät mitgelieferte Nummer gegen patient.lab_number (team-scoped). */
    protected function matchPatient(int $teamId, ?string $number)
    {
        if ($number === null || $number === '') {
            return null;
        }
        $cls = '\Platform\Patient\Models\Patient';
        if (!class_exists($cls)) {
            return null;
        }
        return $cls::where('team_id', $teamId)->where('lab_number', $number)->first();
    }
}
