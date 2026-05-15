# Design: ci-db-matrix

**Change-ID:** ci-db-matrix
**Datum:** 2026-05-13

---

## 1. Architektur-Übersicht

```
CI-Pipeline (nach Change)
│
├── backend-tests (Matrix: mysql | pgsql)
│   ├── Service-Container: mysql:8.0   (Port 3306, läuft in beiden Legs)
│   ├── Service-Container: postgres:16 (Port 5432, läuft in beiden Legs)
│   ├── docker build → dog-school-php-{matrix.db}
│   ├── composer install (--network=host)
│   ├── php artisan key:generate (--network=host)
│   └── pest --no-coverage (--network=host, DB_CONNECTION={matrix.db_connection})
│
└── frontend-tests (kein Matrix, kein Service-Container)
    ├── npm install (working-directory: frontend)
    └── npm run test (working-directory: frontend)
```

### Netzwerk-Topologie in GitHub Actions

GitHub-Actions-Service-Container werden auf dem Runner-Host gestartet und sind über `127.0.0.1` mit dem gemappten Port erreichbar. `docker run`-Container hingegen haben ein eigenes Netzwerk-Namespace und können den Runner-Localhost nicht sehen — außer der Container wird mit `--network=host` gestartet. **Alle `docker run`-Aufrufe im `backend-tests`-Job erhalten daher `--network=host`.**

---

## 2. Soll-Zustand: `.github/workflows/ci.yml`

Vollständige neue YAML (ersetzt die bestehende Datei komplett):

```yaml
name: CI – Build & Test

on:
  push:
    branches: ["**"]
  pull_request:
    branches: ["**"]

env:
  FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: true

jobs:
  backend-tests:
    name: Backend tests (${{ matrix.db }})
    runs-on: ubuntu-latest

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

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root_password
          MYSQL_DATABASE: dog_school_test
          MYSQL_USER: test_user
          MYSQL_PASSWORD: test_password
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h 127.0.0.1 -u root --password=root_password"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: dog_school_test
          POSTGRES_USER: test_user
          POSTGRES_PASSWORD: test_password
        ports:
          - 5432:5432
        options: >-
          --health-cmd="pg_isready -U test_user -d dog_school_test"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

    steps:
      - name: Checkout current branch
        uses: actions/checkout@v4

      - name: Set backend directory permissions
        run: |
          mkdir -p "${{ github.workspace }}/backend/storage/fonts"
          chmod -R 777 "${{ github.workspace }}/backend"

      - name: Build PHP Docker image
        run: docker build --no-cache -t dog-school-php-${{ matrix.db }} ./docker/php

      - name: Install Composer dependencies
        run: |
          docker run --rm \
            --network=host \
            -v "${{ github.workspace }}/backend:/var/www/html" \
            -w /var/www/html \
            dog-school-php-${{ matrix.db }} \
            composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Prepare test environment
        working-directory: backend
        run: |
          cp .env.example .env
          sed -i "s|^APP_KEY=.*|APP_KEY=|" .env
          chmod 666 .env

      - name: Generate application key
        run: |
          docker run --rm \
            --network=host \
            -v "${{ github.workspace }}/backend:/var/www/html" \
            -w /var/www/html \
            dog-school-php-${{ matrix.db }} \
            php artisan key:generate

      - name: Run backend tests
        run: |
          docker run --rm \
            --network=host \
            -v "${{ github.workspace }}/backend:/var/www/html" \
            -w /var/www/html \
            -e APP_ENV=testing \
            -e DB_CONNECTION=${{ matrix.db_connection }} \
            -e DB_HOST=127.0.0.1 \
            -e DB_PORT=${{ matrix.db_port }} \
            -e DB_DATABASE=dog_school_test \
            -e DB_USERNAME=test_user \
            -e DB_PASSWORD=test_password \
            -e CACHE_STORE=array \
            -e SESSION_DRIVER=array \
            -e QUEUE_CONNECTION=sync \
            dog-school-php-${{ matrix.db }} \
            ./vendor/bin/pest --no-coverage

      - name: Upload logs on failure
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: laravel-logs-${{ matrix.db }}
          path: backend/storage/logs/
          retention-days: 3

  frontend-tests:
    name: Frontend tests
    runs-on: ubuntu-latest

    steps:
      - name: Checkout current branch
        uses: actions/checkout@v4

      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: frontend/package.json

      - name: Install npm dependencies
        working-directory: frontend
        run: npm install

      - name: Run frontend tests
        working-directory: frontend
        run: npm run test
```

