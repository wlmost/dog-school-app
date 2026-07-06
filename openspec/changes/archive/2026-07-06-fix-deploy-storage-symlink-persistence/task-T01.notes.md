# Notes: T01 — `.github/workflows/deploy.yml`

**Status:** implementiert

## Was implementiert wurde

1. **rsync-Exclude ergänzt** (`.github/workflows/deploy.yml:176`): Im
   Schritt „Deploy files via rsync" wurde
   `--exclude='backend/public/storage'` in die bestehende Exclude-Liste
   eingefügt, gruppiert direkt nach `--exclude='backend/storage/app/'`
   (kosmetisch, rsync-Reihenfolge ist unerheblich). Schützt den Symlink
   `public/storage` → `storage/app/public` vor `rsync --delete`.

2. **Neuer Schritt „Ensure public storage symlink exists"** eingefügt
   zwischen „Deploy files via rsync" und „Run database migrations"
   (`.github/workflows/deploy.yml:186-205`). Führt per SSH aus:
   `cd '${{ secrets.DEPLOY_PATH }}/backend' && php artisan storage:link`.
   **Kein** Best-Effort-Fallback (`|| echo "::warning::..."`), analog zum
   bestehenden Migrations-Schritt — Begründung wie in `design.md`,
   Abschnitt 3.3, vorgegeben: `storage:link` ist idempotent (Exit-Code 0
   in beiden Fällen, ob Symlink schon existiert oder neu angelegt wird),
   verifiziert gegen
   `vendor/laravel/framework/src/Illuminate/Foundation/Console/StorageLinkCommand.php`
   (siehe `design.md` Abschnitt 4). Der Kommentarblock wurde wortgleich
   aus `design.md` Abschnitt 3.2 übernommen.

3. **Kommentar-Nummerierung der nachfolgenden Schritte** um jeweils eins
   erhöht:
   - `# 8. Run database migrations` → `# 9. Run database migrations`
   - `# 9. Rebuild application caches` → `# 10. Rebuild application caches`
   - `# 10. Disable maintenance mode` → `# 11. Disable maintenance mode`
   - `# 11. Cleanup` → `# 12. Cleanup`
   - `# 12. Write deployment summary to the Actions run page` →
     `# 13. Write deployment summary to the Actions run page`

4. Sonst wurde nichts an der Datei verändert. `git diff -- .github/workflows/deploy.yml`
   zeigt ausschließlich die oben beschriebenen Blöcke — keine Änderung an
   Secrets-Referenzen, Trigger-Konfiguration oder anderen Schritten.

## Verifikation durchgeführt

- **YAML-Syntax-Check:** `python3` hatte kein `pyyaml`-Modul installiert
  und `pip install pyyaml` war im Sandbox-Kontext nicht möglich/sinnvoll
  nachzuinstallieren als dauerhafte Abhängigkeit — stattdessen wurde der
  in den Akzeptanzkriterien alternativ vorgeschlagene Ruby-Parser
  verwendet (Ruby war bereits im System vorhanden, `/usr/bin/ruby`):
  ```
  ruby -ryaml -e "YAML.load_file('.github/workflows/deploy.yml'); puts 'YAML OK'"
  ```
  Ergebnis: `YAML OK` (kein Parse-Fehler).
- `git diff -- .github/workflows/deploy.yml` manuell gesichtet: enthält
  ausschließlich die drei in den Akzeptanzkriterien beschriebenen
  Änderungsblöcke (Exclude-Zeile, neuer Schritt, Nummerierungs-Bumps).
- `git status --short`: keine weiteren Dateien durch diese Task berührt.

## Nicht verifizierbar durch composer qa / Pest

Diese Änderung liegt vollständig in `.github/workflows/deploy.yml`,
also außerhalb von `backend/`. Weder der lokale Docker-Service
(`docker-compose.yml`, mountet nur `./backend:/var/www/html`) noch der
CI-Job `backend-tests` (`.github/workflows/ci.yml`, mountet ebenfalls
nur `backend/`) haben Zugriff auf diese Datei. `composer qa`/Pest ist
für diesen Change daher strukturell **nicht anwendbar** — analog zur in
`design.md` Abschnitt 6 dokumentierten Begründung für den (optionalen)
T03. Das ist erwartet, kein Fehler.

## Offener Punkt nach Merge

Ein echter Shared-Hosting-Smoke-Test bleibt nach dem Merge nötig: Deploy
auslösen (workflow_dispatch oder regulärer main-Push) und auf dem
Zielserver prüfen, dass

1. `backend/public/storage` nach dem Deploy weiterhin als Symlink auf
   `storage/app/public` existiert (nicht durch `rsync --delete` entfernt
   wurde), und
2. ein zuvor hochgeladenes Bild über die öffentliche URL weiterhin
   erreichbar ist.

Dieser Smoke-Test kann durch dieses Backend-Task (T01) selbst nicht
automatisiert abgedeckt werden. Sollte T03 (optionaler CI-Regressionsschutz)
umgesetzt werden, deckt dieser nur die *Präsenz* der Exclude-Zeile und des
`storage:link`-Aufrufs im Workflow-Text ab (Grep-Check), nicht das
tatsächliche Verhalten auf dem Zielserver selbst.

## Betroffene Dateien

- `/Users/wolfgang/Documents/05-Entwicklung/01-Projekte/dog-school-app/.github/workflows/deploy.yml`
