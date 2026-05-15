# Review: T01 — CI-Matrix für MySQL + PostgreSQL

**Gesamturteil:** freigegeben

---

## Befunde

### [Schweregrad: hinweis] Beide Service-Container laufen in beiden Matrix-Legs

**Datei:** `.github/workflows/ci.yml` Z. 27–54 (services-Block)  
**Beschreibung:** Die `services:`-Deklaration enthält `mysql` und `postgres` auf Job-Ebene, nicht auf Matrix-Leg-Ebene. Damit starten in jedem Leg beide Datenbank-Container: Im `mysql`-Leg wartet GitHub Actions auch auf den PostgreSQL-Health-Check, obwohl Postgres nie angesprochen wird — und umgekehrt. Das verlängert die Startzeit beider Legs um ca. 10–20 s je nach Runner-Auslastung.  
**Beweis:** `design.md` Z. 9–10 beschreibt dies explizit als Soll-Zustand (`mysql:8.0 (läuft in beiden Legs)`), daher kein Implementierungsfehler. Hinweis für spätere Optimierung: Je nach GitHub-Actions-Feature-Stand können Matrix-Legs einen `services:`-Block mit konditionalen Feldern nicht direkt bekommen — die aktuelle Lösung ist der einfachste korrekte Ansatz.  
**Empfehlung:** Kein Handlungsbedarf jetzt. Bei signifikanter CI-Laufzeit-Verlängerung später zwei separate Jobs (backend-tests-mysql / backend-tests-pgsql) ohne gemeinsamen services-Block erwägen.

---

### [Schweregrad: hinweis] Floating Image-Tags ohne Digest-Pin

**Datei:** `.github/workflows/ci.yml` Z. 29 (`mysql:8.0`) und Z. 43 (`postgres:16`)  
**Beschreibung:** Beide Tags sind nicht mit einem SHA256-Digest fixiert. Ein stilles Patch-Update von Docker Hub könnte das CI-Verhalten ändern, ohne dass eine CI-Änderung im Git-Log auftaucht. In der Praxis ist das Risiko bei Major-Version-Pinning gering, aber vorhanden.  
**Empfehlung:** Für einen CI-Prototyp akzeptabel. Wenn CI-Reproduzierbarkeit später kritisch wird, Tags auf `mysql:8.0@sha256:…` und `postgres:16@sha256:…` fixieren.

---

### [Schweregrad: hinweis] Test-Passwörter im Klartext sichtbar in CI-Logs

**Datei:** `.github/workflows/ci.yml` Z. 31 (`MYSQL_ROOT_PASSWORD: root_password`), Z. 34 (`MYSQL_PASSWORD: test_password`), Z. 44–45, Z. 93–94  
**Beschreibung:** Die DB-Credentials sind hardcodiert. GitHub Actions maskiert keine Werte, die nicht als `${{ secrets.* }}` deklariert sind. Die Werte erscheinen damit in Build-Logs. Das Angriffsszenario ist: Ein Angreifer mit Lesezugriff auf CI-Logs erhält die Test-Credentials — jedoch sind diese Credentials nur in ephemeren Service-Containern ohne externe Port-Freigabe auf dem Runner gültig. Eine Verbindung von außen ist technisch nicht möglich (kein `ports:` auf öffentliche IP, kein `0.0.0.0:3306:3306`). Das Risiko ist damit de facto null.  
**Empfehlung:** Keine Änderung notwendig. Muster ist Standard-Praxis für CI-Test-DBs.

---

## Akzeptanzkriterien

