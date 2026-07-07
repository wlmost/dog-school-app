# Abnahme: fix-anamnesis-template-questions-not-saved

**Status:** bereit-für-user-review

---

## 0. Strukturelle Validität

```
$ openspec validate fix-anamnesis-template-questions-not-saved --strict
Change 'fix-anamnesis-template-questions-not-saved' is valid
```

Keine Nacharbeit an der Spec-Struktur nötig.

## 1. Vollständigkeit der Tasks

Alle 5 Tasks in `tasks.md` sind vollständig abgehakt (`[x]` auf allen Akzeptanzkriterien von T01–T05), jede mit zugehöriger `task-T0X.notes.md`. T04/T05 wurden — dokumentiert in `task-T04.notes.md:5-15` und deckungsgleich mit der expliziten User-Korrektur an `CLAUDE.md` — mit `dev-typescript` statt dem in der ursprünglichen `tasks.md` (Zeile 173, 216) eingetragenen `dev-javascript` durchgeführt. Diese Abweichung ist sachlich begründet (`frontend/src/**/*.vue` ist durchgängig `<script setup lang="ts">`, `vue-tsc -b` ist fester Bestandteil des Builds — siehe `git diff -- CLAUDE.md`, Abschnitte 2/6/7.2), vom User autorisiert und für die Abnahme unkritisch, da der tatsächlich zuständige Agent-Typ (TypeScript-fähig) korrekt eingesetzt wurde. Kein Nacharbeitsbedarf.

## 2. Beide gemeldeten Symptome — Abdeckung geprüft

Das Problem-Statement (`proposal.md:13-22`) nennt zwei Beobachtungen des Trainers: Fragen "werden nicht gespeichert" beim Anlegen **und** beim Bearbeiten. Die Root-Cause-Analyse identifiziert dafür zwei unabhängige Ursachen — beide sind durch T01–T05 abgedeckt:

- **Ursache 1 (Anzeigeproblem, Create UND Edit, kein echter Datenverlust):** Behoben durch T02 (`questionsCount` in `AnamnesisTemplateResource`/`index()`, verifiziert per `git diff` gegen `backend/app/Http/Controllers/AnamnesisTemplateController.php` und `backend/app/Http/Resources/AnamnesisTemplateResource.php` — Code entspricht 1:1 `design.md` Abschnitt 3) und T04 (`AnamnesisView.vue::openTemplateModal()` lädt jetzt per `getById()` nach, verifiziert per `git diff` — entspricht `design.md` Abschnitt 6). Der ursprünglich beobachtete Effekt ("0 Fragen" in Liste und beim erneuten Öffnen) ist damit strukturell behoben.
- **Ursache 2 (echter Persistenz-Bug, nur Edit, echter Datenverlust):** Behoben durch T03 (`syncQuestions()` in `AnamnesisTemplateController::update()`, ID-basierte Synchronisation mit Lösch-Schutz für beantwortete Fragen — Code-Diff stimmt exakt mit `design.md` Abschnitt 4 überein) und T05 (Frage-`id` durchgängig von `getById()` über das Formular bis in den `PUT`-Payload durchgereicht, verifiziert per `git diff` gegen `AnamnesisTemplateFormModal.vue`/`anamnesis.ts`). Ohne T05 wäre T03 wirkungslos geblieben (jede Bearbeitung hätte clientseitig wie "alle Fragen sind neu" ausgesehen) — das ist in `task-T03.notes.md:152-159` korrekt antizipiert und durch T05 tatsächlich geschlossen.
- **Der ursprünglich befürchtete "neue, schwerwiegenere" Datenverlust** (Kaskaden-Löschung von `AnamnesisAnswer` bei einem naiven "alle löschen und neu anlegen"-Fix) wurde durch die gewählte ID-Diff-Lösung vermieden und ist durch einen eigenen Testfall ("removing a question with existing answers …") sowie durch den Reviewer zusätzlich empirisch gegen die reale Docker-Umgebung geprüft (`review.md:27-40`, JSON-Serialisierungs-Hypothese widerlegt).

