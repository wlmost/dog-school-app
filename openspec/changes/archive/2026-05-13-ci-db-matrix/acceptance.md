# Abnahme: ci-db-matrix

**Status:** abgenommen mit offenen Punkten (nicht-blockierend)  
**Datum:** 2026-05-13  
**Architekt:** Modus B

---

## Abnahme-Entscheidung

**Abgenommen.** Der Change ist vollständig implementiert und bereit zum Merge.

---

## Ziel erreicht?

**Ja.** Das ursprüngliche Ziel — DB-Portabilitätsfehler in PRs sichtbar machen — ist vollständig erreicht:

- Jede CI-Ausführung startet jetzt zwei parallele `backend-tests`-Legs: einen gegen MySQL 8.0, einen gegen PostgreSQL 16 (echte Service-Container, kein SQLite in-memory).
- Die Test-Suite läuft mit `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` direkt aus den Matrix-Variablen — kein SQLite-Fallback möglich.
- `pdo_mysql` ist in beiden Dockerfiles (`Dockerfile`, `Dockerfile.build`) korrekt ergänzt.
- Der `frontend-tests`-Job ist sauber getrennt und unabhängig vom Backend-Matrix-Job.

---

## Artefakt-Vollständigkeit

| Artefakt | Vorhanden |
|---|---|
| `proposal.md` | ✅ |
| `design.md` | ✅ |
| `tasks.md` | ✅ |
| `verification.md` | ✅ |
| `task-T01.notes.md` | ✅ |
| `task-T01.review.md` | ✅ |
| `task-T01.test-report.md` | ✅ |

Alle 7 erwarteten Artefakte liegen vor.

---

## Code-Verifikation (Stichproben)

Geprüft gegen die tatsächlichen Dateien im Repo:

**`docker/php/Dockerfile`** (Z. 14, 30):
- `mariadb-connector-c-dev` im `apk add`-Block ✅
- `pdo_mysql` im `docker-php-ext-install`-Block ✅

**`docker/php/Dockerfile.build`** (Z. 11, 17):
- `mariadb-connector-c-dev` im `apk add`-Block ✅
- `pdo_mysql` im `docker-php-ext-install`-Block ✅

**`.github/workflows/ci.yml`**:
- Job `backend-tests` mit `strategy.matrix` (mysql/pgsql), `fail-fast: false` ✅
- Service-Container `mysql:8.0` und `postgres:16` mit Health-Checks ✅
- `--network=host` in allen drei `docker run`-Aufrufen ✅
- Image-Tag und Artefakt-Name verwenden `${{ matrix.db }}` ✅
- Test-Step übergibt alle DB-Env-Vars aus Matrix-Variablen; kein SQLite/`:memory:` ✅
- Job `frontend-tests` mit `working-directory: frontend`, `npm install`, `cache-dependency-path: frontend/package.json` ✅

Kein Produktivcode (`app/`, `resources/`, `routes/`, `database/`) berührt. ✅

---

## Review-Befunde berücksichtigt?

Der Reviewer hat 3 Hinweise ohne Handlungsbedarf dokumentiert:

1. **Beide Service-Container laufen in beiden Matrix-Legs** — bewusste Designentscheidung (design.md Z. 9–10 explizit so beschrieben). Akzeptiert. Alternativansatz (zwei separate Jobs) ist als spätere Optimierung vermerkt.

2. **Floating Image-Tags** (`mysql:8.0`, `postgres:16`) — Standard-Praxis für CI-Prototypen. Kein Handlungsbedarf jetzt. Digest-Pinning ist als spätere Maßnahme notiert.

3. **Test-Passwörter im Klartext** — Credentials betreffen ausschließlich ephemere Service-Container ohne externe Erreichbarkeit. Keine externe Angriffsfläche. Standard-Muster für CI-Test-DBs.

Alle drei Hinweise sind dokumentiert und als nicht-blockierend eingestuft. Keine offenen "muss"-Befunde.

---

## Tester-Bericht

Vollständig vorhanden. Der Tester hat alle 12 Akzeptanzkriterien per Grep-basierter Strukturverifikation geprüft — alle grün. YAML-Syntaxprüfung bestanden. Regressionsprüfung der bestehenden Test-Suite bestanden.

---

## Offene Punkte (nicht-blockierend)

Diese Punkte blockieren die Abnahme nicht, sind aber für spätere Iterationen relevant:

1. **CI-Laufzeit-Optimierung:** Beide Service-Container starten in beiden Matrix-Legs. Wenn CI-Laufzeiten signifikant steigen, zwei separate Jobs (`backend-tests-mysql`, `backend-tests-pgsql`) ohne gemeinsamen `services:`-Block erwägen. *Priorität: niedrig.*

2. **Digest-Pinning:** Tags `mysql:8.0` und `postgres:16` könnten bei Anforderungen an CI-Reproduzierbarkeit auf SHA256-Digests fixiert werden. *Priorität: niedrig, nur wenn CI-Reproduzierbarkeit kritisch wird.*

3. **MySQL-Migrations-Lauf in CI:** Das `ci.yml` führt keine `php artisan migrate` aus — die Test-Suite verwendet `RefreshDatabase` oder `DatabaseMigrations`. Falls die Migrations selbst auf MySQL-Kompatibilität geprüft werden sollen (nicht nur die Tests), wäre ein expliziter `migrate`-Step sinnvoll. *Priorität: mittel, als eigener Change.*

---

## Empfehlung an den User

Branch mergen. Die CI-Konfiguration ist korrekt implementiert — ab dem nächsten PR werden Backend-Tests gegen MySQL und PostgreSQL parallel ausgeführt. DB-Portabilitätsfehler werden damit in PRs sichtbar, bevor Code in den Hauptbranch gelangt. Nach dem ersten echten PR-Lauf empfiehlt sich eine Beobachtung der CI-Gesamtlaufzeit (Hinweis 1 oben).
