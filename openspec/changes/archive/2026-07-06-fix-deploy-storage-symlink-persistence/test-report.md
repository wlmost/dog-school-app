# Test-Report: fix-deploy-storage-symlink-persistence (T01, T02, T03)

**Status:** alle-gruen

## Vorbemerkung

Dieser Change betrifft ausschließlich CI/CD-Pipeline-Konfiguration
(`.github/workflows/deploy.yml`, `.github/workflows/ci.yml`) und
Dokumentation (`DEPLOY-WORKFLOW.md`) — kein Anwendungscode unter `app/`,
`resources/js/` etc. Es existiert daher kein Pest-/Vitest-Test im
klassischen Sinn (siehe auch `task-T01.notes.md`, `task-T03.notes.md`).
Verifiziert wurde stattdessen die einzig sinnvolle Ebene: syntaktische
Korrektheit der YAML-Dateien, funktionale Korrektheit des neuen
Grep-Lint-Jobs (Positiv- und Negativ-Fälle) und Diff-Vollständigkeit
(keine unbeabsichtigten Nebenänderungen).

Alle Prüfungen wurden **unabhängig vom Entwickler-Agenten neu ausgeführt**,
nicht aus `task-T0X.notes.md` übernommen — inklusive eigenständig neu
erzeugter Fehlschlag-Kopien im Scratchpad (siehe Abschnitt 3).

## Hinzugefügte / geänderte Tests

Keine neuen automatisierten Testdateien (kein `tests/`-Verzeichnis
betroffen). Stattdessen: manuelle/skriptgestützte Verifikation, wie sie
`design.md` Abschnitt 6 für diesen Change vorsieht (Grep-basierter
CI-Job `deploy-workflow-lint` ist selbst der einzige automatisierte
Regressionsschutz und wurde hier simuliert und lokal gegen den echten
Stand sowie gegen eigens erzeugte Fehlschlag-Kopien geprüft).

## Akzeptanzkriterien-Abdeckung

### T01 — `.github/workflows/deploy.yml`

- [x] `--exclude='backend/public/storage'` ist Teil der rsync-Exclude-Liste
      — verifiziert per `grep -n "exclude='backend/public/storage'"
      .github/workflows/deploy.yml` → Treffer in Zeile 176.
- [x] Neuer Schritt „Ensure public storage symlink exists" existiert
      zwischen „Deploy files via rsync" und „Run database migrations",
      führt `php artisan storage:link` per SSH aus, ohne
      `|| echo "::warning::..."`-Fallback — verifiziert per `grep -n`
      (Zeile 200 `name:`, Zeile 205 SSH-Aufruf) und Sichtprüfung des
      gesamten Step-Blocks (`.github/workflows/deploy.yml:186-206`); kein
      `|| echo` im Step vorhanden.
- [x] Kommentar-Nummerierung ist konsistent fortlaufend — verifiziert per
      `grep -nE "# [0-9]+\. " .github/workflows/deploy.yml`: Ergebnis
      `1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13` — lückenlos, keine
      Duplikate.
- [x] YAML-Datei ist syntaktisch valide — verifiziert mit **beiden**
      empfohlenen Parsern:
      `ruby -ryaml -e "YAML.load_file(...)"` → `deploy.yml OK`
      `python3 -c "import yaml; yaml.safe_load(open(...))"` →
      `deploy.yml OK (python)`.
- [x] Kein anderer bestehender Inhalt verändert — verifiziert per
      `git diff main -- .github/workflows/deploy.yml`: 5 Hunks, exakt
      die Exclude-Zeile, der neue Step und die drei
      Nummerierungs-Anpassungen der Folgeschritte. Keine Änderung an
      Secrets-Referenzen oder Trigger-Konfiguration.
- [x] `task-T01.notes.md` dokumentiert die Nicht-Anwendbarkeit von
      `composer qa`/Pest korrekt und verweist auf den nötigen manuellen
      Shared-Hosting-Smoke-Test (siehe Abschnitt 5 unten) — bestätigt
      durch Lektüre der Notes-Datei.

Zusätzlich unabhängig verifiziert: Die im Kommentarblock behauptete
Idempotenz von `artisan storage:link` (kein Fehler-Exit bei bereits
vorhandenem Symlink) wurde gegen den tatsächlichen Vendor-Code geprüft —
`backend/vendor/laravel/framework/src/Illuminate/Foundation/Console/StorageLinkCommand.php:32-54`:
`handle()` hat keinen `return`-Wert (impliziter Exit-Code 0), auch im
Zweig, in dem der Link bereits existiert und nicht `--force` gesetzt ist
(`components->error(...)` + `continue`, kein Abbruch). Die Behauptung ist
korrekt. Auch die referenzierte Zeile `backend/.gitignore:5` wurde
gegengeprüft: enthält tatsächlich `/public/storage`.

### T02 — `DEPLOY-WORKFLOW.md`