> **Anmerkung zu `npm install` statt `npm ci`:** `package-lock.json` ist in `.gitignore` eingetragen und wird nicht versioniert. Da `npm ci` zwingend eine Lock-Datei voraussetzt, wird stattdessen `npm install` verwendet. Als Cache-Key dient `frontend/package.json`. Sollte die Lock-Datei künftig aus `.gitignore` entfernt und committed werden, kann auf `npm ci` mit `cache-dependency-path: frontend/package-lock.json` umgestellt werden.

### Design-Entscheidungen in der YAML

**`fail-fast: false`**: Beide Matrix-Legs laufen bis zum Ende durch, auch wenn eine Leg fehlschlägt. So sind immer beide DB-Fehlerbilder in einem CI-Lauf sichtbar.

**Matrix mit `include`-Syntax statt einfacher Array**: Ermöglicht pro Leg saubere Zuweisung von `db_connection` und `db_port`, ohne bedingte Logik in den Steps.

**Beide Service-Container starten in beiden Legs**: GitHub Actions unterstützt keine konditionalen Services. Beide Container laufen immer; die Matrix-Leg entscheidet nur über die Env-Variablen. Der Overhead (ein unbenutzter DB-Container pro Leg) ist vernachlässigbar.

**Image-Tag `dog-school-php-${{ matrix.db }}`**: Parallele Matrix-Legs auf demselben Runner würden den gleichen Image-Namen überschreiben. Der pro-Leg eindeutige Tag verhindert Race Conditions.

**Artefakt-Name `laravel-logs-${{ matrix.db }}`**: GitHub Actions erlaubt keine zwei Artefakte mit gleichem Namen pro Workflow-Run. Das Suffix macht den Namen eindeutig.

---

## 3. Soll-Zustand: `docker/php/Dockerfile`

Änderung: `mariadb-connector-c-dev` zu `apk add` und `pdo_mysql` zu `docker-php-ext-install` ergänzen.

```dockerfile
FROM php:8.4-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    mariadb-connector-c-dev \
    oniguruma-dev \
    libzip-dev \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Xdebug (for development)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN addgroup -g 1000 www \
    && adduser -u 1000 -G www -s /bin/sh -D www

# Copy PHP configuration
COPY php.ini /usr/local/etc/php/php.ini
COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Copy and configure entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set permissions
RUN chown -R www:www /var/www/html

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# Change current user to www
USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000

CMD ["php-fpm"]
```

**Diff (minimaler Änderungsumfang):**
- `apk add`: `mariadb-connector-c-dev` nach `postgresql-dev` eingefügt
- `docker-php-ext-install`: `pdo_mysql` nach `pdo_pgsql` und `pgsql` eingefügt

---

## 4. Soll-Zustand: `docker/php/Dockerfile.build`

Änderung: `mariadb-connector-c-dev` und `pdo_mysql` analog zum Entwicklungs-Image ergänzen.

```dockerfile
# Lightweight PHP build image for generating composer vendor directory.
# Used by build-deployment-docker.sh with --php-version argument.
#
# Usage:
#   docker build --build-arg PHP_VERSION=8.3 -t homocanis-builder-php83 -f docker/php/Dockerfile.build .
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache \
    postgresql-dev \
    mariadb-connector-c-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        zip \
        mbstring \
        intl \
        bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
```

**Diff (minimaler Änderungsumfang):**
- `apk add`: `mariadb-connector-c-dev` nach `postgresql-dev` eingefügt
- `docker-php-ext-install`: `pdo_mysql` nach `pdo_pgsql` eingefügt

