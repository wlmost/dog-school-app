# Test-Report: fix-anamnesis-template-questions-not-saved

**Status:** alle-gruen

**Umfang:** Change-weiter Test-Report (nicht pro Einzel-Task, da die
Tester-Rolle hier primär die T03-Backend-Testfälle und die T04/T05-Frontend-
Kette end-to-end verifiziert; siehe Auftrag). Docker-Umgebung war in dieser
Session erreichbar (Service heißt `php`, nicht `app` — wie bereits in
`task-T01.notes.md`/`task-T02.notes.md`/`task-T03.notes.md` dokumentiert).

---

## 0. Docker-Erreichbarkeit (priorisiert geprüft)

```
docker compose ps
```

Alle Container liefen bereits (`dog-school-php`, `dog-school-postgres`,
`dog-school-nginx`, `dog-school-node`, `dog-school-redis`,
`dog-school-mailpit`, `dog-school-queue`, `dog-school-scheduler`, seit 3h
`Up`). Der Service für PHP heißt `php`, wie von den Dev-Agenten dokumentiert.
Kein `docker compose up -d` nötig, Container liefen bereits.

## Hinzugefügte / geänderte Tests

- `backend/tests/Feature/AnamnesisTemplateApiTest.php`: 1 neuer Testfall
  ergänzt (`it('synchronisiert erstellte, geänderte und gelöschte fragen in
  einem einzigen realistischen frontend-payload', …)`), eingefügt nach dem
  letzten der 6 bereits von T03 hinzugefügten Testfälle und vor "trainer
  cannot update another trainers template". Alle anderen sichtbaren
  Diff-Zeilen der Datei stammen bereits aus T02/T03 (nicht von mir
  hinzugefügt — per `git diff backend/tests/Feature/AnamnesisTemplateApiTest.php`
  verifiziert: nur mein neuer `it(...)`-Block wurde von mir ergänzt).
- `frontend/e2e/anamnesis-templates.spec.ts` (neue Datei): 1 neuer
  Playwright-E2E-Test, der die **komplette** Kette über einen echten
  Browser abdeckt (siehe Abschnitt "Nachtrag: echter Browser-E2E-Test"
  unten) — geht über den ursprünglichen Auftrag (Ergänzung in
  `AnamnesisTemplateApiTest.php`) hinaus, schließt aber genau die vom
  T05-Agenten offen gelassene Lücke vollständig.

Kein Produktivcode wurde geändert (nur die zwei genannten Testdateien).

## Nachtrag: echter Browser-E2E-Test (über den ursprünglichen Auftrag hinaus)

Bei der Suche nach einem "sinnvollen Weg, die Kette näher an einem echten
Roundtrip zu testen" wurde entdeckt, dass das Projekt entgegen meiner
ersten Einschätzung **doch** über E2E-Test-Tooling verfügt:
`frontend/package.json` enthält `"e2e": "playwright test"` /
`"e2e:ui": "playwright test --ui"` sowie `@playwright/test` als
Dependency, und `frontend/e2e/` enthält bereits vier bestehende Specs
(`customers.spec.ts`, `invoices.spec.ts`, `navigation.spec.ts`,
`installation-wizard.spec.ts`) mit `frontend/playwright.config.ts`
(`baseURL: http://localhost:5173`, `webServer` startet/nutzt `npm run dev`).
Das war mir beim ersten Durchgang entgangen (ursprüngliche Formulierung in
diesem Report fälschlich "kein E2E-Tooling" — korrigiert).

Da der laufende Docker-Stack bereits einen Node-Dev-Server
(`dog-school-node`, Port 5173) und die echte Backend-Kette (Nginx `8081` →
`dog-school-php` → Postgres) bereitstellt, habe ich zusätzlich zum
angeforderten Backend-Feature-Test einen **echten Browser-Roundtrip-Test**
ergänzt: `frontend/e2e/anamnesis-templates.spec.ts`. Dieser Test:

1. Loggt sich als `trainer@example.com` (Seed-User aus
   `backend/database/seeders/DatabaseSeeder.php:28-36`, in der laufenden
   Dev-DB vorhanden, per `php artisan tinker` verifiziert) über die echte
   Login-Seite ein.
2. Navigiert zu `/app/anamnesis` und legt über den echten
   "Neue Vorlage"-Button/Formular (`AnamnesisTemplateFormModal.vue`) eine
   neue Vorlage mit 2 Fragen an (echter `POST` über Axios gegen den echten
   Backend-Container).
