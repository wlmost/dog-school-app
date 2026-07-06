# Design: fix-deploy-storage-symlink-persistence

**Change-ID:** fix-deploy-storage-symlink-persistence
**Datum:** 2026-07-06

---

## 1. Betroffene Dateien (Übersicht)

| Datei | Änderung | Task | DB-Bezug? |
|---|---|---|---|
| `.github/workflows/deploy.yml` | rsync-Exclude für `backend/public/storage` + neuer `storage:link`-Schritt | T01 | Nein |
| `DEPLOY-WORKFLOW.md` | Schritt-Tabelle + „Geschützte Verzeichnisse"-Liste aktualisieren | T02 | Nein |
| `.github/workflows/ci.yml` (neuer, optionaler Job) | Grep-basierte Regressionsprüfung gegen `deploy.yml` | T03 (könnte) | Nein |

**Kein DB-Bezug in diesem Change.** Es wird weder eine Migration noch raw
SQL noch Eloquent-Code berührt — reine CI/CD-Pipeline-Konfiguration
(YAML) und Dokumentation. Abschnitt 4.2 der `CLAUDE.md` (SQL/DB-
Portabilität) ist nicht anwendbar. Abschnitt 4.1 (PHP-Sprachfeatures) ist
ebenfalls nicht anwendbar — es wird kein PHP-Anwendungscode geändert, nur
ein bereits vorhandener Artisan-Befehl (`storage:link`) per SSH aufgerufen.

---

## 2. Agenten-Zuständigkeit — explizite Entscheidung (für Skeptiker/Review)

`CLAUDE.md` Abschnitt 2/7.2 listet `dev-php` für `app/`, `routes/`,
`database/`, `config/`, `tests/Feature/`, `tests/Unit/`, Blade-Templates,
und `dev-javascript` für Vue-SFCs/JS. Weder `.github/workflows/*.yml` noch
Top-Level-Markdown-Dokumentation (`DEPLOY-WORKFLOW.md`) ist explizit
gelistet.

**Entscheidung: Beide Tasks (T01, T02) sowie der optionale T03 werden
`dev-php` zugewiesen.** Begründung:

1. **Domänenwissen entscheidet, nicht Dateiendung:** Der Kern von T01 ist
   die korrekte Nutzung von Laravels `storage:link`-Befehl und dessen
   Exit-Code-Semantik (siehe Abschnitt 4) — das ist Laravel-/PHP-
   Anwendungswissen, kein generisches YAML- oder DevOps-Thema. `dev-javascript`
   passt hier fachlich nicht.
2. **Etablierter Präzedenzfall im selben Projekt:** Im unmittelbar zuvor
   archivierten Change `fix-dog-image-upload-shared-hosting`
   (`openspec/changes/archive/2026-07-06-fix-dog-image-upload-shared-hosting/tasks.md`,
   T02) wurden Änderungen an `build-deployment.sh`, `build-deployment-docker.sh`
   (Bash-Skripte) und `DEPLOYMENT.md` (Markdown-Dokumentation) bereits
   `dev-php` zugewiesen — beides ebenfalls außerhalb der wörtlich in
   `CLAUDE.md` gelisteten Pfad-Muster. Die Projektpraxis behandelt
   deployment-nahe Konfiguration/Skripte/Dokumentation konsistent als
   `dev-php`-Zuständigkeit, mangels eines dedizierten DevOps-/YAML-Agenten.
3. **Kein Match für `dev-javascript`:** Es gibt keinerlei Vue-/JS-Bezug in
   diesem Change.
4. Es gibt aktuell **keinen** `dev-devops`/`dev-yaml`-Agenten im Projekt
   (`CLAUDE.md` Abschnitt 2: nur `dev-php`, `dev-javascript` verfügbar;
   `dev-go`, `dev-typescript` sind explizit ausgeschlossen).

**Für den Skeptiker zu prüfen:** Ob diese Zuordnung akzeptabel ist oder ob
der User stattdessen einen dedizierten Review-Schritt für YAML-Syntax
wünscht (z. B. manuelles `yamllint`/GitHub-Actions-Syntax-Check vor dem
Push, siehe Risikobewertung unten).

