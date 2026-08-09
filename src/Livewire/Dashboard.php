<?php

namespace Platform\MedicalDevices\Livewire;

use Livewire\Component;
use Platform\MedicalDevices\Models\DeviceReading;
use Platform\MedicalDevices\Models\MedicalDevice;
use Platform\MedicalDevices\Services\CoreObservationClient;

class Dashboard extends Component
{
    public function render()
    {
        $teamId = auth()->user()?->currentTeam?->id;

        $devices = MedicalDevice::where('team_id', $teamId)->count();
        $pending = DeviceReading::where('team_id', $teamId)
            ->whereIn('status', [DeviceReading::S_RECEIVED, DeviceReading::S_MATCHED, DeviceReading::S_UNMATCHED])
            ->count();
        $forwarded = DeviceReading::where('team_id', $teamId)
            ->where('status', DeviceReading::S_FORWARDED)->count();

        return view('medical-devices::livewire.dashboard', [
            'devices'       => $devices,
            'pending'       => $pending,
            'forwarded'     => $forwarded,
            'coreConfigured' => app(CoreObservationClient::class)->isConfigured(),
        ])->layout('platform::layouts.app');
    }
}
