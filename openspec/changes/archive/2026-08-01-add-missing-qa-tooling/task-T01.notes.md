# Task T01 — Notes (dev-php)

## Umgebung
Alle Befehle unten wurden **innerhalb der Docker-Umgebung** ausgeführt
(`docker compose exec php ...`), Arbeitsverzeichnis `/var/www/html` (=
`backend/`), PHP 8.4.21, Composer 2.9.8.

## Vorab-Fix: doppelter `config`-Key in `backend/composer.json`

`composer validate --no-check-all` meldete beim ersten Check:
`Key config is a duplicate in ./composer.json at line 82` (siehe
`verification.md`, Abschnitt "Sonstige Beobachtung"). Beide `config`-Blöcke
(`platform.php: 8.3.0` und `optimize-autoloader`/`preferred-install`/
`sort-packages`/`allow-plugins`) wurden zu **einem** `config`-Objekt
zusammengeführt, bevor irgendetwas anderes geändert wurde — sonst hätte
jeder nachfolgende `composer require`-Aufruf den zweiten `config`-Block
(inkl. `platform.php`) stillschweigend überschrieben. Nach dem Merge:
`composer validate` meldet keine Duplicate-Key-Warnung mehr.

## 1. Neue Dev-Dependencies

Installiert via `composer require --dev ... --ignore-platform-reqs`
(Begründung für `--ignore-platform-reqs` s. u.):

| Paket | Installierte Version | Nachweis |
|---|---|---|
| `larastan/larastan` | **v3.10.0** | `composer show larastan/larastan` |
| `phpstan/phpstan` (transitiv, von Larastan) | 2.2.6 | `composer show phpstan/phpstan` |
| `phpcompatibility/php-compatibility` | **10.0.0-alpha2** (siehe Abschnitt 3, Abweichung von design.md) | `composer show phpcompatibility/php-compatibility` |
| `squizlabs/php_codesniffer` (transitiv) | 3.13.5 | `composer show squizlabs/php_codesniffer` |
| `dealerdirect/phpcodesniffer-composer-installer` | v1.2.1 | `composer show dealerdirect/...` |
| `phpcsstandards/phpcsutils` (transitiv, von PHPCompatibility 10.x) | 1.2.3 | `composer show phpcsstandards/phpcsutils` |

Alle Einträge sind unter `require-dev` in `backend/composer.json` und im
`packages-dev`-Block von `backend/composer.lock` real vorhanden (kein
Constraint-Only-Treffer wie vor diesem Task).

`config.allow-plugins` bekam den Eintrag
`"dealerdirect/phpcodesniffer-composer-installer": true`, sonst bricht
`composer require` mit "contains a Composer plugin which is blocked by
your allow-plugins config" ab.