---

## 3. T01: `.github/workflows/deploy.yml` — Details

### 3.1 rsync-Exclude ergänzen

Aktueller Zustand (`.github/workflows/deploy.yml:171-182`):

```yaml
      - name: Deploy files via rsync
        run: |
          rsync -az --delete \
            --exclude='backend/.env' \
            --exclude='backend/storage/app/' \
            --exclude='backend/storage/logs/' \
            --exclude='backend/storage/framework/sessions/' \
            --exclude='backend/storage/framework/cache/' \
            --exclude='backend/storage/framework/views/' \
            -e "ssh -i ~/.ssh/deploy_key -p $DEPLOY_PORT" \
            deploy-package/ \
            "${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }}:${{ secrets.DEPLOY_PATH }}/"
```

Neue Zeile `--exclude='backend/public/storage'` ergänzen (Position:
zusammen mit den anderen `storage`-bezogenen Excludes, z. B. direkt nach
`--exclude='backend/storage/app/'`, damit alle Storage-bezogenen Excludes
gruppiert bleiben — rein kosmetisch, rsync-Reihenfolge ist unerheblich).

**Warum reines Excludieren ausreicht (kein `-K`/`--keep-dirlinks` nötig):**
`rsync`s `--exclude` verhindert, dass ein Pfad überhaupt als Lösch-
Kandidat betrachtet wird — unabhängig davon, ob er in der Quelle
existiert. Da `backend/public/storage` nie in `deploy-package/`
(Quelle) existiert (gitignored, siehe `backend/.gitignore:5`, und im
„Prepare deployment package"-Schritt, `.github/workflows/deploy.yml:87-129`
gelesen, wird dort ebenfalls kein `public/storage` angelegt), gibt es kein
Konfliktpotential mit dem Kopieren selbst — der Exclude wirkt hier
ausschließlich als Schutz vor `--delete`.

### 3.2 Neuer Schritt: Symlink sicherstellen

Einfügen nach dem rsync-Schritt (aktuell Zeilen 171-182), vor dem
Migrations-Schritt (aktuell Zeilen 184-192):

```yaml
      # -----------------------------------------------------------------------
      # 8. Ensure public storage symlink exists
      #    backend/public/storage is gitignored (Laravel standard, see
      #    backend/.gitignore:5) and therefore never present in the rsync
      #    source (deploy-package/). Excluding it above protects an
      #    already-existing symlink on the server from rsync's --delete;
      #    this step (re-)creates it if missing (first deploy, or if it was
      #    ever removed by an older workflow run before this fix). Laravel's
      #    storage:link command exits 0 even if the link already exists (it
      #    only prints an informational message via the components->error()
      #    output helper, it does not fail the command – see
      #    vendor/laravel/framework/src/Illuminate/Foundation/Console/StorageLinkCommand.php),
      #    so this step is safe to run on every deploy without --force and
      #    without a best-effort fallback.
      # -----------------------------------------------------------------------
      - name: Ensure public storage symlink exists
        run: |
          ssh -i ~/.ssh/deploy_key \
            -p "$DEPLOY_PORT" \
            "${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }}" \
            "cd '${{ secrets.DEPLOY_PATH }}/backend' && php artisan storage:link"
```

Nachfolgende Kommentar-Nummerierung (`# 8. Run database migrations`, `# 9.
Rebuild application caches`, `# 10. Disable maintenance mode`, `# 11.
Cleanup`, `# 12. Deployment summary`) muss um jeweils eins erhöht werden
(→ 9, 10, 11, 12, 13), damit die fortlaufende Nummerierung der
Kommentarblöcke konsistent bleibt. Dies ist rein kosmetisch (Kommentare),
hat aber Auswirkung auf die Lesbarkeit/Wartbarkeit und wird daher als
Akzeptanzkriterium in `tasks.md` aufgenommen.

### 3.3 Warum **kein** Best-Effort-Fallback (`|| echo "::warning::..."`)

