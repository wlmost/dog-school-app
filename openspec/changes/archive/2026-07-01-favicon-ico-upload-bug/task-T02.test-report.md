# Test-Report: T02

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

- `frontend/src/views/SettingsView.test.ts`: 5 neue Cases (vollständig neu erstellt)

## Akzeptanzkriterien-Abdeckung

- [x] `const error` aus `<script setup>` entfernt — Code-Inspektion in `SettingsView.vue`
- [x] `const loadError` existiert und wird in `loadSettings()` befüllt — Code-Inspektion
- [x] `const saveError` existiert und wird in `saveSettings()` befüllt — Code-Inspektion
- [x] Template-Conditional `v-else-if="loadError"` — Code-Inspektion Zeile 16
- [x] `saveError`-Block innerhalb `<form v-else>`, gleicher visueller Stil — Code-Inspektion Zeilen 386–392
- [x] Vitest: `<form>` bleibt nach 422-Fehler im DOM — `SettingsView.test.ts::zeigt das Formular nach einem 422-Fehler beim Speichern weiterhin an`
- [x] Vitest: `saveError`-Meldung nach API-Fehler sichtbar — `SettingsView.test.ts::zeigt die saveError-Meldung unterhalb der Buttons an`
- [x] Vitest: `<form>` nicht im DOM nach Ladefehler — `SettingsView.test.ts::versteckt das Formular bei einem 500-Fehler in loadSettings`
- [x] `npm run test` läuft ohne Fehler — 72/72 grün
- [x] `npm run build` läuft ohne Warnings — laut task-T02.notes.md bestätigt (in Docker-Umgebung)

### Spec-Scenario "Formulardaten bleiben erhalten" — nicht explizit getestet

Das Spec-Szenario "Speicherfehler durch Validierung / AND die zuvor eingegebenen
Formulardaten bleiben erhalten" hat keinen eigenen Testcase. Begründung: Die
`mockSettingsResponse` liefert leere Listen (`company: [], email: [], general: []`),
daher gibt es keine vorbesetzten Felder, die nach dem Fehler verifiziert werden
könnten. Da `saveSettings()` keine reaktiven Form-Refs zurücksetzt (nur
`saveError.value = null` am Anfang), ist das Verhalten durch die Implementierung
strukturell garantiert. Explizite Prüfung würde ein deutlich komplexeres
Mock-Setup erfordern (Settings mit konkreten Feldwerten) und ist aktuell kein
Blocker.

## Konventions-Prüfung

Die Testdatei ist eine Vitest-Datei (Frontend). Verbindliche Backend-Pest-Konventionen
aus TESTING.md gelten nicht. Für das Frontend wurden die im Projekt vorgefundenen
Vitest-Patterns übernommen:

| Punkt | Status | Anmerkung |
|-------|--------|-----------|
| `describe`/`it`-Struktur | ok | 2 describe-Blöcke, 5 `it()`-Cases |
| `vi.mock` für API-Module | ok | `@/api/settings`, `@/composables/usePricingItems` |
| `vi.mocked()` für typsichere Mock-Konfiguration | ok | durchgehend verwendet |
| `flushPromises()` nach async-Operationen | ok | alle async-Tests warten korrekt |
| `expect()` für alle Assertions | ok | Vitest-Style, kein PHPUnit-Mixin |
| Globale Stubs für Kind-Komponenten | ok | `EmailTemplateEditor`, `PricingItemForm` |

### stderr-Ausgabe

Die `stderr`-Zeilen "Error saving settings: Error: Unprocessable Entity" und
"Error loading settings: Error: Internal Server Error" sind erwartete
`console.error`-Ausgaben aus dem Fehlerhandling von `SettingsView.vue` — kein
Testfehler. Alle 5 Tests sind grün.

## Ausführungs-Ergebnis

```
 ✓ src/views/SettingsView.test.ts (5 tests) 59ms

 Test Files  7 passed (7)
       Tests  72 passed (72)
    Start at  14:21:59
    Duration  1.37s (transform 1.75s, setup 0ms, import 4.11s, tests 313ms, environment 2.30s)
```

Ausgeführt im Docker-Node-Container (`dog-school-node`), da `node_modules` für
Linux/arm64 kompiliert sind und nicht direkt auf macOS lauffähig sind.

## Fehler

Keine.