- [x] Schritt-Tabelle enthält neue Zeile für den `storage:link`-Schritt
      mit identischem Namen wie in `deploy.yml` — verifiziert per Diff-
      Gegenprüfung: `grep -n "Ensure public storage symlink exists"` auf
      beiden Dateien liefert exakt denselben Text
      (`deploy.yml:200` `name:`-Feld vs. `DEPLOY-WORKFLOW.md:182`
      Tabellenzeile).
- [x] Liste „Geschützte Verzeichnisse" enthält `backend/public/storage`
      mit erklärendem Kommentar — per Lektüre bestätigt (Zeilen 198-202).
- [x] Kein anderer Abschnitt verändert — `git diff main --
      DEPLOY-WORKFLOW.md`: 2 Hunks, exakt Tabellenzeile + Listen-Eintrag.
- [x] `DEPLOYMENT.md` wurde nicht verändert — `git diff --stat main --
      DEPLOYMENT.md` liefert keine Ausgabe.

### T03 — `deploy-workflow-lint`-Job in `.github/workflows/ci.yml`

- [x] Job läuft bei jedem Push/PR (kein job-spezifischer `on:`-Filter,
      erbt die Datei-weiten Trigger) — per Lektüre bestätigt.
- [x] Job schlägt fehl, wenn eine der beiden Zeilen aus `deploy.yml`
      entfernt wird — **eigenständig neu simuliert** (nicht die
      Entwickler-Kopien wiederverwendet), siehe Abschnitt 3 unten:
      beide Fehlschlag-Fälle bestätigt (Exit-Code 1, korrekte
      `::error::`-Annotation).
- [x] Job läuft grün gegen den tatsächlich implementierten Stand von
      `deploy.yml` — verifiziert, siehe Abschnitt 2 unten.
- [x] YAML-Syntax von `ci.yml` valide — mit Ruby und Python geprüft.
- [ ] Realer GitHub-Actions-Lauf (tatsächlicher Push/PR-Trigger) — nicht
      testbar in dieser Umgebung, da kein Zugriff auf GitHub Actions.
      Die lokale Grep-Simulation verwendet exakt dieselbe Shell-Logik wie
      im Job definiert; ein Abweichen im echten Runner ist nicht zu
      erwarten (reines POSIX-`grep`, keine Runner-spezifischen
      Abhängigkeiten). Dies ist kein Test-Gap, das im aktuellen Setup
      schließbar wäre — analog zum in Abschnitt 5 dokumentierten
      Shared-Hosting-Smoke-Test.

## Ausführungs-Ergebnis

### 1. YAML-Syntax-Checks (alle drei betroffenen Workflow-/Doku-Dateien)

```
$ ruby -ryaml -e "YAML.load_file('.github/workflows/deploy.yml'); puts 'deploy.yml OK'"
deploy.yml OK
$ ruby -ryaml -e "YAML.load_file('.github/workflows/ci.yml'); puts 'ci.yml OK'"
ci.yml OK
$ python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/deploy.yml')); print('deploy.yml OK (python)')"
deploy.yml OK (python)
$ python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/ci.yml')); print('ci.yml OK (python)')"
ci.yml OK (python)
```

(`DEPLOY-WORKFLOW.md` ist Markdown, kein YAML — kein Parser-Check
anwendbar; stattdessen Diff-Sichtprüfung, siehe Abschnitt 4.)

### 2. Simulation des `deploy-workflow-lint`-Jobs gegen die echte `deploy.yml`

```
$ grep -q "exclude='backend/public/storage'" .github/workflows/deploy.yml && echo "PASS: exclude found"
PASS: exclude found
$ grep -q "artisan storage:link" .github/workflows/deploy.yml && echo "PASS: storage:link found"
PASS: storage:link found

$ ( set -e
    grep -q "exclude='backend/public/storage'" .github/workflows/deploy.yml \
      || { echo "::error::backend/public/storage exclude missing from rsync step"; exit 1; }
    grep -q "artisan storage:link" .github/workflows/deploy.yml \
      || { echo "::error::storage:link step missing from deploy.yml"; exit 1; }
  )
$ echo "job exit code: $?"
job exit code: 0
```

Job läuft grün — deckungsgleich mit dem tatsächlichen Job-Skript in
`.github/workflows/ci.yml`.

### 3. Eigenständig erzeugte Fehlschlag-Simulation (Scratchpad, nicht im Repo)

Zwei neue, unabhängig vom Entwickler erzeugte Kopien in
`/private/tmp/claude-501/.../scratchpad/`:

```
$ grep -v "exclude='backend/public/storage'" .github/workflows/deploy.yml \
    > .../scratchpad/deploy-tester-no-exclude.yml
$ grep -v "artisan storage:link" .github/workflows/deploy.yml \
    > .../scratchpad/deploy-tester-no-link.yml

$ diff .github/workflows/deploy.yml .../scratchpad/deploy-tester-no-exclude.yml
176d175
<             --exclude='backend/public/storage' \
$ diff .github/workflows/deploy.yml .../scratchpad/deploy-tester-no-link.yml
205d204
<             "cd '${{ secrets.DEPLOY_PATH }}/backend' && php artisan storage:link"
```

