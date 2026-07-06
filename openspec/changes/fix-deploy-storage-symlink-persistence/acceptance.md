# Abnahme: fix-deploy-storage-symlink-persistence

**Status:** bereit-für-user-review

---

## Schritt 0 — Strukturelle Validität

```
openspec validate fix-deploy-storage-symlink-persistence --strict
→ Change 'fix-deploy-storage-symlink-persistence' is valid
```

## Verifikationsmethode

Alle Aussagen unten wurden nicht aus den Agenten-Berichten übernommen,
sondern selbst gegen den tatsächlichen Working-Tree-Diff auf
`feature/fix-deploy-storage-symlink-persistence` nachvollzogen:

```bash
git status --short
git diff --stat main -- .github/workflows/deploy.yml .github/workflows/ci.yml \
  DEPLOY-WORKFLOW.md DEPLOYMENT.md
git diff main -- .github/workflows/deploy.yml
git diff main -- .github/workflows/ci.yml
git diff main -- DEPLOY-WORKFLOW.md
git log --oneline main..feature/fix-deploy-storage-symlink-persistence
```

**Prozess-Hinweis (kein inhaltlicher Mangel, aber wichtig für Gate 2):**
Auf dem Feature-Branch existieren aktuell **keine Commits** —
`git log main..HEAD` ist leer, alle drei geänderten Dateien stehen als
unstaged Working-Tree-Änderungen (`M .github/workflows/ci.yml`,
`M .github/workflows/deploy.yml`, `M DEPLOY-WORKFLOW.md`). Das dem
`WORKFLOW.md`-Schritt 12 zugrunde liegende Kommando
`git diff main...feature/fix-deploy-storage-symlink-persistence` würde
aktuell **leer** ausfallen, weil es auf committete Commits abzielt, nicht
auf den Working Tree. Die inhaltliche Prüfung dieser Abnahme wurde daher
gegen den Working-Tree-Diff (`git diff main -- <Datei>`) geführt, was
inhaltlich gleichwertig ist. **Empfehlung an den User:** vor oder während
Gate 2 die drei Dateien committen (z. B. `feat(fix-deploy-storage-symlink-persistence):
T01 T02 T03 ...`), damit der branch-basierte Diff-Befehl aus der
`WORKFLOW.md` wie vorgesehen funktioniert.

Zusätzlich im Arbeitsverzeichnis vorhanden, aber **nicht** Teil dieses
Changes: `openspec/changes/course-run-booking/`,
`openspec/triage/20260517184957-course-run-booking.md`,
`openspec/triage/20260706173935-dog-image-not-shown-after-edit.md`
(letztere ist die zugehörige Triage-Datei dieses Changes und gehört mit
committet — die ersten beiden gehören zu einem separaten, hier
irrelevanten Vorhaben und sollten beim Commit dieses Changes **nicht**
mit eingecheckt werden).

---

## Vollständigkeit (Tasks)

Alle drei Tasks in `tasks.md` sind vollständig abgehakt (`[x]`):

- **T01** (`.github/workflows/deploy.yml`): im Diff bestätigt —
  `--exclude='backend/public/storage'` in der rsync-Exclude-Liste
  (Zeile nach `--exclude='backend/storage/app/'`), neuer Schritt „Ensure
  public storage symlink exists" zwischen „Deploy files via rsync" und
  „Run database migrations" ohne Best-Effort-Fallback, Kommentar-Nummerierung
  der Folgeschritte lückenlos auf `# 9.` bis `# 13.` erhöht. Kein anderer
  Bestandsinhalt (Secrets, Trigger, übrige Schritte) verändert.
- **T02** (`DEPLOY-WORKFLOW.md`): im Diff bestätigt — neue Tabellenzeile
  „Ensure public storage symlink exists" (Name identisch mit dem
  `name:`-Feld aus T01) zwischen `rsync`- und `Migrationen`-Zeile, neuer
  Eintrag `backend/public/storage` in „Geschützte Verzeichnisse" mit
  erklärendem Kommentar. `DEPLOYMENT.md` unverändert
  (`git diff --stat main -- DEPLOYMENT.md` liefert keine Ausgabe) — korrekt,
  da dieses Dokument den unbetroffenen Wizard-/VPS-Pfad beschreibt.
- **T03** (könnte, umgesetzt): neuer Job `deploy-workflow-lint` in
  `.github/workflows/ci.yml`, Grep-Check auf beide neuen Textmuster aus T01,
  kein Docker, keine neue Tool-Abhängigkeit. Diff enthält ausschließlich
  diesen neuen Job, `backend-tests`/`frontend-tests` unverändert.

Fazit: **Alle drei Tasks sind im Working Tree tatsächlich umgesetzt** —
keine Diskrepanz zwischen den Agenten-Berichten (`task-T01.notes.md`,
`task-T02.notes.md`, `task-T03.notes.md`) und dem selbst gelesenen Diff
gefunden. Die in den Notes zitierten Zeilennummern (z. B. `deploy.yml:176`
Exclude, `:200` `name:`-Feld, `:205` SSH-Aufruf) stimmen mit dem
tatsächlichen Diff überein.

