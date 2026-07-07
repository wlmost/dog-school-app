# Notes T04: Frontend — Vollständige Vorlagen-Details vor dem Editor nachladen

**Status:** Erledigt durch `dev-typescript`.

## Vorgeschichte (Kontext für Nachvollziehbarkeit)

Ein erster Versuch mit `dev-javascript` wurde abgebrochen, weil
`AnamnesisView.vue` (wie das gesamte Frontend unter `frontend/src/`)
durchgängig `<script setup lang="ts">` verwendet und damit echtes
TypeScript ist, nicht reines JavaScript. `CLAUDE.md` wurde inzwischen vom
User korrigiert: Das Frontend liegt unter `frontend/src/` (nicht
`resources/js/`) und `dev-typescript` ist der korrekte Agent für alle
`.vue`/`.ts`-Dateien dort. Dieser Durchlauf wurde entsprechend mit
`dev-typescript` neu gestartet; die vorherige Abbruch-Notiz wurde durch
diese Datei ersetzt.

## Geänderte Datei

`frontend/src/views/anamnesis/AnamnesisView.vue`

`openTemplateModal(template?)` wurde `async` gemacht. Bei vorhandenem
`template`-Argument wird jetzt zuerst `anamnesisTemplatesApi.getById(template.id)`
aufgerufen und das Ergebnis (inkl. `questions`-Relation, dank `show()`
im Backend, siehe T02/`AnamnesisTemplateController.php:102-109`) als
`selectedTemplate.value` gesetzt, **bevor** der Modal geöffnet wird.
Schlägt der Request fehl, wird `handleApiError()` aufgerufen und die
Funktion kehrt zurück, **ohne** `showTemplateModal.value = true` zu
setzen — der Modal bleibt geschlossen.

```ts
async function openTemplateModal(template?: AnamnesisTemplate) {
  if (template) {
    try {
      selectedTemplate.value = await anamnesisTemplatesApi.getById(template.id)
    } catch (error) {
      handleApiError(error, 'Fehler beim Laden der Vorlagendetails')
      return
    }
  } else {
    selectedTemplate.value = null
  }
  showTemplateModal.value = true
}
```

Der "Neue Vorlage"-Button (`@click="openTemplateModal()"`, ohne Argument)
ist unverändert vom `if (template)`-Zweig betroffen und geht weiterhin
direkt in den `else`-Zweig (`selectedTemplate.value = null`) — kein
Nachlade-Versuch, kein API-Call.

Die Aufrufstellen im Template (`@click="openTemplateModal(template)"`
Zeile 156, `@click="openTemplateModal()"` Zeile 95/141) mussten nicht
angepasst werden — Vue unterstützt `async`-Handler in `@click`
transparent (kein Awaiten im Template nötig, keine UI-Blockierung durch
fehlendes Await; das ist für dieses UI unkritisch, da der Modal ohnehin
erst nach Abschluss des Promises sichtbar wird).

## Keine Änderung an

- `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue` —
  das ist T05 (Frage-`id`-Durchreichung). `Props.template` ist dort
  bereits als `template?: any` typisiert, sodass die Zuweisung des
  vollständigen `AnamnesisTemplate`-Objekts (inkl. der zur Laufzeit
  vorhandenen, aber im TS-Interface nicht deklarierten `questions`-Relation)
  keinen Typfehler auslöst.
- `frontend/src/api/anamnesis.ts` — `getById()` existierte bereits
  unverändert (Zeile 78-81) und wurde nur aufgerufen, nicht angepasst.

## Verifikation gegen Akzeptanzkriterien

