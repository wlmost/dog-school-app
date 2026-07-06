# Notes: T02 — `DEPLOY-WORKFLOW.md` an den korrigierten Ablauf anpassen

**Change:** fix-deploy-storage-symlink-persistence
**Task:** T02
**Agent:** dev-php
**Datei:** `DEPLOY-WORKFLOW.md`

## Zusammenfassung

Zwei reine Dokumentations-Ergänzungen in `DEPLOY-WORKFLOW.md`, Abschnitt 5
(„Was der Workflow im Detail macht"), passend zum in T01 bereits
implementierten Schritt in `.github/workflows/deploy.yml`.

1. **Schritt-Tabelle:** Neue Zeile zwischen `rsync` und `Migrationen`
   eingefügt:

   ```
   | **Ensure public storage symlink exists** | `php artisan storage:link` – legt `backend/public/storage` an, falls er fehlt (idempotent, kein Fehler bei bereits vorhandenem Symlink) |
   ```

   Der Name „Ensure public storage symlink exists" wurde wörtlich aus dem
   `name:`-Feld des in T01 eingefügten Schritts in
   `.github/workflows/deploy.yml:200` übernommen (verifiziert per `Read`
   vor dem Edit).

2. **„Geschützte Verzeichnisse"-Liste:** `backend/public/storage` als
   neuen Eintrag ergänzt, mit Kommentar, der explizit klarstellt, dass es
   sich — anders als die übrigen Einträge (`.env`, `storage/app/`,
   `storage/logs/` etc., die alles Nutzerdaten/Logs sind) — um einen
   Symlink handelt, der nach dem rsync-Schritt automatisch
   sichergestellt wird (Verweis auf die Schritt-Tabelle).

## Abgrenzung eingehalten

- Kein anderer Abschnitt von `DEPLOY-WORKFLOW.md` wurde verändert
  (verifiziert per `git diff -- DEPLOY-WORKFLOW.md` — nur die zwei
  beschriebenen Hunks).
- `DEPLOYMENT.md` wurde nicht angefasst (`git diff --stat -- DEPLOYMENT.md`
  liefert keine Ausgabe) — bleibt für den separaten Wizard-/VPS-Pfad
  unverändert gültig, siehe `design.md` Abschnitt 5 für die Begründung,
  warum `DEPLOY-WORKFLOW.md` statt `DEPLOYMENT.md` das korrekte Ziel ist.

## Pre-Flight-Checks

Dieser Task ändert ausschließlich eine Markdown-Datei im Projekt-Root
(kein PHP-Anwendungscode, keine Migration, kein YAML). Analog zur
Begründung in `task-T01.notes.md`/`design.md` Abschnitt 6 ist dieser
Change durch `composer qa`/Pest nicht sinnvoll automatisiert prüfbar —
die Verifikation erfolgte durch:

- `git diff -- DEPLOY-WORKFLOW.md` (nur die zwei beschriebenen Stellen
  geändert)
- `git diff --stat -- DEPLOYMENT.md` (keine Ausgabe → unverändert)
- Manueller Abgleich des neuen Zeilennamens gegen das `name:`-Feld in
  `.github/workflows/deploy.yml` (exakte Übereinstimmung:
  „Ensure public storage symlink exists")

`composer qa`/`npm run test`/`npm run build` sind für diesen Task nicht
relevant (keine PHP-/JS-Dateien betroffen) und wurden daher nicht erneut
ausgeführt.

## Offene Punkte / Abhängigkeiten

- T03 (optional, CI-Regressionsschutz) hängt weiterhin nur von T01 ab,
  nicht von T02.
- Keine.