**Warum `--ignore-platform-reqs` beim Require nötig war (nicht
Scope-Erweiterung, sondern Umgehung eines vorbestehenden, unabhängigen
Lock-Problems):** `backend/composer.json` setzt `config.platform.php:
8.3.0`, um die Dependency-Resolution auf das Shared-Hosting-Ziel (max. PHP
8.3, CLAUDE.md Abschnitt 3) zu begrenzen. Der **bereits vor diesem Task
bestehende** `composer.lock` enthält jedoch `symfony/yaml` in Version
`v8.0.8`, die `"php": ">=8.4"` verlangt (transitiv über `laravel/sail`).
Ein normaler `composer require --dev larastan/larastan` löste deshalb
sofort einen Resolution-Konflikt aus ("laravel/sail is locked ... requires
symfony/yaml ... does not satisfy php platform override 8.3.0"), **ohne
dass larastan/larastan selbst daran beteiligt war** — reproduzierbar auch
mit einem leeren `require --dev` eines beliebigen anderen Pakets. Das ist
eine vorbestehende Inkonsistenz zwischen `config.platform.php` und dem
bereits gelockten `symfony/yaml`, nicht Teil dieses Tasks (nicht T01s
Scope, `symfony/yaml` wird hier nicht angefasst). `composer install`
(ohne Resolution) läuft davon unberührt weiterhin fehlerfrei durch —
verifiziert vor der Änderung. Um die Task-Dependencies trotzdem
installieren zu können, ohne diese vorbestehende Inkonsistenz zu
"reparieren" (das wäre Scope-Erweiterung), wurde `--ignore-platform-reqs`
ausschließlich für die `composer require`-Aufrufe dieses Tasks verwendet.
Das reine Vorhandensein dieses vorbestehenden Konflikts wird hiermit an
den nächsten Workflow-Schritt (Reviewer/Architekt) gemeldet, da er
außerhalb des Scopes von T01 liegt, aber potenziell zukünftige
`composer require`-Aufrufe im Projekt betrifft.

## 2. `phpstan.neon` → `backend/phpstan.neon`

- `phpstan.neon` (Repo-Root) wurde gelöscht.
- `backend/phpstan.neon` wurde neu angelegt mit denselben Pfadangaben
  (`app`, `database`, `config`, `routes`) wie zuvor am Root, jetzt korrekt
  relativ zu `backend/` aufgelöst (siehe `design.md` Decision 1).
- `excludePaths`-Eintrag `app/Console/Kernel.php` wurde entfernt (Datei
  existiert im Laravel-11-Skeleton dieses Projekts nicht, verifiziert:
  `backend/app/Console/` enthält nur `Commands/`).
- `database/migrations/*` bleibt als Exclude bestehen.
- `includes:` enthält jetzt zusätzlich `phpstan-baseline.neon` (siehe
  Abschnitt 4).

`vendor/bin/phpstan analyse` (ohne `--configuration`) findet
`backend/phpstan.neon` automatisch, verifiziert über die Log-Zeile "Note:
Using configuration file /var/www/html/phpstan.neon."

## 3. PHPCompatibility-Version: Abweichung von design.md (`^9.3` → `10.0.0-alpha2`)

**Wichtiger, im Design nicht vorhergesehener Befund:** `design.md`
Decision 3b ging von "aktuelle stabile Version" von
`phpcompatibility/php-compatibility` aus. Die tatsächlich aktuellste
*stabile* Version ist **9.3.5, veröffentlicht 2019-12-27** (`composer show
phpcompatibility/php-compatibility` → `released: 2019-12-27, 6 years
ago`). Verifiziert über den Vendor-Code selbst: `grep -rn "8\.3\|8\.4"
vendor/phpcompatibility/php-compatibility/PHPCompatibility/Sniffs/`
liefert **keinen einzigen Treffer** — Version 9.3.5 enthält keinerlei
Sniffs für PHP-8.x-Änderungen. Das eigene `README.md` des Pakets
bestätigt das: "Tested on PHP 5.3 | 5.4 | 5.5 | 5.6 | 7.0 | 7.1 | 7.2 |
7.3 | 7.4". Mit Version 9.3.5 hätte `composer compat-check` also **keinen
einzigen** PHP-8.3/8.4-Verstoß erkennen können — das widerspricht direkt
dem Spec-Requirement "composer compat-check erkennt neu eingeführte
PHP-8.3/8.4-Syntax" (`specs/qa-tooling/spec.md`, Scenario "composer
compat-check erkennt neu eingeführte PHP-8.3/8.4-Syntax").

**Lösung:** `composer show phpcompatibility/php-compatibility --all`
zeigt eine getaggte (nicht `dev-*`) Vorabversion `10.0.0-alpha2`. Mit
einem Testfile wurde verifiziert, dass diese Version tatsächlich
PHP-8.3-Verstöße erkennt:
```php
class Probe { const string BAR = 'baz'; }
```
→ `ERROR | Typed constants are not supported in PHP 8.2 or earlier.`
und
```php
$x = new Probe()->test();
```
→ `ERROR | Class member access on object instantiation, without
parentheses around the new expression, was not supported in PHP 8.3 or
earlier`.

Da Composer bei einem **exakten** Versions-Pin (`"10.0.0-alpha2"`, kein
Range) automatisch installiert, obwohl `minimum-stability: stable` gesetzt
ist (Stability-Filter gilt nur für Range-Constraints, nicht für exakte
Versionsangaben), war keine Änderung an `minimum-stability` nötig.
`composer validate` bleibt grün mit dieser exakten Pin-Version.

`backend/composer.json` pinnt daher bewusst
`"phpcompatibility/php-compatibility": "10.0.0-alpha2"` statt `^9.3`. Der
Grund ist im Kommentar-Block von `backend/.phpcs-baseline.xml`
dokumentiert. **Diese Abweichung von `design.md` Decision 3b wird hiermit
explizit an Reviewer/Architekt gemeldet** — sie war nötig, damit
`compat-check` überhaupt seinen in `spec.md` beschriebenen Zweck erfüllt,
ist aber eine Vorabversion (Alpha), kein offizieller Stable-Release.
Risiko: Sollte PHPCompatibility jemals eine stabile 10.x-Version
veröffentlichen, sollte darauf migriert werden (Folge-Change).

## 4. Verbindlicher Prüfschritt: `composer stan` (PHPStan/Larastan)

**Vorher (ungefiltert, ohne Baseline):**
```
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```
→ `[ERROR] Found 143 errors` in 63 Dateien (gezählt über eindeutige
`Line   <Datei>`-Blöcke im Report).

**Baseline generiert:**
```
vendor/bin/phpstan analyse --memory-limit=1G --no-progress --generate-baseline=phpstan-baseline.neon
```
→ `[OK] Baseline generated with 143 errors.` — Datei
`backend/phpstan-baseline.neon` (31.070 Bytes) neu angelegt und in
`backend/phpstan.neon` unter `includes:` eingebunden.

**Nachher (mit Baseline):**
```
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```
→ `[OK] No errors` (Exit-Code 0).

**Baseline-Mechanik:** natives PHPStan-Baseline-Feature (siehe
`design.md` Decision 3a). Neue Fehler in neuem Code lassen `composer stan`
weiterhin fehlschlagen (Snapshot-Prinzip); bereits erfasste Bestandsfehler
werden ignoriert.

## 5. Verbindlicher Prüfschritt: `composer compat-check` (PHPCompatibility)

**Vorher (ungefiltert, `--standard=PHPCompatibility --runtime-set
testVersion 8.2`, mit der funktionsfähigen Version 10.0.0-alpha2):**
```
vendor/bin/phpcs --standard=PHPCompatibility --runtime-set testVersion 8.2 app/ database/ config/ routes/
```
→ `A TOTAL OF 0 ERRORS AND 1 WARNING WERE FOUND IN 1 FILE`
(`routes/console.php:8`, Sniff
`PHPCompatibility.FunctionDeclarations.NewClosure.ThisFoundOutsideClass` —
`$this` in einer Closure außerhalb einer Klasse; Laravel-Standardmuster in
`Artisan::command(...)`-Closures). Exit-Code 1 (PHPCS liefert bei
Warnings standardmäßig einen Nicht-Null-Exit-Code).

**Baseline-Mechanik — Fallback statt natives Feature (Abweichung von
design.md Decision 3b, dort als möglicher Fall bereits vorgesehen):**
`vendor/bin/phpcs --help` (Version 3.13.5) enthält **kein**
`--generate-baseline`/`--baseline=`-Flag — verifiziert durch
vollständiges Durchsuchen der Hilfe-Ausgabe (`Rule Selection Options`,
`Run Options`, `Reporting Options`, `Configuration Options`,
`Miscellaneous Options` — keiner dieser Abschnitte enthält "baseline").
`design.md` Decision 3b hatte genau diesen Fall als Fallback vorgesehen:
gezielte `exclude-pattern`-Einträge in einer Ruleset-Datei. Neu angelegt:
`backend/.phpcs-baseline.xml` — bindet den Standard `PHPCompatibility`
vollständig ein, setzt `testVersion: 8.2` und schließt **ausschließlich**
den oben genannten einen Sniff für **genau eine** Datei
(`routes/console.php`) aus. Kein pauschaler Verzeichnis-Ausschluss.

`compat-check`-Script wurde entsprechend auf
`vendor/bin/phpcs --standard=.phpcs-baseline.xml app/ database/ config/ routes/`
gesetzt (statt `--standard=PHPCompatibility --runtime-set testVersion
8.2`, da `testVersion` jetzt in der Ruleset-Datei selbst über `<config
name="testVersion" value="8.2"/>` gesetzt ist).

**Nachher (mit `.phpcs-baseline.xml`):**
```
vendor/bin/phpcs --standard=.phpcs-baseline.xml app/ database/ config/ routes/
```
→ kein Output, Exit-Code 0.

## 6. `composer.json` — neue Scripts

```json
"test": "@php vendor/bin/pest --no-coverage",
"lint": "vendor/bin/pint --test",
"stan": "vendor/bin/phpstan analyse --memory-limit=1G",
"compat-check": "vendor/bin/phpcs --standard=.phpcs-baseline.xml app/ database/ config/ routes/",
"qa": ["@lint", "@stan", "@compat-check", "@test"]
```

Einzeln verifiziert (Exit-Codes):

| Script | Exit-Code |
|---|---|
| `composer test` | **0** (718 Tests, 2275 Assertions bestanden) |
| `composer stan` | **0** (mit Baseline) |
| `composer compat-check` | **0** (mit `.phpcs-baseline.xml`) |
| `composer lint` | **1** — siehe Abschnitt 7 (nicht behoben, eskaliert) |
| `composer qa` | **1** — bricht am `@lint`-Schritt ab, siehe Abschnitt 7 |

## 7. ESKALATION: `composer lint` (Laravel Pint) — nicht im Design vorgesehener Bestandsverstoß

**Befund:** `vendor/bin/pint --test` (ohne jede Konfiguration, Pint mit
Laravel-Default-Preset, wie in `design.md` Non-Goals explizit
vorgeschrieben: "Kein `pint.json` ... Pint läuft mit Defaults") meldet:

```
FAIL   ....................................... 291 files, 197 style issues
```

197 von 291 geprüften PHP-Dateien (~68 %) verstoßen gegen das
Laravel-Pint-Default-Preset (u. a. `fully_qualified_strict_types`,
`concat_space`, `ordered_imports`, `no_whitespace_in_blank_line`,
`binary_operator_spaces` — überwiegend Formatierungsregeln, keine
Logikfehler). Reproduzierbar, deterministisch (zweimal ausgeführt,
identisches Ergebnis).

**Warum das nicht im Rahmen dieses Tasks behoben werden konnte, ohne
gegen explizite Vorgaben zu verstoßen:**

1. **`design.md` Non-Goals verbietet explizit** die Anlage eines
   `pint.json` ("Kein `pint.json`/`.php-cs-fixer.php` — Pint läuft mit
   Defaults... kein `pint.json` im Repo"). Diese Aussage war offenbar auf
   der (falschen) Annahme aufgebaut, der Bestandscode entspreche bereits
   dem Default-Preset — das trifft nicht zu.
2. Ein `pint.json` mit breiten `exclude`-Einträgen für fast den gesamten
   `app/`- und `database/`-Baum (~68 % der Dateien) wäre kein gezielter
   Baseline-Mechanismus mehr, sondern würde das Tool faktisch entwerten
   (vgl. dieselbe Abwägung, die `design.md` Decision 3c bereits für
   ESLint als Risiko benennt — hier in noch größerem Ausmaß).
3. Den Bestandscode selbst mit `vendor/bin/pint` (ohne `--test`) zu
   formatieren, würde 197 Dateien inhaltlich verändern — das ist exakt
   das in `proposal.md`/`design.md` (Abschnitt "Non-Goals" und
   "Scope-Grenze") sowie in den T01-Akzeptanzkriterien ("Kein
   Bestandscode ... wurde inhaltlich verändert, um Verstöße zu beheben")
   explizit ausgeschlossene "Aufräumen von Bestandscode".
4. Der "Verbindliche Prüfschritt vor Task-Abschluss" in `design.md` ist
   wörtlich nur für `composer stan`, `composer compat-check` und `npm run
   lint` formuliert ("Da `composer stan`, `composer compat-check` und `npm
   run lint` bislang **nie** gelaufen sind") — `composer lint` (Pint) wird
   dort nicht erwähnt, obwohl es exakt demselben Muster folgt (Tool war
   zwar bereits installiert, aber laut Triage/`proposal.md` "nirgends
   verdrahtet", also faktisch nie gegen den vollständigen Bestandscode
   gelaufen).

**Gemäß dem in `design.md` festgelegten Eskalationsprotokoll** ("Falls
ein Tool aus technischen Gründen ... gar nicht durchläuft [oder] die
Fehleranzahl ... unhandlich groß ist ...: Der Task bricht NICHT
eigenmächtig in Zusatz-Aufräumarbeit aus, sondern dokumentiert den Befund
... und meldet ihn an den nächsten Workflow-Schritt") wird dieser Befund
hiermit **nicht eigenmächtig gelöst**, sondern zur Entscheidung an
Reviewer/Architekt (Modus B) eskaliert. Optionen für die Entscheidung:

- (a) Scope von T01 nachträglich um einen `pint.json`-Baseline-Mechanismus
  erweitern (Widerspruch zum expliziten Non-Goal, bräuchte neues
  User-Gate).
- (b) Einen separaten Formatierungs-Change zulassen, der **nur** `pint`
  (ohne `--test`) über den Bestandscode laufen lässt, geprüft durch
  `composer test` (718 Tests bleiben grün, da reine Formatierung), bevor
  `composer lint` in diesem Change scharf geschaltet wird.
- (c) `qa`/`lint` vorerst ohne `@lint`-Schritt scharf schalten und Pint
  separat nachziehen (Abweichung von `spec.md`, das `lint` explizit als
  Teil von `qa` verlangt).

**Konsequenz für diesen Task (Stand bei Task-Abschluss durch den
dev-php-Agenten):** `composer qa` lief zu diesem Zeitpunkt **nicht**
vollständig grün durch (brach am `@lint`-Schritt ab, Exit-Code 1). Alle
anderen Bestandteile (`test`, `stan`, `compat-check` sowie deren
Verkettung bis zu diesem Punkt) waren grün und einzeln verifiziert
(Abschnitt 6).

**Auflösung der Eskalation (Hauptagent, nach Rückfrage an den User):**
Der User wurde direkt vor die Entscheidung gestellt (Optionen b/c aus der
Eskalation oben) und hat sich für **Option (b)** entschieden: `vendor/bin/pint`
(ohne `--test`) wurde einmalig über den gesamten Bestandscode laufen
lassen — eine rein mechanische Formatierungsänderung (Whitespace,
`fully_qualified_strict_types`, `concat_space`, `ordered_imports` etc.),
keine Logikänderung. Ergebnis: `291 files, ...` formatiert. Anschließend
verifiziert:

```
docker compose exec php composer qa
```
→ Exit-Code **0**. `vendor/bin/pint --test`: `PASS ... 291 files`.
`vendor/bin/phpstan analyse`: `[OK] No errors`. Pest: **718 passed (2275
assertions)** — identische Testanzahl wie vor der Formatierung, keine
Regression durch die reine Formatierungsänderung.

Damit ist `composer qa` jetzt vollständig grün, das Akzeptanzkriterium
erfüllt. Die durch Pint formatierten Dateien sind Teil des Diffs dieses
Changes (reine Formatierung, siehe `git diff --stat` im finalen PR).

## 8. Was NICHT verändert wurde

- Kein Bestandscode unter `backend/app/`, `backend/database/`,
  `backend/config/`, `backend/routes/`, `backend/tests/` wurde inhaltlich
  angepasst.
- Kein `pint.json` wurde angelegt (siehe Abschnitt 7).
- `symfony/yaml`, `laravel/sail` und weitere vorbestehende Pakete wurden
  nicht angetastet (siehe Abschnitt 1, `--ignore-platform-reqs`).

## 9. Geänderte/neue Dateien in diesem Task

- `backend/composer.json` (dup. `config`-Key gemergt; neue
  `require-dev`-Einträge; neue Scripts `test`/`lint`/`stan`/
  `compat-check`/`qa`; `config.allow-plugins`-Eintrag)
- `backend/composer.lock` (regeneriert durch `composer require`)
- `backend/phpstan.neon` (neu, verschoben von Repo-Root, `Kernel.php`-
  Exclude entfernt, `phpstan-baseline.neon` per `includes:` eingebunden)
- `phpstan.neon` (Repo-Root, gelöscht)
- `backend/phpstan-baseline.neon` (neu, generiert, 143 Bestandsfehler)
- `backend/.phpcs-baseline.xml` (neu, PHPCompatibility-Ruleset mit
  gezieltem Ausschluss von 1 Bestandswarnung)

## 10. PHP-8.2-Kompatibilität (CLAUDE.md Abschnitt 4.1)

Es wurde in diesem Task kein neuer Anwendungs-PHP-Code geschrieben (nur
Composer-/PHPStan-/PHPCS-Konfigurationsdateien in JSON/NEON/XML). Damit
entfällt eine 8.3/8.4-Feature-Prüfung gegen eigenen Code; `composer
compat-check` selbst (jetzt mit funktionsfähiger PHPCompatibility-Version,
siehe Abschnitt 3) ist das Werkzeug, das künftigen Code dagegen prüft.

## 11. Nachtrag: Fix des Reviewer-Blockers (`symfony/yaml` vs. `config.platform.php: 8.3.0`)

**Befund des Reviewers** (`task-T01.review.md`, Abschnitt "Muss"): Der in
Abschnitt "Vorab-Fix" dokumentierte Merge des doppelten `config`-Keys
aktiviert `config.platform.php: 8.3.0` erstmals wirksam. Das zuvor in
Abschnitt 1 als "vorbestehend, außerhalb des Scopes" gemeldete
`symfony/yaml v8.0.8`-Problem (`require.php: >=8.4`, transitiv über
`laravel/sail`) führt dadurch zu einem reproduzierbaren Fehlschlag von
`composer install --no-interaction --prefer-dist --optimize-autoloader`
(exakt der CI-Befehl) mit Exit-Code 2 — der Reviewer hat das in Docker
gegen einen vollständig entfernten `vendor/` selbst nachvollzogen.

**Fix (nach Reviewer-Vorschlag, Option "symfony/yaml zurücksetzen"):**

```
docker compose exec php composer update symfony/yaml --with-all-dependencies --no-interaction
```

Ergebnis: `symfony/yaml` `v8.0.8` → **`v7.4.15`** (erfüllt `php ^8.2`,
kompatibel mit `config.platform.php: 8.3.0`). Zwei transitive
Nebenpakete wurden dabei minimal mitgezogen (beide reine Patch-/Minor-Bumps,
von `symfony/yaml` bzw. dessen Abhängigkeiten benötigt):
`symfony/deprecation-contracts` `v3.6.0` → `v3.7.1`,
`symfony/polyfill-ctype` `v1.36.0` → `v1.37.0`. Keine weiteren
Versionsänderungen in `backend/composer.lock` durch diesen Fix (verifiziert
per `git diff backend/composer.lock`, isoliert von den bereits vorher in
diesem Task neu hinzugefügten `require-dev`-Paketen).

Die Alternative aus dem Reviewer-Vorschlag (`config.platform.php` auf
`8.4.0` anheben) wurde **nicht** gewählt, da das gegen CLAUDE.md Abschnitt 3
verstoßen hätte (Demo-Umgebung: max. PHP 8.3) — der Zweck von
`platform.php: 8.3.0` (Lock-File-Resolution auf den kleinsten gemeinsamen
Nenner der Shared-Hosting-Ziele begrenzen) bleibt damit erhalten.

**Re-Verifikation (genau wie vom Reviewer gefordert):**

```
docker compose exec php sh -c "rm -rf vendor && composer install --no-interaction --prefer-dist --optimize-autoloader"
```
→ Exit-Code 0, keine Platform-Konflikte mehr.

```
docker compose exec php composer qa
```
→ Exit-Code 0 (`lint`: 291 Dateien, PASS; `stan`: `[OK] No errors`;
`compat-check`: kein Output; `test`: 718 passed, 2275 assertions —
identisch zum vorherigen Stand, keine Regression).

Damit ist der Muss-Blocker aus `task-T01.review.md` behoben.

## Akzeptanzkriterien-Status (tasks.md T01)

- [x] `composer test`, `composer lint`, `composer stan`, `composer
  compat-check`, `composer qa` existieren und sind ausführbar
- [x] `larastan/larastan` und `phpcompatibility/php-compatibility` real
  installiert
- [x] `backend/phpstan.neon` existiert, Root-`phpstan.neon` existiert
  nicht mehr
- [x] `composer stan` läuft ohne Exit-Code-Fehler durch (mit Baseline)
- [x] `composer compat-check` läuft ohne Exit-Code-Fehler durch (mit
  `.phpcs-baseline.xml`)
- [x] `composer qa` läuft komplett grün durch — nach Nutzer-Entscheidung
  (Option b, siehe Abschnitt 7 "Auflösung") durch einmaligen
  `vendor/bin/pint`-Lauf über den Bestandscode erfüllt
- [x] Vorher-/Nachher-Fehleranzahl für `stan` (143 → 0), `compat-check`
  (1 Warning → 0) und `lint` (197 Style-Issues in 291 Dateien → 0)
  dokumentiert
- [x] Kein Bestandscode *inhaltlich* (Logik) verändert — die einzige
  Ausnahme ist die bewusste, vom User freigegebene rein mechanische
  Pint-Formatierung (Abschnitt 7)
- [x] Neuer Code in diesem Task (Baseline-/Config-Dateien) verstößt nicht
  gegen CLAUDE.md Abschnitt 4.1 (kein PHP-Anwendungscode geschrieben)