3. Prüft, dass die Kachel-Liste `2 Fragen` zeigt (T02/T04-Regressionsschutz).
4. Öffnet den Editor erneut (T04: `getById()`-Nachladen) und prüft, dass
   beide zuvor gespeicherten Fragen tatsächlich im Formular erscheinen
   (nicht "0 Fragen").
5. Ändert Frage 1 (Update per `id`), entfernt Frage 2 (Delete), fügt eine
   neue Frage 3 hinzu (Create ohne `id`) — genau die Mischung, die T05
   durchs Formular in den `PUT`-Payload bringen muss — und speichert
   (echter `PUT` über Axios).
6. Öffnet den Editor ein drittes Mal und verifiziert per DOM-Inspektion der
   Formularfelder, dass genau die erwarteten zwei Fragen (geänderte + neue)
   persistiert sind und die alten Texte (Original von Frage 1, entfernte
   Frage 2) nicht mehr auftauchen — das ist die **einzige** Verifikation im
   gesamten Change, die tatsächlich durch den kompletten Stack läuft: echtes
   Vue-Formular → echte Vue-Reaktivität/Payload-Erzeugung (T05) → echte
   Axios-Serialisierung → echter HTTP-Request → echter Laravel-Controller
   (T03) → echte Postgres-DB → echtes erneutes Laden (T04) → echte
   Formular-Darstellung.
7. Räumt die Test-Vorlage über den echten "Löschen"-Button wieder auf
   (Bestätigungsdialog per `page.on('dialog', …)` akzeptiert). Aufräumen
   per direktem DB-Blick verifiziert (`AnamnesisTemplate::where('name',
   'like', 'E2E Vorlage%')->count()` → `0` nach Testlauf).

**Ausführung:** `npx playwright install chromium` war nötig (Browser-Binary
fehlte lokal), danach zweimal hintereinander ausgeführt — beide Male grün,
keine Flakiness beobachtet (siehe Ausführungs-Ergebnis unten).

**Vorbestehender, unabhängiger Befund (nicht von mir verursacht, nicht
behoben):** Die vier bereits bestehenden E2E-Specs
(`customers.spec.ts`, `invoices.spec.ts`, `navigation.spec.ts`) warten nach
dem Login auf `page.waitForURL('**/home', …)` — diese Route existiert im
aktuellen Router (`frontend/src/router/index.ts`) nicht mehr (Dashboard
liegt unter `/app`, siehe `router.push({ name: 'Dashboard' })` in
`frontend/src/views/auth/LoginView.vue:134`). Ich habe testweise
`customers.spec.ts` laufen lassen: **alle 7 Tests schlagen bereits vor
meiner Änderung fehl**, exakt mit diesem Timeout — ein vorbestehender,
projektweiter E2E-Drift (Router-Refactoring nach `/app`-Präfix, ohne
Nachführung der E2E-Specs), unabhängig von diesem Change. Ich habe diese
bestehenden Dateien **nicht angefasst** (nicht mein Auftrag, kein Bezug zum
Anamnese-Bug) — nur meine eigene neue Datei korrekt auf `'**/app'` bzw.
`/app/anamnesis` ausgerichtet. Empfehlenswerter Folge-Change: alle
bestehenden E2E-Specs auf die aktuellen `/app`-Routen nachziehen.

### Warum dieser zusätzliche Test nötig war

Der T05-Entwickler-Agent konnte laut `task-T05.notes.md` ("Offene Punkte /
Hinweise") die End-to-End-Kette (Frage-`id` vom Frontend bis zum
Backend-Sync) mangels Docker-Zugriff **nur per Code-Trace** verifizieren,
nicht per echtem HTTP-Roundtrip. Die 6 bestehenden T03-Testfälle prüfen
zwar jede Sync-Operation (Create/Update/Delete/Schutz/422/kein-Schlüssel)
**isoliert je Testfall**, aber keiner davon bildet exakt den **gemischten,
realistischen Payload** nach, den `AnamnesisTemplateFormModal.vue::save()`
nach T05 tatsächlich erzeugt (vollständiges `questions`-Array mit
`name`/`description`/`isDefault` **und** einer Mischung aus Fragen mit
`id` (geändert) und ohne `id` (neu), während eine dritte, bestehende Frage
durch schlichtes Weglassen gelöscht wird — exakt das Verhalten von
`removeQuestion()`/`splice()` in Kombination mit `save()`).

