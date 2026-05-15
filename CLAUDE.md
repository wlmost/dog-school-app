# Projekt: dog-school-app

Webanwendung für Hundeschulen. Laravel-Backend + Vue.js-Frontend, deployed
auf Shared Hosting bei verschiedenen Webhostern.

> Diese Datei wird von ALLEN Agenten als Erstes gelesen.
> Sie ist die einzige Quelle für projekt-spezifische Regeln.

---

## 1. Stack

- **Backend:** PHP 8.4 (Entwicklung) / **PHP 8.2 als Mindest-Ziel** (siehe Abschnitt 3)
- **Framework:** Laravel
- **DB-Zugriff:** Eloquent
- **Migrationen:** Laravel Migrations (`database/migrations/`, gesteuert über `php artisan migrate`)
- **Frontend:** Vue.js 3, Composition API mit `<script setup>`
- **Build-Tool Frontend:** Vite (Laravel-Standard via `laravel-vite-plugin`)
- **Tests Backend:** PHPUnit + Pest (Pest-Engine läuft auch klassische PHPUnit-Klassen)
- **Tests Frontend:** Vitest
- **CI:** GitHub Actions

## 2. Verfügbare Entwickler-Agenten

- `dev-php` — für alles unter `app/`, `routes/`, `database/`, `config/`, `tests/Feature/`, `tests/Unit/`, sowie Blade-Templates (`resources/views/`)
- `dev-javascript` — für Vue-SFCs (`resources/js/**/*.vue`), sonstiges JS unter `resources/js/`, Vitest-Tests

> **Hinweis für den Architekten:** Blade-Templates (`.blade.php`) gehören zu
> `dev-php`, weil sie PHP-Logik enthalten. Vue-SFCs gehören zu `dev-javascript`.
> Wenn ein Feature beides braucht (Blade rendert die Seitenhülle, Vue mounted
> als Insel-Komponente darin), zwei separate Tasks in `tasks.md` anlegen
> — eine pro Sprache, mit klarem Übergabepunkt (z. B. via Data-Attribut oder
> `window.__INITIAL_STATE__`).

**Nicht in diesem Projekt verwenden:** `dev-go`, `dev-typescript`.

---

## 3. Umgebungs-Matrix — KRITISCH

Diese Tabelle ist die wichtigste Regel des Projekts. Jeder geschriebene Code
muss in **allen drei Spalten** funktionieren.

| Aspekt        | Entwicklung (lokal Docker) | Demo (Shared Hosting)        | Produktion (Shared Hosting) |
|---------------|----------------------------|------------------------------|-----------------------------|
| PHP-Version   | 8.4                        | **min. 8.2, max. 8.3**       | 8.4                         |
| Datenbank     | PostgreSQL                 | **MySQL**                    | **MySQL**                   |
| Dateisystem   | Schreibbar                 | Eingeschränkt                | Eingeschränkt               |
| Shell-Zugriff | Voll                       | Kein `exec`/`shell_exec`     | Kein `exec`/`shell_exec`    |
| Cron          | Manuell                    | Webhoster-spezifisch         | Webhoster-spezifisch        |
| Composer      | Verfügbar                  | Nur Build-Artefakte          | Nur Build-Artefakte         |
| Node/npm      | Verfügbar                  | **Nicht verfügbar**          | **Nicht verfügbar**         |
| Queue-Worker  | Möglich (Docker)           | **Nicht als Daemon**         | **Nicht als Daemon**        |

> **Wichtig zur Demo-PHP-Version:** Verschiedene Shared-Hosting-Anbieter führen
> unterschiedliche PHP-Versionen. Da nicht jeder Anbieter zeitnah auf 8.3 oder
> 8.4 aktualisiert, ist der **kleinste gemeinsame Nenner PHP 8.2**.

