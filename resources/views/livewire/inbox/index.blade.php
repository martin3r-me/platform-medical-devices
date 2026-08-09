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

    <x-ui-page-container width="full" spacing="space-y-6">

        @if ($flash)
            <div class="text-sm rounded-md p-3 {{ $flashType === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800' }}">{{ $flash }}</div>
        @endif

        <x-nx-card>
            <div class="p-3 text-sm font-medium">Offen — {{ $pending->count() }}</div>
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
                                    <div class="text-sm text-[color:var(--nx-muted)] mt-0.5">
                                        @if ($r->matched_patient_id)
                                            Zugeordnet: <span class="font-medium">{{ $r->patient_name ?? ('Patient #'.$r->matched_patient_id) }}</span>
                                        @else
                                            <span class="text-amber-700">Nicht zugeordnet</span>
                                            @if ($r->patient_number) <span class="font-mono text-xs">(Nr. {{ $r->patient_number }})</span> @endif
                                        @endif
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach (($r->parsed['components'] ?? []) as $c)
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--nx-bg-muted)] font-mono">
                                                {{ $c['name'] ?? $c['test'] ?? '?' }}: {{ $c['value'] ?? '—' }}{{ isset($c['unit']) ? ' '.$c['unit'] : '' }}
                                            </span>
                                        @endforeach
                                    </div>
                                    @if ($r->note)
                                        <div class="text-xs text-red-600 mt-1">{{ $r->note }}</div>
                                    @endif
                                </div>
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    @if ($r->matched_patient_id)
                                        <button wire:click="confirm({{ $r->id }})" class="px-3 py-1.5 text-sm rounded-md bg-[var(--nx-accent,#0B6E5B)] text-white">Bestätigen & an Core</button>
                                    @else
                                        <div class="flex items-center gap-1">
                                            <input type="text" wire:model="manualNumber.{{ $r->id }}" placeholder="Patient-Nr." class="w-28 text-xs rounded-md border-[color:var(--nx-line)] bg-[var(--nx-bg)]" />
                                            <button wire:click="assign({{ $r->id }})" class="px-2 py-1.5 text-xs rounded-md border border-[color:var(--nx-line)]">Zuordnen</button>
                                        </div>
                                    @endif
                                    <button wire:click="reject({{ $r->id }})" class="text-xs text-[color:var(--nx-faint)] hover:underline">verwerfen</button>
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
                <div class="p-3 text-sm font-medium">Zuletzt</div>
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
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">an Core</span>
                                        @else
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">verworfen</span>
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
</x-ui-page>
