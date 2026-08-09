<?php

namespace Platform\MedicalDevices\Livewire\Inbox;

use Livewire\Component;
use Platform\MedicalDevices\Models\DeviceReading;
use Platform\MedicalDevices\Services\CoreObservationClient;

class Index extends Component
{
    /** Manuelles Matching: [readingId => patient_id] */
    public array $manualPatient = [];

    /** Rohdaten (GDT) je Reading ein-/ausklappen: [readingId => bool] */
    public array $showRaw = [];

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

    /** Manuell einem Patienten zuordnen (per Dropdown-Auswahl). */
    public function assign(int $id): void
    {
        $reading = $this->find($id);
        if (!$reading) {
            return;
        }
        $patientId = (int) ($this->manualPatient[$id] ?? 0);
        $patient = $this->findPatientById($reading->team_id, $patientId);

        if (!$patient) {
            $this->setFlash('Bitte einen Patienten wählen.', 'error');
            return;
        }

        $reading->update([
            'matched_patient_id' => $patient->id,
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

    public function toggleRaw(int $id): void
    {
        $this->showRaw[$id] = !($this->showRaw[$id] ?? false);
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

    protected function findPatientById(int $teamId, int $id)
    {
        if ($id <= 0) {
            return null;
        }
        $cls = '\Platform\Patient\Models\Patient';
        if (!class_exists($cls)) {
            return null;
        }
        return $cls::where('team_id', $teamId)->find($id);
    }

    /** Patienten des Teams als Dropdown-Optionen [['value'=>id,'label'=>…], …]. */
    protected function patientOptions(int $teamId): array
    {
        $cls = '\Platform\Patient\Models\Patient';
        if (!class_exists($cls)) {
            return [];
        }
        return $cls::where('team_id', $teamId)
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'birth_date'])
            ->map(function ($p) {
                $name = trim(($p->last_name ?? '') . ', ' . ($p->first_name ?? ''), ', ');
                $dob  = $p->birth_date ? ' (' . \Illuminate\Support\Carbon::parse($p->birth_date)->format('d.m.Y') . ')' : '';
                return ['value' => $p->id, 'label' => ($name !== '' ? $name : 'Patient #' . $p->id) . $dob];
            })->all();
    }

    /** Zugeordnete Patienten (Name + Akte-Link) für die Anzeige/den Absprung. */
    protected function patientMap(int $teamId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }
        $cls = '\Platform\Patient\Models\Patient';
        if (!class_exists($cls)) {
            return [];
        }
        return $cls::where('team_id', $teamId)->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(function ($p) {
                $name = trim(($p->last_name ?? '') . ', ' . ($p->first_name ?? ''), ', ');
                return [$p->id => [
                    'name' => $name !== '' ? $name : ('Patient #' . $p->id),
                    'url'  => route('patient.patients.show', $p->id),
                ]];
            })->all();
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

        $matched = $this->patientMap(
            (int) $teamId,
            $pending->pluck('matched_patient_id')->merge($recent->pluck('matched_patient_id'))->all()
        );

        return view('medical-devices::livewire.inbox.index', [
            'pending'        => $pending,
            'recent'         => $recent,
            'patientOptions' => $this->patientOptions((int) $teamId),
            'matched'        => $matched,
        ])->layout('platform::layouts.app');
    }
}
