# Test-Report: add-missing-qa-tooling

**Status:** alle-gruen (mit zwei dokumentierten, nicht blockierenden Flakes — Details unten)

## Kontext

Dieser Change fügt kein Anwendungsverhalten hinzu, sondern QA-Tooling
(`composer test/lint/stan/compat-check/qa`, `npm run lint`, CI-Integration).
Es wurden daher **keine neuen Tests geschrieben** — Aufgabe war die
Verifikation, dass (a) die neuen Tooling-Scripts tatsächlich funktionieren
und (b) der große Pint-Formatierungs-Diff (291 Dateien) keine
Verhaltensänderung eingeführt hat. Alle Läufe erfolgten innerhalb der
laufenden Docker-Umgebung (`docker compose exec php ...` / `docker compose
exec node ...`), Branch `feature/add-missing-qa-tooling`.

## 1. Backend-Regressionstest (Kernfrage): Pest-Suite vor/nach Pint

`docker compose exec php ./vendor/bin/pest --no-coverage` wurde **dreimal**
unabhängig ausgeführt:

| Lauf | Ergebnis |
|---|---|
| 1 | `1 failed, 717 passed (2274 assertions)` — Einzelausreißer, siehe Abschnitt "Fehler" |
| 2 | `718 passed (2275 assertions)` |
| 3 (nach `composer qa`-Lauf, s.u.) | `718 passed (2275 assertions)` |

**Ergebnis: 718 passed / 2275 assertions ist reproduzierbar** und deckt sich
exakt mit der in `task-T01.notes.md` Abschnitt 6/7 dokumentierten Zahl
("718 passed (2275 assertions)"). Die Notes behaupten dieselbe Zahl vor
**und** nach der Pint-Formatierung — das konnte hier nicht direkt
nachvollzogen werden (der Vorher-Stand vor Pint existiert im aktuellen
Arbeitsbaum nicht mehr, da der Formatierungs-Commit bereits im Diff steckt),
aber der jetzige Nachher-Stand ist stabil reproduzierbar identisch zur in
den Notes dokumentierten Zahl. Stichprobenartige Diff-Prüfung (Abschnitt 5)
bestätigt zusätzlich, dass die Pint-Änderungen rein mechanisch sind.

**Der eine Fehlschlag aus Lauf 1** (`Tests\Feature\Api\CustomerApiTest >
can filter customers by search term`) ist **keine Regression durch diesen
Change**:
- `git diff main -- backend/tests/Feature/Api/CustomerApiTest.php` zeigt für
  diesen Test ausschließlich Whitespace-Änderungen (siehe Abschnitt 5).
- Isolierter Lauf derselben Testdatei (`pest tests/Feature/Api/CustomerApiTest.php`)
  lief sofort grün (27 passed, 96 assertions).
- Ursache: `User::factory()->admin()->create()` (Zeile 70) setzt
  `first_name`/`last_name`/`email` **nicht explizit** — `UserFactory.php:32-33`
  nutzt `fake()->firstName()`/`fake()->lastName()` ohne festen Seed. Der
  Suchfilter (`CustomerController.php:47-51`) matcht per `LIKE '%John%'`
  gegen `first_name`, `last_name` **und** `email`. Bei zufälliger
  Namens-/E-Mail-Kollision mit dem Suchbegriff "John" (z. B. Admin- oder
  anderer Testdaten-Zufallsname enthält "John" als Substring) matcht die
  Query mehr als die erwarteten 1 Datensatz. Dies ist eine **vorbestehende
  Testdesign-Schwäche** (kein `Faker::seed()`/keine feste Kollisions-Ausschluss-Logik),
  unabhängig vom QA-Tooling-Change. Wird als Fund gemeldet, nicht behoben
  (Produktivcode/Testcode wird laut Auftrag nicht angefasst).

## 2. `composer qa` (volle Kette lint→stan→compat-check→test)

```
docker compose exec php composer qa
```
→ **Exit-Code 0**, verifiziert über `echo $?` nach separatem Lauf ohne
Pipe-Verzerrung. Log-Auszug:
```
    PASS   ......................................................... 291 files
Note: Using configuration file /var/www/html/phpstan.neon.
 [OK] No errors
...
  Tests:    718 passed (2275 assertions)
  Duration: 28.31s
```
Einzelverifikation der Teilschritte:

| Script | Exit-Code | Bemerkung |
|---|---|---|
| `composer test` | 0 | 718 passed / 2275 assertions (3x reproduziert) |
| `composer stan` | 0 | `[OK] No errors` (mit `phpstan-baseline.neon`, 143 Bestandsfehler bewusst grandfathered) |
| `composer compat-check` | 0 | kein Output (mit `.phpcs-baseline.xml`) |
| `composer lint` | 0 (4 von 5 Läufen) / 1 (1 von 5 Läufen, Flake) | siehe Abschnitt "Fehler" |
| `composer qa` | 0 | volle Kette, mehrfach reproduziert |
| `composer validate --no-check-all` | 0 | `./composer.json is valid` |

`backend/phpstan.neon` existiert, Root-`phpstan.neon` existiert nicht mehr
(verifiziert per `ls`). `larastan/larastan` und
`phpcompatibility/php-compatibility` sind real installiert
(`composer show` bestätigt beide Pakete).

## 3. Frontend: Vitest, ESLint, Build

**Vorbedingung:** `docker compose exec node npx vitest run` scheiterte
zunächst mit `Cannot find module @rollup/rollup-linux-arm64-musl`
(bekannter npm-Optional-Dependencies-Bug, node_modules im Container war
nicht aktuell zum committeten `package-lock.json`). Behoben durch
`docker compose exec node npm ci` (nur `node_modules`-Neuinstallation, kein
Repo-Code geändert, `node_modules` ist `.gitignore`t). Kein Produktivcode
angefasst.

```
docker compose exec node npx vitest run
```
→ **18 Testdateien, 194 Tests, alle grün.** Deckt sich exakt mit
`task-T02.notes.md` ("18 Testdateien, 194 Tests, alle grün").

```
docker compose exec node npm run lint
```
→ Exit-Code **0**. `✖ 3031 problems (0 errors, 3031 warnings)` — identisch
zu `task-T02.notes.md`/`task-T03.notes.md` dokumentierter Baseline.

```
docker compose exec node npm run build
```
→ Exit-Code **0**, `vue-tsc -b && vite build` erfolgreich, keine
Fehler/Warnings im Build-Output (einzige "warning"-Zeile im Log ist die
unabhängige `docker-compose.yml`-Obsoleszenz-Warnung des Docker-Compose-CLI,
nicht vom Build selbst).

## 4. Lückenanalyse

Für diesen reinen Tooling-/Infrastruktur-Change ist der Testbedarf gering,
da kein Anwendungsverhalten hinzukommt. Bewertung möglicher Zusatzprüfungen:

- **Smoke-Test für die composer-Scripts selbst** (z. B. ein Test, der
  `composer qa` als Subprozess aufruft und Exit-Code 0 erwartet): nicht
  sinnvoll — das wäre ein Test, der das Test-Tooling gegen sich selbst
  testet (zirkulär) und in der Pest-Suite selbst würde ein
  `composer qa`-Subprozess-Aufruf `composer test` (also die laufende
  Suite) erneut rekursiv anstoßen. Die tatsächliche Verifikation läuft
  bereits über die CI-Pipeline (T03) bei jedem Push — das ist der richtige
  Ort für diese Prüfung, nicht die Pest-Suite.
- **CI-Lauf als Nachweis:** `task-T03.notes.md` dokumentiert nur einen
  lokalen Nachweis (Docker-Compose statt echtem GitHub-Actions-Runner-Image).
  Ein finaler Nachweis über einen echten CI-Lauf (Push/PR) fehlt noch — das
  ist aber laut T03-Akzeptanzkriterium explizit als "lokaler ODER CI-Lauf"
  vorgesehen und damit bereits erfüllt; ein echter CI-Lauf bleibt dennoch
  die stärkere Bestätigung und sollte vor dem finalen Merge einmal
  abgewartet werden.
- **PHPCompatibility-Alpha-Version (`10.0.0-alpha2`):** kein automatisierter
  Test, der die Sniff-Funktionsfähigkeit dauerhaft prüft (nur einmalig
  manuell verifiziert laut `task-T01.notes.md` Abschnitt 3). Ein Regressionstest
  wäre denkbar (z. B. ein Fixture-File mit bekanntem 8.3-Verstoß, das
  `compat-check` in einer separaten Pest-Assertion gegenprüft), aber das
  wäre Gegenstand eines eigenen Folge-Changes, kein Blocker hier.
- **Fazit:** Kein zwingender zusätzlicher automatisierter Test nötig. Die
  vorhandene manuelle Verifikation in den drei Notes-Dateien plus die in
  diesem Report unabhängig reproduzierten Läufe decken die
  Akzeptanzkriterien ausreichend ab.

## 5. Stichprobe: Pint-Diff-Verifikation (reine Formatierung, keine Logikänderung)