Der neue Test schickt genau einen solchen gemischten Payload
(Feld-für-Feld nach `save()`-Struktur in
`frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue`
nachgebaut: `name`, `description`, `isDefault: false`,
`questions[].{id?, questionText, questionType, isRequired, options, order}`)
gegen den echten `PUT /api/v1/anamnesis-templates/{id}`-Endpunkt und
verifiziert per direkter DB-Query (`assertDatabaseHas`/
`assertDatabaseMissing`/`assertDatabaseCount` sowie `AnamnesisQuestion::query()`),
dass in **einem** Request alle drei Semantiken korrekt greifen:

1. Update: bestehende Frage (per `id`) wird aktualisiert, keine neue Zeile.
2. Create: neue Frage ohne `id` wird angelegt (inkl. `options`-Array).
3. Delete: die im Payload ausgelassene, unbeantwortete dritte Frage wird
   gelöscht.

Damit ist die Backend-Seite der End-to-End-Kette per echtem HTTP-Roundtrip
abgedeckt (nicht mehr nur per Code-Trace). **Zusätzlich** wurde — wie oben
im Abschnitt "Nachtrag: echter Browser-E2E-Test" beschrieben — ein echter
Playwright-Browser-Test ergänzt, der die **vollständige** Kette inklusive
Vue-Formular-Submit und echter Axios-Serialisierung abdeckt. Damit ist die
in `task-T05.notes.md` offen gelassene Lücke (Frontend-Payload-Erzeugung
nur per Code-Trace verifiziert) vollständig geschlossen.

## Akzeptanzkriterien-Abdeckung

### T01 — Verifikation Create-Flow

- [x] `composer test -- --filter=AnamnesisTemplateApiTest` (Äquivalent:
      `vendor/bin/pest --filter=AnamnesisTemplateApiTest`) in Docker
      ausgeführt — grün (30 passed, siehe Ausführungs-Ergebnis).
- [x] "trainer can create template with questions" — weiterhin grün,
      bestätigt per Testlauf (nicht nur laut Entwickler-Notes).

### T02 — `questionsCount`

- [x] Liste liefert `questionsCount` pro Vorlage — getestet in
      `AnamnesisTemplateApiTest.php::"listed templates report the correct
      questions count per template"` (0/2/5 Fragen, alle korrekt).
- [x] `assertJsonStructure` in "can list anamnesis templates" wurde bereits
      um `questionsCount` erweitert (T02-Dev-Änderung) — grün.
- [x] Bestehender Test "can list anamnesis templates" bleibt grün.

### T03 — Fragen-Synchronisation beim Update (6 Szenarien, priorisiert
bestätigt)

- [x] Kriterium 1 (neue Fragen ohne `id` anlegen) — getestet in
      `AnamnesisTemplateApiTest.php::"trainer can add new questions when
      updating template"` — **grün, per echtem Testlauf bestätigt**.
- [x] Kriterium 2 (bestehende Frage per `id` ändern, keine neue Zeile) —
      getestet in `"trainer can modify existing question via id when
      updating template"` — **grün**.
- [x] Kriterium 3 (Frage durch Auslassen löschen) — getestet in `"trainer
      can remove a question by omitting it from the update payload"` —
      **grün**.
- [x] Kriterium 4 (beantwortete Frage bleibt bei Auslassung geschützt) —
      getestet in `"removing a question with existing answers from the
      update payload does not delete the question or its answers"` —
      **grün**.
- [x] Kriterium 5 (fremde `id` → HTTP 422, keine Datenänderung) — getestet
      in `"update rejects a question id belonging to a different
      template"` — **grün**.
- [x] Kriterium 6 (`questions`-Schlüssel fehlt → keine Änderung) —
      getestet in `"updating template without the questions key leaves
      existing questions untouched"` — **grün**.
- [x] Regressionsschutz "trainer can update own template" — weiterhin
      grün.
- [x] **Neu (Tester-Ergänzung):** realistischer Misch-Payload
      (Update+Create+Delete in einem Request, exakt nach
      `save()`-Payload-Form) — getestet in `it('synchronisiert erstellte,
      geänderte und gelöschte fragen in einem einzigen realistischen
      frontend-payload', …)` — **grün**.

### T04 — Frontend Anzeige (Nachladen vor Editor)

- [x] `npm run test` (Vitest) grün, keine Regression.
- [x] `npm run build` (inkl. `vue-tsc -b`) grün, keine Typfehler/Warnings.
- [x] "Klick auf Bearbeiten zeigt tatsächliche Fragen" — **jetzt per echtem
      Browser-E2E-Test abgedeckt**:
      `frontend/e2e/anamnesis-templates.spec.ts` legt eine Vorlage mit 2
      Fragen an, öffnet den Editor erneut und prüft per
      `expect(loadedQuestionInputs).toHaveCount(2)` +
      Werte-Assertions auf beide Fragetexte, dass tatsächlich die
      gespeicherten Fragen erscheinen — nicht "0 Fragen"/leeres Formular.
      Ein isolierter Vitest-Component-/Unit-Test für `AnamnesisView.vue`
      existiert weiterhin nicht (vorbestehender Gap, s. u., Abschnitt
      "Grenzen dieses Reports"), aber das Verhalten selbst ist jetzt
      end-to-end verifiziert.

