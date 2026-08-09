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

    <x-ui-page-container width="full" spacing="space-y-6">

        @if ($plainToken)
            <x-nx-card>
                <div class="p-4 space-y-2">
                    <div class="text-sm font-medium text-emerald-700">Token für „{{ $plainTokenDevice }}" — jetzt kopieren, wird nur einmal angezeigt</div>
                    <div class="font-mono text-sm bg-[var(--nx-bg-muted)] rounded-md p-3 break-all select-all">{{ $plainToken }}</div>
                    <div class="text-xs text-[color:var(--nx-faint)]">Im Windows-Agent je Geräte-Ordner hinterlegen. Bei Verlust: neu ausstellen (rotieren).</div>
                </div>
            </x-nx-card>
        @endif

        <x-nx-card>
            <div class="p-4">
                <div class="text-sm font-medium mb-3">Gerät anlegen</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-[color:var(--nx-muted)]">Name</label>
                        <input type="text" wire:model="name" placeholder="Audiometer Raum 2" class="w-full text-sm rounded-md border-[color:var(--nx-line)] bg-[var(--nx-bg)]" />
                        @error('name') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="text-xs text-[color:var(--nx-muted)]">Gattung (kind)</label>
                        <input type="text" wire:model="kind" placeholder="audiometry" class="w-full text-sm rounded-md border-[color:var(--nx-line)] bg-[var(--nx-bg)]" />
                    </div>
                    <div>
                        <label class="text-xs text-[color:var(--nx-muted)]">Core-Definition (LOINC/Code)</label>
                        <input type="text" wire:model="definition_code" placeholder="z.B. 28615-3" class="w-full text-sm rounded-md border-[color:var(--nx-line)] bg-[var(--nx-bg)]" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-[color:var(--nx-muted)]">Hersteller</label>
                            <input type="text" wire:model="manufacturer" class="w-full text-sm rounded-md border-[color:var(--nx-line)] bg-[var(--nx-bg)]" />
                        </div>
                        <div>
                            <label class="text-xs text-[color:var(--nx-muted)]">Modell</label>
                            <input type="text" wire:model="model" class="w-full text-sm rounded-md border-[color:var(--nx-line)] bg-[var(--nx-bg)]" />
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button wire:click="create" class="px-3 py-1.5 text-sm rounded-md bg-[var(--nx-accent,#0B6E5B)] text-white">Anlegen & Token ausstellen</button>
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
                                        <span class="text-xs px-2 py-0.5 rounded-full {{ $d->isActive() ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">{{ $d->isActive() ? 'aktiv' : 'deaktiviert' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                        <button wire:click="rotate({{ $d->id }})" class="text-xs text-[color:var(--nx-muted)] hover:underline">Token neu</button>
                                        <button wire:click="toggle({{ $d->id }})" class="text-xs text-[color:var(--nx-muted)] hover:underline ml-2">{{ $d->isActive() ? 'deaktivieren' : 'aktivieren' }}</button>
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
</x-ui-page>
