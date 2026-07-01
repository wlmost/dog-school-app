# Tasks: favicon-ico-upload-bug

## T01: Favicon-Validierungsregel korrigieren (Backend)

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Requests/UpdateSettingsRequest.php` (ändern)
  - `backend/tests/Feature/SettingsValidationTest.php` (neu erstellen)
- **Abhängigkeiten:** keine
- **Beschreibung:**
  In `UpdateSettingsRequest::rules()` wird Zeile 45 angepasst:
  `image` wird durch `file` ersetzt. Die übrigen Regeln (`sometimes`,
  `nullable`, `max:512`, `mimes:png,ico`) bleiben unverändert.
  Anschließend wird eine neue Pest-Testdatei
  `tests/Feature/SettingsValidationTest.php` erstellt, die
  die Validierungsregeln für `company_favicon` abdeckt.
  Details siehe `design.md` Abschnitt "Bug 1".

- **Akzeptanzkriterien:**
  - [x] `'company_favicon' => ['sometimes', 'nullable', 'file', 'max:512', 'mimes:png,ico']`
        steht in Zeile 45 (oder dessen Nachfolger nach dem Edit).
  - [x] `company_logo` behält weiterhin `image` (kein unbeabsichtigter Seiteneffekt).
  - [x] Pest-Test: Upload einer `.ico`-Datei (MIME `image/x-icon`) → HTTP 422 bleibt aus.
  - [x] Pest-Test: Upload einer `.png`-Datei → wird weiterhin akzeptiert.
  - [x] Pest-Test: Upload einer `.exe`-Datei → wird abgelehnt (422).
  - [x] Pest-Test: Upload einer Datei > 512 KB → wird abgelehnt (422).
  - [x] `composer qa` läuft ohne Fehler (lint + stan + compat-check + pest).
  - [x] `composer compat-check` meldet keine PHP 8.3/8.4-Verstöße.

---

## T02: Fehlerstate in SettingsView.vue aufteilen (Frontend)

- **Agent:** dev-javascript
- **Dateien:**
  - `frontend/src/views/SettingsView.vue` (ändern)
  - `frontend/src/views/SettingsView.test.ts` (neu erstellen)
- **Abhängigkeiten:** keine (kann parallel zu T01 bearbeitet werden)
- **Beschreibung:**
  Die einzige `error`-Ref in `SettingsView.vue` wird in zwei getrennte Refs
  aufgeteilt: `loadError` (steuert die `v-else-if`-Bedingung und damit die
  Sichtbarkeit des Formulars) und `saveError` (wird innerhalb des Formulars
  als Inline-Fehlermeldung angezeigt, schaltet das Formular nicht aus).
  Template und Script werden entsprechend angepasst.
  Details (Vorher/Nachher, Platzierung, Sequenzdiagramm) siehe `design.md`
  Abschnitt "Bug 2".

  Vitest-Tests decken ab:
  - Formular bleibt nach einem Speicherfehler sichtbar.
  - `saveError` wird bei einem API-Fehler korrekt befüllt und
    unterhalb der Buttons angezeigt.
  - `loadError` blendet das Formular aus (bisheriges Verhalten bleibt erhalten).

- **Akzeptanzkriterien:**
  - [x] `const error` ist aus dem `<script setup>`-Block entfernt.
  - [x] `const loadError = ref<string | null>(null)` existiert und wird in
        `loadSettings()` befüllt.
  - [x] `const saveError = ref<string | null>(null)` existiert und wird in
        `saveSettings()` befüllt.
  - [x] Template-Conditional: `v-else-if="loadError"` (nicht `error`).
  - [x] `saveError`-Block ist im DOM innerhalb von `<form v-else>` vorhanden
        und folgt dem gleichen visuellen Stil wie der `successMessage`-Block.
  - [x] Vitest-Test: Beim Mocken eines 422-API-Fehlers in `saveSettings()`
        bleibt `<form>` im gerenderten DOM.
  - [x] Vitest-Test: `saveError`-Meldung ist nach dem API-Fehler sichtbar.
  - [x] Vitest-Test: Beim Mocken eines 500-API-Fehlers in `loadSettings()`
        wird das Formular durch den Fehlerblock ersetzt.
  - [ ] `npm run lint` läuft ohne Fehler.
  - [x] `npm run test` läuft ohne Fehler.
  - [x] `npm run build` läuft ohne Warnings oder Fehler.
