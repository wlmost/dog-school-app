# Abnahme: add-missing-qa-tooling

**Status:** bereit-für-user-review

## Prüfschritte durchgeführt

- `openspec validate add-missing-qa-tooling --strict` → **"Change 'add-missing-qa-tooling' is valid"** (Exit 0).
- `tasks.md` gelesen: alle Akzeptanzkriterien von T01, T02, T03 als `[x]` markiert.
- Diff/Working-Tree gegen `design.md`/`proposal.md`/`specs/qa-tooling/spec.md` stichprobenartig gegengelesen (siehe unten).
- `task-T01.review.md`, `task-testreport.md`, `task-T01.notes.md` §11, `task-T02.notes.md`, `task-T03.notes.md` gelesen.

## Erfüllt

- **Spec-Konformität, real verifiziert:**
  - `backend/composer.json:64-73` enthält `test`, `lint`, `stan`, `compat-check`, `qa` (Kette) — deckungsgleich mit `specs/qa-tooling/spec.md` Requirement 1.
  - `backend/phpstan.neon` existiert, Repo-Root-`phpstan.neon` existiert nicht mehr (`ls phpstan.neon` → "No such file or directory") — Requirement 2 erfüllt.
  - `frontend/package.json:11` `"lint": "eslint ."`, `frontend/eslint.config.ts` vorhanden — Requirement 3 erfüllt.
  - `.github/workflows/ci.yml` Diff selbst gelesen: `backend-tests`-Job ruft jetzt `composer qa` statt direktem `pest`-Aufruf; `frontend-tests`-Job hat neuen Step `npm run lint`; kein anderer Step/Job verändert — Requirement 4 erfüllt.
  - `larastan/larastan` und `phpcompatibility/php-compatibility` real in `backend/composer.lock` als `packages-dev` vorhanden (selbst per `grep` geprüft, Zeilen 7418 bzw. 8400).
- **Blocker aus `task-T01.review.md` behoben und unabhängig re-verifiziert:** Der einzige Muss-Befund (Merge des doppelten `config`-Keys aktiviert `config.platform.php: 8.3.0` erstmals wirksam und kollidiert mit dem gelockten `symfony/yaml v8.0.8`, das `php >=8.4` verlangt → `composer install` bricht mit Exit-Code 2 ab, exakt der CI-Pfad) ist gelöst: `symfony/yaml` wurde per `composer update symfony/yaml --with-all-dependencies` auf `v7.4.15` (`php ^8.2`-kompatibel) zurückgesetzt. Selbst im `composer.lock` verifiziert (`grep -A3 '"name": "symfony/yaml"'` → `"version": "v7.4.15"`). Reviewer-Update in `task-T01.review.md` (Zeile 9-19) bestätigt eigenständig einen frischen `rm -rf vendor && composer install` (ohne `--ignore-platform-reqs`, exakter CI-Befehl) sowie `composer qa`, beide Exit-Code 0, minimaler Lockfile-Diff (nur `symfony/yaml` + zwei transitive Patch-Bumps), keine neuen `composer audit`-Advisories. Damit ist die einzige Gesamtempfehlung des Reviewers von "nacharbeit-nötig" auf abnahmefähig aktualisiert.
- **Tests grün:** `task-testreport.md` dokumentiert 718 Backend-Tests (Pest) und 194 Frontend-Tests (Vitest), jeweils reproduziert über mehrere Läufe, sowie `composer qa` und `npm run lint` beide mit Exit-Code 0. `npm run build` läuft fehlerfrei (Akzeptanzkriterium für Frontend-Tasks laut `CLAUDE.md` §7.1 "Frontend-Tasks").
- **Zwei dokumentierte, nicht-blockierende Flakes** (unseeded Faker-Kollision in `CustomerApiTest`, transiente Pint-Whitespace-Falschmeldung) sind nachvollziehbar als vorbestehend bzw. Docker-Bind-Mount-Artefakt begründet, nicht durch diesen Change verursacht (Pint-Diff der betroffenen Testdatei enthält nur Whitespace-Änderungen).
- **Scope-Grenze eingehalten:** Kein Anwendungscode wurde logisch verändert. Die einmalige, vom User freigegebene mechanische Pint-Reformatierung (291 Dateien) ist dokumentiert (`task-T01.notes.md` §7) und wurde vom Reviewer stichprobenartig an 8 Dateien unterschiedlicher Kategorien sowie per Syntax-/Kollisions-Scan verifiziert — keine Logikänderung gefunden.
- **PHP-8.2-Kompatibilität und DB-Portabilität:** Dieser Change betrifft ausschließlich Tooling/Konfiguration, keine Migrations oder raw SQL — Abschnitt 4.1/4.2 aus `CLAUDE.md` sind nicht einschlägig; das neue `compat-check`-Script selbst prüft `testVersion 8.2` gegen Anwendungscode, wie in `proposal.md`/`design.md` vorgesehen.
- **Verbleibende "Sollte"/"Könnte"-Punkte aus `task-T01.review.md`** (Doku-Präzision zur Formulierung "vorbestehend" vs. "durch T01 aktiviert"; Ergänzungsvorschlag für einen künftigen Fresh-Install-Prüfschritt im `design.md`-Prüfschritt-Katalog; optionaler `composer validate --strict`-Schnellcheck in CI) sind laut Reviewer selbst nicht blockierend, sondern Verbesserungsvorschläge für Folge-Changes. Sie werden hiermit dokumentiert, nicht in diesem Change umgesetzt (YAGNI/Scope-Grenze).

## Offen / Nacharbeit

- Keine blockierenden Befunde. Empfehlenswert für einen **separaten** Folge-Change (kein Blocker hier): Fix des `CustomerApiTest`-Flakes (Faker-Seed/Kollisionsvermeidung), siehe `task-testreport.md` "Fehler/Beobachtungen".
- Der Arbeitsbaum auf `feature/add-missing-qa-tooling` enthält die Implementierung derzeit **unkommittiert** (`git status` zeigt alle Task-Dateien als modifiziert/neu, keine divergierenden Commits gegenüber `main`). Das ist keine inhaltliche Nacharbeit, aber vor Schritt 13 (`openspec archive`) und Schritt 14 (PR) muss noch committet werden (T01/T02/T03 je einzeln oder gebündelt, gemäß `WORKFLOW.md` Schritt 8/9-Konvention).
- `task-T03.notes.md` weist selbst darauf hin, dass ein echter GitHub-Actions-CI-Lauf (statt nur lokaler Docker-Nachvollzug) noch aussteht — laut Akzeptanzkriterium von T03 ("lokal ODER CI") bereits erfüllt, aber die stärkere Bestätigung folgt erst nach dem Push.

## Empfehlung an den User

Der Change ist inhaltlich abnahmefähig: alle drei Tasks sind vollständig, spec-konform umgesetzt und durch Tester sowie einen zweiten, unabhängigen Reviewer-Durchlauf verifiziert; der einzige Muss-Blocker (`symfony/yaml`-Plattformkonflikt) ist behoben und nachweislich gelöst. Freigabe für User-Gate 2 empfohlen — danach committen, `openspec archive add-missing-qa-tooling` und Push, um den echten CI-Lauf als finale Bestätigung zu erhalten.
