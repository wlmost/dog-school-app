# Triage: CI DB-Matrix (MySQL + PostgreSQL)

**Pfad:** klein
**Geschätzter Umfang:** 3 Dateien, CI-YAML + Dockerfiles (kein Produktivcode)
**Risiko:** mittel — Docker-Image-Änderung betrifft auch die lokale Entwicklungsumgebung; falsche Netzwerkkonfiguration kann CI-Tests stumm fälschlich passieren lassen
**Klarheit:** klar — Anforderung eindeutig; konkrete technische Lösungspfade sind bekannt

## Anforderung (Zusammenfassung)

Die CI-Pipeline soll Backend-Tests parallel gegen MySQL **und** PostgreSQL ausführen, damit DB-Portabilitätsfehler (falsche Queries, inkompatible Migrations) bereits in Pull Requests sichtbar werden — bevor Code auf dem Shared-Hosting-Demo-System landet. Das Frontend (Vitest) soll nicht verdoppelt werden. Aktuell laufen alle Tests nur gegen SQLite in-memory; echte DB-Treiber werden nicht geprüft.

## Analyse des Ist-Zustands

### `.github/workflows/ci.yml`
- Ein einziger Job `build-and-test`, **keine Matrix**.
- Tests laufen via `docker run dog-school-php ./vendor/bin/pest` mit `-e DB_CONNECTION=sqlite -e DB_DATABASE=:memory:`.
- Keine GitHub-Actions-Service-Container für MySQL oder PostgreSQL vorhanden.

### `docker/php/Dockerfile`
- Installiert `pdo_pgsql` und `pgsql` ✓
- **`pdo_mysql` fehlt vollständig** — die MySQL-Matrix-Leg würde sofort mit `PDO Exception: could not find driver` scheitern.
- Betrifft auch `docker/php/Dockerfile.build` (gleiches Problem).

### `backend/phpunit.xml`
- Hardcodiert `DB_CONNECTION=sqlite` und `DB_DATABASE=:memory:` via `<env>`.
- PHPUnit respektiert echte Umgebungsvariablen mit Vorrang vor `<env>`-Einträgen (kein `force="true"` gesetzt) → `-e DB_CONNECTION=mysql` im `docker run`-Aufruf überschreibt den Wert korrekt.

### Docker-Netzwerk (kritischer Punkt)
- GitHub-Actions-Service-Container sind vom Runner-Host aus über `localhost` erreichbar.
- `docker run`-Container bekommen ein eigenes Netzwerk-Namespace; sie können **nicht** auf `localhost`-Services des Hosts zugreifen — es sei denn, der Container wird mit `--network=host` gestartet.
- **Ohne `--network=host` würde die MySQL/PostgreSQL-Verbindung im `docker run`-Container scheitern**, auch wenn der Service-Container läuft.

## Betroffene Dateien

| Datei | Änderungstyp | Pflicht? |
|---|---|---|
| `.github/workflows/ci.yml` | Umstrukturierung: Matrix-Strategy + Service-Container + `--network=host` | ✅ Pflicht |
| `docker/php/Dockerfile` | `pdo_mysql` zur Extension-Liste hinzufügen | ✅ Pflicht (Blocker) |
| `docker/php/Dockerfile.build` | `pdo_mysql` hinzufügen (für Deployment-Builds auf MySQL-Hosts) | Empfohlen |

## Offene Punkte / Entscheidungen für den Architekten

1. **Frontend-Tests in der Matrix?**
   Vitest-Tests haben keinerlei DB-Abhängigkeit. Empfehlung: Frontend-Tests als separaten Job ohne Matrix behalten, um unnötige Duplizierung und CI-Laufzeit zu vermeiden.

2. **`Dockerfile.build` für MySQL?**
   Das Build-Image wird für Deployment-Builds verwendet. Da Demo/Produktion MySQL nutzen, sollte `pdo_mysql` dort ebenfalls ergänzt werden — andernfalls würden Deployment-Builds, die eine MySQL-Verbindung aufbauen, scheitern.

3. **CI-Laufzeit**
   Die Matrix erzeugt 2 parallele Backend-Jobs (mysql + pgsql) statt 1. Bei aktuellem Scope (~30s pro Lauf) ist das akzeptabel.

4. **DB-Credentials in CI**
   MySQL- und PostgreSQL-Service-Container benötigen Zugangsdaten. Diese werden als Standard-Werte direkt in der Workflow-YAML gesetzt (keine Secrets nötig, da CI-only-Test-DB ohne sensible Daten).

## Empfohlene nächste Aktion

**Architekt** (`openspec-continue-change` oder `openspec-ff-change`) erstellt einen Change mit einem einzigen Task `T01 (dev-php/devops)`:
- `docker/php/Dockerfile` + `docker/php/Dockerfile.build`: `pdo_mysql` ergänzen
- `.github/workflows/ci.yml`: Matrix-Strategy mit `db: [mysql, pgsql]`, GitHub-Actions-Service-Container, `--network=host` in `docker run`, DB-spezifische Umgebungsvariablen je Matrix-Leg

Reviewer prüft: kein Produktivcode berührt, `--network=host` ist korrekt gesetzt, keine SQLite-Fallbacks im Matrix-Lauf.
