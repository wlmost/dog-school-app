# Notes T05: Frontend — Frage-IDs durch Formular und Speicher-Payload durchreichen

**Status:** Erledigt durch `dev-typescript`. Letzter offener Task des Changes.

## Geänderte Dateien

1. `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue`
2. `frontend/src/api/anamnesis.ts`

## Änderungen im Detail

### `AnamnesisTemplateFormModal.vue`

- `Question`-Interface (Zeile ~234-241): `id?: number` als erstes Feld
  ergänzt.
- `watch(() => props.template, …)`-Block (Zeile ~287-304): beim Mapping der
  vom Backend gelieferten Fragen (dank T04 vollständig inkl. `questions`-
  Relation) wird jetzt `id: q.id` mit ins gemappte Objekt übernommen. Für
  eine neu geöffnete "Neue Vorlage" (`props.template` ist `undefined`) greift
  weiterhin `resetForm()`, unverändert.
- `save()` (Zeile ~392-401): jedes Payload-Objekt im `questions`-Array
  bekommt jetzt zusätzlich `id: q.id`. Für über `addQuestion()` (Zeile
  320-328) neu hinzugefügte Fragen ist `q.id` `undefined` (das Interface
  legt `id` nirgends bei `addQuestion()` fest) — beim Serialisieren durch
  Axios (Standard-JSON-Transform, kein `transformRequest` in
  `frontend/src/api/client.ts` überschrieben) wird das `id`-Property mit
  Wert `undefined` automatisch weggelassen (siehe Verifikation unten).
- Nebenbei eine vorbestehende trailing-whitespace-Stelle in derselben
  Zeile (`includes(q.question_type) ` → ohne Leerzeichen) entfernt, keine
  funktionale Änderung, unvermeidbarer Nebeneffekt der Zeilenänderung.

### `anamnesis.ts`

- `updateTemplate()`-Parametertyp (Zeile ~118-131): `questions`-Array-Objekt
  um optionales `id?: number` erweitert, damit `vue-tsc -b` das
  durchgereichte Feld aus dem Modal nicht als überzähliges/unbekanntes
  Property ablehnt.
- `createTemplate()` (Zeile 102-116) **nicht** angefasst, wie in der Task
  vorgegeben — dort existiert naturgemäß nie eine `id` (neue Vorlage).

## Verifikation gegen Akzeptanzkriterien

- **Bestehende Frage behält korrekte `id` im PUT-Payload:** Nachvollzogen
  per Code-Trace: `getById()` (T04) lädt die volle `questions`-Relation
  inkl. `id` → `watch`-Block übernimmt `id: q.id` ins Formularobjekt →
  `save()` übernimmt `id: q.id` unverändert ins Payload-Objekt. Kein Schritt
  dazwischen überschreibt oder verwirft `id`.
- **Neue Frage hat keine `id` im Payload:** `addQuestion()` erzeugt ein
  Objekt ohne `id`-Property → TypeScript erlaubt das wegen `id?: number`
  (optional) → `q.id` ist zur Laufzeit `undefined` → im Payload-Mapping
  `id: q.id` ergibt das `id: undefined` im JS-Objekt. Verifiziert, dass
  das beim Request-Body-Serialisieren tatsächlich verschwindet:
  ```
  $ node -e "console.log(JSON.stringify({id: undefined, name: 'x'}))"
  {"name":"x"}
  ```
  `frontend/src/api/client.ts` setzt kein eigenes `transformRequest` (nur
  `baseURL`, `timeout`, Header, Auth-Interceptor), Axios verwendet also
  seine Standard-JSON-Serialisierung (intern äquivalent zu
  `JSON.stringify`) für Objekt-Bodies — das Backend sieht in diesem Fall
  korrekt keinen `id`-Schlüssel für die neue Frage.
- **Entfernte Frage fehlt im Payload:** `removeQuestion(index)` (Zeile
  330-336) macht ein `splice()` auf `form.value.questions` — das Element
  ist danach schlicht nicht mehr im Array und taucht folglich auch nicht
  im `save()`-Payload auf. Das Backend (T03, `syncQuestions()`) erkennt
  jede `id`, die in `existingIds`, aber nicht mehr in `incomingIds`
  vorkommt, als Löschung (außer bei vorhandenen Antworten).