- [x] `docker/php/Dockerfile` enthält `mariadb-connector-c-dev` in der `apk add`-Zeile (`docker/php/Dockerfile` Z. 9)
- [x] `docker/php/Dockerfile` enthält `pdo_mysql` in der `docker-php-ext-install`-Zeile (`docker/php/Dockerfile` Z. 21)
- [x] `docker/php/Dockerfile.build` enthält `mariadb-connector-c-dev` in der `apk add`-Zeile (`docker/php/Dockerfile.build` Z. 9)
- [x] `docker/php/Dockerfile.build` enthält `pdo_mysql` in der `docker-php-ext-install`-Zeile (`docker/php/Dockerfile.build` Z. 14)
- [x] `.github/workflows/ci.yml` enthält keinen Job `build-and-test` mehr (Datei vollständig ersetzt)
- [x] Job `backend-tests` hat `strategy.matrix` mit Legs `mysql` und `pgsql` (`.github/workflows/ci.yml` Z. 17–25)
- [x] Job `backend-tests` hat Service-Container `mysql:8.0` und `postgres:16` mit korrekten Health-Checks (Z. 27–54)
- [x] Alle drei `docker run`-Aufrufe im Job `backend-tests` enthalten `--network=host` (Z. 68, 82, 88)
- [x] Image-Tag lautet `dog-school-php-${{ matrix.db }}` (Z. 63, 68, 82, 88)
- [x] Artefakt-Name lautet `laravel-logs-${{ matrix.db }}` (Z. 104)
- [x] Test-Step übergibt `DB_CONNECTION`, `DB_HOST=127.0.0.1`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` aus Matrix-Variablen; kein `-e DB_CONNECTION=sqlite`, kein `-e DB_DATABASE=:memory:` im Matrix-Job (Z. 90–99)
- [x] Job `frontend-tests` vorhanden, keine Matrix, kein Service-Container, kein `docker run`, `working-directory: frontend` (Z. 113–139)
- [x] `actions/setup-node@v4` im Frontend-Job verwendet `cache-dependency-path: frontend/package.json` (nicht `package-lock.json`) (Z. 121–125)
- [x] Frontend-Job verwendet `npm install` (nicht `npm ci`) (Z. 127–129)
- [x] `backend/phpunit.xml` ist unverändert — enthält weiterhin sqlite/:memory:-Fallback ohne `force="true"`, der korrekt durch CI-Env-Vars überschrieben wird
- [x] Kein Produktivcode unter `app/`, `resources/`, `routes/`, `database/` berührt

---

## Einzelprüfungen

**`--network=host` vollständig?** Ja. Alle drei `docker run`-Aufrufe (Install Composer, key:generate, Run tests) haben das Flag. ✅

**Image-Tag-Konsistenz?** Ja. `docker build -t dog-school-php-${{ matrix.db }}` und alle nachfolgenden `docker run`-Aufrufe referenzieren exakt diesen Tag. ✅

**`fail-fast: false` gesetzt?** Ja, Z. 18. ✅

**`pdo_mysql` benötigt `mariadb-connector-c-dev` auf Alpine?** Ja, korrekt — auf Alpine Linux liefert `libmysqlclient` die C-Header für `pdo_mysql`, dieses Paket ist in Alpine als `mariadb-connector-c-dev` verfügbar. Standard-Praxis. ✅

**`npm run test` (Vitest ohne `--run`) hängt in CI?** Nein. Vitest erkennt `CI=true` (von GitHub Actions automatisch gesetzt) und wechselt in den non-interactive run-Modus ohne Watch. Kein Handlungsbedarf. ✅

**Kein SQLite-Fallback im Matrix-Job?** Korrekt. `DB_CONNECTION` wird ausschließlich aus `matrix.db_connection` gesetzt. Das `phpunit.xml` definiert `DB_CONNECTION=sqlite` ohne `force="true"`, wird aber zur Laufzeit durch die -e-Flags überschrieben. ✅

---

## Fazit

Die Implementierung ist korrekt und vollständig. Alle 16 Akzeptanzkriterien aus `tasks.md` sind erfüllt. Die drei Hinweis-Befunde sind entweder bewusste Designentscheidungen (beide Service-Container pro Leg) oder Standard-CI-Praxis (Credentials in ephemeren Containern, Floating Tags). Kein Befund blockiert die Abnahme.
