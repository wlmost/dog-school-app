# Notes: T03 — Grep-basierter CI-Regressionsschutz

## Was wurde gemacht

In `.github/workflows/ci.yml` wurde ein neuer, eigenständiger Job
`deploy-workflow-lint` ergänzt (direkt nach `frontend-tests`, parallel zu
`backend-tests` und `frontend-tests`). Der Job läuft direkt auf
`ubuntu-latest`, ohne Docker-Container, mit vollem Repo-Checkout, und
prüft per `grep`, dass `.github/workflows/deploy.yml`

1. den rsync-Exclude `exclude='backend/public/storage'` enthält, und
2. einen `artisan storage:link`-Aufruf enthält.

Fehlt eines von beidem, gibt der Schritt eine `::error::`-Annotation aus
und beendet sich mit Exit-Code 1 (Job schlägt fehl). Der Job-Entwurf
entspricht exakt dem in `design.md`, Abschnitt 6, vorformulierten
Vorschlag; die grep-Muster wurden gegen den tatsächlichen, durch T01
bereits implementierten Wortlaut in `.github/workflows/deploy.yml`
verifiziert (Zeile 176: `--exclude='backend/public/storage'`, Zeile 205:
`"cd '${{ secrets.DEPLOY_PATH }}/backend' && php artisan storage:link"`).

Es wurde **keine** neue externe Tool-Abhängigkeit eingeführt (kein
`actionlint`, kein `yamllint`-Package) — ausschließlich `grep`, das auf
dem GitHub-Actions-`ubuntu-latest`-Image bereits vorhanden ist.

Kein anderer Inhalt von `ci.yml` wurde verändert (siehe `git diff`
unten).

## Warum kein Pest-Test möglich ist (Kurzfassung, Details in `design.md` Abschnitt 6)

`backend/tests/Unit/Deployment/HtaccessTemplatesTest.php:7-36`
dokumentiert bereits dasselbe strukturelle Problem: Sowohl der lokale
Docker-Service als auch der `backend-tests`-Job in `ci.yml`
(`-v "${{ github.workspace }}/backend:/var/www/html"`) mounten
ausschließlich `backend/` in den Test-Container. `.github/workflows/
deploy.yml` liegt außerhalb von `backend/` und ist daher für einen
Pest-Test unter dem in `CLAUDE.md` Abschnitt 7.1 vorgeschriebenen
`composer qa`/Docker-Pre-Flight strukturell unerreichbar. Ein neuer
Volume-Mount nur für diesen einen Regressionsschutz wäre unverhältnismäßig
(YAGNI). Der Grep-Check als eigener, Docker-loser CI-Job mit vollem
Checkout-Zugriff ist die im `design.md` gewählte, pragmatische
Alternative.

## Durchgeführte lokale Verifikation

### 1. YAML-Syntax von `ci.yml`

```bash
ruby -ryaml -e "YAML.load_file('.github/workflows/ci.yml'); puts 'ci.yml OK'"
# → ci.yml OK
python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/ci.yml')); print('python OK')"
# → python OK
```

Beide Parser waren in der Entwicklungsumgebung verfügbar und liefen
fehlerfrei durch.

### 2. Grep-Check gegen die aktuelle `deploy.yml` (Positiv-Fall)

```bash
grep -q "exclude='backend/public/storage'" .github/workflows/deploy.yml \
  && echo "PASS: exclude found"
grep -q "artisan storage:link" .github/workflows/deploy.yml \
  && echo "PASS: storage:link found"
```

Ergebnis: beide `PASS`. Der Job läuft grün gegen den durch T01
tatsächlich implementierten Stand.

### 3. Simulation des Fehlschlag-Falls (OHNE Veränderung der echten `deploy.yml`)

Es wurden zwei temporäre Kopien im Scratchpad-Verzeichnis erzeugt
(`deploy-no-exclude.yml` bzw. `deploy-no-link.yml`), jeweils mit `grep -v`
um genau eine der beiden Ziel-Zeilen reduziert, und dieselbe Grep-Logik
wie im CI-Job dagegen ausgeführt:

- Kopie ohne `exclude='backend/public/storage'`:
  - `grep -q "exclude='backend/public/storage'"` → **kein Treffer** →
    Simulation gab `::error::backend/public/storage exclude missing from
    rsync step` aus (Job würde mit Exit-Code 1 fehlschlagen).
  - `grep -q "artisan storage:link"` → weiterhin Treffer (unberührt).
- Kopie ohne `artisan storage:link`-Zeile:
  - `grep -q "exclude='backend/public/storage'"` → weiterhin Treffer
    (unberührt).
  - `grep -q "artisan storage:link"` → **kein Treffer** → Simulation gab
    `::error::storage:link step missing from deploy.yml` aus (Job würde
    fehlschlagen).

Beide Fehlschlag-Fälle wurden damit bestätigt, ohne die echte
`.github/workflows/deploy.yml` anzufassen. Die temporären Kopien liegen
ausschließlich im Session-Scratchpad, nicht im Repo.

### 4. `git diff -- .github/workflows/ci.yml`

Der Diff zeigt ausschließlich den neu hinzugefügten Job
`deploy-workflow-lint` (17 neue Zeilen), keine Änderung an bestehendem
Inhalt (`backend-tests`, `frontend-tests` unverändert).

## Nicht durch `composer qa` verifizierbar

Wie schon bei T01: Dieser Change betrifft ausschließlich
`.github/workflows/ci.yml` (YAML, außerhalb von `backend/`). `composer
qa`/Pest/PHPStan/PHP-CS-Fixer/compat-check sind hier nicht anwendbar —
es wurde kein PHP-Code unter `app/`, `routes/`, `database/`, `config/`
geändert. Die Verifikation erfolgte ausschließlich über YAML-Parser-
Checks und manuelle Grep-Simulation (siehe oben).

## Akzeptanzkriterien (siehe `tasks.md`, T03)

- [x] Der neue Job läuft bei jedem Push/PR (identische `on:`-Trigger wie
      die bestehenden Jobs, da auf oberster `on:`-Ebene der Datei
      definiert, keine job-spezifische Einschränkung ergänzt).
- [x] Der Job schlägt fehl, wenn eine der beiden Zeilen aus `deploy.yml`
      entfernt wird — verifiziert per Simulation gegen temporäre Kopien
      (siehe Abschnitt 3 oben).
- [x] Der Job läuft grün gegen den tatsächlich implementierten Stand von
      `deploy.yml` (T01) — verifiziert (Abschnitt 2 oben).
- [x] Task wurde vom User nicht zurückgewiesen, daher keine
      „verworfen"-Markierung nötig.

## Verbleibende Restrisiken / offene Punkte

- Der Job wurde nicht tatsächlich in GitHub Actions ausgeführt (kein
  Push in dieser Session) — die Verifikation basiert auf lokaler
  Grep-Simulation mit identischer Logik. Ein echter CI-Lauf nach dem
  Push wird dies endgültig bestätigen.
- Wie in `design.md` Abschnitt 6 festgehalten: Dies ist bewusst kein
  vollwertiger YAML-Linter, sondern deckt ausschließlich die konkrete,
  durch diesen Change behobene Regression ab.