---

## 5. `backend/phpunit.xml` — keine Änderung nötig

Die Datei enthält `<env name="DB_CONNECTION" value="sqlite"/>` ohne `force="true"`. PHPUnit respektiert echte Umgebungsvariablen vorrangig vor `<env>`-Einträgen. Die über `docker run -e` gesetzten Variablen überschreiben den SQLite-Default korrekt — keine Änderung an `phpunit.xml` erforderlich.

---

## 6. DB-Portabilitäts-Checkliste

Diese Punkte werden durch die neue CI-Matrix geprüft und müssen beim Review mitgedacht werden:

| Prüfpunkt | Risiko | Mitigation |
|---|---|---|
| Migrations ohne DB-spezifische Typen | mittel | Lint via CLAUDE.md Abschnitt 4.2 |
| Raw SQL in `whereRaw`/`DB::raw` | hoch | CI schlägt bei MySQL-inkompatiblen Queries fehl |
| `ON CONFLICT` / `RETURNING` (Postgres-only) | mittel | CI (MySQL-Leg) würde fehlschlagen |
| JSON-Operatoren `@>`, `->` in raw SQL | hoch | CI (MySQL-Leg) würde fehlschlagen |
| `$table->jsonb()` in Migrations | niedrig | `jsonb()` existiert in Laravel nicht — würde bereits lokal fehlschlagen |

---

## 7. Risiken und Mitigationsmaßnahmen

| Risiko | Wahrscheinlichkeit | Auswirkung | Mitigation |
|---|---|---|---|
| Service-Container startet nicht rechtzeitig | niedrig | MySQL/pgsql-Leg schlägt fehl | Health-Checks mit 5 Retries; GitHub Actions wartet auf `healthy` |
| `--network=host` auf nicht-Linux-Runnern | niedrig | Tritt nicht auf (GitHub Actions Standard-Runner ist Ubuntu) | Kein Handlungsbedarf; `runs-on: ubuntu-latest` beibehalten |
| `mariadb-connector-c-dev` nicht in Alpine verfügbar | niedrig | Docker-Build schlägt fehl | Paket ist in Alpine 3.x seit Jahren stabil; fallback: `mysql-dev` |
| Race Condition bei parallelen Image-Builds | mittel | Ein Job überschreibt das Image des anderen | Behoben durch Image-Tag mit `${{ matrix.db }}`-Suffix |
| Artefakt-Namens-Kollision bei Upload | mittel | Workflow-Run schlägt mit Name-Conflict-Fehler fehl | Behoben durch Suffix `${{ matrix.db }}` im Artefaktnamen |
| Ungenutzte DB-Connection im jeweiligen Leg | sehr niedrig | Minimale Laufzeit-Overhead | Akzeptiert; beide Container starten immer |

---

## 8. Entscheidungsprotokoll

| Entscheidung | Alternative | Begründung |
|---|---|---|
| Beide Service-Container in beiden Legs | Konditionale Services | GitHub Actions unterstützt keine konditionalen Services |
| `matrix.include` statt Array + Conditions | `if: matrix.db == 'mysql'` in Steps | Sauberer, weniger redundanter YAML; einzelner Test-Step statt zwei konditionaler Steps |
| `fail-fast: false` | Standard (`true`) | Beide DB-Fehlerbilder in einem Lauf sichtbar machen |
| `mariadb-connector-c-dev` (nicht `mysql-dev`) | `mysql-dev` | Offizielles Alpine-Paket für MySQL-Client-Headers; `mysql-dev` ist ein Alias, `mariadb-connector-c-dev` ist expliziter |
| Frontend als separater Job ohne Matrix | Frontend in Backend-Matrix | Vitest hat keine DB-Abhängigkeit; Duplizierung wäre reine Laufzeit-Verschwendung |
| Credentials direkt in YAML (kein `secrets`) | GitHub Secrets | Test-DB ohne Produktivdaten; Credentials sind test-only und haben keinen Sicherheitswert |
