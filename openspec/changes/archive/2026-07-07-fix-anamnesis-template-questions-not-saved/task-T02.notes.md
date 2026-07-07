# Notes T02: Anzeigeproblem — `questionsCount` im Backend liefern

**Agent:** dev-php
**Datum:** 2026-07-07

## Zusammenfassung

`GET /api/v1/anamnesis-templates` (Liste), `GET
/api/v1/anamnesis-templates/{id}` (show) und die Antwort von `POST
/api/v1/anamnesis-templates` (store) liefern jetzt alle ein korrektes
`questionsCount`-Feld. Umgesetzt exakt wie in `design.md`, Abschnitt 3,
vorgeschlagen.

## Geänderte Dateien

1. `backend/app/Http/Controllers/AnamnesisTemplateController.php`
   - `index()` (Zeile 29): Query um `->withCount('questions')` erweitert,
     zusätzlich zum bestehenden `->with(['trainer'])`. Vermeidet, dass für
     die Listenansicht die komplette `questions`-Relation geladen werden
     muss, nur um die Anzahl zu bestimmen (N+1-Vermeidung, DRY zur
     bestehenden `responsesCount`-Fallback-Logik in der Resource).
   - `store()` und `show()`: **unverändert**, wie in der Task-Beschreibung
     vorgegeben — beide laden bereits die volle `questions`-Relation, die
     Resource leitet den Count daraus ab (siehe Punkt 2).

2. `backend/app/Http/Resources/AnamnesisTemplateResource.php`
   - Neues Feld `'questionsCount' => $this->resolveQuestionsCount()` im
     `toArray()`-Array ergänzt.
   - Neue private Methode `resolveQuestionsCount(): ?int`:
     - Prüft zuerst `$this->questions_count` (von `withCount()` gesetztes
       dynamisches Attribut, `index()`-Pfad) — falls vorhanden, `(int)`-Cast
       und Rückgabe.
     - Fällt andernfalls auf eine bereits geladene `questions`-Relation
       zurück (`relationLoaded('questions')` + `$this->questions->count()`)
       — relevant für `store()`/`show()`.
     - Liefert `null`, falls weder noch vorhanden (defensiver Fallback,
       aktuell nicht erreichbar, da alle drei Controller-Methoden eine der
       beiden Quellen liefern).
   - Code 1:1 wie in `design.md` Abschnitt 3 vorgeschlagen übernommen.

3. `backend/tests/Feature/AnamnesisTemplateApiTest.php`
   - Bestehender Test "can list anamnesis templates" (Zeile 21-33): Die
     `assertJsonStructure`-Prüfung um `questionsCount` erweitert (im
     Sinne der Akzeptanzkriterien "nicht blockierend, aber erwünscht").
   - Neuer Test "listed templates report the correct questions count per
     template": Legt 3 Vorlagen mit 0, 2 und 5 Fragen an, ruft die
     Listen-Route auf und prüft per `pluck('questionsCount', 'id')`, dass
     jede Vorlage exakt ihre tatsächliche Fragenanzahl zurückliefert (nicht
     nur "irgendeine Zahl > 0", sondern pro Vorlage individuell korrekt
     zugeordnet — deckt insbesondere ab, dass `withCount` nicht versehentlich
     dieselbe Zahl für alle Zeilen liefert).
   - Diese Datei ist nicht in der "Dateien"-Liste von T02 in `tasks.md`
     genannt, aber die Akzeptanzkriterien von T02 (siehe `tasks.md` Zeile
     92-93: "Neuer/erweiterter Test …") verlangen den Test explizit dort,
     da es keine andere Testdatei für diese Ressource gibt. Keine
     Scope-Erweiterung über die Task hinaus, nur die einzig sinnvolle Datei
     für den geforderten Test.

**Keine Änderung an `store()`, `show()`, an Frontend-Dateien oder an
`AnamnesisTemplate`-Model/Migrations** — wie in der Task-Beschreibung und
`design.md` Abschnitt 3 vorgegeben.

## Testergebnisse