- **`vue-tsc -b` ohne Typfehler:** Teil von `npm run build` — siehe
  "Lokale Checks" unten, grün.
- **Kein Einfluss auf `createTemplate()`:** Datei-Diff bestätigt, dass die
  Funktion `createTemplate()` selbst und ihr Parametertyp (Zeile 102-116)
  unverändert geblieben sind.

## Lokale Checks (Pre-Flight, CLAUDE.md Abschnitt 7.1)

Ausgeführt außerhalb Docker (lokal, macOS/darwin-arm64) — reine
Frontend-Änderung, keine PHP/DB-Umgebung nötig:

- **`npm run lint`:** Skript existiert weiterhin **nicht** in
  `frontend/package.json` (bereits in T04 als vorbestehender
  Environment-Gap dokumentiert, kein T05-Scope).
- **`npx vitest run`:** 12 Testdateien, 128 Tests, alle grün. Keine
  Regression durch die Änderung. Kein bestehender Test deckt
  `AnamnesisTemplateFormModal.vue` gezielt ab — Testabdeckung für T05 ist
  Aufgabe des `tester`-Agenten (Workflow-Schritt 9), nicht Teil dieses
  Dev-Tasks (Präzedenzfall: siehe `task-T04.notes.md`, letzter Absatz).
- **`npm run build`** (`vue-tsc -b && vite build`): grün, keine
  TS-Fehler, keine neuen Warnings. Der von T04 dokumentierte
  esbuild-Plattform-Mismatch (`@esbuild/darwin-arm64` fehlte in
  `node_modules`) trat in dieser Session **nicht** auf — vermutlich, weil
  das Paket bereits durch den T04-Workaround (`npm install
  @esbuild/darwin-arm64@0.27.7 --no-save`) in `node_modules` vorhanden
  war (keine Lockfile-Änderung nötig, `git status` zeigt keine Änderung
  an `package-lock.json`).

## Offene Punkte / Hinweise

- Kein Docker-Daemon in dieser Session verfügbar, daher konnte die
  End-to-End-Konsistenz nicht per echtem HTTP-Request gegen das laufende
  Backend verifiziert werden. Stattdessen: vollständiger Code-Trace durch
  die Kette T04 (Laden) → T05 (Formular-Mapping) → Axios-Serialisierung →
  T03 (Backend-Sync-Logik, `design.md` Abschnitt 4) sowie ein isolierter
  `node -e`-Nachweis für das `undefined`-Verhalten von `JSON.stringify`.
  Aus Code-Sicht ist die Kette lückenlos; ein realer manueller/E2E-Test
  gegen die Docker-Umgebung wird dem `tester`-Agenten (Workflow-Schritt 9)
  empfohlen, um dies auch tatsächlich über die laufende Anwendung zu
  bestätigen.
- Das `AnamnesisTemplate`-Interface in `frontend/src/api/anamnesis.ts`
  (Zeile 3-12) deklariert kein `questions`-Feld (nur `questionsCount`),
  obwohl `getById()`/`show()` laut Backend tatsächlich die volle
  `questions`-Relation liefert (siehe T04-Notes). Das ist ein
  vorbestehender Typ-Lücken-Befund, kein T05-Bug: `Props.template` in
  `AnamnesisTemplateFormModal.vue` ist bewusst `any` typisiert (Zeile
  250), wodurch der Zugriff auf `newTemplate.questions` trotzdem
  typkorrekt kompiliert. Nicht behoben, da außerhalb des in `tasks.md`
  vorgegebenen T05-Scopes (nur die 4 dort genannten Punkte).

## Fazit zum Gesamt-Change

T05 ist der letzte offene Task in `fix-anamnesis-template-questions-not-saved`
(T01-T04 bereits abgehakt laut `tasks.md`). Mit diesem Task ist die
komplette Kette Backend (`questionsCount`, ID-basierte Update-Synchronisation)
und Frontend (Vollständiges Nachladen vor dem Editor, ID-Durchreichung durch
Formular und Save-Payload) implementiert. Aus Entwickler-Sicht ist der Change
bereit für Review (`reviewer`) und Tests (`tester`), Workflow-Schritt 9.
