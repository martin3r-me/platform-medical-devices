<?php

/**
 * Medizinische Messgeräte — Konfiguration.
 *
 * Das Modul verwaltet medizinische MESSGERÄTE (Audiometer, Sehtest, Blutdruck,
 * Spirometrie …), nimmt deren Messungen (v1: GDT via lokalem Windows-Agent) an,
 * matcht sie auf einen Patienten, lässt sie vom Arzt bestätigen, STRIPPT die PII
 * und schiebt nur den Wert patient-owned an den sovra-Core.
 *
 * Der Core bleibt blind (nur subject_uuid + Werte). Identität + Termin bleiben hier.
 */

return [

    'routing' => [
        'mode'   => env('MEDICAL_DEVICES_MODE', 'path'),
        'prefix' => 'medical-devices',
    ],

    'guard' => 'web',

    'navigation' => [
        'route' => 'medical-devices.dashboard',
        'icon'  => 'heroicon-o-signal',
        'order' => 65,
    ],

    'sidebar' => [
        [
            'group' => 'Medizinische Messgeräte',
            'items' => [
                [
                    'label' => 'Übersicht',
                    'route' => 'medical-devices.dashboard',
                    'icon'  => 'heroicon-o-home',
                ],
                [
                    'label' => 'Eingang',
                    'route' => 'medical-devices.inbox.index',
                    'icon'  => 'heroicon-o-inbox-arrow-down',
                ],
                [
                    'label' => 'Geräte',
                    'route' => 'medical-devices.devices.index',
                    'icon'  => 'heroicon-o-cpu-chip',
                ],
            ],
        ],
    ],

    /**
     * Anbindung an den sovra-Core (die Bridge).
     * base   = Basis-URL der Core-Instanz (bis /api, ohne trailing slash)
     * token  = Consumer-Token von nodera (in der Sovereignty des Core ausgestellt)
     *
     * Solange kein Token gesetzt ist, bleibt die Weiterleitung inert (kein Forward,
     * Readings bleiben in der Inbox stehen).
     */
    'core' => [
        'base'    => env('SOVRA_CORE_URL', 'https://core.sovra.health'),
        'token'   => env('SOVRA_CORE_TOKEN', ''),
        'timeout' => (int) env('SOVRA_CORE_TIMEOUT', 8),
    ],
];
