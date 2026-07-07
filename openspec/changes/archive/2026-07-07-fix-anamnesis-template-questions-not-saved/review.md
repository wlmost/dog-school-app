# Review: fix-anamnesis-template-questions-not-saved (T02–T05)

**Gesamtempfehlung:** ok

**Geprüfter Diff:** `git diff main` (Branch `change/fix-anamnesis-template-questions-not-saved`,
Arbeitsverzeichnis, keine Commits vorhanden). Umfasst T02 (`questionsCount`),
T03 (Update-Persistenz/`syncQuestions()`), T04 (`getById()`-Nachladen), T05
(Frage-`id`-Durchreichung) sowie eine unerwartete Änderung an `CLAUDE.md`
(siehe unten). T01 war reine Verifikation ohne Code-Änderung, nichts zu
reviewen.

**Besondere Prüfmaßnahmen für diesen Review (über Diff-Lektüre hinaus):**
- `syncQuestions()` wurde nicht nur gelesen, sondern gegen die reale
  Docker-Umgebung verifiziert: `docker compose exec php vendor/bin/pest
  --filter=AnamnesisTemplateApiTest` lief mit **30 passed (125
  assertions)**, inkl. eines zusätzlichen, während dieses Reviews vom
  Tester ergänzten Szenarios (`AnamnesisTemplateApiTest.php:370`,
  „synchronisiert erstellte, geänderte und gelöschte fragen in einem
  einzigen realistischen frontend-payload").
- Da `composer stan`/`compat-check` im Docker-Setup nicht ausführbar sind
  (dokumentierter, vorbestehender Environment-Gap, siehe
  `task-T01.notes.md` bis `task-T03.notes.md`), habe ich den PHP-Diff
  manuell zusätzlich gegen CLAUDE.md Abschnitt 4.1 gegengeprüft (keine
  Property Hooks, keine Typed Class Constants, kein `#[\Override]`, keine
  8.4-`array_*`-Funktionen, kein klammerloses `new`) — **keine Verstöße
  gefunden**. `php -l` gegen alle geänderten PHP-Dateien lief fehlerfrei.
- Eine konkrete Hypothese zu einem möglichen Datenkorruptions-Bug in
  `syncQuestions()` (Mass-`update()` auf einer Eloquent-Relation-Query
  serialisiert `options`-Arrays evtl. nicht korrekt zu JSON, da
  `Builder::update()` anders als `Model::save()` keine Attribut-Casts
  anwendet) habe ich anhand des Laravel-Quellcodes
  (`vendor/laravel/framework/src/Illuminate/Database/Query/Grammars/{MySqlGrammar,PostgresGrammar,SQLiteGrammar}.php`,
  jeweils `prepareBindingsForUpdate()`) **und** empirisch gegen die echte
  Postgres-Dev-DB verifiziert (Ad-hoc-Skript, das exakt
  `$template->questions()->whereKey($id)->update([...])` mit einem
  mehrelementigen `options`-Array nachstellt, danach wieder entfernt). **Kein
  Bug:** Alle drei Grammar-Klassen json-enkodieren Array-Werte in
  `prepareBindingsForUpdate()` automatisch, unabhängig vom Ziel-DB-Treiber.
  Ergebnis bestätigt: `options` wird nach dem Mass-Update korrekt als Array
  zurückgelesen. Kein Datenintegritätsrisiko.

---

## Muss (blockiert Abnahme)

Keine.

---

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Konsistenz/Scope]** `CLAUDE.md` (Diff gegen `main`, u. a. Zeilen 22–35,
  208–215, 280–285): Diese Datei wird im aktuellen Arbeitsstand mitgeändert
  (Verzeichnisstruktur-Doku `backend/`/`frontend/`, `dev-typescript` statt
  `dev-javascript`), ist aber in keiner Task in `tasks.md` als Scope
  erwähnt und betrifft keine der in `proposal.md` beschriebenen
  Ursachen/Ziele. Laut `task-T04.notes.md:10-15` war das eine Korrektur des
  Users mitten in der Task-Kette (falscher Agent-Routing-Eintrag), sachlich
  nachvollziehbar, aber inhaltlich ein eigenständiges, unabhängiges Anliegen
  (Projekt-Doku-Korrektur) und keine Bugfix-Änderung. Vorschlag: vor dem
  Commit in einen eigenen `chore:`-Commit auslagern (oder separat vor
  diesem Change committen), damit der Bugfix-Commit-Verlauf sauber auf
  T02–T05 fokussiert bleibt und ein späteres `git bisect`/Review des
  Fix-Commits nicht durch eine unrelated Doku-Änderung verwässert wird.

