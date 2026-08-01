# Triage: QA-Tooling-Scripts fehlen (composer qa / npm run lint)

**Pfad:** standard
**Geschätzter Umfang:** ca. 6–9 Dateien, zwei Sprach-Stacks (PHP/Composer, TypeScript/npm), keine Anwendungslogik
**Risiko:** mittel — keine Auth/Datenmodell/Migration betroffen, aber reale Gefahr von Scope-Explosion durch neu sichtbar werdende Bestandsverstöße (siehe unten)
**Klarheit:** mehrdeutig — ein zentraler Punkt muss vor Architekt-Arbeit geklärt werden (siehe Rückfragen)

## Anforderung (Zusammenfassung)

CLAUDE.md schreibt in Abschnitt 5 und 7.1 `composer qa`, `composer lint`,
`composer stan`, `composer compat-check` sowie `npm run lint` als
verbindliche Pre-Flight-/QA-Befehle vor. Im gerade archivierten Change
`fix-trainer-select-customer-creation` haben alle drei dev-Agenten
unabhängig dokumentiert, dass diese Scripts tatsächlich nicht existieren.
Der User möchte einen eigenen, kleinen openspec-Change, der diese Lücke
schließt, sodass die in CLAUDE.md beschriebenen Befehle real funktionieren.

## Verifikation im Repo (mit Beleg)

**Backend (`backend/composer.json`):**
- Der `scripts`-Block enthält nur `post-autoload-dump`, `post-update-cmd`,
  `post-root-package-install`, `post-create-project-cmd`, `dev`. Keine
  Einträge für `qa`, `lint`, `stan`, `compat-check`. Bestätigt.
- `require-dev` enthält `laravel/pint: ^1.13` — Pint ist also **bereits
  installiert**, nur nicht als `composer lint`-Script verdrahtet.
- `larastan/larastan` ist **nicht** in `require-dev` von
  `backend/composer.json`. In `backend/composer.lock` taucht der String
  `larastan/larastan` zwar dreimal auf (Zeilen ~246, ~7380, ~7656), aber
  **nur als `require-dev`-Angabe fremder Pakete** (`dompdf/dompdf`,
  Laravel-Zero-Toolchain, `pestphp/pest`) — es gibt **keinen**
  `packages-dev`-Eintrag mit `"name": "larastan/larastan"`. Larastan ist
  also tatsächlich nicht installiert. Bestätigt.
- `phpcompatibility/php-compatibility` erscheint weder in
  `backend/composer.json` noch in `backend/composer.lock`. Bestätigt: nicht
  installiert.
- `phpstan.neon` existiert, aber **am Repo-Root**, nicht unter `backend/`.
  Er referenziert `vendor/larastan/larastan/extension.neon` (nicht
  vorhanden) und Pfade `app`, `database`, `config`, `routes` relativ zum
  Repo-Root — diese Verzeichnisse liegen aber unter `backend/app`,
  `backend/database` usw. Der `phpstan.neon` ist damit **zusätzlich
  fehlkonfiguriert relativ zur Verzeichnisstruktur**, unabhängig vom
  fehlenden Larastan-Paket. Das ist ein Fund, der über die User-Beschreibung
  hinausgeht und dem Architekten mitgegeben werden muss. Datei laut
  `git log --diff-filter=A -- phpstan.neon` seit Commit `23a73e0` im Repo
  (Commit-Message bezieht sich inhaltlich auf Kurs-Terminserien-Feature,
  nicht auf Tooling — die Datei kam vermutlich als Nebenprodukt dieses
  Commits mit rein).
- Kein `pint.json` und kein `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` im
  Repo — Pint läuft aktuell nur mit Defaults, das ist für `composer lint`
  ausreichend, aber erwähnenswert.

**Frontend (`frontend/package.json`):**
- Scripts: `dev`, `build`, `build:deploy`, `preview`, `test`, `test:ui`,
  `test:coverage`, `e2e`, `e2e:ui`. Kein `lint`-Script. Bestätigt.
- `devDependencies` enthält kein `eslint`-Paket. `grep -i eslint
  frontend/package.json` liefert keinen Treffer. Kein
  `eslint.config.*`/`.eslintrc*` im Repo gefunden. Bestätigt: keine
  ESLint-Dependency/-Konfiguration vorhanden.

**CI (`~.github/workflows/ci.yml`):**
- Die aktuelle CI führt **nur** `./vendor/bin/pest --no-coverage`
  (Backend) und `npm run test` (Frontend) aus. Sie ruft **keines** der in
  CLAUDE.md vorausgesetzten QA-Scripts auf. Das heißt: CI ist aktuell nicht
  von diesen fehlenden Scripts betroffen/blockiert — aber es bedeutet auch,
  dass CLAUDE.md und CI aktuell auseinanderlaufen (CLAUDE.md verspricht QA-
  Gates, die weder lokal noch in CI existieren).

**Bestandsaufnahme dev-Notes aus `fix-trainer-select-customer-creation`
(archiviert):**
- `openspec/changes/archive/2026-07-28-fix-trainer-select-customer-creation/task-T01.notes.md:62-95` (composer qa/stan/compat-check fehlen, Larastan nicht installiert)
- `openspec/changes/archive/2026-07-28-fix-trainer-select-customer-creation/task-T02.notes.md:80-111` (npm run lint fehlt, kein ESLint)
- `openspec/changes/archive/2026-07-28-fix-trainer-select-customer-creation/task-T03.notes.md:82-110` (dieselbe Lücke, dritte unabhängige Bestätigung)
- `openspec/changes/archive/2026-07-28-fix-trainer-select-customer-creation/review.md:27` (Reviewer lobt die ehrliche Dokumentation statt Erfindung)