### T05 — Frontend Frage-ID-Durchreichung

- [x] `vue-tsc -b` meldet keinen Typfehler durch das neue `id`-Feld —
      bestätigt (kein Output = kein Fehler).
- [x] `npm run test` (128 Tests) grün.
- [x] `npm run build` grün (Assets erzeugt, keine Warnings außer der
      bereits vorbestehenden, unveränderten Chunk-Size-Hinweis-losen
      Ausgabe — tatsächlich: keine Warnings in dieser Build-Ausgabe).
- [x] **End-to-End-Konsistenz der Frage-ID-Kette — jetzt vollständig per
      echtem Roundtrip bestätigt**, auf zwei Ebenen:
      1. Backend-Feature-Test (`AnamnesisTemplateApiTest.php`): realistischer
         Misch-Payload direkt gegen `PUT` — bestätigt, dass das Backend den
         von `save()` erzeugten Payload korrekt verarbeitet.
      2. **Browser-E2E-Test** (`frontend/e2e/anamnesis-templates.spec.ts`):
         echtes Formular, echte Nutzer-Interaktion (Frage ändern, Frage per
         "Frage entfernen"-Button löschen, neue Frage per "Frage
         hinzufügen"-Button anlegen), echter `save()`-Aufruf, echter
         Axios-Request, echtes Backend, danach erneutes Laden und
         DOM-Verifikation der drei Effekte.
      Damit ist nicht mehr nur der Code-Trace des T05-Agenten die einzige
      Verifikationsquelle für "Frontend erzeugt den Payload tatsächlich
      korrekt" — das ist jetzt per echtem Browser-Test nachgewiesen.
- [ ] "`npm run lint` existiert nicht" — bestätigt kein Fehler meinerseits,
      wie vom Auftraggeber vorab mitgeteilt (Skript fehlt tatsächlich in
      `frontend/package.json`, per `cat`/`grep` verifiziert).

## Ausführungs-Ergebnis

### Backend — gezielt: `AnamnesisTemplateApiTest`

```
$ docker compose exec php vendor/bin/pest --filter=AnamnesisTemplateApiTest

   PASS  Tests\Feature\AnamnesisTemplateApiTest
  ✓ can list anamnesis templates                                         0.22s
  ✓ listed templates report the correct questions count per template     0.03s
  ✓ admin cannot list anamnesis templates                                0.02s
  ✓ can filter templates by trainer                                      0.02s
  ✓ can filter default templates                                         0.02s
  ✓ can search templates by name                                         0.02s
  ✓ trainer can create template without questions                        0.03s
  ✓ trainer can create template with questions                           0.03s
  ✓ customer cannot create template                                      0.02s
  ✓ can view template details with questions                             0.03s
  ✓ admin cannot view anamnesis template                                 0.02s
  ✓ trainer can update own template                                      0.02s
  ✓ trainer can add new questions when updating template                 0.03s
  ✓ trainer can modify existing question via id when updating template   0.02s
  ✓ trainer can remove a question by omitting it from the update payload 0.02s
  ✓ removing a question with existing answers from the update payload d… 0.03s
  ✓ update rejects a question id belonging to a different template       0.02s
  ✓ updating template without the questions key leaves existing questio… 0.03s
  ✓ it synchronisiert erstellte, geänderte und gelöschte fragen in eine… 0.03s
  ✓ trainer cannot update another trainers template                      0.02s
  ✓ admin cannot update template                                         0.02s
  ✓ trainer can delete own template                                      0.02s
  ✓ trainer cannot delete other trainers template                        0.02s
  ✓ admin cannot delete template                                         0.02s
  ✓ cannot delete template with responses                                0.02s
  ✓ customer cannot delete template                                      0.02s
  ✓ admin cannot create anamnesis template                               0.02s
  ✓ can get template questions ordered                                   0.02s
  ✓ validates required fields when creating template                     0.02s
  ✓ validates question types                                             0.02s

  Tests:    30 passed (125 assertions)
  Duration: 1.13s
```

### Backend — volle Suite

```
$ docker compose exec php vendor/bin/pest

  Tests:    679 passed (2120 assertions)
  Duration: 24.98s
```

(678 vorbestehende + 1 neu von mir ergänzter Test = 679, keine Regression.)

### Frontend

```
$ cd frontend && npx vitest run

 Test Files  12 passed (12)
      Tests  128 passed (128)
   Duration  906ms

$ npx vue-tsc -b
(kein Output = keine Typfehler)

$ npm run build
> vue-tsc -b && vite build
✓ 636 modules transformed.
...
✓ built in 1.28s
```

Keine Warnings, keine Fehler.

### Frontend — Playwright-E2E (`anamnesis-templates.spec.ts`)

```
$ npx playwright install chromium   # Browser-Binary fehlte lokal, einmalig nachinstalliert

$ npx playwright test e2e/anamnesis-templates.spec.ts --reporter=list

Running 1 test using 1 worker

  ✓  1 [chromium] › e2e/anamnesis-templates.spec.ts:33:3 › Anamnesis template questions › trainer can create a template with questions and see edits persist after saving (6.2s)

  1 passed (6.5s)
```

Zweiter Lauf zur Flakiness-Prüfung (identischer Test, frische Vorlage
dank Zeitstempel im Namen):

```
$ npx playwright test e2e/anamnesis-templates.spec.ts --reporter=list

  ✓  1 [chromium] › ... trainer can create a template with questions and see edits persist after saving (6.0s)

  1 passed (6.3s)
```

Aufräum-Verifikation nach Testlauf (kein Datenmüll in der Dev-DB):

```
$ docker compose exec php php artisan tinker --execute="echo App\Models\AnamnesisTemplate::where('name','like','E2E Vorlage%')->count();"
0
```

## Fehler (falls vorhanden)

Keine. Alle Backend- und Frontend-Läufe grün.

## Grenzen dieses Reports / Offene Punkte

1. **`composer qa` / `composer stan` / `composer compat-check` weiterhin
   nicht ausführbar** im Docker-`php`-Service (vorbestehender
   Environment-Gap, bereits in `task-T01.notes.md`, `task-T02.notes.md`,
   `task-T03.notes.md` dokumentiert — kein neuer Befund). Ich habe dies
   nicht erneut isoliert nachgeprüft, da es außerhalb der Tester-Rolle
   liegt (keine Composer-Script-Änderungen durch mich) und bereits
   dreifach dokumentiert ist.
2. **`npm run lint` existiert nicht** — wie vom Auftraggeber vorab
   mitgeteilt, bestätigt kein Fehler meinerseits.
3. **Echter Browser-E2E-Test jetzt vorhanden** (Korrektur meiner
   ursprünglichen Einschätzung, s. o.): `frontend/e2e/anamnesis-templates.spec.ts`
   deckt die komplette Kette ab. Ein isolierter Vitest-**Component**-Test
   für `AnamnesisTemplateFormModal.vue` (mit gemocktem
   `anamnesisTemplatesApi`) existiert weiterhin nicht und wäre als
   schnellerer, isolierterer Regressionsschutz (ohne Browser-Overhead)
   trotzdem sinnvoll — das ist aber durch den jetzt vorhandenen E2E-Test
   kein blockierender Gap mehr, sondern nur noch eine
   Testpyramiden-Optimierung für einen möglichen Folge-Change.
4. **Vorbestehender E2E-Drift in den vier bereits existierenden Specs**
   (`customers.spec.ts`, `invoices.spec.ts`, `navigation.spec.ts`,
   ungeprüft: `installation-wizard.spec.ts`): Sie warten auf die veraltete
   Route `**/home`, die es im aktuellen Router nicht mehr gibt (aktuelle
   Dashboard-Route liegt unter `/app`). Verifiziert durch einen Testlauf
   von `customers.spec.ts`: alle 7 Tests schlagen mit demselben
   Timeout-Fehler fehl. Das ist ein vorbestehender, von mir **nicht**
   verursachter und **nicht** behobener Befund (kein Bezug zum
   Anamnese-Bug, keine Berechtigung, bestehende Test-Dateien im Rahmen
   dieses Changes zu ändern). Empfehlenswerter, unabhängiger Folge-Change:
   alle vier Dateien auf die aktuellen `/app`-Routen nachziehen.
5. Kein Produktivcode wurde von mir geändert — ausschließlich zwei
   Testdateien: `backend/tests/Feature/AnamnesisTemplateApiTest.php`
   (1 Testfall ergänzt) und `frontend/e2e/anamnesis-templates.spec.ts`
   (neu angelegt).
