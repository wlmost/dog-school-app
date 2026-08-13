# T08: Cross-Cutting QA-Durchlauf — Notes

Durchgeführt vom Orchestrator nach Abschluss von T01-T07.

## Backend

- `docker compose exec php composer lint` — grün (315 Dateien).
- `docker compose exec php composer stan` — grün, 0 Fehler (207 Dateien).
- `docker compose exec php composer compat-check` — grün, kein PHP 8.3/8.4-Verstoß.
- `docker compose exec php vendor/bin/pest --no-coverage` gegen SQLite (Standard) — **842 passed, 1 skipped** (Concurrency-Test wird auf SQLite bewusst übersprungen, siehe `task-T02.notes.md`: SQLite hat keine echten MVCC-Zeilensperren).

## PostgreSQL (dedizierte `dog_school_test`-Datenbank)

- `migrate:fresh` läuft fehlerfrei durch (inkl. neuer `payments.notes`-Migration).
- `vendor/bin/pest --no-coverage` mit `DB_CONNECTION=pgsql DB_DATABASE=dog_school_test` (env-Variablen korrekt an den `pest`-Aufruf selbst gebunden, nicht nur an den vorangehenden `migrate:fresh`-Befehl in derselben Kette — erster Versuch lief durch einen Shell-Scoping-Fehler versehentlich wieder gegen SQLite und übersprang den Concurrency-Test, korrigiert via `docker compose exec -e ... php ...`) — **843 passed** (inkl. `InvoicePaymentRecorderConcurrencyTest`, der auf SQLite übersprungen wird, hier aber real mit zwei `pcntl_fork()`-Prozessen läuft und grün ist).

## MySQL 8.4 (Ad-hoc-Container im bestehenden Docker-Netzwerk)

- Kein `docker-compose.mysql.yml` im Repo vorhanden (bekannte, seit Change 1 dokumentierte Lücke, eigener Folge-Change wert). Stattdessen Ad-hoc-`mysql:8.4`-Container gestartet, migriert, getestet, danach entfernt — analog zum in Change 1/2 etablierten Verfahren.
- `migrate:fresh` läuft fehlerfrei durch.
- `vendor/bin/pest --no-coverage` — **843 passed** (inkl. Concurrency-Test, real gegen MySQLs Zeilensperren).

## Frontend

- `npx vitest run` — **307 passed** (25 Testdateien).
- `npm run lint` — 0 Fehler, 3179 Warnings (unveränderte Bestandscode-Baseline, keine neue Kategorie).
- `npm run build` (`vue-tsc -b && vite build`) — erfolgreich, keine Typ-/Buildfehler.

## Ergebnis

Alle Akzeptanzkriterien aus T08 erfüllt. Change `add-invoice-payment-entry` ist bereit für Reviewer + Tester.
