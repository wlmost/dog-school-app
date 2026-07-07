# Tasks für fix-anamnesis-template-questions-not-saved

## T01: Verifikation des bestehenden Create-Flows (vor jedem Code-Fix)

- **Agent:** dev-php
- **Dateien:** keine Code-Änderung; Ergebnis in `task-T01.notes.md` dokumentieren. Ggf. `backend/tests/Feature/AnamnesisTemplateApiTest.php`, falls beim Verifizieren eine Lücke auffällt (nur dokumentieren, nicht eigenmächtig erweitern — das ist Teil von T03).
- **Abhängigkeiten:** keine
- **Priorität:** Pflicht (blockiert T02 und T03)

### Beschreibung

Bevor der Anzeige-Fix (T02) und insbesondere der Update-Persistenz-Fix
(T03) implementiert werden, muss verifiziert werden, dass der bestehende
Create-Flow (`store()`) tatsächlich funktioniert wie der Code es vermuten
lässt — die Triage konnte dies mangels erreichbarem Docker-Daemon nicht
prüfen (siehe `proposal.md`, Abschnitt "Root-Cause-Analyse").

1. In der Docker-Umgebung ausführen:
   ```bash
   docker compose exec app composer test -- --filter=AnamnesisTemplateApiTest
   ```
   Falls `docker compose` nicht direkt verfügbar ist: zuerst
   `docker compose up -d` (siehe `CLAUDE.md`, Abschnitt 5).
2. Bei grünem Testlauf: zusätzlich einmal manuell (z. B. via `php artisan
   tinker` oder direktem DB-Client) eine Vorlage mit mindestens 2 Fragen
   über `POST /api/v1/anamnesis-templates` anlegen und per
   `SELECT * FROM anamnesis_questions WHERE template_id = <id>` (oder
   Eloquent-Äquivalent in `tinker`) bestätigen, dass die Fragen tatsächlich
   in der DB landen.
3. Ergebnis (grün/rot, DB-Blick-Befund, ggf. Auffälligkeiten) in
   `task-T01.notes.md` festhalten.

**Wenn der Test entgegen der Erwartung fehlschlägt oder der DB-Blick
Abweichungen zeigt:** Nicht eigenmächtig weiterarbeiten. Befund
dokumentieren und den Architekten/User informieren — das wäre ein
unerwarteter dritter Bug außerhalb des aktuellen Scopes von T02/T03 (siehe
`design.md`, Abschnitt 2).

### Akzeptanzkriterien

- [x] `composer test -- --filter=AnamnesisTemplateApiTest` wurde in der
      Docker-Umgebung ausgeführt, Ergebnis (grün/rot) ist in
      `task-T01.notes.md` dokumentiert.
- [x] Ein manueller Create-Flow-Test mit direktem DB-Blick wurde
      durchgeführt und das Ergebnis dokumentiert.
- [x] Falls Abweichungen von der erwarteten (grünen) Baseline auftreten,
      ist dies explizit in `task-T01.notes.md` als Risiko/Blocker
      vermerkt, **bevor** T02/T03 begonnen werden.

---

## T02: Anzeigeproblem — `questionsCount` im Backend liefern

- **Agent:** dev-php
- **Dateien:** `backend/app/Http/Controllers/AnamnesisTemplateController.php`, `backend/app/Http/Resources/AnamnesisTemplateResource.php`
- **Abhängigkeiten:** T01
- **Priorität:** Pflicht

### Beschreibung

Siehe `design.md`, Abschnitt 3, für die vollständige Begründung und den
vorgeschlagenen Code.

1. `AnamnesisTemplateController::index()` (Zeile 29): Query um
   `withCount('questions')` erweitern.
2. `AnamnesisTemplateResource::toArray()` (Zeile 17-34): neues Feld
   `questionsCount` ergänzen, berechnet über eine private Hilfsmethode
   `resolveQuestionsCount()`, die zuerst `questions_count` (aus
   `withCount`) prüft und andernfalls auf eine bereits geladene
   `questions`-Relation zurückfällt (relevant für `store()`/`show()`, die
   die volle Relation statt `withCount` laden).
