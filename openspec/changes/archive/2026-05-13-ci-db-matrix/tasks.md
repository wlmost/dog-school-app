# Tasks: ci-db-matrix

---

## T01: CI-Matrix für MySQL + PostgreSQL + pdo_mysql-Treiber

- **Agent:** dev-php
- **Dateien:**
  - `.github/workflows/ci.yml`
  - `docker/php/Dockerfile`
  - `docker/php/Dockerfile.build`
- **Abhängigkeiten:** keine
- **Beschreibung:**

  Drei Dateien werden geändert. Kein Produktivcode wird berührt.

  ### 1. `docker/php/Dockerfile`

  In der `apk add`-Zeile `mariadb-connector-c-dev` nach `postgresql-dev` einfügen.
  In `docker-php-ext-install` den Treiber `pdo_mysql` nach `pdo_pgsql` und `pgsql` einfügen.

  **Warum:** `pdo_mysql` fehlt bisher vollständig. Ohne diesen Treiber schlägt jede MySQL-Verbindung mit `PDO Exception: could not find driver` fehl — sowohl in der neuen CI-Matrix als auch im lokalen Docker-Setup, wenn gegen MySQL getestet wird.

  ### 2. `docker/php/Dockerfile.build`

  In der `apk add`-Zeile `mariadb-connector-c-dev` nach `postgresql-dev` einfügen.
  In `docker-php-ext-install` den Treiber `pdo_mysql` nach `pdo_pgsql` einfügen.

  **Warum:** Das Build-Image wird für Deployment-Artefakte auf MySQL-Hosts verwendet (Demo + Produktion). Ohne `pdo_mysql` würden deployete Composer-Artefakte mit einem MySQL-Host nicht funktionieren.

  ### 3. `.github/workflows/ci.yml`

  Den bestehenden einzelnen Job `build-and-test` durch zwei neue Jobs ersetzen:

  **Job `backend-tests`** mit Matrix-Strategy:
  ```yaml
  strategy:
    fail-fast: false
    matrix:
      include:
        - db: mysql
          db_connection: mysql
          db_port: 3306
        - db: pgsql
          db_connection: pgsql
          db_port: 5432
  ```

  Service-Container:
  - `mysql:8.0` auf Port 3306, DB `dog_school_test`, User `test_user`, Passwort `test_password`, Root-Passwort `root_password`; Health-Check: `mysqladmin ping -h 127.0.0.1 -u root --password=root_password`
  - `postgres:16` auf Port 5432, DB `dog_school_test`, User `test_user`, Passwort `test_password`; Health-Check: `pg_isready -U test_user -d dog_school_test`

  Wichtige technische Constraints:
  - **Alle** `docker run`-Aufrufe im Backend-Job brauchen `--network=host`. Ohne dieses Flag kann der Container die Service-Container auf `127.0.0.1` nicht erreichen (eigener Netzwerk-Namespace).
  - Image-Tag muss `dog-school-php-${{ matrix.db }}` sein (nicht `dog-school-php`), damit parallele Builds nicht kollidieren.
  - Artefakt-Name im Upload-Step muss `laravel-logs-${{ matrix.db }}` sein, da GitHub Actions keine zwei Artefakte mit identischem Namen erlaubt.
  - Der "Run backend tests"-Step übergibt `DB_CONNECTION`, `DB_HOST=127.0.0.1`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` aus den Matrix-Variablen. Kein SQLite mehr in diesem Job.
  - `phpunit.xml` wird **nicht geändert** — PHPUnit liest echte Umgebungsvariablen vorrangig, da `force="true"` nicht gesetzt ist.

  **Job `frontend-tests`** (neu, ohne Matrix):
  - Checkout → `actions/setup-node@v4` mit Node 20, `cache: npm`, `cache-dependency-path: frontend/package.json` → `npm install` → `npm run test`; alles mit `working-directory: frontend`
  - Keine Service-Container, kein `docker run`
  - **Hinweis:** `npm install` statt `npm ci`, weil `frontend/package-lock.json` in `.gitignore` steht und nicht versioniert ist. `npm ci` würde ohne Lock-Datei fehlschlagen.

  Das vollständige YAML des Soll-Zustands ist in `design.md` Abschnitt 2 ausgeschrieben.

- **Akzeptanzkriterien:**
  - [x] `docker/php/Dockerfile` enthält `mariadb-connector-c-dev` in der `apk add`-Zeile
  - [x] `docker/php/Dockerfile` enthält `pdo_mysql` in der `docker-php-ext-install`-Zeile
  - [x] `docker/php/Dockerfile.build` enthält `mariadb-connector-c-dev` in der `apk add`-Zeile
  - [x] `docker/php/Dockerfile.build` enthält `pdo_mysql` in der `docker-php-ext-install`-Zeile
  - [x] `.github/workflows/ci.yml` enthält keinen Job `build-and-test` mehr
  - [x] Job `backend-tests` in `ci.yml` hat eine `strategy.matrix` mit den Legs `mysql` und `pgsql`
  - [x] Job `backend-tests` hat Service-Container `mysql:8.0` und `postgres:16` mit Health-Checks
  - [x] Alle `docker run`-Aufrufe im Job `backend-tests` enthalten `--network=host`
  - [x] Der Image-Tag lautet `dog-school-php-${{ matrix.db }}` (nicht `dog-school-php`)
  - [x] Der Artefakt-Name im Upload-Step lautet `laravel-logs-${{ matrix.db }}`
  - [x] Der Test-Step übergibt `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` aus Matrix-Variablen; kein `-e DB_CONNECTION=sqlite` und kein `-e DB_DATABASE=:memory:` mehr im Matrix-Job
  - [x] Job `frontend-tests` ist vorhanden, hat keine Matrix, kein Service-Container, kein `docker run`, läuft mit `working-directory: frontend`
  - [x] `actions/setup-node@v4` im Frontend-Job verwendet `cache-dependency-path: frontend/package.json` (nicht `package-lock.json`)
  - [x] Frontend-Job verwendet `npm install` (nicht `npm ci`)
  - [x] `backend/phpunit.xml` ist **unverändert** (keine Änderung an dieser Datei)
  - [x] Kein Produktivcode unter `app/`, `resources/`, `routes/`, `database/` wird berührt
