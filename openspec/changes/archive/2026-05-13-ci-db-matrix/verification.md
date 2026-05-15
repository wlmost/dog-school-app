# Verification: ci-db-matrix

**Gesamtstatus:** freigegeben-mit-design-anpassung

> **Update 2026-05-13:** F1 behoben. `package-lock.json` steht in `.gitignore` → Option B umgesetzt: `npm install` statt `npm ci`, `cache-dependency-path: frontend/package.json`. Design und Tasks entsprechend angepasst.

---

## Zusammenfassung

Die zentralen Ist-Zustand-Behauptungen des Change (fehlendes `pdo_mysql`, SQLite-only-CI, fehlende Matrix) sind vollständig durch die Codebasis bestätigt. Die geplanten Änderungen an `Dockerfile`, `Dockerfile.build` und `ci.yml` sind korrekt beschrieben und technisch plausibel. Ein konkreter Fehler steckt im `frontend-tests`-Job des Soll-YAML: `frontend/package-lock.json` existiert nicht im Repository — weder als Datei noch als Lock-Datei eines alternativen Package-Managers. `npm ci` schlägt ohne Lock-Datei fehl, und der `cache-dependency-path`-Eintrag in `actions/setup-node@v4` referenziert eine nicht-existente Datei. Dieser Punkt muss im Design geklärt werden, bevor T01 implementiert werden kann.

---

## Annahmen-Tabelle

| # | Quelle | Behauptung | Status | Beleg |
|---|--------|-----------|--------|-------|
| 1 | proposal.md | CI-Tests laufen aktuell nur gegen SQLite in-memory | ✅ bestätigt | `.github/workflows/ci.yml`: `-e DB_CONNECTION=sqlite -e DB_DATABASE=":memory:"` im Run-backend-tests-Step |
| 2 | proposal.md | `pdo_mysql` fehlt in `docker/php/Dockerfile` | ✅ bestätigt | `docker/php/Dockerfile`: `docker-php-ext-install` enthält nur `pdo`, `pdo_pgsql`, `pgsql` — kein `pdo_mysql` |
| 3 | proposal.md | `pdo_mysql` fehlt in `docker/php/Dockerfile.build` | ✅ bestätigt | `docker/php/Dockerfile.build`: `docker-php-ext-install` enthält `pdo`, `pdo_pgsql` — kein `pdo_mysql` |
| 4 | proposal.md | CI-Job soll in `backend-tests` (Matrix) und `frontend-tests` aufgeteilt werden | ✅ neu/plausibel | Aktuell ein einzelner Job `build-and-test`; Aufteilung ist konsistent |
| 5 | proposal.md | `phpunit.xml` bleibt unverändert | ✅ bestätigt | `backend/phpunit.xml`: enthält `DB_CONNECTION=sqlite` ohne `force="true"`; Umgebungsvariablen überschreiben korrekt |
| 6 | design.md §1 | Aktueller Job heißt `build-and-test` | ✅ bestätigt | `.github/workflows/ci.yml` Z.9: `build-and-test:` |
| 7 | design.md §1 | Aktuell kein Matrix, kein Service-Container | ✅ bestätigt | `.github/workflows/ci.yml`: keine `strategy.matrix`, keine `services:` |
| 8 | design.md §1 | Kein `--network=host` in aktuellen `docker run`-Aufrufen | ✅ bestätigt | `.github/workflows/ci.yml`: alle `docker run`-Aufrufe ohne `--network=host` |
| 9 | design.md §1 | `docker run`-Container können Service-Container ohne `--network=host` nicht sehen | ✅ bestätigt | GitHub Actions Dokumentation + technisches Netzwerk-Verhalten (eigener Namespace) |
| 10 | design.md §3 | `mariadb-connector-c-dev` fehlt in `docker/php/Dockerfile` `apk add` | ✅ bestätigt | `docker/php/Dockerfile`: `apk add` enthält `postgresql-dev`, aber kein `mariadb-connector-c-dev` |
| 11 | design.md §4 | `mariadb-connector-c-dev` fehlt in `docker/php/Dockerfile.build` `apk add` | ✅ bestätigt | `docker/php/Dockerfile.build`: `apk add` enthält nur `postgresql-dev`, `libzip-dev`, `oniguruma-dev`, `icu-dev` |
| 12 | design.md §5 | `phpunit.xml` hat kein `force="true"` → Env-Vars überschreiben `<env>`-Einträge | ✅ bestätigt | `backend/phpunit.xml`: alle `<env>`-Einträge ohne `force`-Attribut |
| 13 | tasks.md T01 | `./vendor/bin/pest` als Test-Executable | ✅ bestätigt | `backend/composer.lock` Z.7727: `pestphp/pest` v3.8.6 installiert; `./vendor/bin/pest` wird nach `composer install` existieren |
| 14 | tasks.md T01 | `backend/.env.example` existiert (für `cp .env.example .env`) | ✅ bestätigt | Datei vorhanden: `backend/.env.example` |
| 15 | tasks.md T01 | `frontend/package.json` enthält Script `"test": "vitest"` | ✅ bestätigt | `frontend/package.json` Z.11: `"test": "vitest"` |
| 16 | tasks.md T01 | Frontend-Tests haben keine DB-Abhängigkeit | ✅ bestätigt | `frontend/vitest.config.ts`: Environment `happy-dom`; kein DB-Client, kein Axios-Mock auf Backend-Endpoints erkennbar |
| 17 | design.md §2 | `cache-dependency-path: frontend/package-lock.json` | ✅ behoben | Design auf `frontend/package.json` korrigiert. `package-lock.json` steht in `.gitignore` und wird nicht versioniert. |
| 18 | design.md §2 | `npm ci` im `frontend-tests`-Job | ✅ behoben | Design auf `npm install` korrigiert. `npm ci` erfordert Lock-Datei, die nicht versioniert ist. |
| 19 | tasks.md T01 | `backend/storage/fonts` Verzeichnis existiert | ⚠️ nicht auffindbar | Kein Verzeichnis im Repository getrackt. CI verwendet `mkdir -p` — das ist korrekt und kein Problem, aber das Verzeichnis ist nicht committed. Kein Handlungsbedarf, nur Hinweis. |

