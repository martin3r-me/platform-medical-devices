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

    <x-ui-page-container width="contained" spacing="space-y-6">
        <x-nx-stat-grid cols="3">
            <x-nx-stat label="Geräte" :value="$devices" icon="heroicon-o-cpu-chip" :href="route('medical-devices.devices.index')" />
            <x-nx-stat label="Im Eingang (offen)" :value="$pending" icon="heroicon-o-inbox-arrow-down" :href="route('medical-devices.inbox.index')" accent="var(--nx-warning)" />
            <x-nx-stat label="An Core übergeben" :value="$forwarded" icon="heroicon-o-check-circle" accent="var(--nx-success)" />
        </x-nx-stat-grid>

        @if ($coreConfigured)
            <x-nx-callout variant="success" title="Core verbunden">Weiterleitung aktiv — bestätigte Messungen gehen patient-owned an den sovra-Core.</x-nx-callout>
        @else
            <x-nx-callout variant="warning" title="Core-Anbindung nicht konfiguriert">
                <code>SOVRA_CORE_URL</code> und <code>SOVRA_CORE_TOKEN</code> setzen. Bis dahin bleiben Messungen im Eingang stehen (nichts fließt versehentlich in den Core).
            </x-nx-callout>
        @endif
    </x-ui-page-container>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">So läuft der Strom</h3>
                    <div class="text-sm text-[color:var(--nx-muted)] space-y-2">
                        <p>Gerät → GDT-Datei im Ordner → lokaler Windows-Agent (Token je Ordner) → Eingang.</p>
                        <p>Hier zuordnen, der Arzt bestätigt, die Identität wird <strong>gestrippt</strong>, nur der Wert geht patient-owned an den Core.</p>
                        <p>Die Patientenakte liest zurück aus dem Core — nodera speichert den Wert nicht doppelt.</p>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] mb-3">Letzte Aktivitäten</h3>
                    <div class="text-sm text-[color:var(--nx-muted)]">Noch keine Aktivitäten.</div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