**Konsequenz — kleinster gemeinsamer Nenner:**
**PHP 8.2, MySQL, kein Shell-Exec, kein Node zur Laufzeit, kein dauerhafter Queue-Worker, nur Build-Artefakte deployen.**

---

## 4. Kompatibilitäts-Regeln (für alle dev-Agenten verbindlich)

### 4.1 PHP-Sprachfeatures

> **Hinweis:** Da die Demo auf PHP 8.2 laufen kann, ist 8.2 der echte
> kleinste gemeinsame Nenner. Sowohl 8.3- als auch 8.4-Features sind im
> Anwendungscode verboten.

**Verboten — kam mit PHP 8.4:**

- Property Hooks (`public string $name { get => …; set => …; }`)
- Asymmetric Visibility (`public private(set) string $x`)
- `#[\Deprecated]`-Attribut
- `array_*`-Funktionen aus 8.4: `array_find`, `array_find_key`, `array_any`, `array_all`
- `new MyClass()->method()` ohne Klammern um den `new`-Ausdruck
- Lazy Objects API (`newLazyGhost`, `newLazyProxy`)

**Verboten — kam mit PHP 8.3:**

- Typed Class Constants (`class Foo { const string BAR = '...'; }`)
- `#[\Override]`-Attribut (gibt's in 8.2 nicht — Methoden ohne Marker schreiben)
- `json_validate()` (in 8.2 nicht verfügbar — stattdessen `json_decode()` mit `JSON_THROW_ON_ERROR` und try/catch)
- Dynamic Class Constant Fetch (`MyClass::{$name}`)
- Readonly Classes (Readonly *Properties* gibt's seit 8.1, aber ganze readonly Klassen erst ab 8.2 — die sind ok)
- `Randomizer::getBytesFromString()` und weitere `Random\Randomizer`-Methoden aus 8.3

**Erlaubt** (ab 8.2 verfügbar):

- Readonly Properties, Readonly Classes (klassen-Level seit 8.2)
- Enums, First-Class Callable Syntax
- DNF Types (`(A&B)|null`)
- `true`/`false`/`null` als eigenständige Return-Types
- Constants in Traits

**Prüfung:** Vor jedem Commit muss `composer compat-check` laufen — er ist
auf `testVersion 8.2` konfiguriert und meldet alle 8.3- und 8.4-Verstöße.

### 4.2 SQL / Datenbank-Portabilität

**Goldene Regel:** Eloquent bevorzugen, raw SQL vermeiden. Eloquent abstrahiert
die meisten DB-Unterschiede zwischen Postgres und MySQL automatisch. Wenn raw
SQL nötig ist, MUSS er auf beiden DBs laufen — sonst gehört er hinter eine
Repository-Methode mit `DB::connection()->getDriverName()`-Switch.

**Verboten in raw SQL / `DB::raw()` / `whereRaw()`:**

- Postgres-spezifische Typen: `JSONB`, `UUID`, `SERIAL`, `BIGSERIAL`, `TEXT[]`
- Postgres-Operatoren: `@>`, `<@`, `?`, `?|`, `?&`, `->`/`->>` für JSON-Pfade
- Postgres-Funktionen: `gen_random_uuid()`, `now() AT TIME ZONE …`
- MySQL-Backticks für Identifier in raw SQL — verwende Doppel-Anführungszeichen oder besser nur Eloquent
- `RETURNING` (Postgres-only) — MySQL kennt das nicht
- `ON CONFLICT … DO UPDATE` — stattdessen Eloquents `upsert()` verwenden

**Verboten in Migrations:**

- `$table->jsonb()` — gibt's nicht; verwende `$table->json()` (wird in MySQL als JSON, in Postgres als JSONB gemappt)
- Plattform-spezifische `DB::statement()`-Aufrufe ohne Driver-Check
- DB-spezifische Index-Typen (z. B. Postgres `GIN`/`GIST`) ohne Plattform-Switch

**Empfohlen in Migrations:**

- `$table->id()` für Primärschlüssel (Laravel mappt korrekt: BIGINT UNSIGNED AUTO_INCREMENT bzw. BIGSERIAL)
- `$table->uuid('id')->primary()` + `Str::uuid()` für UUIDs
- `$table->json('payload')` für JSON-Felder — Inhalte in PHP manipulieren, nicht via DB-JSON-Operatoren
- `$table->boolean(...)` — Laravel mappt zu TINYINT(1) bzw. BOOLEAN
- `$table->timestamps()` — plattformneutral
- In Modellen: `Model::query()->where(...)` statt `DB::select('SELECT ...')`

**Migrations-Test:** Die CI sollte Migrations gegen MySQL **und** Postgres
laufen lassen (Matrix-Pipeline). Falls noch nicht eingerichtet: das ist
ein eigener openspec-Change wert.

### 4.3 Laravel-Features mit Shared-Hosting-Risiko

**Verboten / mit Vorsicht:**

- **Queues mit Worker-Daemon:** `php artisan queue:work` braucht einen Long-Running-Prozess. Shared Hosting kann das nicht. → Verwende **`sync`-Driver für synchrone Ausführung** ODER **`database`-Driver mit `queue:work --stop-when-empty`, getriggert per Hoster-Cron** (alle 5–15 Minuten, je nach Hoster).
- **Scheduler:** `php artisan schedule:work` ist ein Long-Running-Prozess → nicht nutzbar. Stattdessen `php artisan schedule:run` per Hoster-Cron alle 1–5 Minuten (je nach Mindest-Cron-Intervall).
- **`exec()`/`shell_exec()`** in eigenen Commands — viele Shared Hostings deaktivieren das.
- **Laravel Octane** — braucht Swoole/RoadRunner, nicht möglich auf Shared Hosting.
- **WebSockets** (Laravel Reverb, Pusher-Self-Hosted) — Shared Hosting kann das nicht. Für Realtime → Polling oder SSE prüfen, ob der Hoster es erlaubt.
- **`storage:link`** funktioniert, aber prüfe, dass die Symlink-Strategie zum Doc-Root des Hosters passt.

**Erlaubt mit Bedacht:**

- Cache-Driver `file` oder `database` (kein `redis`/`memcached` ohne Hoster-Check — über `.env` konfigurierbar halten)
- Session-Driver `database` oder `cookie` (nicht `redis` als Standard annehmen)
- Mail über SMTP — sicherer als `mail()`-Function, die auf vielen Hostern blockiert ist

### 4.4 Frontend / Vue / Vite

- `npm run build` läuft in der **CI**, nicht auf dem Produktiv-Server
- Build-Output (`public/build/`) wird **deployed**, nicht zur Laufzeit erzeugt
- `@vite()`-Direktive in Blade liest aus `public/build/manifest.json` — diese Datei MUSS deployed werden
- Keine HMR-Annahmen im Produktivcode
- Asset-Pfade über Vites `base`-Option konfigurierbar halten, falls der Hoster den App in ein Sub-Verzeichnis legt

---

## 5. Build, Test, Lint

```bash
# Backend
composer install
composer test                   # PHPUnit
composer lint                   # PHP-CS-Fixer --dry-run (oder Pint)
composer stan                   # PHPStan / Larastan
composer compat-check           # PHPCompatibility-Sniffs gegen PHP 8.3

# Frontend
npm ci
npm run lint                    # ESLint
npm run test                    # Vitest
npm run build                   # Produktions-Build (in CI für Deployment)

# Volle Docker-Umgebung
docker compose up -d
docker compose exec app composer test
docker compose exec app php artisan migrate
docker compose exec app php artisan test    # alternativ zu composer test

# DB-Portabilitäts-Test (Migrationen gegen MySQL prüfen)
docker compose -f docker-compose.yml -f docker-compose.mysql.yml up -d
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan test
```

> **Hinweis:** Falls `composer compat-check` noch nicht existiert, in `composer.json` anlegen:
> ```json
> "scripts": {
>   "compat-check": "vendor/bin/phpcs --standard=PHPCompatibility --runtime-set testVersion 8.3 app/ database/ config/ routes/"
> }
> ```
> Voraussetzung: `phpcompatibility/php-compatibility` als Dev-Dependency.

## 6. Konventionen

- **Branch-Namen:** `change/<openspec-change-id>`
- **Commits:** Conventional Commits (`feat:`, `fix:`, `refactor:`, `chore:`, `docs:`)
- **PHP-Stil:** PSR-12, `declare(strict_types=1);` in jeder neuen Datei in `app/`
- **PHP-Fehlerbehandlung:** Eigene Exception-Klassen pro Domäne, keine generischen `\Exception`-Würfe in Anwendungscode
- **Eloquent-Konventionen:**
  - Model-Klassen in `app/Models/`, Singular (z. B. `Dog`, `Owner`, `Course`)
  - Beziehungen explizit typisiert: `public function owner(): BelongsTo`
  - Mass-Assignment-Schutz: `$fillable` oder `$guarded` immer setzen
  - Casts in `protected function casts(): array` (Laravel 11+) statt `$casts`-Property
- **Vue-Konventionen:**
  - SFCs in PascalCase-Dateinamen (`DogList.vue`)
  - Komponenten-Tags in Templates in PascalCase (`<DogList />`)
  - Composables in `resources/js/composables/`, Präfix `use` (`useDogList.js`)
- **Logging:** Über Laravels `Log`-Facade oder `Psr\Log\LoggerInterface`-Injection. Keine `error_log()`-Aufrufe in Anwendungscode.
- **Test-Konventionen:** **Verbindlich** in `TESTING.md` festgelegt. Der
  `tester`-Agent und der `reviewer`-Agent MÜSSEN diese Datei vor jeder
  Test-Arbeit gelesen haben. Kurz: Pest-Engine, Factory-States statt Magic
  Strings, API-Tests mit Groups, HTTP-Assertions Laravel-Style, Werte-Assertions
  Pest-`expect()`. Details siehe `TESTING.md`.
- **Konfiguration:** Über `.env` / `config/*.php`. Niemals hardcoded. Shared-Hosting-Hoster setzen Env-Variablen via Hoster-Panel oder per `.env`-Datei im Projekt-Root.

---

## 7. Workflow

Der vollständige Workflow ist projektübergreifend in `~/.claude/WORKFLOW.md`
definiert. Diese Datei muss von allen Agenten (insbesondere `triage`,
`architect`, `skeptic`, `dev-*`, `reviewer`, `tester`) **vor Arbeitsbeginn
gelesen werden**, falls sie noch nicht im Kontext ist.

**Kurz-Überblick (Details in `~/.claude/WORKFLOW.md`):**

1. `triage` → 2. `architect` (Mode A, openspec) → 3. `skeptic` → **User-Gate 1**
→ 5. Feature-Branch `feature/<change-id>` → 6. `dev-*` pro Task → 7. `reviewer` + `tester` parallel
→ 8. `architect` (Mode B) → **User-Gate 2** → 10. `openspec archive` → 11. PR.

### 7.1 Projekt-Pre-Flight (verbindlich für dieses Projekt)

Die globale `WORKFLOW.md` verweist an mehreren Stellen auf "projekt-spezifische
Pre-Flight-Checks". Für **dog-school-app** sind das:

**Nach jeder Task-Implementierung (Schritt 8 der globalen Workflow):**
Tests sind innerhalb der Docker-Umgebung auszuführen.

```bash
composer qa                   # lint + stan + compat-check + pest
# bei Frontend-Tasks zusätzlich:
npm run lint
npm run test
npm run build                 # nur als Lauffähigkeits-Check, kein Commit von dist/
```

**Vor User-Gate 2 (Schritt 12 der globalen Workflow):**

```bash
composer qa
npm run test
git diff main...feature/<change-id>          # Full-Diff zur Sichtung
```

**Vor `git push` / PR (Schritt 14 der globalen Workflow):**

```bash
# Sicherstellen, dass auf beiden DBs lokal getestet wurde:
docker compose -f docker-compose.yml -f docker-compose.mysql.yml up -d
docker compose exec app php artisan migrate:fresh
docker compose exec app composer test
```

> Die CI-Matrix übernimmt das nach dem Push noch einmal automatisch
> (siehe `add-db-matrix-ci`). Der lokale Lauf ist Vorab-Versicherung, kein Ersatz.

### 7.2 Verfügbare dev-Agenten (für tasks.md)

- `dev-php` — für alles unter `app/`, `routes/`, `database/`, `config/`, `tests/Feature/`, `tests/Unit/`, sowie Blade-Templates
- `dev-javascript` — für Vue-SFCs (`resources/js/**/*.vue`) und JS unter `resources/js/`

**Nicht verfügbar in diesem Projekt:** `dev-go`, `dev-typescript`.

### Projektspezifische Workflow-Regeln

- **DB-bezogene Tasks:** Der Architekt markiert sie explizit und führt im
  `design.md` auf, ob die Migration MySQL- und Postgres-kompatibel ist.
  Eloquent-only-Änderungen sind unkritisch; raw SQL, Migrations und
  Plattform-Funktionen sind kritisch.
- **PHP-Kompatibilität:** Der Reviewer prüft jeden PHP-Diff explizit gegen
  die Liste verbotener 8.4-Features (Abschnitt 4.1).
- **Frontend-Tasks:** Der Tester führt Vitest-Tests aus UND prüft, dass
  `npm run build` ohne Warnings durchläuft. Die Build-Lauffähigkeit ist
  Akzeptanzkriterium.
- **Queue-/Scheduler-Tasks:** Architekt prüft, dass keine Worker-Daemon-Annahme
  gemacht wird. Lösungen müssen über Cron + `--stop-when-empty` funktionieren.
- **Migrations:** Wenn der Reviewer eine neue Migration sieht, prüft er
  zusätzlich, dass keine Postgres- oder MySQL-spezifischen Konstrukte
  drinstecken (Abschnitt 4.2).

---

## 8. Verzeichnisstruktur openspec

```
openspec/
  triage/<ts>-<kurzname>.md
  changes/<change-id>/
    proposal.md
    design.md
    tasks.md
    verification.md             (Skeptiker)
    task-T01.notes.md           (Entwickler)
    task-T01.review.md          (Reviewer)
    task-T01.test-report.md     (Tester)
    acceptance.md               (Architekt am Ende)
    specs/<capability>/spec.md
```

---

## 9. Anti-Halluzinations-Regeln (alle Agenten)

1. Behauptungen über Code mit Datei:Zeile belegen — nichts aus dem Gedächtnis.
2. Was nicht im Repo / in `composer.lock` / in `package-lock.json` steht, existiert nicht.
3. Bei Unsicherheit: in Notes dokumentieren, nicht erfinden.
4. Spec = Wahrheit für "was soll gebaut werden". Code = Wahrheit für "was existiert".
5. **Projektspezifisch:** Vor jeder SQL-Query, jeder Migration und jeder
   PHP-Syntaxentscheidung mental gegen Abschnitt 4 prüfen. Im Zweifel:
   8.2-konform, DB-agnostisch, Shared-Hosting-tauglich.
6. **Laravel-spezifisch:** Lies die `laravel/framework`-Version aus `composer.lock`,
   bevor du Framework-APIs nutzt. Laravel 10, 11 und 12 unterscheiden sich
   in Konfiguration (`config/app.php` vs. `bootstrap/app.php`), in den
   Cast-Definitionen und in Skeleton-Strukturen.