Alle vom User genannten Fakten sind damit direkt im Repo verifiziert — keine
ungeprüfte Referenz.

## Umfang- und Risikoeinschätzung

Reine Tooling-/Infrastruktur-Änderung, keine Anwendungslogik, kein
Datenmodell, keine Migration, keine öffentliche Schnittstelle. Betroffene
Dateien voraussichtlich:
- `backend/composer.json` (Scripts + require-dev: larastan/larastan,
  phpcompatibility/php-compatibility)
- `backend/composer.lock` (regeneriert)
- `phpstan.neon` (Pfad-Korrektur: entweder nach `backend/phpstan.neon`
  verschieben oder Pfade relativ zum Repo-Root korrigieren — Architekt muss
  entscheiden)
- ggf. `backend/pint.json` (optional, falls Standard-Regelset nicht reicht)
- `frontend/package.json` (lint-Script + eslint-Dependencies)
- `frontend/package-lock.json` (regeneriert)
- neue `frontend/eslint.config.*` (Flat Config, da Vite/Vue 3 + TS-Stack aktuell ist)

Das sind zwei Sprach-Stacks (`dev-php` und `dev-typescript` beide
involviert) mit jeweils eigenem Task in `tasks.md`, wie in CLAUDE.md
Abschnitt 2 vorgeschrieben ("zwei separate Tasks ... eine pro Sprache").

**Zentrales Risiko (Scope-Explosion):** Sobald PHPStan/Larastan (Level 5
It laut `phpstan.neon`), PHPCompatibility (testVersion 8.2) und ESLint
aktiviert werden, prüfen sie den **gesamten Bestandscode** — nicht nur
neuen Code. Grobe Bestandsgröße: 157 PHP-Dateien unter `backend/app/`, 42
Migrationsdateien unter `backend/database/migrations/`, 90
Vue/TS-Dateien unter `frontend/src/`. Es ist unbekannt, ob und wie viele
Verstöße dabei sichtbar werden. Falls die Anforderung "composer qa /
npm run lint laufen fehlerfrei durch" so verstanden wird, dass **der
gesamte Bestandscode bereits sauber sein muss**, kann aus einem
"kleinen" Tooling-Change ein sehr großer Aufräum-Change werden, der den
ursprünglichen Zweck sprengt. Das ist der Hauptgrund, warum dieser Change
NICHT als "klein" (ohne Skeptiker/User-Gate) eingestuft wird, sondern als
"standard" mit explizitem Skeptiker-Realitätsabgleich und User-Gate 1 —
dort muss die Scope-Grenze verbindlich festgelegt werden, bevor
Implementierung beginnt.

## Rückfragen an den User

- **Scope-Grenze bei Bestandsverstößen:** Sollen `composer qa` und
  `npm run lint` nach diesem Change **nur lauffähig** sein (d. h. die
  Scripts existieren, die Tools sind installiert, aber bestehende
  Alt-Verstöße dürfen z. B. über eine Baseline-Datei
  (`phpstan-baseline.neon`, ESLint-`ignore`-Liste o. Ä.) vorerst toleriert
  werden), oder soll dieser Change **auch alle dabei neu gefundenen
  Bestandsverstöße beheben**? Letzteres würde den Umfang erheblich
  vergrößern und eher in Richtung "gross" mit Vorab-Zerlegung durch den
  Architekten gehen.
- **`phpstan.neon`-Ort:** Soll die Datei nach `backend/phpstan.neon`
  verschoben werden (Pfade dann `app`, `database`, `config`, `routes`
  relativ zu `backend/`), oder soll sie am Repo-Root bleiben und stattdessen
  mit `backend/app`, `backend/database` usw. referenzieren? Beides ist
  technisch möglich, aber es beeinflusst, wie `composer stan` als Script in
  `backend/composer.json` aufgerufen werden muss (`phpstan.neon` liegt
  außerhalb von `backend/`, wo `composer.json` liegt).
- **CI-Integration:** Soll `composer qa` bzw. `npm run lint` zusätzlich in
  `.github/workflows/ci.yml` verdrahtet werden, damit CI und CLAUDE.md
  wieder übereinstimmen? Aktuell ruft die CI diese Schritte nicht auf. Falls
  ja, gehört das als eigener Task in den Change; falls nein, sollte das
  explizit als "nicht im Scope" in `proposal.md` vermerkt werden, damit
  spätere Missverständnisse vermieden werden.

## Empfohlene nächste Aktion

`@architect` mit dem Auftrag, basierend auf dieser Triage-Datei einen
openspec-Change (Vorschlag Change-ID: `add-missing-qa-tooling`) im vollen
Modus A anzulegen — inklusive expliziter Scope-Abgrenzung im `design.md`
zur Baseline-/Bestandsverstöße-Frage (siehe Rückfragen oben), bevor Tasks
für `dev-php` (composer.json/phpstan.neon/Larastan/PHPCompatibility) und
`dev-typescript` (package.json/ESLint-Setup) geschrieben werden. Die
Rückfragen oben sollten vom User beantwortet werden, bevor der Architekt
den Change final ausarbeitet — sonst besteht das Risiko, dass Skeptiker
oder User-Gate 1 den Change wegen der ungeklärten Scope-Frage zurückweisen.
