# Task T02 — Notes: Fehlerstate in SettingsView.vue aufteilen

## Implementierung

### Geänderte Datei: `frontend/src/views/SettingsView.vue`

Alle Änderungen sind rein lokale State-Umbenennungen ohne Auswirkung auf
API-Verträge oder externe Schnittstellen.

**Template (Zeilen 15–18):**
- `v-else-if="error"` → `v-else-if="loadError"`
- `{{ error }}` → `{{ loadError }}`
- Kommentar aktualisiert: "Load Error State"

**Template (nach successMessage-Block, Zeilen 386–392):**
- Neuer `saveError`-Block eingefügt, direkt nach dem `successMessage`-Block,
  vor dem schließenden `</form>`-Tag.
- Gleicher visueller Stil wie der `successMessage`-Block (rot statt grün):
  `class="bg-red-50 border border-red-200 rounded-lg p-4"`

**Script (war Zeile 557, jetzt Zeilen 565–566):**
- `const error = ref<string | null>(null)` entfernt
- Durch zwei separate Refs ersetzt:
  - `const loadError = ref<string | null>(null)`
  - `const saveError = ref<string | null>(null)`

**`loadSettings()` (catch-Block):**
- `loadError.value = null` am Funktionsanfang (Reset)
- `loadError.value = err.response?.data?.message || '...'` im catch

**`saveSettings()` (catch-Block):**
- `saveError.value = null` am Funktionsanfang (Reset)
- `saveError.value = err.response?.data?.message || '...'` im catch

### Neue Datei: `frontend/src/views/SettingsView.test.ts`

5 Vitest-Tests in 2 `describe`-Blöcken:

**"Speicherfehler lässt Formular sichtbar":**
1. Form bleibt nach 422-Fehler sichtbar (`wrapper.find('form').exists() === true`)
2. saveError-Meldung erscheint im Text des Formulars
3. saveError wird beim nächsten Speicherversuch zurückgesetzt (zweiter Versuch
   erfolgreich → Fehlertext nicht mehr sichtbar)

**"Ladefehler blendet Formular aus":**
4. Form-Element nicht im DOM nach 500-Fehler in loadSettings
5. loadError-Meldung erscheint im Text

**Mock-Strategie:**
- `@/api/settings` vollständig gemockt mit `vi.fn()`-Methoden
- `@/composables/usePricingItems` gemockt mit statischen Werten
  (Pricing-Bereich ist nicht Testgegenstand)
- `EmailTemplateEditor` und `PricingItemForm` als leere Stubs

## Skeptiker-Befunde

Alle Zeilenangaben aus `verification.md` lagen exakt an den beschriebenen
Stellen. Keine Abweichungen von der Spec festgestellt.

## Checks

- `npm run test -- --run` (Docker): **72/72 Tests grün** (inkl. 5 neue)
- `npm run build` (Docker): **Erfolgreich, keine Warnings oder Fehler**
