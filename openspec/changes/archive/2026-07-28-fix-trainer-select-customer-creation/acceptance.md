# Abnahme: fix-trainer-select-customer-creation

**Status:** bereit-für-user-review

## Schritt 0: Strukturelle Validität

```
$ openspec validate fix-trainer-select-customer-creation --strict
Change 'fix-trainer-select-customer-creation' is valid
```

Keine strukturellen Mängel.

## Erfüllt

### Vollständigkeit der Tasks
- T01 (Backend-Endpoint, `dev-php`): alle sechs inhaltlichen Akzeptanzkriterien `[x]`. Der letzte Punkt (`composer qa`) ist `[~]` — Script existiert projektweit nicht (vorbestehende Infrastruktur-Lücke, in `task-T01.notes.md` sauber dokumentiert, nicht durch T01 verursacht). Ersatzweise Pint/Pest/`php -l` grün, von mir gegenverifiziert (siehe unten).
- T02 (`CustomerFormModal.vue`, `dev-typescript`): alle fünf inhaltlichen Kriterien `[x]`, letzter Punkt (`npm run lint`) `[~]` aus demselben, projektweit vorbestehenden Grund (kein ESLint installiert).
- T03 (`CourseFormModal.vue`, `dev-typescript`): analog, alle inhaltlichen Kriterien `[x]`, `npm run lint` `[~]`.
- Die `[~]`-Markierungen betreffen ausschließlich fehlende, projektweite QA-Scripts (`composer qa`, `npm run lint`), nicht die fachlichen Akzeptanzkriterien der Tasks. Sie sind in allen drei Notes-Dateien mit Begründung dokumentiert (Anti-Halluzinations-Regel 3, CLAUDE.md Abschnitt 9) statt stillschweigend als erledigt markiert — das ist der korrekte Umgang mit einer vorbestehenden Lücke, kein neuer Mangel dieses Changes.

### Spec-Konformität (Stichprobe gegen Diff)
- `specs/trainer-authorization/spec.md`: Requirement "Reduced-data trainer-options endpoint accessible to Admin and Trainer roles" — durch `git diff main -- backend/routes/api.php backend/app/Http/Controllers/Api/TrainerController.php backend/app/Http/Resources/TrainerOptionResource.php` verifiziert: neue Route `GET /api/v1/trainers/options` mit `can:trainer`-Middleware (Admin+Trainer, deckt Requirement exakt), `TrainerOptionResource::toArray()` liefert ausschließlich `id`, `firstName`, `lastName`, `fullName` (`backend/app/Http/Resources/TrainerOptionResource.php:28-33`). Alle vier Spec-Szenarien (Admin 200, Trainer 200, Customer 403, unauthentifiziert 401) sowie das Ausschluss-Szenario sind 1:1 in `backend/tests/Feature/TrainerApiTest.php` (neuer `describe('Trainer Options Endpoint', ...)`-Block) abgebildet und lokal in Docker grün nachvollzogen (`docker compose exec php ./vendor/bin/pest --group=trainers` → 22 passed, inkl. der 4 neuen Tests).
- `specs/trainer-assignment-forms/spec.md`: Requirement "Trainer assignment select loads successfully" — `CustomerFormModal.vue`/`CourseFormModal.vue` rufen nachweislich `/api/v1/trainers/options` statt der admin-only Route auf (`git diff`, je eine Zeile in `loadTrainers()`). Requirement "Trainer is preselected... remains changeable" — Vorauswahl-Logik in `CustomerFormModal.vue` unverändert gelassen (funktioniert jetzt, weil der Datenabruf nicht mehr an 403 scheitert), durch `describe('Vorauswahl für die Rolle trainer', ...)` in `CustomerFormModal.test.ts` mit drei Tests abgedeckt (u. a. Select nicht `disabled`, umschaltbar). Requirement "Trainer-list load failures are surfaced" — beide `catch`-Blöcke rufen jetzt zusätzlich `handleApiError(err, 'Fehler beim Laden der Trainerliste')` auf (`git diff` bestätigt je eine neue Zeile), durch je einen 403-Test in beiden `*.test.ts`-Dateien abgedeckt.
- Additivität bestätigt: `git diff main -- backend/routes/api.php` zeigt nur eine neu eingefügte Route vor der bestehenden `can:admin`-Gruppe, keine Änderung an `Route::apiResource('trainers', ...)`. `TrainerApiTest.php`-Diff zeigt ausschließlich einen neuen `describe`-Block am Dateiende, keine Änderung an den 18 bestehenden Tests (insb. Zeilen zum 403-Test der Trainer-Rolle auf `GET /api/v1/trainers` unverändert und weiterhin grün).