**Eigene Verifikation (unabhängig von Dev/Reviewer/Tester durchgeführt):**

```
$ docker compose exec php vendor/bin/pest --filter=AnamnesisTemplateApiTest
Tests: 30 passed (125 assertions)

$ docker compose exec php vendor/bin/pest
Tests: 679 passed (2120 assertions)
```

Beide Läufe decken sich exakt mit den in `review.md` und `test-report.md` dokumentierten Zahlen — keine Abweichung, keine Regression. Stichprobenartiger Diff-Abgleich (`git diff` gegen `AnamnesisTemplateController.php`, `AnamnesisTemplateResource.php`, `UpdateAnamnesisTemplateRequest.php`, `AnamnesisView.vue`, `anamnesis.ts`, `AnamnesisTemplateFormModal.vue`) bestätigt: Der tatsächliche Code entspricht 1:1 den in `design.md` vorgeschlagenen Implementierungen und den Angaben in den jeweiligen `task-T0X.notes.md`.

## 3. Spec-Konformität

Alle 3 ADDED-Requirements in `specs/anamnesis-template-management/spec.md` sind durch konkrete, benannte Testfälle abgedeckt:

- "Template listings and detail views expose a questions count" → T02-Test "listed templates report the correct questions count per template" (0/2/5-Fragen-Szenario) plus bestehende Tests für Detail-/Create-Antwort.
- "Updating a template synchronizes its questions" (7 Szenarien inkl. "Sending an empty questions array removes all unanswered questions") → alle 6 T03-Testfälle plus der vom Tester ergänzte gemischte Payload-Testfall. Das "empty array"-Szenario aus der Spec ist nicht als isolierter Testfall in `tasks.md`/`design.md` explizit benannt, folgt aber unmittelbar aus derselben `syncQuestions()`-Logik (leeres `$incomingIds`-Array → alle unbeantworteten Fragen landen in `$toDelete`) und ist durch dieselbe Code-Prüfung abgedeckt wie Szenario 3 ("Questions omitted … are removed"); kein eigener Blocker, da die Implementierung diesen Fall nicht anders behandelt als "alle bestehenden IDs fehlen im Payload".
- "Editing a template loads its full question set before display" → durch T04-Diff sowie den neuen Browser-E2E-Test (`frontend/e2e/anamnesis-templates.spec.ts`) end-to-end abgedeckt.

Keine Abweichung zwischen Spec-Delta und tatsächlicher Implementierung gefunden.

## 4. Review-Befunde

`review.md`: **0 Muss-Befunde.** 2 Sollte-Befunde:

- **Testkonvention (`it()` statt `test()`)** — laut Aufgabenstellung bereits nachträglich behoben; eigene Stichprobe (`grep -n "^test(\|^it(" backend/tests/Feature/AnamnesisTemplateApiTest.php`) bestätigt: alle 7 neuen T03-/Tester-Testfälle verwenden jetzt durchgängig `it(...)`. Erledigt.
- **`CLAUDE.md`-Änderung in eigenen `chore:`-Commit auslagern** — reines Commit-Organisations-Thema (aktuell ist noch nichts committet, alles liegt im Arbeitsverzeichnis, siehe `git status`). Kein Code-Fix nötig; wird beim finalen Commit-Schnitt berücksichtigt (Empfehlung an den User unten).

Ein dritter, "Könnte"-eingestufter Befund zur 422-Fehlermeldung (`abort_if(...)` liefert englischen Text ohne `errors`-Struktur, `review.md:85-101`) ist als "Sollte" klassifiziert im Review, betrifft aber ausschließlich einen über die normale UI nicht erreichbaren Fall (Cross-Template-`id`-Manipulation) und ist explizit als nicht-blockierend markiert — dokumentiert, kein Abnahme-Hindernis.

## 5. Testergebnisse