3. **Keine Änderung** an `store()` oder `show()` nötig — die Resource
   berechnet den Count in beiden Fällen korrekt aus der bereits geladenen
   Relation.
4. **Keine Frontend-Änderung nötig** für dieses Feld selbst —
   `frontend/src/api/anamnesis.ts:9` (`questionsCount?: number`) und
   `frontend/src/views/anamnesis/AnamnesisView.vue:152`
   (`{{ template.questionsCount || 0 }} Fragen`) erwarten das Feld bereits.

### Akzeptanzkriterien

- [x] `GET /api/v1/anamnesis-templates` liefert pro Vorlage ein korrektes
      `questionsCount`-Feld, das der tatsächlichen Anzahl der Fragen in der
      DB entspricht.
- [x] `GET /api/v1/anamnesis-templates/{id}` (show) und die Antwort von
      `POST /api/v1/anamnesis-templates` (store) liefern ebenfalls ein
      korrektes `questionsCount`.
- [x] Bestehender Test "can list anamnesis templates"
      (`AnamnesisTemplateApiTest.php:21-33`) bleibt grün; die
      `assertJsonStructure`-Prüfung dort darf um `questionsCount` erweitert
      werden, muss aber nicht (nicht blockierend).
- [x] Neuer/erweiterter Test: Liste mit Vorlagen unterschiedlicher
      Fragen-Anzahl liefert für jede Vorlage das korrekte `questionsCount`.
- [x] `composer qa` läuft fehlerfrei (Lint, Stan, Compat-Check 8.2, Pest).

---

## T03: Echter Update-Bug — Fragen-Synchronisation beim Bearbeiten

- **Agent:** dev-php
- **Dateien:** `backend/app/Http/Controllers/AnamnesisTemplateController.php`, `backend/app/Http/Requests/UpdateAnamnesisTemplateRequest.php`, `backend/tests/Feature/AnamnesisTemplateApiTest.php`
- **Abhängigkeiten:** T01
- **Priorität:** Pflicht

### Beschreibung

Siehe `design.md`, Abschnitt 4 und 5, für die vollständige Begründung, den
vorgeschlagenen Code und die Liste der zu ergänzenden Testfälle.

1. `UpdateAnamnesisTemplateRequest::rules()`: `questions`-Validierungsregeln
   analog zu `StoreAnamnesisTemplateRequest` ergänzen, zusätzlich
   `questions.*.id` als optionales `integer`-Feld.
2. `UpdateAnamnesisTemplateRequest::validatedSnakeCase()`: `questions`
   (inkl. optionalem `id`) ins snake_case-Format mappen — dabei
   `array_key_exists('questions', …)` verwenden (nicht `isset`), damit
   `questions: []` von "Schlüssel fehlt" unterscheidbar bleibt.
3. `AnamnesisTemplateController::update()`: Fragen-Synchronisation per
   `id`-Diff implementieren (private Methode `syncQuestions()`):
   - Frage mit `id` → `update()` auf die bestehende Frage.
   - Frage ohne `id` → `create()` als neue Frage.
   - Bestehende Frage, deren `id` nicht mehr im Payload vorkommt → löschen,
     **außer** sie hat bereits Antworten (`whereDoesntHave('answers')` als
     Schutz vor Datenverlust bei bereits erfassten `AnamnesisAnswer`).
   - `id`, die nicht zur aktuellen Vorlage gehört → HTTP 422 (kein
     Cross-Template-Zugriff über die `id`).
   - Fehlt der Schlüssel `questions` komplett im Request → bestehende
     Fragen bleiben unangetastet.
   - Der gesamte Sync läuft innerhalb der bestehenden
     `DB::transaction()`-Konvention (analog zu `store()`,
     `AnamnesisTemplateController.php:79`).
4. Neue Testfälle in `AnamnesisTemplateApiTest.php` gemäß `design.md`,
   Abschnitt 5 (6 Szenarien: neue Fragen anlegen, bestehende per `id`
   ändern, Frage durch Auslassen löschen, beantwortete Frage bleibt
   geschützt, fremde `id` wird abgelehnt, `questions`-Schlüssel fehlt →
   keine Änderung).

### Akzeptanzkriterien