- **[Testkonvention, TESTING.md Abschnitt 10]**
  `backend/tests/Feature/AnamnesisTemplateApiTest.php:212,246,280,306,324,353`
  (die 6 in T03 neu hinzugefügten Testfälle) sowie `:36` (T02): Alle
  verwenden `test(...)` statt des laut `TESTING.md` Abschnitt 2/2.1/10 für
  **neue** Test-Definitionen verbindlichen `it(...)`. Der einzige neue Test,
  der `it(...)` korrekt verwendet, ist `:370` (offenbar vom Tester-Agent
  ergänzt). Da die Bestandsdatei komplett im `test()`-Stil geschrieben ist,
  ist die Wahl nachvollziehbar (Konsistenz mit Nachbar-Tests in derselben
  Datei), verstößt aber laut der in `TESTING.md` Abschnitt 10 explizit dem
  Reviewer aufgetragenen mechanischen Checkliste gegen Punkt 1 ("`it(`
  statt `test(`" für neue Test-Definitionen). Nach der in `TESTING.md`
  Abschnitt 10 selbst festgelegten Eskalationsregel ("ein fehlschlagender
  Punkt = mindestens Sollte") bleibt dies ein Sollte-Befund, da alle
  anderen Checklisten-Punkte (Groups, Factory-States, Assertion-Stile,
  DB-Assertions, keine `dd()`/auskommentierten Tests) eingehalten sind.
  Kein Blocker, aber vor Merge zu entscheiden: entweder die 7 neuen Tests
  auf `it(...)` umstellen, oder bewusst als Ausnahme dokumentieren (Boy-
  Scout-Regel erlaubt beides, aber nicht stillschweigend).

- **[Konsistenz/UX]** `backend/app/Http/Controllers/AnamnesisTemplateController.php:154`:
  `abort_if($unknownIds !== [], 422, 'One or more question ids do not belong
  to this template.')` liefert eine **englische** Fehlermeldung ohne
  `errors`-Struktur. `frontend/src/utils/errorHandler.ts:23-28` prüft
  explizit `status === 422 && data.errors` für den
  „Validierungsfehler"-Zweig — da `abort_if()` kein `errors`-Objekt
  mitliefert (anders als eine `ValidationException`), fällt der Fall in den
  generischen „Other errors with message"-Zweig
  (`frontend/src/utils/errorHandler.ts:56-59`) und zeigt dem Trainer den
  englischen Rohtext in einem sonst durchgängig deutschsprachigen UI an.
  Praktisch nur bei manipulierten/fehlerhaften Requests relevant (über die
  normale UI kann eine Frage-ID nie zu einer fremden Vorlage gehören), aber
  inkonsistent mit dem Rest der Fehlerbehandlung im Projekt. Vorschlag:
  entweder deutsche Meldung verwenden, oder (sauberer) eine
  `ValidationException::withMessages(['questions' => ['...']])` werfen,
  damit die Antwort dem Standard-Laravel-422-Format (`message` + `errors`)
  entspricht und der Frontend-Errorhandler sie konsistent behandelt.

---

## Könnte (optional, Verbesserung)

- **[Testkonvention/Pfad, TESTING.md Abschnitt 7.1, Altbefund]**
  `backend/tests/Feature/AnamnesisTemplateApiTest.php` liegt direkt unter
  `tests/Feature/`, obwohl die Gruppe `api`
  (`uses()->group('api', 'anamnesis')`, Zeile 13) laut Tabelle in
  `TESTING.md` Abschnitt 7.1 eigentlich `tests/Feature/Api/` erwarten
  würde. Vorbestehend (Datei existierte bereits so in `main`, nicht durch
  diesen Change verursacht) — nur der Vollständigkeit halber erwähnt, kein
  Handlungsbedarf in diesem Change (Boy-Scout-Regel: nur bei Gelegenheit).

- **[Wartbarkeit/Tooling]** `backend/app/Http/Resources/AnamnesisTemplateResource.php:48`
  (`$this->questions_count`) greift auf ein durch `withCount('questions')`
  dynamisch erzeugtes Attribut zu, das in
  `backend/app/Models/AnamnesisTemplate.php:17-25` (`@property-read`-Block)
  nicht dokumentiert ist. Funktioniert zur Laufzeit korrekt (Eloquents
  `__get`-Magie), wäre aber für Larastan/IDE-Unterstützung sauberer mit
  einer ergänzten `@property-read int|null $questions_count`-Annotation.
  Kann aktuell ohnehin nicht durch `composer stan` verifiziert werden
  (dokumentierter Environment-Gap) — daher nur als Anregung, kein Blocker.

- **[UX]** `frontend/src/views/anamnesis/AnamnesisView.vue:344-357`
  (`openTemplateModal()`): Während des `await
  anamnesisTemplatesApi.getById(...)` gibt es keinen sichtbaren
  Ladezustand (z. B. Spinner/Disabled-State auf dem Bearbeiten-Button) —
  bei langsamer Verbindung könnte ein Trainer den Eindruck bekommen, der
  Klick sei wirkungslos, und erneut klicken. Kein Akzeptanzkriterium aus
  `tasks.md` verlangt das, daher nicht blockierend.

---

## Lob (kurz, was gut gelöst wurde)

- Die Entscheidung gegen "alle Fragen löschen und neu anlegen" zugunsten
  einer ID-basierten Synchronisation mit explizitem Löschschutz für
  bereits beantwortete Fragen
  (`backend/app/Http/Controllers/AnamnesisTemplateController.php:148-176`)
  ist sauber begründet, exakt wie in `design.md` Abschnitt 4 vorgeschlagen
  umgesetzt, und durch einen eigenen Testfall
  (`AnamnesisTemplateApiTest.php:306`) belegt. Die doppelte Absicherung
  (globaler `abort_if`-Check auf `$unknownIds` **und** zusätzlich
  `whereKey($id)` innerhalb der Update-/Delete-Query, jeweils gescoped auf
  `$template->questions()`) verhindert Cross-Template-Zugriff auch dann,
  wenn die erste Prüfung je einmal übersprungen würde — defense in depth.
- Die `array_key_exists('questions', …)`-vs-`isset()`-Unterscheidung
  (`UpdateAnamnesisTemplateRequest.php:63`) ist exakt wie gefordert
  umgesetzt und durch einen eigenen Regressionstest
  (`AnamnesisTemplateApiTest.php:353`, "questions"-Schlüssel fehlt) belegt.
- T04/T05 sind trotz fehlendem Docker-Zugriff in der jeweiligen
  Dev-Session ordentlich per Code-Trace nachgewiesen und die offenen
  Punkte (kein echter HTTP-Roundtrip getestet) ehrlich dokumentiert statt
  verschwiegen — durch den inzwischen von Tester/mir durchgeführten realen
  Backend-Testlauf (inkl. des kombinierten Frontend-Payload-Testfalls,
  `AnamnesisTemplateApiTest.php:370`) ist die Lücke nun geschlossen.
- Der Fallback in `AnamnesisTemplateResource::resolveQuestionsCount()`
  (`AnamnesisTemplateResource.php:46-53`) vermeidet eine redundante
  zusätzliche Query in `store()`/`show()` und ist ein gutes Beispiel für
  DRY ohne Performance-Kompromiss.

---

## Zusammenfassung für den Architekten/User

Kein "Muss"-Befund. Zwei "Sollte"-Befunde sind reine Prozess-/
Konsistenz-Themen (unrelated `CLAUDE.md`-Änderung im selben Diff, `test()`
statt `it()` in neuen Tests) plus ein kleiner UX/Konsistenz-Punkt bei der
422-Fehlermeldung — nichts davon gefährdet Datenintegrität oder
Funktionalität. Die sicherheitskritische `syncQuestions()`-Logik wurde
über die reine Diff-Lektüre hinaus gegen die reale Docker-Umgebung
(Postgres) empirisch verifiziert, inklusive einer eigens konstruierten
Gegenprobe zu einem potenziellen JSON-Serialisierungs-Bug beim
Mass-Update, der sich nicht bestätigt hat. Aus Reviewer-Sicht mergefähig,
sobald die beiden "Sollte"-Punkte gesehen/entschieden wurden (Fix oder
bewusstes Zurückstellen).
