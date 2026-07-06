# Tasks für fix-deploy-storage-symlink-persistence

## T01: `.github/workflows/deploy.yml` — Symlink vor Löschung schützen und automatisch sicherstellen

- **Agent:** dev-php
- **Dateien:** `.github/workflows/deploy.yml`
- **Abhängigkeiten:** keine
- **Priorität:** Pflicht

### Beschreibung

Siehe `design.md`, Abschnitt 2 (Begründung der Agenten-Zuordnung) und
Abschnitt 3 (vollständige Details).

1. Im Schritt „Deploy files via rsync" (aktuell `.github/workflows/deploy.yml:171-182`)
   die Zeile `--exclude='backend/public/storage'` zur bestehenden
   Exclude-Liste hinzufügen (Gruppierung mit den anderen `storage`-Excludes,
   Reihenfolge ist rsync-technisch unerheblich).
2. Direkt nach diesem Schritt und vor dem Schritt „Run database migrations"
   (aktuell Zeilen 184-192) einen neuen Schritt „Ensure public storage
   symlink exists" einfügen, der per SSH `cd
   '${{ secrets.DEPLOY_PATH }}/backend' && php artisan storage:link`
   ausführt — **ohne** Best-Effort-Fallback (`|| echo "::warning::..."`),
   analog zum bestehenden Migrations-Schritt (Begründung: `design.md`,
   Abschnitt 3.3).
3. Den erklärenden Kommentarblock (Format wie die bestehenden
   `# -----` / `# N. <Titel>`-Blöcke) exakt wie in `design.md`, Abschnitt
   3.2, vorformuliert übernehmen (inkl. Verweis auf
   `backend/.gitignore:5` und die verifizierte Idempotenz von
   `storage:link`).
4. Die nachfolgenden Kommentar-Nummern (`# 8.` bis `# 12.`) um jeweils
   eins erhöhen (→ `# 9.` bis `# 13.`), damit die fortlaufende
   Nummerierung konsistent bleibt.
5. Kein anderer Inhalt der Datei darf verändert werden (bestehende
   Schritte, Secrets-Referenzen, Trigger-Konfiguration bleiben
   unangetastet).

### Akzeptanzkriterien

- [x] `--exclude='backend/public/storage'` ist Teil der rsync-Exclude-Liste
      im Schritt „Deploy files via rsync".
- [x] Ein neuer Schritt „Ensure public storage symlink exists" existiert
      zwischen „Deploy files via rsync" und „Run database migrations" und
      führt `php artisan storage:link` per SSH aus, **ohne**
      `|| echo "::warning::..."`-Fallback.
- [x] Die Kommentar-Nummerierung der nachfolgenden Schritte ist konsistent
      fortlaufend (keine Lücken, keine Duplikate).
- [x] Die YAML-Datei ist syntaktisch valide (z. B. `yamllint
      .github/workflows/deploy.yml` falls verfügbar, oder zumindest ein
      YAML-Parser-Check wie `python3 -c "import yaml,
      sys; yaml.safe_load(open('.github/workflows/deploy.yml'))"` /
      `ruby -ryaml -e "YAML.load_file('.github/workflows/deploy.yml')"` —
      je nachdem, was in der Entwicklungsumgebung verfügbar ist).
- [x] Kein anderer bestehender Schritt/Inhalt der Datei wurde verändert
      (per `git diff` nachvollziehbar).
- [x] `task-T01.notes.md` dokumentiert, dass dieser Change **nicht** durch
      `composer qa`/Pest verifizierbar ist (YAML liegt außerhalb von
      `backend/`, siehe `design.md`, Abschnitt 6) und ein echter
      Shared-Hosting-Smoke-Test (Deploy auslösen, prüfen, dass
      `backend/public/storage` danach existiert und ein Bild-Upload
      erreichbar ist) nach dem Merge nötig bleibt, bis T03 (falls
      umgesetzt) eine automatisierte Teilprüfung liefert.

---

## T02: `DEPLOY-WORKFLOW.md` — Dokumentation an den korrigierten Ablauf anpassen

