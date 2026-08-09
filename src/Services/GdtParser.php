<?php

namespace Platform\MedicalDevices\Services;

/**
 * Pragmatischer GDT-Parser (v1).
 *
 * GDT ist zeilenbasiert: jede Zeile = "LLLFFFFinhalt" mit LLL = 3-stellige Länge,
 * FFFF = 4-stellige Feldkennung, Rest = Inhalt (CRLF-terminiert, meist ISO-8859-1/CP437).
 *
 * Wir extrahieren die Identität (zum Matchen, wird später gestrippt) und die Messwert-
 * Felder (8410 Test-Ident, 8411 Test-Bezeichnung, 8420 Wert, 8421 Einheit) als Komponenten.
 *
 * Bewusst tolerant und dialekt-arm: Gerätemodelle formatieren leicht unterschiedlich.
 * Feinschliff je Gerätetyp kommt an DIESER einen Stelle, nicht im Windows-Agent.
 */
class GdtParser
{
    public function parse(string $raw): array
    {
        $text = $this->toUtf8($raw);
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        $fields = [];              // feldkennung => letzter Wert (für Kopf-Felder)
        $components = [];          // gesammelte Messwerte
        $current = null;           // laufende Komponente (8410 eröffnet, 8420/8421 füllen)

        foreach ($lines as $line) {
            if (strlen($line) < 7) {
                continue;
            }
            // Bytes 0-2 Länge (ignoriert), 3-6 Feldkennung, ab 7 Inhalt.
            $fieldId = substr($line, 3, 4);
            $content = rtrim(substr($line, 7));

            if (!ctype_digit($fieldId)) {
                continue;
            }

            switch ($fieldId) {
                case '8410': // Test-Ident → neue Komponente eröffnen
                    if ($current !== null) {
                        $components[] = $current;
                    }
                    $current = ['test' => $content, 'name' => null, 'value' => null, 'unit' => null];
                    break;
                case '8411': // Test-Bezeichnung
                    if ($current !== null) {
                        $current['name'] = $content;
                    }
                    break;
                case '8420': // Ergebnis-Wert
                    if ($current !== null) {
                        $current['value'] = $content;
                    }
                    break;
                case '8421': // Einheit
                    if ($current !== null) {
                        $current['unit'] = $content;
                    }
                    break;
                default:
                    $fields[$fieldId] = $content;
                    break;
            }
        }
        if ($current !== null) {
            $components[] = $current;
        }

        return [
            'patient_number' => $fields['3000'] ?? null,
            'last_name'      => $fields['3101'] ?? null,
            'first_name'     => $fields['3102'] ?? null,
            'birth_date'     => $this->date($fields['3103'] ?? null),
            'measured_at'    => $this->date($fields['6200'] ?? null),
            'components'     => $components,
        ];
    }

    /** Zusammengesetzter Anzeigename für die Inbox (transient, wird gestrippt). */
    public function displayName(array $parsed): ?string
    {
        $name = trim(($parsed['last_name'] ?? '') . ', ' . ($parsed['first_name'] ?? ''), ', ');
        return $name !== '' ? $name : null;
    }

    protected function toUtf8(string $raw): string
    {
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }
        // GDT ist klassisch ISO-8859-1 / CP437.
        return mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    }

    /** GDT-Datum ddmmyyyy → Y-m-d (oder null). */
    protected function date(?string $v): ?string
    {
        if ($v === null || !preg_match('/^(\d{2})(\d{2})(\d{4})$/', $v, $m)) {
            return null;
        }
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
}