Jede Kopie unterscheidet sich vom Original um genau die erwartete eine
Zeile. Gegen beide Kopien wurde exakt dieselbe Job-Logik ausgeführt wie
in `.github/workflows/ci.yml`:

```
=== Case A: copy without the exclude line ===
::error::backend/public/storage exclude missing from rsync step
exit code: 1

=== Case B: copy without the storage:link line ===
::error::storage:link step missing from deploy.yml
exit code: 1
```

Beide Fehlschlag-Fälle bestätigt. Die echte `.github/workflows/deploy.yml`
wurde dabei zu keinem Zeitpunkt verändert (nur `grep -v > neue Datei`,
kein `sed -i` o. ä. auf der Originaldatei).

### 4. `git diff` gegen `main` — Vollständigkeits-/Abgrenzungsprüfung

```
$ git diff --stat main -- .github/workflows/deploy.yml .github/workflows/ci.yml DEPLOY-WORKFLOW.md
 .github/workflows/ci.yml     | 16 ++++++++++++++++
 .github/workflows/deploy.yml | 33 ++++++++++++++++++++++++++++-----
 DEPLOY-WORKFLOW.md           |  6 ++++++
 3 files changed, 50 insertions(+), 5 deletions(-)

$ git diff --stat main -- DEPLOYMENT.md
(keine Ausgabe — unverändert, wie in tasks.md/T02 gefordert)

$ git diff --stat main -- backend/ resources/js/
(keine Ausgabe — kein Anwendungscode betroffen, composer qa/npm test
strukturell nicht anwendbar, wie in task-T01.notes.md dokumentiert)
```

Hunk-Zählung je Datei: `deploy.yml` 5 Hunks (Exclude-Zeile, neuer Step,
3× Nummerierungs-Bump), `ci.yml` 1 Hunk (neuer Job), `DEPLOY-WORKFLOW.md`
2 Hunks (Tabellenzeile, Listen-Eintrag) — deckungsgleich mit den in den
Notes-Dateien beschriebenen Änderungsblöcken. Manuelle Volltext-Sichtung
des gesamten Diffs bestätigt: keine Änderung an Secrets-Referenzen,
Trigger-Konfiguration, bestehenden Step-Inhalten oder anderen
Dokumentationsabschnitten.

Kommentar-Nummerierung in `deploy.yml` (`grep -nE "# [0-9]+\. "`):
`1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13` — lückenlos, keine Duplikate.

Namensgleichheit zwischen Code und Doku bestätigt:
`deploy.yml:200` `name: Ensure public storage symlink exists` ==
`DEPLOY-WORKFLOW.md:182` Tabellenzeile `**Ensure public storage symlink
exists**`.

## Fehler

Keine. Alle Prüfungen liefen wie erwartet grün bzw. schlugen in den
gezielt herbeigeführten Negativ-Fällen erwartungsgemäß fehl.

## Explizit dokumentierte Test-Lücke (kein Fehler, erwartetes Verhalten)

**End-to-End-Smoke-Test auf echtem Shared Hosting — muss nach dem Merge
manuell erfolgen:**

Diese Umgebung hat keinen Zugriff auf einen echten Shared-Hosting-Server
und keinen Zugriff auf GitHub Actions (kein Auslösen echter Workflow-Runs
möglich). Folgende Prüfungen sind daher **strukturell nicht
automatisierbar** durch den Tester-Agenten und müssen nach dem Merge
manuell durchgeführt werden:

1. Einen echten Deploy auslösen (regulärer Push auf den Deploy-Trigger-
   Branch oder `workflow_dispatch`, je nach Konfiguration in
   `deploy.yml`).
2. Auf dem Zielserver per SSH prüfen, dass `backend/public/storage` nach
   dem Deploy weiterhin als Symlink auf `storage/app/public` existiert
   (`ls -la backend/public/storage`) und **nicht** durch
   `rsync --delete` entfernt wurde.
3. Ein vor dem Deploy hochgeladenes Bild über die öffentliche URL
   erneut abrufen und bestätigen, dass es weiterhin erreichbar ist
   (HTTP 200, kein 404).
4. Optional: einen zweiten Deploy-Lauf direkt danach anstoßen und
   erneut prüfen (Regressionsschutz gegen einen erneuten
   `rsync --delete`, falls der Exclude aus Versehen wieder entfernt
   würde — hierfür ist T03 der automatisierte Vorab-Schutz, ersetzt
   aber nicht den echten Smoke-Test).

Dies ist **kein Test-Gap, das der Tester-Agent selbst schließen kann**
— es ist eine Konsequenz der fehlenden Infrastruktur-Zugriffsrechte in
dieser Sandbox-Umgebung (kein Shared-Hosting-Zugriff, kein GitHub-
Actions-Trigger) und wird hier bewusst als offener, manuell
abzuschließender Punkt dokumentiert, analog zu den entsprechenden
Hinweisen in `task-T01.notes.md` und `design.md` Abschnitt 6.
