<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Geräte" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Medizinische Messgeräte', 'icon' => 'signal'],
            ['label' => 'Geräte'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6">

        @if ($plainToken)
            <x-nx-callout variant="success" title="Token für „{{ $plainTokenDevice }}" — jetzt kopieren, wird nur einmal angezeigt">
                <div class="font-mono text-sm bg-[color:var(--nx-hover)] rounded-md p-3 break-all select-all mt-1">{{ $plainToken }}</div>
                <div class="text-xs text-[color:var(--nx-muted)] mt-2">Im Windows-Agent je Geräte-Ordner hinterlegen. Bei Verlust: neu ausstellen (rotieren).</div>
            </x-nx-callout>
        @endif

        <x-nx-card>
            <div class="p-4 space-y-4">
                <div class="text-sm font-medium text-[color:var(--nx-text)]">Gerät anlegen</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <x-nx-input-text label="Name" placeholder="Audiometer Raum 2" wire:model="name" errorKey="name" required />
                    <x-nx-input-text label="Gattung (kind)" placeholder="audiometry" wire:model="kind" errorKey="kind" />
                    <x-nx-input-text label="Core-Definition (LOINC/Code)" placeholder="z.B. 28615-3" wire:model="definition_code" errorKey="definition_code" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-text label="Hersteller" wire:model="manufacturer" />
                        <x-nx-input-text label="Modell" wire:model="model" />
                    </div>
                </div>
                <div>
                    <x-nx-button variant="primary" wire:click="create">Anlegen &amp; Token ausstellen</x-nx-button>
                </div>
            </div>
        </x-nx-card>

        <x-nx-card>
            @if ($devices->count())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[color:var(--nx-faint)] uppercase text-xs">
                                <th class="px-3 py-2">Gerät</th>
                                <th class="px-3 py-2">Gattung</th>
                                <th class="px-3 py-2">Definition</th>
                                <th class="px-3 py-2">Token</th>
                                <th class="px-3 py-2">Zuletzt gesehen</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($devices as $d)
                                <tr class="border-t border-[color:var(--nx-line)]">
                                    <td class="px-3 py-2">
                                        {{ $d->name }}
                                        @if ($d->manufacturer || $d->model)
                                            <div class="text-xs text-[color:var(--nx-faint)]">{{ trim($d->manufacturer.' '.$d->model) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ $d->kind ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $d->definition_code ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $d->token_prefix ? $d->token_prefix.'…' : '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-[color:var(--nx-muted)] whitespace-nowrap">{{ $d->last_seen_at?->diffForHumans() ?? 'nie' }}</td>
                                    <td class="px-3 py-2">
                                        <x-nx-badge :variant="$d->isActive() ? 'success' : 'neutral'" dot>{{ $d->isActive() ? 'aktiv' : 'deaktiviert' }}</x-nx-badge>
                                    </td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        <x-nx-button variant="ghost" size="sm" wire:click="rotate({{ $d->id }})">Token neu</x-nx-button>
                                        <x-nx-button variant="ghost" size="sm" wire:click="toggle({{ $d->id }})">{{ $d->isActive() ? 'deaktivieren' : 'aktivieren' }}</x-nx-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-nx-empty icon="heroicon-o-cpu-chip">Noch keine Geräte. Lege das erste an und hinterlege den Token im Windows-Agent.</x-nx-empty>
            @endif
        </x-nx-card>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Windows-Agent</h3>
                    <div class="text-sm text-[color:var(--nx-muted)] space-y-2">
                        <p>Je Geräte-Ordner einen Token hinterlegen. Der Agent postet die rohe GDT-Datei an:</p>
                        <p class="font-mono text-xs bg-[color:var(--nx-hover)] rounded-md p-2 break-all">POST /api/medical-devices/ingest</p>
                        <p>Bearer = der Geräte-Token. Parsen, Matchen und Strippen macht nodera.</p>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