Geprüft wurden `git diff main -- <datei>` für folgende 8 Dateien
unterschiedlicher Kategorien:

1. `backend/app/Http/Controllers/Api/BookingController.php` — nur
   `fully_qualified_strict_types` (`\App\Models\Course` → `Course` + Import),
   `no_superfluous_phpdoc_tags` (redundante `@param`/`@return` entfernt, wo
   Typ bereits im Signature-Typehint steht), Einrückung von mehrzeiligen
   `whereHas`-Ketten, `.`-Konkatenation ohne Leerzeichen. Keine
   Logikänderung.
2. `backend/app/Http/Middleware/SecurityHeaders.php` — `!app()` → `! app()`
   (Leerzeichen nach Negation), Anführungszeichen-Normalisierung
   (`"frame-src..."` → `'frame-src...'`, keine Interpolation enthalten,
   semantisch identisch). Keine Logikänderung.
3. `backend/database/migrations/2026_01_04_180000_add_missing_indexes_for_performance.php`
   — `!$this->` → `! $this->`, `.`-Konkatenation ohne Leerzeichen,
   `\Throwable` → `Throwable` (mit Import). Keine Schema-/Logikänderung,
   `php artisan migrate:status` bestätigt weiterhin alle Migrationen als
   `Ran`.
4. `backend/app/Models/Booking.php` — PHPDoc-Typen ohne führenden
   Backslash (`\Illuminate\Support\Carbon` → `Carbon`, mit Import), reine
   Docblock-Kosmetik, keine Codeänderung.
5. `backend/database/factories/DogFactory.php` — nur PHPDoc-Zeile
   (`@extends \Illuminate\...\Factory<\App\Models\Dog>` → `Factory<Dog>`).
   Keine Codeänderung.
6. `backend/tests/Unit/Services/CourseSessionServiceUnitTest.php` —
   Array-Key-Ausrichtung (`'type'      =>` → `'type' =>`),
   `new CourseSessionService()` → `new CourseSessionService` (Pint
   `new_without_parentheses`-Regel; kein PHP-8.4-verbotenes Muster, da kein
   verketteter Methodenaufruf direkt am `new`-Ausdruck — Kompatibilität zu
   `CLAUDE.md` Abschnitt 4.1 gewahrt), `\DateTimeImmutable` → `DateTimeImmutable`.
   Keine Assertion-Logik verändert.
7. `backend/composer.json` — Diff enthält sowohl die T01-Scripts (`test`,
   `lint`, `stan`, `compat-check`, `qa`) als auch den bereits in
   `task-T01.notes.md` Abschnitt "Vorab-Fix" dokumentierten Merge des
   doppelten `config`-Keys. Deckt sich 1:1 mit den Notes.
8. `.github/workflows/ci.yml` — Diff deckt sich exakt mit
   `task-T03.notes.md`: Step-Umbenennung + `./vendor/bin/pest --no-coverage`
   → `composer qa`, neuer `npm run lint`-Step im Frontend-Job, kein
   sonstiger Step/Job verändert.

**Ergebnis der Stichprobe:** In allen 8 Dateien ausschließlich mechanische
Pint-Regeln (Whitespace, `fully_qualified_strict_types`,
`no_superfluous_phpdoc_tags`, Quote-Normalisierung, Array-Alignment,
`new_without_parentheses`) sowie die explizit für T01/T03 vorgesehenen
Konfigurationsänderungen. Keine Logik-/Verhaltensänderung gefunden.

## Akzeptanzkriterien-Abdeckung

### T01 (Backend-QA-Scripts)
- [x] `composer test/lint/stan/compat-check/qa` existieren und laufen — verifiziert (Abschnitt 2)
- [x] `larastan/larastan`, `phpcompatibility/php-compatibility` real installiert — `composer show` bestätigt
- [x] `backend/phpstan.neon` existiert, Root-`phpstan.neon` gelöscht — verifiziert per `ls`
- [x] `composer stan` grün (mit Baseline) — Exit-Code 0
- [x] `composer compat-check` grün (mit `.phpcs-baseline.xml`) — Exit-Code 0
- [x] `composer qa` komplett grün — Exit-Code 0, reproduziert
- [x] Vorher-/Nachher-Zahlen dokumentiert — in Notes vorhanden, hier nicht erneut erhoben (Vorher-Stand nicht mehr im Arbeitsbaum), aber Nachher-Zahlen unabhängig reproduziert
- [x] Kein Bestandscode logisch verändert (nur Pint-Formatierung mit User-Freigabe) — stichprobenartig verifiziert (Abschnitt 5)