- **Agent:** dev-php
- **Dateien:** `DEPLOY-WORKFLOW.md`
- **Abhängigkeiten:** T01 (Dokumentation muss den tatsächlich implementierten
  Schritt-Namen und die tatsächliche Position exakt widerspiegeln)
- **Priorität:** Pflicht

### Beschreibung

Siehe `design.md`, Abschnitt 5, für die vollständige Begründung, warum
`DEPLOY-WORKFLOW.md` (statt `DEPLOYMENT.md`) das korrekte
Dokumentationsziel ist.

1. In der Schritt-Tabelle (Abschnitt 5, „Was der Workflow im Detail
   macht", aktuell `DEPLOY-WORKFLOW.md:171-186`) eine neue Zeile zwischen
   der `rsync`-Zeile und der `Migrationen`-Zeile einfügen, die den in T01
   implementierten Schritt beschreibt (Name muss exakt mit dem in T01
   gewählten `name:`-Feld übereinstimmen).
2. In der Liste „Geschützte Verzeichnisse" (aktuell
   `DEPLOY-WORKFLOW.md:188-197`) `backend/public/storage` als neuen
   Eintrag ergänzen, mit einem kurzen Kommentar, der klarstellt, dass es
   sich (anders als die übrigen Einträge) um einen Symlink handelt, der
   nach dem Sync automatisch sichergestellt wird (nicht um ein
   Nutzerdaten-Verzeichnis).
3. Keine anderen Abschnitte von `DEPLOY-WORKFLOW.md` verändern.

### Akzeptanzkriterien

- [x] Die Schritt-Tabelle enthält eine neue Zeile für den `storage:link`-
      Schritt, mit demselben Namen wie in `deploy.yml` (T01).
- [x] Die Liste „Geschützte Verzeichnisse" enthält `backend/public/storage`
      mit erklärendem Kommentar.
- [x] Kein anderer Abschnitt von `DEPLOY-WORKFLOW.md` wurde inhaltlich
      verändert (per `git diff` nachvollziehbar).
- [x] `DEPLOYMENT.md` wurde **nicht** verändert (bleibt für den separaten
      Wizard-Pfad wie bisher gültig, siehe `proposal.md`, "Out of Scope").

---

## T03 (könnte, nicht blockierend): Grep-basierter CI-Regressionsschutz

- **Agent:** dev-php
- **Dateien:** `.github/workflows/ci.yml`
- **Abhängigkeiten:** T01 (prüft exakt die dort eingeführten Textmuster)
- **Priorität:** könnte

### Beschreibung

Siehe `design.md`, Abschnitt 6, für die vollständige Begründung (inkl.
warum kein Pest-Test möglich ist) und den vorformulierten Job-Entwurf.

1. Neuen Job `deploy-workflow-lint` in `.github/workflows/ci.yml` ergänzen
   (parallel zu `backend-tests`/`frontend-tests`, läuft direkt auf dem
   Runner, kein Docker).
2. Der Job prüft per `grep`, dass `.github/workflows/deploy.yml` sowohl
   den `backend/public/storage`-Exclude als auch einen
   `artisan storage:link`-Aufruf enthält, und schlägt mit einer klaren
   `::error::`-Annotation fehl, falls eines von beidem fehlt.
3. Kein neuer externer Tool-Dependency (kein `actionlint`, kein
   `yamllint`-Package-Install) — reines `grep` auf dem bereits
   vorhandenen Runner-Image.

### Akzeptanzkriterien

- [x] Der neue Job läuft bei jedem Push/PR (wie die bestehenden Jobs in
      `ci.yml`).
- [x] Der Job schlägt fehl, wenn `backend/public/storage`-Exclude oder
      `storage:link`-Schritt aus `deploy.yml` entfernt werden (manuell
      verifizierbar durch testweises, lokales Entfernen einer der beiden
      Zeilen und Beobachten des Job-Ergebnisses vor dem finalen Commit).
- [x] Der Job läuft grün gegen den in T01 tatsächlich implementierten
      Stand von `deploy.yml`.
- [x] Falls dieser Task vom User im User-Gate 1 als nicht gewünscht
      zurückgewiesen wird: `tasks.md` entsprechend als „verworfen"
      markieren, `design.md` Abschnitt 6 bleibt als Dokumentation der
      Entscheidung erhalten.
