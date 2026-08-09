<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Eingang" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Medizinische Messgeräte', 'icon' => 'signal'],
            ['label' => 'Eingang'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">

        @if ($flash)
            <x-nx-callout :variant="$flashType === 'success' ? 'success' : 'danger'">{{ $flash }}</x-nx-callout>
        @endif

        <x-nx-card>
            <div class="p-3 text-sm font-medium text-[color:var(--nx-text)]">Offen — {{ $pending->count() }}</div>
            @if ($pending->count())
                <div class="divide-y divide-[color:var(--nx-line)]">
                    @foreach ($pending as $r)
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="text-sm">
                                        <span class="font-medium">{{ $r->device?->name ?? 'Gerät' }}</span>
                                        <span class="text-[color:var(--nx-faint)]">· {{ $r->measured_at?->format('d.m.Y H:i') ?? 'ohne Datum' }}</span>
                                    </div>
                                    <div class="text-sm text-[color:var(--nx-muted)] mt-1">
                                        @if ($r->matched_patient_id)
                                            <x-nx-badge variant="success" dot>{{ $r->patient_name ?? ('Patient #'.$r->matched_patient_id) }}</x-nx-badge>
                                        @else
                                            <x-nx-badge variant="warning" dot>Nicht zugeordnet</x-nx-badge>
                                            @if ($r->patient_number) <span class="font-mono text-xs ml-1">Nr. {{ $r->patient_number }}</span> @endif
                                        @endif
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach (($r->parsed['components'] ?? []) as $c)
                                            <x-nx-badge variant="neutral">
                                                {{ $c['name'] ?? $c['test'] ?? '?' }}: {{ $c['value'] ?? '—' }}{{ isset($c['unit']) ? ' '.$c['unit'] : '' }}
                                            </x-nx-badge>
                                        @endforeach
                                    </div>
                                    @if ($r->note)
                                        <div class="text-xs text-[color:var(--nx-danger)] mt-2">{{ $r->note }}</div>
                                    @endif
                                </div>
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    @if ($r->matched_patient_id)
                                        <x-nx-button variant="primary" size="sm" wire:click="confirm({{ $r->id }})">Bestätigen &amp; an Core</x-nx-button>
                                    @else
                                        <div class="flex items-end gap-1">
                                            <x-nx-input-select size="sm" :options="$patientOptions" nullable nullLabel="— Patient wählen —" wire:model="manualPatient.{{ $r->id }}" class="w-56" />
                                            <x-nx-button size="sm" wire:click="assign({{ $r->id }})">Zuordnen</x-nx-button>
                                        </div>
                                    @endif
                                    <x-nx-button variant="ghost" size="sm" wire:click="reject({{ $r->id }})">verwerfen</x-nx-button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <x-nx-empty icon="heroicon-o-inbox">Eingang leer. Messungen erscheinen hier, sobald ein Gerät liefert.</x-nx-empty>
            @endif
        </x-nx-card>

        @if ($recent->count())
            <x-nx-card>
                <div class="p-3 text-sm font-medium text-[color:var(--nx-text)]">Zuletzt</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[color:var(--nx-faint)] uppercase text-xs">
                                <th class="px-3 py-2">Gerät</th>
                                <th class="px-3 py-2">Zeitpunkt</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Core-Referenz</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $r)
                                <tr class="border-t border-[color:var(--nx-line)]">
                                    <td class="px-3 py-2">{{ $r->device?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-[color:var(--nx-muted)] whitespace-nowrap">{{ $r->measured_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($r->isForwarded())
                                            <x-nx-badge variant="success" dot>an Core</x-nx-badge>
                                        @else
                                            <x-nx-badge variant="neutral">verworfen</x-nx-badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs text-[color:var(--nx-muted)]">{{ $r->observation_uuid ? \Illuminate\Support\Str::limit($r->observation_uuid, 13, '…') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-nx-card>
        @endif
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Der Eingang</h3>
                    <div class="text-sm text-[color:var(--nx-muted)] space-y-2">
                        <p>Eingegangene Messungen warten hier auf Zuordnung + Bestätigung.</p>
                        <p><strong>Bestätigen &amp; an Core</strong> strippt die Identität und schiebt nur den Wert patient-owned in den sovra-Core.</p>
                        <p>Kein Match? Patient-Nummer eintragen und zuordnen.</p>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