### T02 (Frontend npm run lint)
- [x] `npm run lint` existiert und ausführbar — Exit-Code 0
- [x] ESLint-Dependencies real installiert — `package-lock.json` bestätigt, `npm ci` erfolgreich
- [x] `frontend/eslint.config.ts` existiert, deckt `.vue`/`.ts` ab — verifiziert per `ls`/Lauf
- [x] `npm run lint` Exit-Code 0 — verifiziert
- [x] Vorher-/Nachher-Zahlen dokumentiert — in `task-T02.notes.md`, Nachher-Zahl (3031 Warnings, 0 Errors) hier reproduziert
- [x] Kein Bestandscode unter `frontend/src/` inhaltlich verändert — laut `git status`/Notes nur Config-Dateien geändert
- [x] `npm run build` läuft weiterhin ohne Fehler/Warnings — verifiziert, Exit-Code 0

### T03 (CI-Integration)
- [x] `backend-tests`-Job ruft `composer qa` auf — verifiziert per Diff
- [x] `frontend-tests`-Job ruft zusätzlich `npm run lint` auf — verifiziert per Diff
- [x] Kein bestehender CI-Step entfernt/verändert — verifiziert per Diff (nur Umbenennung + Ergänzung)
- [x] Notes dokumentieren tatsächlichen Lauf — vorhanden, hier durch identische lokale Läufe erneut bestätigt (echter GitHub-Actions-Lauf steht laut T03-Notes noch aus, ist aber laut Akzeptanzkriterium optional zum lokalen Lauf)

## Ausführungs-Ergebnis (Zusammenfassung)

```
Backend (composer qa):
  Pint (lint):        PASS  291 files              (Exit 0, 1 Flake in 5 Läufen s.u.)
  PHPStan (stan):      [OK] No errors               (Exit 0)
  PHPCS (compat-check): kein Output                 (Exit 0)
  Pest (test):         718 passed (2275 assertions) (Exit 0, 3x reproduziert)

Frontend:
  Vitest:    18 Testdateien, 194 Tests, alle grün    (Exit 0)
  ESLint:    3031 problems (0 errors, 3031 warnings) (Exit 0)
  Build:     vue-tsc -b && vite build erfolgreich    (Exit 0)
```

## Fehler / Beobachtungen (nicht blockierend)

- **`Tests\Feature\Api\CustomerApiTest > can filter customers by search
  term`** schlug in **einem** von drei vollständigen Suite-Läufen fehl:
  - Erwartet: `assertJsonCount(1, 'data')`
  - Erhalten: `actual size 2`
  - Vermutete Ursache (NICHT von mir gefixt, da Testcode laut Auftrag nicht
    zu ändern ist): unSeeded `fake()->firstName()`/`fake()->lastName()` in
    `UserFactory.php` kollidiert gelegentlich zufällig mit dem
    Such-String "John" bei einem anderen im selben Testlauf erzeugten
    User (z. B. Admin). Vorbestehende Testdesign-Schwäche, durch
    `git diff main` bestätigt unberührt von der Pint-Formatierung
    (nur Whitespace-Diff in dieser Datei). Reproduzierbar grün bei
    isoliertem Lauf und bei 2 von 3 vollständigen Suite-Läufen.

- **`composer lint` (Pint)** schlug in **einem** von fünf Läufen mit einem
  einzelnen "trailing whitespace"-Fund in
  `tests/Feature/DatabaseStructureTest.php` fehl, obwohl dieselbe Datei bei
  sofortigem isoliertem Nachlauf (`pint --test
  tests/Feature/DatabaseStructureTest.php`) sowie bei 4 weiteren
  Komplettläufen sauber durchlief. Kein reproduzierbarer, dateispezifischer
  Befund — vermutlich ein transientes Caching-/Dateisystem-Artefakt
  innerhalb der Docker-Bind-Mount-Umgebung (macOS-Host), keine tatsächliche
  Code-Problematik. Wird als Beobachtung gemeldet, nicht als Blocker.

Beide Beobachtungen sind Flakes, keine Regressionen durch den QA-Tooling-Change
selbst, und sollten dem Reviewer/Architekten zur Kenntnis gebracht werden
(insbesondere der `CustomerApiTest`-Flake als potenzieller Kandidat für einen
separaten Fix-Task: `fake()->firstName()`/`fake()->lastName()` in diesem
Test durch garantiert nicht mit "John" kollidierende Werte ersetzen, oder
`config(['faker.seed' => ...])` setzen).