Im Gegensatz zu den bestehenden Schritten „Enable maintenance mode"
(Zeile 156, `|| echo "::warning::..."`) und „Disable maintenance mode"
(Zeile 221, ebenfalls mit Fallback) wird der neue `storage:link`-Schritt
**ohne** Fallback ausgeführt, aus denselben Gründen wie der bestehende
„Run database migrations"-Schritt (Zeilen 185-192, ebenfalls ohne
Fallback):

- Die beiden bestehenden Best-Effort-Schritte existieren, weil beim
  **allerersten** Deploy `php artisan` auf dem Server noch gar nicht
  existiert (chicken-egg-Problem vor dem ersten rsync) — das ist in den
  Kommentaren explizit dokumentiert („may fail on first deploy").
- Der neue Schritt läuft **nach** rsync, wenn `backend/` inklusive
  `vendor/`, `artisan` und (laut `DEPLOY-WORKFLOW.md:61-70`, bereits vor
  dem ersten Deploy manuell angelegter) `.env` bereits vorhanden sind —
  exakt dieselbe Voraussetzung wie beim Migrations-Schritt, der ebenfalls
  keinen Fallback hat.
- Verifiziert (Abschnitt 4): Der einzige Fall, in dem `storage:link`
  einen Exit-Code ≠ 0 liefern könnte, wäre ein grundlegender
  Bootstrapping-Fehler der Laravel-Anwendung selbst (z. B. fehlender
  `APP_KEY`, kaputte `.env`) — ein Fehler, der ohnehin sofort sichtbar
  gemacht werden **soll**, statt still verschluckt zu werden, genau wie
  bei den Migrationen.

---

## 4. Verifikation: `storage:link` ist idempotent (Exit-Code immer 0)

Datei gelesen:
`backend/vendor/laravel/framework/src/Illuminate/Foundation/Console/StorageLinkCommand.php`
(Laravel-Version `v11.51.0`, siehe `backend/composer.lock:1507`).

```php
public function handle()
{
    $relative = $this->option('relative');

    foreach ($this->links() as $link => $target) {
        if (file_exists($link) && ! $this->isRemovableSymlink($link, $this->option('force'))) {
            $this->components->error("The [$link] link already exists.");
            continue;
        }
        // ... create the link ...
    }
}
```

- `handle()` hat **keinen expliziten `return`-Wert** (implizit `null`).
  Laravels `Illuminate\Console\Command::execute()` interpretiert einen
  `null`-Rückgabewert von `handle()` als Erfolg (Exit-Code `0`).
- `$this->components->error(...)` ist ein reiner Konsolen-Ausgabe-Helfer
  (formatiertes STDOUT/STDERR-Styling für eine "error"-artige Meldung), er
  wirft **keine** Exception und beeinflusst den Rückgabewert von `handle()`
  **nicht**.
- Konsequenz: Ob der Symlink bereits existiert oder neu angelegt wird —
  in **beiden** Fällen terminiert `php artisan storage:link` mit
  Exit-Code `0`. Der neue Deploy-Schritt kann daher **ohne** `--force`
  und **ohne** zusätzliches Error-Handling bei jedem Deploy erneut
  ausgeführt werden, ohne den Job fehlschlagen zu lassen.

Dies beantwortet direkt die im Triage-Dokument aufgeworfene und vom
Skeptiker zu prüfende Frage („ob ein erneuter `storage:link`-Aufruf bei
bereits bestehendem Symlink den Deploy-Job nicht fehlschlagen lässt")
bereits mit einer Code-Verifikation, nicht nur einer Annahme. Der
Skeptiker sollte diesen Befund gegenprüfen (Datei + Zeilen oben sind
exakt zitierfähig).

---

## 5. T02: `DEPLOY-WORKFLOW.md` — Details

Zwei Stellen sind zu aktualisieren:

**Schritt-Tabelle (Abschnitt 5, `DEPLOY-WORKFLOW.md:171-186`):** Neue
Zeile zwischen der `rsync`-Zeile und der `Migrationen`-Zeile einfügen,
z. B.:

```markdown
| **Storage-Symlink sicherstellen** | `php artisan storage:link` – legt `backend/public/storage` an, falls er fehlt (idempotent, kein Fehler bei bereits vorhandenem Symlink) |
```

**„Geschützte Verzeichnisse"-Liste (`DEPLOY-WORKFLOW.md:188-197`):**
`backend/public/storage` als zusätzlichen Eintrag ergänzen, mit einem
kurzen Kommentar, dass dies (im Gegensatz zu den anderen Einträgen) kein
Nutzerdaten-Verzeichnis ist, sondern ein Symlink, der nur vor versehentlichem
Löschen durch `--delete` geschützt wird:

```markdown
backend/public/storage        ← Symlink zu storage/app/public (wird nach
                                 rsync automatisch sichergestellt, siehe
                                 Schritt-Tabelle oben)
```

**Warum `DEPLOY-WORKFLOW.md` statt `DEPLOYMENT.md` (Korrektur gegenüber
Triage-Vorschlag):** Die Triage schlug ursprünglich `DEPLOYMENT.md:600-608`
als Dokumentationsziel vor. Bei eigener Prüfung (`DEPLOYMENT.md`
vollständig referenziert um Zeile 600-608 sowie Abschnitt "Automatisiertes
Deployment", Zeilen 940-977) zeigt sich: Dieser Abschnitt beschreibt einen
**anderen, generischen VPS-artigen Deploy-Mechanismus** (eigenes
`deploy.sh`-Skript mit `git pull`, Supervisor, `systemctl restart
php8.4-fpm`) — nicht den hier relevanten
`.github/workflows/deploy.yml`-Workflow. `DEPLOY-WORKFLOW.md` (282 Zeilen,
vollständig gelesen) ist dagegen explizit "Dieses Dokument beschreibt die
Einrichtung und Nutzung des automatisierten Deployment-Workflows
(`.github/workflows/deploy.yml`)" (Zeile 3-5) und enthält bereits eine
1:1-Schritt-Tabelle sowie eine Liste der "Geschützten Verzeichnisse", die
exakt dem aktuellen (fehlerhaften) Stand von `deploy.yml` entsprechen.
`DEPLOY-WORKFLOW.md` ist damit die präzisere und korrekte
Dokumentationsquelle für diesen Change. `DEPLOYMENT.md` bleibt unverändert,
da sein eigener (Wizard-/VPS-)Pfad von diesem Bug nicht betroffen ist.

---

## 6. T03 (könnte, optional): CI-Regressionsschutz

### Warum kein Pest/PHPUnit-Test

`backend/tests/Unit/Deployment/HtaccessTemplatesTest.php:7-36` dokumentiert
bereits ein identisches strukturelles Problem für einen vorherigen Change:
Sowohl der lokale Docker-Service (`docker-compose.yml`, mountet nur
`./backend:/var/www/html`) als auch der CI-Job `backend-tests`
(`.github/workflows/ci.yml:74`, `-v
"${{ github.workspace }}/backend:/var/www/html"`) stellen dem Pest-
Testlauf **ausschließlich** den Inhalt von `backend/` zur Verfügung.
`.github/workflows/deploy.yml` liegt außerhalb von `backend/` und ist für
einen Pest-Test daher strukturell unerreichbar — ein Pest-Test würde nur
bei Ausführung mit vollem Repo-Zugriff (Host-PHP außerhalb des Containers)
grün laufen, nicht aber unter dem in `CLAUDE.md` Abschnitt 7.1
vorgeschriebenen `composer qa`/Docker-Pre-Flight. Einen neuen
Volume-Mount oder eine neue Test-Infrastruktur nur für diesen einen Test
einzuführen wäre unverhältnismäßig (YAGNI) für einen optionalen
Regressionsschutz.

### Vorschlag: einfacher Grep-Check als eigener CI-Job (kein Docker nötig)

Ein neuer, leichtgewichtiger Job in `.github/workflows/ci.yml` (parallel zu
`backend-tests`/`frontend-tests`, läuft direkt auf dem GitHub-Actions-
Runner, **nicht** im Docker-Container, hat daher vollen Repo-Zugriff):

```yaml
  deploy-workflow-lint:
    name: Deploy workflow – storage symlink safeguard
    runs-on: ubuntu-latest
    steps:
      - name: Checkout current branch
        uses: actions/checkout@v4

      - name: Verify storage symlink is protected and re-created
        run: |
          set -e
          grep -q "exclude='backend/public/storage'" .github/workflows/deploy.yml \
            || { echo "::error::backend/public/storage exclude missing from rsync step"; exit 1; }
          grep -q "artisan storage:link" .github/workflows/deploy.yml \
            || { echo "::error::storage:link step missing from deploy.yml"; exit 1; }
```

Dies ist bewusst **kein** vollwertiger YAML-Linter (`actionlint` o. Ä.) —
das wäre ein größerer, hier nicht angeforderter Infrastruktur-Umbau
(YAGNI). Der Grep-Check deckt ausschließlich die konkrete Regression ab,
die dieser Change behebt (ein zukünftiger, versehentlicher Revert der
beiden Änderungen aus T01), und ist bewusst minimal gehalten (KISS).

**Nicht blockierend:** Dieser Task kann entfallen, ohne das Kernziel des
Changes zu gefährden — er ist reine Zusatzabsicherung. Entscheidung über
Aufnahme liegt beim User im User-Gate 1.

---

## 7. Risikobewertung

| Risiko | Schwere | Maßnahme |
|---|---|---|
| YAML-Syntaxfehler in `deploy.yml` durch die Änderung (z. B. falsche Einrückung) | Mittel — würde den gesamten Deploy-Job zum Scheitern bringen | Reviewer prüft den Diff explizit gegen YAML-Syntax; empfohlen: `act`/lokaler Dry-Run oder zumindest `yamllint deploy.yml`, falls verfügbar, vor dem Merge (siehe `tasks.md`, T01-Akzeptanzkriterien) |
| `storage:link`-Schritt schlägt fehl, weil `.env`/`APP_KEY` auf dem Zielserver nicht korrekt gesetzt sind | Niedrig | Wäre ein bereits vorher existierendes Problem (auch der Migrations-Schritt direkt davor würde dann schon fehlschlagen) — kein neues Risiko durch diesen Change |
| Neuer rsync-Exclude verhindert versehentlich das Kopieren von etwas Erwünschtem | Sehr niedrig | `backend/public/storage` existiert nie in der Quelle (verifiziert, Abschnitt 3.1) — der Exclude kann nichts Erwünschtes betreffen |
| Dokumentation (T02) gerät durch künftige Pipeline-Änderungen erneut aus dem Tritt | Niedrig, strukturell | Kein Automatismus dagegen vorgesehen (YAGNI) — bleibt manuelle Sorgfaltspflicht wie bisher im Projekt üblich |
| Kommentarnummerierung (Abschnitt 3.2) wird beim Einfügen vergessen | Niedrig, kosmetisch | Explizites Akzeptanzkriterium in `tasks.md`, T01 |

### PHP-8.2-Kompatibilität

Nicht anwendbar — es wird keine PHP-Anwendungsdatei geändert, nur ein
bereits vorhandener Artisan-Befehl in der CI/CD-Pipeline aufgerufen.

### DB-Portabilität

Nicht anwendbar — kein DB-Zugriff, keine Migration, kein raw SQL in
diesem Change.

---

## 8. Nicht-Scope-Abgrenzung

| Thema | Entscheidung |
|---|---|
| Sofortige manuelle Behebung auf dem Kundenserver | Operative Maßnahme außerhalb des Changes (siehe `proposal.md`, "Out of Scope") |
| Wizard-/VPS-Installationspfad (`DEPLOYMENT.md`, `build-deployment.sh`, `install.php`) | Unverändert, eigener Mechanismus, führt `storage:link` bereits selbst aus |
| Vollwertiger YAML-Linter (`actionlint`) für die gesamte Pipeline | Größerer, nicht angeforderter Umbau — YAGNI, siehe Abschnitt 6 |
| Änderungen an `DogController`/`TrainingAttachmentController` | Beide bereits strukturell korrekt, kein Fix nötig |
