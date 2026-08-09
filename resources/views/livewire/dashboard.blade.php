<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Medizinische Messgeräte" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Medizinische Messgeräte', 'icon' => 'signal'],
            ['label' => 'Übersicht'],
        ]" />
    </x-slot>

    <x-ui-page-container width="full" spacing="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-nx-card>
                <div class="p-4">
                    <div class="text-xs uppercase tracking-wide text-[color:var(--nx-faint)]">Geräte</div>
                    <div class="text-3xl font-semibold mt-1">{{ $devices }}</div>
                </div>
            </x-nx-card>
            <x-nx-card>
                <div class="p-4">
                    <div class="text-xs uppercase tracking-wide text-[color:var(--nx-faint)]">Im Eingang (offen)</div>
                    <div class="text-3xl font-semibold mt-1">{{ $pending }}</div>
                </div>
            </x-nx-card>
            <x-nx-card>
                <div class="p-4">
                    <div class="text-xs uppercase tracking-wide text-[color:var(--nx-faint)]">An Core übergeben</div>
                    <div class="text-3xl font-semibold mt-1">{{ $forwarded }}</div>
                </div>
            </x-nx-card>
        </div>

        <x-nx-card>
            <div class="p-4 space-y-3 text-sm text-[color:var(--nx-muted)]">
                <div class="font-medium text-[color:var(--nx-text)]">So läuft der Strom</div>
                <div>
                    Gerät → GDT-Datei im Ordner → lokaler Windows-Agent (Geräte-Token je Ordner) →
                    <code>POST /api/medical-devices/ingest</code> → Eingang. Hier ordnest du zu, der Arzt
                    bestätigt, die Identität wird <strong>gestrippt</strong>, und nur der Wert geht
                    patient-owned an den sovra-Core. Die Akte liest zurück aus dem Core.
                </div>
                <div>
                    Core-Anbindung:
                    @if ($coreConfigured)
                        <span class="text-emerald-600 font-medium">verbunden</span> — Weiterleitung aktiv.
                    @else
                        <span class="text-amber-600 font-medium">nicht konfiguriert</span> —
                        <code>SOVRA_CORE_URL</code> + <code>SOVRA_CORE_TOKEN</code> setzen. Bis dahin bleiben
                        Messungen im Eingang stehen.
                    @endif
                </div>
            </div>
        </x-nx-card>
    </x-ui-page-container>
</x-ui-page>
