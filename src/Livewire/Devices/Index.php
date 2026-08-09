<?php

namespace Platform\MedicalDevices\Livewire\Devices;

use Livewire\Component;
use Platform\MedicalDevices\Models\MedicalDevice;

class Index extends Component
{
    public string $name = '';
    public string $kind = '';
    public string $definition_code = '';
    public string $manufacturer = '';
    public string $model = '';

    /** Klartext-Token wird EINMALIG nach Anlage/Rotation angezeigt. */
    public ?string $plainToken = null;
    public ?string $plainTokenDevice = null;

    protected function rules(): array
    {
        return [
            'name'            => 'required|string|max:191',
            'kind'            => 'nullable|string|max:64',
            'definition_code' => 'nullable|string|max:64',
            'manufacturer'    => 'nullable|string|max:191',
            'model'           => 'nullable|string|max:191',
        ];
    }

    public function create(): void
    {
        $this->validate();
        $teamId = auth()->user()?->currentTeam?->id;

        $device = MedicalDevice::create([
            'team_id'           => $teamId,
            'name'              => $this->name,
            'kind'              => $this->kind ?: null,
            'definition_code'   => $this->definition_code ?: null,
            'manufacturer'      => $this->manufacturer ?: null,
            'model'             => $this->model ?: null,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->plainToken = $device->issueToken();
        $this->plainTokenDevice = $device->name;

        $this->reset(['name', 'kind', 'definition_code', 'manufacturer', 'model']);
    }

    public function rotate(int $id): void
    {
        $device = $this->find($id);
        if ($device) {
            $this->plainToken = $device->issueToken();
            $this->plainTokenDevice = $device->name;
        }
    }

    public function toggle(int $id): void
    {
        $device = $this->find($id);
        if ($device) {
            $device->status = $device->isActive() ? 'disabled' : 'active';
            $device->save();
        }
    }

    protected function find(int $id): ?MedicalDevice
    {
        return MedicalDevice::where('team_id', auth()->user()?->currentTeam?->id)->find($id);
    }

    public function render()
    {
        $devices = MedicalDevice::where('team_id', auth()->user()?->currentTeam?->id)
            ->orderBy('name')->get();

        return view('medical-devices::livewire.devices.index', [
            'devices' => $devices,
        ])->layout('platform::layouts.app');
    }
}