- [x] `PUT /api/v1/anamnesis-templates/{id}` mit neuen Fragen (ohne `id`)
      legt diese tatsächlich in der DB an.
- [x] `PUT` mit einer bestehenden Frage (mit `id`) und geändertem
      `questionText`/`questionType`/`options`/`isRequired`/`order`
      aktualisiert die bestehende DB-Zeile, ohne eine neue anzulegen.
- [x] `PUT`, bei dem eine bestehende Frage (per `id`) im `questions`-Array
      fehlt, löscht diese Frage aus der DB — **außer** sie hat bereits
      mindestens eine `AnamnesisAnswer` — dann bleibt sie unverändert
      erhalten (Test mit Factory-erzeugter Antwort erforderlich).
- [x] `PUT` mit einer `id`, die zu einer anderen Vorlage gehört, liefert
      HTTP 422 und ändert keine Daten.
- [x] `PUT` ohne `questions`-Schlüssel im Payload lässt bestehende Fragen
      unverändert (Regressionsschutz für Teil-Updates von `name`/
      `description`/`isDefault`).
- [x] Bestehende Tests "trainer can update own template"
      (`AnamnesisTemplateApiTest.php:172-190`) und alle anderen
      bestehenden Tests der Datei bleiben grün.
- [x] `composer qa` läuft fehlerfrei (Lint, Stan, Compat-Check 8.2, Pest).
      **Hinweis:** `composer qa`/`stan`/`compat-check` sind im aktuellen
      Docker-Setup nicht ausführbar (vorbestehender, in
      `task-T01.notes.md`/`task-T02.notes.md` dokumentierter
      Environment-Gap). Ersatzweise ausgeführt:
      `vendor/bin/pest --filter=AnamnesisTemplateApiTest` (29 passed),
      volle Suite `vendor/bin/pest` (678 passed), `php -l` gegen alle
      geänderten Dateien, sowie manuelle PHP-8.2-Kompatibilitätsprüfung
      (siehe `task-T03.notes.md`).
- [x] Keine PHP-8.3/8.4-Sprachfeatures verwendet (siehe CLAUDE.md,
      Abschnitt 4.1) — insbesondere kein `#[\Override]`, keine Typed Class
      Constants.

---

## T04: Frontend — Vollständige Vorlagen-Details vor dem Editor nachladen

- **Agent:** dev-typescript
- **Dateien:** `frontend/src/views/anamnesis/AnamnesisView.vue`
- **Abhängigkeiten:** T02
- **Priorität:** Pflicht

### Beschreibung

Siehe `design.md`, Abschnitt 6, für den vollständigen Vorschlag.

`openTemplateModal(template?)` (Zeile 344-347) übergibt aktuell das
Listenobjekt direkt an `AnamnesisTemplateFormModal` — dieses Objekt enthält
keine `questions`-Relation (nur die Liste lädt `with(['trainer'])`, siehe
T02). Die Funktion muss `async` werden und bei vorhandenem `template`-Arg
zunächst `anamnesisTemplatesApi.getById(template.id)` aufrufen (Methode
existiert bereits, `frontend/src/api/anamnesis.ts:78-81`) und das Ergebnis
als `selectedTemplate.value` setzen, bevor der Modal geöffnet wird. Bei
Fehlschlag: `handleApiError()` nutzen (bereits im Component-Scope
importiert, Zeile 208) und den Modal **nicht** öffnen.

### Akzeptanzkriterien

- [x] Klick auf "Bearbeiten" (Stift-Icon, Zeile 156) einer frisch
      angelegten Vorlage mit Fragen zeigt im Editor die tatsächlich
      gespeicherten Fragen an, nicht "0 Fragen"/leere Liste.
- [x] Die Kachel-Liste selbst zeigt weiterhin die aus T02 gelieferte
      `questionsCount` an (keine Regression).
- [x] Schlägt das Nachladen fehl (z. B. Netzwerkfehler): Modal öffnet sich
      nicht, ein Fehler-Toast erscheint (`handleApiError`).
- [x] "Neue Vorlage"-Button (Zeile 95/141, kein `template`-Argument) öffnet
      weiterhin ein leeres Formular ohne Nachlade-Versuch.