- **Bearbeiten-Klick zeigt echte Fragen:** `getById()` ruft
  `GET /api/v1/anamnesis-templates/{id}` auf, dessen `show()`-Methode
  laut `design.md` Abschnitt 6 die `questions`-Relation eager lädt.
  `selectedTemplate.value` enthält nach erfolgreichem Request also die
  volle Relation, die an `AnamnesisTemplateFormModal` durchgereicht wird.
  (End-to-End mit tatsächlich befüllten Fragen im Formular ist erst nach
  T05 vollständig verifizierbar, da das Formular Frage-`id`s aktuell noch
  nicht durchreicht — das ist explizit T05s Scope. T04 stellt sicher,
  dass die Rohdaten der Fragen überhaupt beim Formular ankommen.)
- **Kachel-Liste zeigt weiter `questionsCount`:** Unverändert — die Liste
  wird weiterhin über `loadTemplates()`/`anamnesisTemplatesApi.getAll()`
  befüllt, das von T04 nicht angefasst wurde.
- **Fehlschlag → kein Modal-Öffnen + Toast:** `try/catch` fängt jeden
  Fehler aus `getById()` ab, ruft `handleApiError()` auf (bereits
  importiert, Zeile 208) und `return`et vor
  `showTemplateModal.value = true`.
- **"Neue Vorlage" ohne Nachlade-Versuch:** Kein `template`-Argument →
  `if (template)` ist `false` → `else`-Zweig, kein API-Call.

## Lokale Checks (Pre-Flight, CLAUDE.md Abschnitt 7.1)

Ausgeführt außerhalb Docker (lokal, macOS/darwin-arm64), da für diese
reine Frontend-Änderung keine PHP/DB-Umgebung nötig ist:

- **`npm run lint`:** Skript existiert **nicht** in
  `frontend/package.json` (kein ESLint im Frontend konfiguriert,
  vorbestehender Environment-Gap, nicht durch T04 verursacht). Nicht
  eigenmächtig ein Lint-Setup ergänzt, da außerhalb des T04-Scopes.
- **`npx vue-tsc -b --force`:** grün, keine Typfehler.
- **`npm run build`** (`vue-tsc -b && vite build`): grün, kein
  TS-Fehler, keine neuen Warnings. **Hinweis zur lokalen Ausführung:**
  `node_modules` in diesem Checkout enthielt initial nur
  `@esbuild/linux-arm64` (vermutlich aus einem Linux-Container kopiert),
  was den Build auf macOS/`darwin-arm64` mit einem Plattform-Fehler
  abbrach. Kein Code- oder Lockfile-Problem — behoben durch
  `npm install @esbuild/darwin-arm64@0.27.7 --no-save` (keine Änderung
  an `package.json`/`package-lock.json`, verifiziert per `git status`).
  Danach lief `npm run build` fehlerfrei durch (`dist/` erzeugt, danach
  wieder gelöscht, nicht committet).
- **`npx vitest run`:** 12 Testdateien, 128 Tests, alle grün. Kein
  bestehender Test deckt `AnamnesisView.vue` gezielt ab (kein
  `*Anamnesis*.spec.ts` im Frontend gefunden) — Testabdeckung für T04
  ist Aufgabe des `tester`-Agenten (Schritt 9 des Workflows), nicht
  Teil dieses Dev-Tasks.

## Offene Punkte / Hinweise für nachfolgende Schritte

- T05 (Frage-`id`-Durchreichung in `AnamnesisTemplateFormModal.vue` und
  `api/anamnesis.ts`) ist weiterhin offen und notwendig, damit das
  Backend (T03) bestehende Fragen per `id` erkennen kann — T04 liefert
  nur die Rohdaten, das Formular mappt `id` aktuell noch nicht mit.
- Der lokale esbuild-Plattform-Fix (`npm install @esbuild/darwin-arm64
  --no-save`) ist nicht persistent im Repo (keine Lockfile-Änderung) und
  müsste bei jedem frischen macOS-Checkout wiederholt werden, falls
  `node_modules` erneut aus einer Linux-Umgebung übernommen wird — kein
  T04-spezifisches Problem, aber ggf. für den `tester`-Agenten relevant,
  falls dieser denselben Checkout weiterverwendet.