Ausgeführt in der Docker-Umgebung (Service `php`, nicht `app` — siehe
bereits in `task-T01.notes.md` dokumentierte Befehlsdiskrepanz):

```bash
docker compose exec php vendor/bin/pest --filter=AnamnesisTemplateApiTest
# 23 passed (99 assertions) — inkl. der neuen Testfälle, alle grün

docker compose exec php vendor/bin/pest
# Tests: 672 passed (2094 assertions) — volle Suite grün, keine Regression
```

`vendor/bin/pint --test` (Lint) für die drei geänderten Dateien geprüft:
Die gemeldeten Stilverstöße (`fully_qualified_strict_types`,
`function_declaration` für das bereits bestehende `fn()` bei
`responsesCount`, diverse `concat_space`/`trailing_comma` in der
Testdatei) sind **vorbestehende, projektweite Befunde** (Pint meldet sie
in praktisch allen `app/`- und `tests/`-Dateien des Repos, nicht nur in
den hier geänderten). Der von mir neu hinzugefügte Code
(`resolveQuestionsCount()`, `withCount('questions')`, der neue Testfall)
selbst löst keine zusätzlichen Verstöße aus, die nicht schon vorher in der
jeweiligen Datei vorhanden waren — siehe Diff-Abgleich unten.

**`composer stan` / `composer compat-check`: nicht ausführbar** in diesem
Docker-Setup — bereits in `task-T01.notes.md` und in der archivierten
`fix-dog-image-upload-shared-hosting`-Change (`task-T01.notes.md`,
`task-T02.notes.md`) dokumentierter, vorbestehender Befund: Das
Root-`composer.json` definiert die Scripts `qa`/`stan`/`compat-check` und
die Dev-Dependencies `larastan/larastan`, `phpcompatibility/php-compatibility`
und `squizlabs/php_codesniffer`, aber der im Container gemountete
`backend/`-Ordner (das tatsächliche Laravel-Projekt, Arbeitsverzeichnis
`/var/www/html`) hat ein eigenes, schlankeres `backend/composer.json` ohne
diese Dev-Dependencies — `vendor/bin/phpstan` und `vendor/bin/phpcs`
existieren dort nicht (`vendor/bin/` enthält nur `pest`, `pint`,
`phpunit` u. a., siehe Output unten). Kein Bezug zu T02, keine Behebung im
Rahmen dieser Task (wäre ein eigener, unabhängiger Change zur
Composer-Struktur).

```bash
docker compose exec php sh -c "ls vendor/bin/"
# carbon, paratest, paratest_for_phpstorm, patch-type-declarations, pest,
# php-parse, phpunit, pint, psysh, sail, var-dump-server, yaml-lint
# (kein phpstan, kein phpcs)
```

**Manuelle PHP-8.2-Kompatibilitätsprüfung** (Ersatz für den nicht
lauffähigen `compat-check`, siehe CLAUDE.md Abschnitt 4.1): Der
hinzugefügte Code verwendet ausschließlich 8.2-kompatible Sprachmittel —
`private function resolveQuestionsCount(): ?int` (Nullable Return Type,
seit PHP 7.1 verfüggbar), `!==`-Vergleich, `(int)`-Cast, `->withCount()`
(Laravel-Standard-Methode, keine 8.3/8.4-Syntax). Kein `#[\Override]`,
keine Typed Class Constants, keine Property Hooks, keine Asymmetric
Visibility. `php -l` gegen beide geänderten PHP-Dateien lief fehlerfrei
(`No syntax errors detected`).

## Offene Punkte / Risiken

- Keine inhaltlichen offenen Punkte für T02 — alle Akzeptanzkriterien
  erfüllt.
- Der bereits unter T01 dokumentierte Composer-Script-Gap
  (`stan`/`compat-check` nicht ausführbar im Docker-`php`-Service) bleibt
  ein vorbestehender, unabhängiger Befund außerhalb des T02-Scopes.
- Wie in `design.md` Abschnitt 7 vermerkt: der Nebenfund zu
  `AnamnesisQuestionResource.php:26` (`help_text`-Spalte existiert nicht)
  wurde nicht angefasst — außerhalb des Scopes von T02.