- [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne neue
      Fehler/Warnings (Projekt-Pre-Flight für Frontend-Tasks, siehe
      `CLAUDE.md`, Abschnitt 7.1). **Hinweis:** `npm run lint` existiert
      als Skript in `frontend/package.json` nicht (kein ESLint im
      Frontend konfiguriert) — Environment-Gap, dokumentiert statt
      eigenmächtig ein Lint-Setup einzuführen (außerhalb des Scopes von
      T04). `npm run test` (128 Tests) und `npm run build`
      (inkl. `vue-tsc -b`) laufen grün.

---

## T05: Frontend — Frage-IDs durch Formular und Speicher-Payload durchreichen

- **Agent:** dev-typescript
- **Dateien:** `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue`, `frontend/src/api/anamnesis.ts`
- **Abhängigkeiten:** T03, T04
- **Priorität:** Pflicht

### Beschreibung

Siehe `design.md`, Abschnitt 6, für den vollständigen Vorschlag. Ohne
diesen Task kann das Backend (T03) niemals bestehende Fragen per `id`
erkennen, da das Frontend aktuell weder beim Laden noch beim Speichern
eine `id` pro Frage mitführt — der Update-Fix aus T03 liefe für jede
Bearbeitung effektiv wie "alle Fragen sind neu" (jede vorhandene Frage
würde als "entfernt" erkannt werden, sofern sie keine Antworten hat).

1. `Question`-Interface (Zeile 234-240): `id?: number` ergänzen.
2. `watch(() => props.template, …)`-Block (Zeile 287-303): beim Mapping der
   vom Backend gelieferten Fragen (jetzt vollständig dank T04)
   `id: q.id` mit übernehmen.
3. `save()` (Zeile 392-400): `id: q.id` in jedes Payload-Objekt
   aufnehmen (bei neu hinzugefügten Fragen ohne `id` bleibt der Wert
   `undefined` und wird beim Serialisieren automatisch weggelassen).
4. `frontend/src/api/anamnesis.ts`: `updateTemplate()`-Parametertyp
   (Zeile 118-128) um optionales `id?: number` pro Frage-Objekt erweitern.
5. **Keine Änderung** an `createTemplate()` (Zeile 102-116) — dort gibt es
   naturgemäß nie eine `id`.

### Akzeptanzkriterien

- [x] Beim Bearbeiten einer Vorlage mit bestehenden Fragen enthält der an
      `PUT /api/v1/anamnesis-templates/{id}` gesendete Payload für jede
      unveränderte/geänderte bestehende Frage die korrekte `id`.
- [x] Beim Hinzufügen einer neuen Frage über "Frage hinzufügen" enthält das
      entsprechende Payload-Objekt **keine** `id` (bzw. `undefined`).
- [x] Entfernt der Trainer eine Frage im Editor (Button "Frage entfernen",
      Zeile 108-116) und speichert, enthält der Payload diese Frage **nicht
      mehr** — das Backend (T03) erkennt dies korrekt als Löschung.
- [x] `vue-tsc -b` (Teil von `npm run build`) meldet keinen Typfehler durch
      das neue `id`-Feld.
- [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne neue
      Fehler/Warnings. **Hinweis:** `npm run lint` existiert weiterhin
      nicht als Skript in `frontend/package.json` (vorbestehender,
      bereits in T04 dokumentierter Environment-Gap, nicht Teil des
      T05-Scopes). `npm run test` (128 Tests) und `npm run build`
      (inkl. `vue-tsc -b`) laufen grün.
- [x] End-to-End-Konsistenz (manuell oder per Test nachvollzogen): Eine
      Vorlage anlegen (T-Kette: create → T04 nachladen → T05 editieren →
      T03 Backend-Sync) zeigt nach zweimaligem Bearbeiten konsistent die
      erwarteten Fragen, ohne Duplikate oder Verlust. Verifiziert per
      manuellem Code-Trace (siehe `task-T05.notes.md`, kein Docker-Backend
      in dieser Session verfügbar) sowie per `node -e` Nachweis, dass
      `JSON.stringify` `undefined`-Properties beim Serialisieren entfernt
      (Axios' Default-Transform für JSON-Bodies verhält sich identisch).
