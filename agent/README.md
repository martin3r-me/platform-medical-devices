# sovra medical-devices agent

Plattformübergreifender (Windows · macOS · Linux) GDT-Ordner-Watcher. Beobachtet je
Geräte-Ordner neue GDT-Dateien und postet die **rohe Datei** an den nodera-Ingress
(`POST /api/medical-devices/ingest`, Bearer = Geräte-Token). Parsen, Matchen und Strippen
macht **nodera** — der Agent bleibt bewusst dumm.

- **Ein statisches Binary, kein Runtime** auf dem Zielrechner.
- **Polling** statt Datei-Events → robust auf Netzlaufwerken/SMB.
- **Stabilitäts-Check** (Datei erst senden, wenn N Sekunden unverändert).
- Erfolg → `archiv/`, dauerhafte Ablehnung (4xx) → `failed/`, Netz-/Serverfehler → liegen lassen (Retry).
- **Dedupe** per SHA-256; der Server dedupliziert zusätzlich.

## Konfiguration

`config.json` (siehe `config.example.json`):

```json
{
  "base_url": "https://nodera.sovra.health",
  "poll_seconds": 5,
  "stable_seconds": 3,
  "watches": [
    { "folder": "C:\\GDT\\Audiometer", "token": "mdev_…", "pattern": "*.gdt" },
    { "folder": "C:\\GDT\\Blutdruck",  "token": "mdev_…" }
  ]
}
```

Ein Token je Ordner — den bekommst du in nodera unter *Medizinische Messgeräte → Geräte*
beim Anlegen/„Token neu". `pattern` ist optional (leer = alle Dateien).

## Starten

```
./sovra-agent-macos-apple --config config.json      # macOS (Apple Silicon)
sovra-agent-windows-amd64.exe --config config.json  # Windows
```

`--once` scannt einmal und beendet sich (zum Testen).

## Bauen

```
./build.sh          # erzeugt dist/ für Windows/macOS/Linux
go build -o agent . # nur die aktuelle Plattform
```

## Als Dienst (Dauerbetrieb)

- **Windows:** als geplante Aufgabe „Bei Anmeldung/Systemstart" oder via NSSM als Windows-Dienst.
- **macOS:** launchd-`.plist` unter `~/Library/LaunchAgents`.
- **Linux:** systemd-Unit.

(Installer/Code-Signing/Auto-Update sind bewusst v2 — v1 ist das robuste Binary.)
