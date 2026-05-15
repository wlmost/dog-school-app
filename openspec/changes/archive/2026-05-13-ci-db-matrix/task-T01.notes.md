# Task T01 — Implementation Notes

## Zusammenfassung

Drei Dateien wurden geändert, kein Produktivcode berührt. Die Änderungen fügen
`pdo_mysql` zu beiden PHP-Docker-Images hinzu und ersetzen den einzelnen CI-Job
`build-and-test` durch zwei Jobs: `backend-tests` (DB-Matrix MySQL + PostgreSQL)
und `frontend-tests`.

---

## Geänderte Dateien

### `docker/php/Dockerfile`

- `apk add`: `mariadb-connector-c-dev` nach `postgresql-dev` eingefügt
- `docker-php-ext-install`: `pdo_mysql` nach `pdo_pgsql` / `pgsql` und vor `mbstring` eingefügt

### `docker/php/Dockerfile.build`

- `apk add`: `mariadb-connector-c-dev` nach `postgresql-dev` eingefügt
- `docker-php-ext-install`: `pdo_mysql` nach `pdo_pgsql` und vor `zip` eingefügt

### `.github/workflows/ci.yml`

- Job `build-and-test` vollständig entfernt
- Neuer Job `backend-tests`:
  - `strategy.matrix` mit zwei Legs: `mysql` (Port 3306) und `pgsql` (Port 5432)
  - Service-Container `mysql:8.0` und `postgres:16` mit Health-Checks
  - Alle `docker run`-Aufrufe mit `--network=host`
  - Image-Tag `dog-school-php-${{ matrix.db }}`
  - DB-Umgebungsvariablen aus Matrix; kein SQLite mehr
  - Artefakt-Name `laravel-logs-${{ matrix.db }}`
- Neuer Job `frontend-tests`:
  - `actions/setup-node@v4`, Node 20, `cache-dependency-path: frontend/package.json`
  - `npm install` (kein `npm ci` — keine `package-lock.json` im Repo)
  - `npm run test`, `working-directory: frontend`
  - Keine Matrix, keine Service-Container, kein `docker run`

---

## Akzeptanzkriterien

- [x] `docker/php/Dockerfile` enthält `mariadb-connector-c-dev` in der `apk add`-Zeile
- [x] `docker/php/Dockerfile` enthält `pdo_mysql` in der `docker-php-ext-install`-Zeile
- [x] `docker/php/Dockerfile.build` enthält `mariadb-connector-c-dev` in der `apk add`-Zeile
- [x] `docker/php/Dockerfile.build` enthält `pdo_mysql` in der `docker-php-ext-install`-Zeile
- [x] `.github/workflows/ci.yml` enthält keinen Job `build-and-test` mehr
- [x] Job `backend-tests` in `ci.yml` hat eine `strategy.matrix` mit den Legs `mysql` und `pgsql`
- [x] Job `backend-tests` hat Service-Container `mysql:8.0` und `postgres:16` mit Health-Checks
- [x] Alle `docker run`-Aufrufe im Job `backend-tests` enthalten `--network=host`
- [x] Der Image-Tag lautet `dog-school-php-${{ matrix.db }}`
- [x] Der Artefakt-Name lautet `laravel-logs-${{ matrix.db }}`
- [x] Test-Step übergibt DB-Variablen aus Matrix; kein `DB_CONNECTION=sqlite`, kein `DB_DATABASE=:memory:`
- [x] Job `frontend-tests` ist vorhanden, kein Matrix, kein Service-Container, kein `docker run`, `working-directory: frontend`
- [x] Frontend-Job verwendet `cache-dependency-path: frontend/package.json` und `npm install`
- [x] `backend/phpunit.xml` ist unverändert
- [x] Kein Produktivcode berührt