---

## Kohärenz mit `proposal.md` „Ziel" (explizite Prüfung)

1. **„Ein bereits vorhandener Symlink wird von keinem automatisierten
   Deploy mehr gelöscht"** — erfüllt: `--exclude='backend/public/storage'`
   schützt den Pfad kategorisch vor `rsync --delete`, unabhängig davon, ob
   er in der Quelle existiert (rsync-Exclude-Semantik, in `design.md`
   Abschnitt 3.1 korrekt beschrieben und im Diff exakt so umgesetzt).
2. **„Fehlt der Symlink, wird er bei jedem automatisierten Deploy
   automatisch angelegt, ohne den Job fehlschlagen zu lassen, falls er
   bereits existiert"** — erfüllt: neuer Schritt ruft `php artisan
   storage:link` ohne `--force` und ohne Fallback auf; die Idempotenz
   (Exit-Code 0 in beiden Fällen) wurde sowohl vom Architekten
   (`design.md` Abschnitt 4) als auch vom Skeptiker (`verification.md`,
   „Zusätzlich eigenständig geprüfte Punkte", Punkt 3) als auch vom Tester
   (`test-report.md`, Abschnitt „Ausführungs-Ergebnis") unabhängig
   voneinander gegen den Vendor-Code (`StorageLinkCommand.php` +
   `Illuminate\Console\Command::execute()`) verifiziert — keine bloße
   Übernahme einer einzelnen Behauptung.
3. **„Dokumentation beschreibt den korrigierten Ablauf"** — erfüllt:
   `DEPLOY-WORKFLOW.md` wurde an beiden relevanten Stellen aktualisiert,
   Namensgleichheit zwischen Workflow-Code und Doku wurde von Reviewer und
   Tester unabhängig gegengeprüft und ist im Diff bestätigt.

Alle drei Ziele aus `proposal.md` sind durch die Implementierung
tatsächlich erfüllt, nicht nur behauptet.

---

## Spec-Konformität

`specs/deployment-pipeline/spec.md` (neue Capability) fordert exakt die
drei oben geprüften Verhaltensweisen (Symlink übersteht Deploy, fehlender
Symlink wird angelegt, wiederholtes Ausführen bricht den Job nicht ab).
Die Implementierung in `.github/workflows/deploy.yml` deckt alle drei
Szenarien der Spec-Datei ab:

- „Existing symlink survives an automated deploy" → durch den rsync-Exclude
  abgedeckt.
- „Missing symlink is created during an automated deploy" → durch den
  neuen `storage:link`-Schritt abgedeckt.
- „Re-running the symlink step against an already-linked target does not
  fail the deploy" → durch die verifizierte Idempotenz (Exit-Code 0)
  abgedeckt.

Kein Widerspruch zwischen Spec-Delta und Implementierung gefunden.

---

## Review-Befunde

Alle drei Reviews (`task-T01.review.md`, `task-T02.review.md`,
`task-T03.review.md`) melden Gesamtempfehlung „ok", keine „Muss"- und
keine „Sollte"-Befunde. Zwei „Könnte"-Hinweise wurden von mir selbst
geprüft:

1. **T01, kosmetisch:** Der neue Exclude
   `--exclude='backend/public/storage'` hat keinen abschließenden `/`,
   während die Nachbar-Excludes (`backend/storage/app/` etc.) einen haben.
   Eigene Prüfung: Das ist **korrekt so**, nicht nachbesserungswürdig —
   `backend/public/storage` ist (im Zielzustand) ein Symlink, kein
   Verzeichnis; ein abschließender `/` bei einem rsync-Exclude verlangt,
   dass der Pfad ein Verzeichnis ist, und würde einen Symlink gerade
   **nicht** matchen. Der fehlende Slash ist hier fachlich richtig, die
   optische Inkonsistenz zu den Nachbarzeilen ist rein kosmetisch und nicht
   blockierend.
2. **T03, Robustheit:** Die beiden Grep-Muster im neuen
   `deploy-workflow-lint`-Job suchen ohne Kontext-Anker über die gesamte
   Datei. Eigene Prüfung des Diffs: Der erklärende Kommentarblock in
   `deploy.yml` verwendet „Laravel's storage:link command" (nicht „artisan
   storage:link"), es gibt daher aktuell keine Kollision mit dem zweiten
   Grep-Muster. Der Hinweis ist zutreffend dokumentiert, aber bewusst als
   „bewusst kein vollwertiger Linter" (YAGNI, `design.md` Abschnitt 6)
   begründet und für den engen Zweck (Regressionsschutz gegen genau diese
   Regression) ausreichend. Nicht blockierend.

Beide Punkte sind damit tatsächlich nicht blockierend — keine Nacharbeit
nötig.

---

## Test-Ergebnisse

`test-report.md`, Status „alle-gruen". Da dieser Change ausschließlich
CI/CD-YAML und Markdown-Dokumentation betrifft (kein Anwendungscode unter
`app/`, `resources/js/`), ist die dokumentierte Test-Strategie angemessen:
YAML-Syntax-Checks (Ruby + Python, beide für `deploy.yml` und `ci.yml`),
eigenständig simulierte Positiv-/Negativ-Fälle des neuen Grep-Jobs (gegen
selbst erzeugte Fehlschlag-Kopien im Scratchpad, nicht gegen die
Original-Datei), sowie Diff-Vollständigkeitsprüfung. Die einzige
dokumentierte Lücke — ein echter End-to-End-Smoke-Test auf dem
Shared-Hosting-Zielserver nach einem realen Deploy — ist strukturell
nicht aus dieser Umgebung heraus automatisierbar und explizit als
Post-Merge-Schritt dokumentiert, kein Fehlschlag. Kein ungetestetes
Akzeptanzkriterium gefunden; die einzige nicht abgehakte Zeile im
Test-Report (realer GitHub-Actions-Lauf) ist plausibel als
Umgebungsgrenze, nicht als Test-Gap, begründet.

---

## Erfüllt

- Alle drei Ziele aus `proposal.md` Abschnitt „Ziel" sind durch den
  tatsächlichen Diff nachweislich erfüllt (siehe Abschnitt „Kohärenz"
  oben).
- Symlink-Idempotenz wurde von drei unabhängigen Rollen (Architekt,
  Skeptiker, Tester) jeweils eigenständig gegen den Vendor-Code verifiziert
  — keine bloße Weitergabe einer unbelegten Behauptung.
- Kein Anwendungscode (PHP, Vue) betroffen — PHP-8.2-Kompatibilität und
  DB-Portabilität (CLAUDE.md Abschnitt 4) sind für diesen Change korrekt
  als „nicht anwendbar" eingestuft.
- Dokumentation (`DEPLOY-WORKFLOW.md`) und Code (`deploy.yml`) sind
  namensgleich und driftfrei; `DEPLOYMENT.md` bleibt für den unbetroffenen
  Wizard-Pfad unverändert.
- Optionaler Regressionsschutz (T03) wurde vom User im User-Gate 1
  gewünscht und vollständig umgesetzt, inkl. eigenständig simulierter
  Fehlschlag-Fälle durch Reviewer und Tester.
- `openspec validate --strict` grün.

## Offen / Nacharbeit

Keine blockierende Nacharbeit. Folgende Punkte sind dem User zur
Kenntnisnahme, keine Blockade der Abnahme:

- **Noch keine Commits auf dem Feature-Branch** (siehe
  „Verifikationsmethode" oben). Vor oder während User-Gate 2 sollten die
  drei geänderten Dateien committet werden, damit
  `git diff main...feature/fix-deploy-storage-symlink-persistence`
  (wie in `WORKFLOW.md` Schritt 12 vorgesehen) tatsächlich etwas anzeigt.
  Diese Abnahme wurde ersatzweise gegen den Working-Tree-Diff geführt,
  inhaltlich gleichwertig.
- **Echter Shared-Hosting-Smoke-Test nach dem nächsten automatisierten
  Deploy weiterhin nötig** (dokumentiert in `task-T01.notes.md` und
  `test-report.md`): nach einem über `.github/workflows/deploy.yml`
  ausgelösten Deploy per SSH prüfen, dass `backend/public/storage`
  weiterhin als Symlink existiert, und ein zuvor hochgeladenes Bild sowie
  ein neu hochgeladenes Bild über die öffentliche URL erreichbar sind.
  Kein Grund, den Change selbst zurückzuhalten — Post-Deployment-
  Verifikation.
- Im Arbeitsverzeichnis liegen zusätzlich unbeteiligte, unversionierte
  Artefakte eines anderen Vorhabens
  (`openspec/changes/course-run-booking/`,
  `openspec/triage/20260517184957-course-run-booking.md`). Diese gehören
  nicht zu diesem Change und sollten beim Commit **nicht** mit
  eingecheckt werden.

## Empfehlung an den User

Der Change ist inhaltlich vollständig, alle drei Ziele aus `proposal.md`
sind im tatsächlichen Diff nachweislich umgesetzt, keine offenen
„Muss"-Befunde, beide „Könnte"-Hinweise sind bei eigener Prüfung
tatsächlich nicht blockierend. **Empfehlung: Freigabe für User-Gate 2**,
unter der Auflage, vor Gate 2 die drei Dateien zu committen (damit der
branch-basierte Diff-Befehl greift) und nach dem nächsten Produktions-
Deployment den beschriebenen Shared-Hosting-Smoke-Test durchzuführen.
