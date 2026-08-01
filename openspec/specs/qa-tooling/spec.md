# qa-tooling

## Purpose

Definiert die lokal und in CI verbindlich auszuführenden QA-Gates (Lint,
statische Analyse, PHP-Kompatibilitätsprüfung, Tests) für Backend und
Frontend, wie in `CLAUDE.md` Abschnitt 5 und 7.1 vorgeschrieben.

## Requirements

### Requirement: Backend-QA-Scripts existieren und laufen lauffähig durch

`backend/composer.json` SHALL die Scripts `test`, `lint`, `stan`,
`compat-check` und `qa` bereitstellen. `qa` SHALL die anderen vier Scripts
verketten. Jedes dieser Scripts SHALL innerhalb der projekteigenen
Docker-Umgebung mit Exit-Code 0 abschließen, wenn es gegen den zum
Zeitpunkt der Ausführung aktuellen Stand von `backend/app/`,
`backend/database/`, `backend/config/`, `backend/routes/` läuft.

#### Scenario: composer qa läuft grün gegen den aktuellen Backend-Code
- **WHEN** ein Entwickler `composer qa` innerhalb der Docker-Umgebung
  ausführt
- **THEN** `lint` (Laravel Pint), `stan` (PHPStan/Larastan),
  `compat-check` (PHPCompatibility gegen `testVersion 8.2`) und `test`
  (Pest) laufen nacheinander
- **THEN** der Gesamtbefehl schließt mit Exit-Code 0 ab

#### Scenario: composer stan meldet neu eingeführte Fehler, ignoriert aber die Baseline
- **WHEN** ein Entwickler neuen PHP-Code unter `backend/app/` hinzufügt,
  der einen neuen PHPStan-Level-5-Fehler einführt
- **THEN** `composer stan` schlägt mit einem Fehlerbericht für genau
  diesen neuen Fehler fehl
- **THEN** zum Baseline-Zeitpunkt bereits vorhandene, in
  `backend/phpstan-baseline.neon` erfasste Fehler werden weiterhin nicht
  gemeldet

#### Scenario: composer compat-check erkennt neu eingeführte PHP-8.3/8.4-Syntax
- **WHEN** ein Entwickler PHP-Code hinzufügt, der ein laut `CLAUDE.md`
  Abschnitt 4.1 verbotenes PHP-8.3- oder PHP-8.4-Sprachfeature verwendet
  (z. B. Property Hooks, Typed Class Constants)
- **THEN** `composer compat-check` meldet einen Verstoß gegen
  `testVersion 8.2` für die betroffene Datei
- **THEN** der Befehl schließt mit einem Exit-Code ungleich 0 ab

### Requirement: phpstan.neon liegt im Backend-Verzeichnis und referenziert existierende Pfade

Die PHPStan-Konfigurationsdatei SHALL unter `backend/phpstan.neon` liegen
und ausschließlich Pfade referenzieren (`paths`, `excludePaths`), die
relativ zu `backend/` tatsächlich existieren.

#### Scenario: phpstan.neon wird ohne explizite --configuration gefunden
- **WHEN** `vendor/bin/phpstan analyse` aus dem Arbeitsverzeichnis
  `backend/` heraus ohne `--configuration`-Flag aufgerufen wird
- **THEN** PHPStan findet und verwendet `backend/phpstan.neon`
  automatisch

#### Scenario: Kein Repo-Root-Duplikat mehr vorhanden
- **WHEN** das Projekt-Repository nach einer `phpstan.neon`-Datei am
  Repo-Root durchsucht wird
- **THEN** keine solche Datei existiert mehr (einzige Konfigurationsdatei
  ist `backend/phpstan.neon`)

### Requirement: Frontend-Lint-Script existiert und läuft lauffähig durch

`frontend/package.json` SHALL ein `lint`-Script bereitstellen, das ESLint
gegen `frontend/src/**/*.vue` und `frontend/src/**/*.ts` ausführt. Das
Script SHALL mit Exit-Code 0 abschließen, wenn es gegen den zum Zeitpunkt
der Ausführung aktuellen Stand von `frontend/src/` läuft.

#### Scenario: npm run lint läuft grün gegen den aktuellen Frontend-Code
- **WHEN** ein Entwickler `npm run lint` im Verzeichnis `frontend/`
  ausführt
- **THEN** ESLint prüft alle `.vue`- und `.ts`-Dateien unter
  `frontend/src/`
- **THEN** der Befehl schließt mit Exit-Code 0 ab

#### Scenario: npm run lint erkennt neu eingeführte Verstöße
- **WHEN** ein Entwickler neuen TypeScript- oder Vue-Code hinzufügt, der
  gegen eine als `error` konfigurierte ESLint-Regel verstößt
- **THEN** `npm run lint` schlägt mit einem Fehlerbericht für genau
  diesen Verstoß fehl

### Requirement: CI führt dieselben QA-Gates aus wie lokal vorgeschrieben

`.github/workflows/ci.yml` SHALL `composer qa` im Backend-Test-Job und
`npm run lint` im Frontend-Test-Job ausführen, sodass die in `CLAUDE.md`
Abschnitt 7.1 vorgeschriebenen Pre-Flight-Checks auch in der
Continuous-Integration-Pipeline laufen.

#### Scenario: CI schlägt fehl, wenn composer qa fehlschlägt
- **WHEN** ein Pull Request PHP-Code enthält, der `composer qa`
  fehlschlagen lässt (z. B. ein neuer PHPStan-Fehler oder eine verbotene
  PHP-8.3-Syntax)
- **THEN** der `backend-tests`-Job in `.github/workflows/ci.yml` schlägt
  fehl
- **THEN** der Pull Request zeigt einen fehlgeschlagenen CI-Check an

#### Scenario: CI schlägt fehl, wenn npm run lint fehlschlägt
- **WHEN** ein Pull Request Frontend-Code enthält, der `npm run lint`
  fehlschlagen lässt
- **THEN** der `frontend-tests`-Job in `.github/workflows/ci.yml`
  schlägt fehl
- **THEN** der Pull Request zeigt einen fehlgeschlagenen CI-Check an
