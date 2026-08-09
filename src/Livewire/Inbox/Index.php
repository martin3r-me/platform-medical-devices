<?php

namespace Platform\MedicalDevices\Livewire\Inbox;

use Livewire\Component;
use Platform\MedicalDevices\Models\DeviceReading;
use Platform\MedicalDevices\Services\CoreObservationClient;

class Index extends Component
{
    /** Manuelles Matching: [readingId => lab_number] */
    public array $manualNumber = [];

    public ?string $flash = null;
    public ?string $flashType = null;

    /** Arzt bestätigt → PII strippen → an den Core (patient-owned). */
    public function confirm(int $id): void
    {
        $reading = $this->find($id);
        if (!$reading || !$reading->isPending()) {
            return;
        }
        if (!$reading->matched_patient_id) {
            $this->setFlash('Erst einem Patienten zuordnen.', 'error');
            return;
        }

        $result = app(CoreObservationClient::class)->forward($reading);

        if (!($result['ok'] ?? false)) {
            $reading->update(['note' => 'Forward fehlgeschlagen: ' . ($result['error'] ?? 'unknown')]);
            $this->setFlash('An den Core senden fehlgeschlagen: ' . ($result['error'] ?? 'unbekannt'), 'error');
            return;
        }

        $reading->update([
            'status'           => DeviceReading::S_FORWARDED,
            'observation_uuid' => $result['observation_uuid'] ?? null,
            'subject_uuid'     => $result['subject_uuid'] ?? null,
            'forwarded_at'     => now(),
            'note'             => null,
        ]);
        $reading->stripPii();   // nur Referenz bleibt (Single-Storage)

        $this->setFlash('Messung patient-owned an den Core übergeben.', 'success');
    }

    /** Manuell einem Patienten zuordnen (per lab_number). */
    public function assign(int $id): void
    {
        $reading = $this->find($id);
        if (!$reading) {
            return;
        }
        $number = trim((string) ($this->manualNumber[$id] ?? ''));
        $patient = $this->findPatient($reading->team_id, $number);

        if (!$patient) {
            $this->setFlash('Kein Patient mit dieser Nummer gefunden.', 'error');
            return;
        }

        $reading->update([
            'matched_patient_id' => $patient->id,
            'patient_number'     => $number,
            'status'             => DeviceReading::S_MATCHED,
        ]);
        $this->setFlash('Zugeordnet.', 'success');
    }

    public function reject(int $id): void
    {
        $reading = $this->find($id);
        if ($reading && $reading->isPending()) {
            $reading->stripPii();
            $reading->update(['status' => DeviceReading::S_REJECTED]);
        }
    }

    protected function setFlash(string $msg, string $type): void
    {
        $this->flash = $msg;
        $this->flashType = $type;
    }

    protected function find(int $id): ?DeviceReading
    {
        return DeviceReading::where('team_id', auth()->user()?->currentTeam?->id)->find($id);
    }

    protected function findPatient(int $teamId, string $number)
    {
        if ($number === '') {
            return null;
        }
        $cls = '\Platform\Patient\Models\Patient';
        if (!class_exists($cls)) {
            return null;
        }
        return $cls::where('team_id', $teamId)->where('lab_number', $number)->first();
    }

    public function render()
    {
        $teamId = auth()->user()?->currentTeam?->id;

        $pending = DeviceReading::with('device')
            ->where('team_id', $teamId)
            ->whereIn('status', [DeviceReading::S_RECEIVED, DeviceReading::S_MATCHED, DeviceReading::S_UNMATCHED])
            ->orderByDesc('id')->get();

        $recent = DeviceReading::with('device')
            ->where('team_id', $teamId)
            ->whereIn('status', [DeviceReading::S_FORWARDED, DeviceReading::S_REJECTED])
            ->orderByDesc('id')->limit(20)->get();

        return view('medical-devices::livewire.inbox.index', [
            'pending' => $pending,
            'recent'  => $recent,
        ])->layout('platform::layouts.app');
    }
}
