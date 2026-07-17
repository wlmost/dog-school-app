# Task-Notes T05: Resource `AnnouncementResource`

**Agent:** dev-php
**Status:** abgeschlossen

## Umgesetzte Dateien

- `backend/app/Http/Resources/AnnouncementResource.php` *(neu)*

## Umsetzung

Die Resource wurde exakt nach dem in `design.md` Abschnitt 4.4 ("Backend —
Policy, FormRequests, Resource") vorgegebenen Code implementiert (der
Task-Beschreibungstext verweist auf "Abschnitt 5.1", der vollständige
Resource-Code und das verbindliche JSON-Beispiel befinden sich jedoch in
Abschnitt 4.4 — Abschnitt 5.1 enthält den Controller-Code, der die Resource
lediglich verwendet). Feldreihenfolge und -namen entsprechen 1:1 dem
verbindlichen JSON-Beispiel:

```
id, title, body, imageUrl, displayDays, expiresAt, isActive, createdAt, updatedAt
```

- `imageUrl`: `Storage::disk('public')->url($this->image_path)`, `null` falls
  `image_path` nicht gesetzt ist (Ternary-Ausdruck, identisches Muster wie
  `DogResource::profileImageUrl`, `backend/app/Http/Resources/DogResource.php:45-47`).
- `expiresAt`/`createdAt`/`updatedAt`: `toISOString()` mit Null-Safe-Operator
  (`?->`), analog `DogResource.php:48-50`.
- `isActive`: ruft `Announcement::isActive()` (aus T02,
  `backend/app/Models/Announcement.php:77-80`) auf, kein eigenes
  Aktiv-Kriterium dupliziert.

Stilistisch an `DogResource.php` angelehnt (Klassen-PHPDoc mit `@mixin`,
Methoden-PHPDoc `@return array<string, mixed>`). `vendor/bin/pint` hat beim
`--test`-Lauf einen Formatierungshinweis zum `@mixin`-Tag ausgegeben
(`fully_qualified_strict_types`: bevorzugt `use App\Models\Announcement;` +
Kurzname im PHPDoc statt voll qualifiziertem Klassennamen inline). Mit
`vendor/bin/pint` automatisch korrigiert; `--test` läuft danach grün. Hinweis:
`DogResource.php` selbst hat denselben (unkorrigierten) Stil und würde bei
`pint --test` ebenfalls anschlagen — das ist ein vorbestehender Zustand,
nicht Teil dieser Task, daher nicht angefasst.

## Abweichungen vom vorgesehenen Code

Keine inhaltlichen Abweichungen. Einzige Ergänzung gegenüber dem
Code-Snippet in `design.md` 4.4: Klassen-Level-PHPDoc (`@mixin`) und
Methoden-PHPDoc (`@return array<string, mixed>`), übernommen aus dem
bestehenden `DogResource.php`-Muster zur Konsistenz mit dem restlichen
Resource-Bestand.

## PHP-8.2-Kompatibilität

Keine 8.3/8.4-Features verwendet (kein `readonly class`-Marker-Attribut,
keine Typed Class Constants, kein `#[\Override]`, keine Dynamic Class
Constant Fetch, kein `new ...->` ohne Klammern). Nullsafe-Operator (`?->`)
ist seit PHP 8.0 verfügbar, ternäre Ausdrücke und Match sind Standard-PHP —
unkritisch.

## Lokale Checks

Ausgeführt in `dog-school-php`-Docker-Container (`cd /var/www/html`):

- `vendor/bin/pint --test app/Http/Resources/AnnouncementResource.php` →
  zunächst 1 Style-Issue (siehe oben), nach `vendor/bin/pint` (Fix) grün.
- `php -l app/Http/Resources/AnnouncementResource.php` → keine
  Syntaxfehler.
- `composer dump-autoload` → 8438 Klassen erfolgreich generiert, neue
  Klasse ohne Namespace-/Autoload-Konflikt aufgelöst.

**Nicht ausführbar (bestehende Infrastruktur-Lücke, nicht Teil von T05):**
`composer qa` ist in `backend/composer.json` nicht definiert (nur im
Root-`composer.json` des Repos, das sich aber auf nicht-existente
Root-Pfade `app/`, `database/` etc. bezieht — das Backend liegt unter
`backend/`). `backend/composer.json` `require-dev` enthält weder
`larastan/larastan` noch `phpcompatibility/php-compatibility`, entsprechend
existieren `vendor/bin/phpstan` und `vendor/bin/phpcs` in
`backend/vendor/` nicht. `composer stan` und `composer compat-check` konnten
daher nicht ausgeführt werden. Dies betrifft alle Backend-Tasks dieses
Changes gleichermaßen und ist kein durch T05 verursachtes Problem — ggf.
als eigener Findings-Punkt für den Skeptiker/Reviewer relevant.
`composer test` (Pest) wurde nicht separat für T05 ausgeführt, da T08 die
zugehörigen Feature-Tests erst noch liefert; die Resource-Datei selbst
enthält keine testbare Logik über das reine Mapping hinaus.

## Für T09 (Frontend) relevant

Response-Shape ist exakt wie in `design.md` Abschnitt 4.4/"Verbindliches
JSON-Beispiel" fixiert — camelCase-Feldnamen `id`, `title`, `body`,
`imageUrl`, `displayDays`, `expiresAt`, `isActive`, `createdAt`, `updatedAt`,
1:1 übernommen, keine Abweichungen. T09 kann das TypeScript-Interface
`Announcement` direkt danach modellieren.
