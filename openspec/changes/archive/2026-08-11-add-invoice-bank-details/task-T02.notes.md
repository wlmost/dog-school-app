# Task T02 — Settings-Frontend: neue Formularfelder

## Umgesetzt

- **Datei:** `frontend/src/views/SettingsView.vue`
- `formData` (Objekt beginnt Zeile 526): fünf neue Properties nach
  `company_registration_number` und vor `company_small_business` ergänzt:
  ```ts
  company_bank_account_holder: '',
  company_bank_name: '',
  company_bank_iban: '',
  company_bank_bic: '',
  company_payment_term_weeks: 2 as number | string,
  ```
- Template, "Stammdaten"-Card: neuer Unterabschnitt "Bankverbindung"
  zwischen dem schließenden `</div>` des bestehenden
  `grid grid-cols-1 md:grid-cols-2`-Blocks (der u. a.
  `company_registration_number` enthält) und dem "Small Business
  Regulation"-Block eingefügt. Aufbau analog zum bestehenden
  "SMTP Einstellungen"-Unterabschnitt (`border-t … pt-6` + `<h3>`-Überschrift
  + eigenes 2-spaltiges Grid), nicht in das bestehende Stammdaten-Grid
  gequetscht, um dem etablierten Muster für thematische Unterabschnitte
  innerhalb der Card zu folgen (siehe SMTP-Block, ehem. Zeile 274-348).
  Fünf Felder:
  - Kontoinhaber — `type="text"`, `v-model="formData.company_bank_account_holder"`
  - Bankname — `type="text"`, `v-model="formData.company_bank_name"`
  - IBAN — `type="text"`, `v-model="formData.company_bank_iban"`
  - BIC — `type="text"`, `v-model="formData.company_bank_bic"`
  - Zahlungsziel (Wochen) — `type="number"`, `min="1"`, `max="52"`,
    `v-model.number="formData.company_payment_term_weeks"` (analog zum
    bestehenden `smtp_port`-Feld)

## Verifikation `loadSettings()`/`saveSettings()`

Wie in tasks.md behauptet, mussten beide Methoden **nicht** angepasst
werden — selbst geprüft:
- `loadSettings()` (Zeile ~574-613 vor der Änderung) iteriert generisch
  über `allSettings.forEach((setting) => { if (setting.key in
  formData.value) { … } })` — jeder neue Key in `formData` wird automatisch
  berücksichtigt, sofern er unter den vorhandenen `if`-Zweigen landet
  (`file`, `boolean`/`company_small_business`, sonst direkte Zuweisung).
  Alle fünf neuen Keys fallen in den generischen `else`-Zweig
  (`formData.value[setting.key] = setting.value`), inkl.
  `company_payment_term_weeks` — Backend liefert dafür laut T01/design.md
  einen `integer`-Wert (kein Boolean-Sonderfall nötig).
- `saveSettings()` (Zeile ~618-657 vor der Änderung) iteriert generisch
  über `Object.entries(formData.value)` und übernimmt jeden Key 1:1 in das
  zu sendende `settings`-Objekt (Ausnahmen nur für `null`-Werte und leeres
  `smtp_password`) — die fünf neuen Keys werden automatisch mitgesendet.
- `frontend/src/api/settings.ts:5` — `Setting.value: string | number |
  boolean | null`, kompatibel mit dem `formData`-Typ `number | string` für
  `company_payment_term_weeks`.

Keine Änderung an `loadSettings()`/`saveSettings()` nötig — Behauptung aus
tasks.md bestätigt.

## Lokale Checks

- `npm run lint` — 0 Errors, nur bereits vorher im gesamten Projekt
  vorhandene Style-Warnings (u. a. `vue/html-self-closing`,
  `vue/max-attributes-per-line`); die neuen Felder in `SettingsView.vue`
  folgen exakt demselben Warn-Muster wie die umliegenden Bestandsfelder
  (z. B. `Disallow self-closing on HTML void elements (<input/>)`), keine
  neuen Fehlerkategorien eingeführt.
- `npx vitest run` (CI=true) — 20 Test-Dateien, 209 Tests, alle grün.
  (Hinweis: lokale Node-`node_modules` waren initial mit
  `@esbuild/linux-arm64` statt `@esbuild/darwin-arm64` verlinkt —
  Umgebungsproblem, nicht Teil des Diffs; behoben via
  `npm install --no-save @esbuild/darwin-arm64`, `package-lock.json`
  unverändert, siehe `git status` vor/nach Fix.)
- `npm run build` (`vue-tsc -b && vite build`) — läuft ohne TypeScript-
  Fehler und ohne Build-Fehler durch, Chunk `SettingsView-*.js` wird
  erzeugt.

## Nachtrag (Tester-Runde)

Der `tester`-Agent hat nach Abschluss dieser Task fünf neue Fälle im
`describe('Bankverbindung-Formularfelder')`-Block in
`frontend/src/views/SettingsView.test.ts` ergänzt (Sichtbarkeit/Labels,
v-model-Bindung, Anzeige geladener Werte, Default-Fallback,
`saveSettings()`-Payload) — diese Datei war zuvor nicht Teil dieser Notes,
da zum Zeitpunkt der T02-Implementierung noch keine dedizierten Tests für
die neuen Felder existierten. Siehe `task-report.test-add-invoice-bank-details.md`
für Details.

## Anmerkungen

- Backend-Änderungen (T01: `UpdateSettingsRequest.php`,
  `SettingsSeeder.php`, `SettingsController.php`,
  `SettingsBankDetailsApiTest.php`) stammen von einem parallelen Agenten
  auf demselben Branch und wurden hier nicht angefasst — laut
  Task-Auftrag keine Überschneidung mit `SettingsView.vue`.
- Keine `dist/`-Artefakte committet (git-ignored), `npm run build` diente
  nur als Lauffähigkeits-Check gemäß CLAUDE.md Abschnitt 5/7.1.