---

## Befunde

### F1 — ✅ BEHOBEN: `frontend/package-lock.json` existiert nicht

**Ursprünglicher Befund:** `frontend/package-lock.json` fehlt; `package-lock.json` steht in `.gitignore` (Z.57).  
**Entscheidung:** Option B gewählt — `package-lock.json` bleibt gitignoriert (Policy respektiert).  
**Umsetzung in design.md + tasks.md:**
- `npm ci` → `npm install`
- `cache-dependency-path: frontend/package-lock.json` → `cache-dependency-path: frontend/package.json`

---

### F2 — HINWEIS: `backend/storage/fonts` nicht im Repository getrackt

**Betroffen:** `design.md` §2 Step "Set backend directory permissions"  
**Beleg:** Dateisuche über `backend/storage/fonts` — keine Treffer.  
**Bewertung:** Das CI legt das Verzeichnis via `mkdir -p` selbst an, was korrekt ist. Kein Handlungsbedarf, da Git `.gitkeep`-Dateien in `storage/` üblicherweise selektiv getrackt werden. Kein Blocker.

---

## Neue Elemente (Plausibilität)

| Element | Ort | Bewertung |
|---------|-----|-----------|
| `mariadb-connector-c-dev` + `pdo_mysql` in `docker/php/Dockerfile` | Zeilen nach `postgresql-dev` / nach `pdo_pgsql pgsql` | ✅ plausibel — Paket ist in Alpine 3.x stabil; keine Konflikte mit bestehenden Extensions |
| `mariadb-connector-c-dev` + `pdo_mysql` in `docker/php/Dockerfile.build` | analog Dockerfile | ✅ plausibel |
| Neuer Job `backend-tests` mit `strategy.matrix` | `.github/workflows/ci.yml` | ✅ plausibel — YAML-Struktur ist korrekt beschrieben |
| Neuer Job `frontend-tests` | `.github/workflows/ci.yml` | ✅ plausibel — Konzept korrekt; `npm install` + `cache-dependency-path: frontend/package.json` (F1 behoben) |

---

## Empfehlung

**Change ist freigegeben.** Alle Blocker behoben. Der Change kann mit T01 implementiert werden.