### Review-Befunde
- Reviewer (`review.md`): 0 "Muss"-Befunde, 1 "Sollte"-Befund (Nullability-Inkonsistenz `TrainerOption`-Interface zwischen `CustomerFormModal.vue` und `CourseFormModal.vue`), 2 "Könnte"-Hinweise (DRY-Duplikation `loadTrainers()`/Interface — bereits in `design.md` als bewusster YAGNI-Trade-off dokumentiert; TESTING.md-Gruppennamen-Inkonsistenz, vorbestehend).
- Der "Sollte"-Befund wurde vom Hauptagenten nachträglich behoben: `git diff main -- frontend/src/components/CustomerFormModal.vue` zeigt `interface TrainerOption { id: number; firstName: string | null; lastName: string | null; fullName: string | null }` (Zeilen 252-257), jetzt identisch zur Deklaration in `CourseFormModal.vue` (Zeilen 221-226, `git diff` geprüft). Stichprobe auf weitere Verwendungsstellen: In beiden Dateien gibt es je genau eine Template-Stelle, die `trainer.fullName`/`trainer.firstName`/`trainer.lastName` liest (`CustomerFormModal.vue:106`, `CourseFormModal.vue:45`, per `grep -n "trainer\."` verifiziert) — beide bereits vor dem Fix mit Template-Literal-Fallback (`` `${trainer.firstName} ${trainer.lastName}` ``) geschrieben, der mit `string | null` klaglos funktioniert (kein TS-Fehler, kein Laufzeitfehler; im Edge-Case `null`/`null` würde `"null null"` gerendert — das ist bereits bekanntes, für T03 explizit dokumentiertes und akzeptiertes Verhalten, keine Regression durch den Nachtrags-Fix). Keine weiteren Stellen im Diff, die noch eine nicht-nullable Annahme voraussetzen.
- Verifikation eigenständig wiederholt: `npx vue-tsc -b` (aus `frontend/`) läuft fehlerfrei durch; `npx vitest run src/components/CustomerFormModal.test.ts src/components/CourseFormModal.test.ts` → 15/15 grün; volle Suite `npx vitest run` → 209/209 grün (20 Testdateien). Backend `docker compose exec php ./vendor/bin/pest --group=trainers` → 22/22 grün. `php -l` auf allen vier Backend-Dateien fehlerfrei.
- "Könnte"-Hinweise sind laut Vorgabe (CLAUDE.md/WORKFLOW.md) nicht blockierend und bleiben als dokumentierte, bewusste Trade-offs bestehen (Composable-Extraktion als möglicher Folge-Change, TESTING.md-Gruppennamen als Boy-Scout-Gelegenheit für einen künftigen Change an derselben Datei).

### Testergebnisse
- `task-testreport.md`: Status `alle-gruen`. Backend voll: 722 passed (2309 assertions), gezielt `--group=trainers`: 22 passed. Frontend voll: 209 passed (20 Testdateien), mehrfach wiederholt inkl. `--no-file-parallelism` zur Flakiness-Prüfung — keine Flakiness. Build (`vue-tsc -b && vite build`) erfolgreich ohne Warnings.
- Zwei zusätzliche Edge-Case-Tests durch den Tester ergänzt (leere Trainerliste, kein Fehler-Handling ausgelöst) — Lückenanalyse in `task-testreport.md` Abschnitt 3 nachvollziehbar, deckt einen vom Architekten in Design/Tasks nicht explizit genannten, aber sinnvollen Randfall ab.
- Alle Akzeptanzkriterien aus `tasks.md` sind in `task-testreport.md` Abschnitt "Akzeptanzkriterien-Abdeckung" auf konkrete Testfälle gemappt — keine ungetesteten Kriterien gefunden.
- Eigenständig nachvollzogen (s. o.): Backend- und Frontend-Testläufe erneut ausgeführt, Ergebnisse decken sich mit dem Report.

## Offen / Nacharbeit

Keine blockierenden Punkte.

Nicht-blockierende, dokumentierte Beobachtungen (zur Kenntnisnahme, kein Handlungsbedarf für dieses Gate):
- `composer qa` / `npm run lint` existieren projektweit nicht (vorbestehende Infrastruktur-Lücke, nicht durch diesen Change verursacht, in allen drei `task-T0X.notes.md` sauber dokumentiert). Empfehlung: eigener openspec-Change, falls gewünscht.
- Zwei nahezu identische `loadTrainers()`-Implementierungen und `TrainerOption`-Interfaces bleiben dupliziert (bewusster YAGNI-Trade-off, in `design.md` "Risks/Trade-offs" begründet). Mögliche Folge-Verbesserung: `useTrainerOptions.ts`-Composable.
- `TrainerApiTest.php` liegt weiterhin unter `tests/Feature/` mit Group `trainers` statt (laut TESTING.md-Konvention) `tests/Feature/Api/` mit Group `api` — vorbestehend, nicht durch diesen Change eingeführt.

## Empfehlung an den User

Der Change ist vollständig, spec-konform, alle Tests grün (722 Backend + 209 Frontend) und der einzige Reviewer-Befund ("Sollte") ist nachweislich korrekt und vollständig behoben. Bereit für User-Gate 2 und anschließendes `openspec archive`.
