# Medizinische Messgeräte (`medical-devices`)

Praxis-Modul für **medizinische Messgeräte** (Audiometer, Sehtest, Blutdruck, Spirometrie …).

Nimmt Gerätemessungen an (v1: **GDT** via lokalem Windows-Agent), matcht sie auf einen Patienten,
lässt den Arzt bestätigen, **strippt die Identität** und schiebt nur den Wert **patient-owned** an
den sovra-Core. Die Patientenakte liest zurück aus dem Core.

## Prinzip

- **Vertrag + Speicher = Core.** Der Core besitzt Definition (LOINC/Einheiten/Komponenten) und die Werte.
- **Identität + Session = nodera.** Der Core bleibt blind — nur `subject_uuid` + Werte, nie Name/Nummer.
- **Transport = Detail.** Der Windows-Agent postet die rohe GDT-Datei; alles Weitere (parsen, matchen,
  strippen, bestätigen) macht dieses Modul.

## Fluss

```
Gerät → GDT-Datei im Ordner → Windows-Agent (Geräte-Token je Ordner)
   → POST /api/medical-devices/ingest  (Bearer Geräte-Token)
   → parsen → auf patient.lab_number matchen → Eingang (pending)
   → Arzt bestätigt → PII strippen → CoreObservationClient → POST /api/observation (Consumer-Token)
   → Patientenakte liest bestätigte Observation ← Core
```

## Konfiguration

```
SOVRA_CORE_URL=https://core.sovra.health
SOVRA_CORE_TOKEN=<Consumer-Token von nodera, in der Core-Sovereignty ausgestellt>
```

Solange kein Token gesetzt ist, bleibt die Weiterleitung inert (Messungen bleiben im Eingang stehen).