`test-report.md`: **alle-gruen.** Backend 679/679, Frontend 128/128 Vitest, `vue-tsc -b` und `npm run build` fehlerfrei, zusätzlich ein neuer Playwright-E2E-Test, der die komplette Kette Vue→Backend→DB→Reload zweimal reproduzierbar grün durchläuft. Alle Akzeptanzkriterien aus `tasks.md` sind mit konkreten Testfällen oder (bei T04/UI-Ladezustand) mit dem E2E-Test verifiziert; keine offenen, ungetesteten Akzeptanzkriterien gefunden.

Bekannte, dokumentierte Environment-Gaps (kein Blocker für diesen Change, da vorbestehend und in mehreren vorangegangenen Changes bereits identisch dokumentiert):
- `composer qa`/`stan`/`compat-check` im Docker-`php`-Service nicht ausführbar (fehlende Dev-Dependencies im `backend/composer.json` des Containers) — durch manuelle PHP-8.2-Kompatibilitätsprüfung der Entwickler und Reviewer ersetzt.
- `npm run lint` existiert nicht im Frontend (kein ESLint konfiguriert).
- Vorbestehender E2E-Drift in 3-4 älteren Playwright-Specs (`**/home`-Route existiert nicht mehr) — unabhängig von diesem Change, nicht durch ihn verursacht, nicht in diesem Change zu beheben.

## Erfüllt

- Beide ursprünglich gemeldeten Symptome (Anzeigeproblem beim Anlegen/Auflisten und echter Datenverlust beim Bearbeiten) sind durch die Implementierung behoben und durch Tests belegt.
- Kein Datenverlust-Risiko für bereits erfasste `AnamnesisAnswer`-Datensätze (ID-Diff-Ansatz statt "alle löschen und neu anlegen", eigener Testfall, Reviewer-Gegenprobe).
- Alle 5 Tasks vollständig, alle Akzeptanzkriterien erfüllt, keine offenen Muss-Befunde.
- `openspec validate --strict` erfolgreich.
- Eigene, unabhängige Nachverifikation (Backend-Vollsuite + gezielter Testlauf) bestätigt exakt die in `review.md`/`test-report.md` berichteten Zahlen — keine Diskrepanz.
- Kohärenz zwischen proposal.md, design.md, tasks.md, spec.md, verification.md, review.md und test-report.md ist durchgehend gegeben; keine widersprüchlichen Aussagen gefunden.
- PHP-8.2-Kompatibilität und MySQL/Postgres-Portabilität sind gewahrt (reines Eloquent, keine raw SQL, keine 8.3/8.4-Sprachfeatures — von Dev-Agent, Reviewer und stichprobenartig auch hier durch Diff-Lektüre bestätigt).

## Offen / Nacharbeit

- Keine inhaltliche Nacharbeit an Code oder Tests nötig.
- **Reiner Organisationspunkt vor dem finalen Commit** (kein Blocker für User-Gate 2, aber zu berücksichtigen vor Schritt 13/14 des Workflows): Die `CLAUDE.md`-Korrektur sollte in einen eigenen `chore:`-Commit ausgelagert werden, getrennt von den `fix:`/`feat:`/`test:`-Commits für T01–T05, damit der Bugfix-Commit-Verlauf sauber fokussiert bleibt (bereits im Review als Sollte-Befund vermerkt, hier nur als Erinnerung für den Commit-Schnitt wiederholt).
- Die "Könnte"-Befunde aus `review.md` (englische 422-Meldung ohne `errors`-Struktur; fehlende `@property-read`-Annotation für `questions_count`; kein UI-Ladezustand während `getById()`; Testdatei-Pfad-Konvention) sind dokumentiert, nicht blockierend, und können bei Gelegenheit in einem Folge-Change aufgegriffen werden.

## Empfehlung an den User

Der Change ist inhaltlich und technisch abnahmereif: beide gemeldeten Bugs sind behoben, keine Muss-Befunde offen, alle Tests grün (eigenständig nachverifiziert), Spec-Struktur valide. Vor der Archivierung sollte lediglich die `CLAUDE.md`-Korrektur in einen separaten `chore:`-Commit ausgelagert werden, bevor der eigentliche Fix committet wird — das ist ein reiner Commit-Hygiene-Schritt, kein Grund, User-Gate 2 zurückzuhalten.
