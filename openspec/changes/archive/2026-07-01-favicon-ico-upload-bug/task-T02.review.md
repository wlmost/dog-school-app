# Review: T02

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

(keine)

## Sollte (vor Merge erledigen, kann diskutiert werden)

(keine)

## Könnte (optional, Verbesserung)

- **[Testkonventionen / Hinweis]** `frontend/src/views/SettingsView.test.ts`: TESTING.md definiert Konventionen ausschließlich für PHP/Pest. Es existieren keine schriftlich festgelegten Vitest-Konventionen für dieses Projekt (Gruppen, Namensschema, Assertion-Stil). Die vorliegenden Vitest-Tests folgen Standard-Idiomen (`describe`, `it`, `expect`, `vi.mock`), was sachlich korrekt ist. Empfehlung: Bei User-Gate 2 oder einem separaten Change Vitest-Konventionen in TESTING.md ergänzen.

## Lob

- Vollständige Ablösung aller `error`-Referenzen: `v-else-if="error"` → `v-else-if="loadError"` (Zeile 16), `{{ error }}` → `{{ loadError }}` (Zeile 17), beide Ref-Deklarationen korrekt getrennt (Zeilen 565–566), beide catch-Zweige konsistent befüllt (Zeile 608 für `loadError`, Zeile 652 für `saveError`). Kein veralteter `error`-Verweis im Template oder Script verblieben.
- `saveError.value = null` wird am Anfang von `saveSettings()` zurückgesetzt (Zeile 620), nicht erst im Fehlerfall. Damit bleibt keine alte Fehlermeldung beim Folgeversuch sichtbar — das ist die korrekte Reset-Reihenfolge.
- Der `saveError`-Block (Zeilen 386–392) folgt dem visuellen Stil des `successMessage`-Blocks unmittelbar davor. Konsistenz mit dem bestehenden UI-Muster.
- `loadSettings()` setzt `loadError.value = null` am Anfang (Zeile 576) — korrekt, damit ein manuell ausgelöster Reload (Zurücksetzen-Button) den alten Fehlerzustand löscht.
- Vitest-Tests decken alle drei Kernszenarien der Akzeptanzkriterien ab: 422-Fehler lässt Formular im DOM (Test 1), `saveError`-Text erscheint (Test 2), Fehler wird beim nächsten Versuch zurückgesetzt (Test 3), 500-Fehler versteckt Formular (Test 4), `loadError`-Text erscheint (Test 5). Mocks für `usePricingItems`, `EmailTemplateEditor` und `PricingItemForm` grenzen den Testgegenstand sauber ein.
