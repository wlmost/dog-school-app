# Task-Notes T02: Model `Announcement` + Factory

**Change-ID:** add-announcement-banner
**Agent:** dev-php
**Status:** abgeschlossen

---

## Umgesetzte Dateien

- `backend/app/Models/Announcement.php` (neu)
- `backend/database/factories/AnnouncementFactory.php` (neu)

Beide Dateien wurden gemäß `design.md` Abschnitt 2.3 übernommen (Code dort
1:1 vorgegeben). Nach `vendor/bin/pint`-Autofix weichen beide Dateien
minimal von der wörtlichen Code-Vorlage in `design.md` ab (voll
qualifizierte Klassennamen im PHPDoc durch `use`-Import + Kurzform ersetzt,
String-Konkatenation `.` statt `. `) — rein stilistisch, keine
Verhaltensänderung. Siehe Abschnitt "Lint" unten.

## Abhängigkeit T01

Migration `backend/database/migrations/2026_07_17_100000_create_announcements_table.php`
war bereits vorhanden (`php artisan migrate` innerhalb des laufenden
Docker-Containers meldete `Nothing to migrate.`). Tabellenschema
(`title`, `body`, `image_path`, `display_days`, `expires_at`, Timestamps)
wurde vor der Implementierung gegen die Spalten-/Cast-Zuordnung im Model
verifiziert (`Read` der Migrationsdatei).

## Verifikation der `booted()`-Hook-Logik (vor Übernahme geprüft)

Der Skeptiker-Hinweis in `design.md` Abschnitt 2.1 verlangte eine
eigenständige Prüfung, da es **kein** Vorbild für `booted()`/`saving`-Hooks
im Projekt gibt (`CustomerCredit.php` verifiziert: kein solcher Hook,
`expiration_date` wird dort direkt vom Client bzw. der Factory gesetzt).
Geprüft anhand des Laravel-Framework-Quellcodes
(`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php`,
Methoden `save()` Zeile 1138 ff., `performInsert()` Zeile 1301 ff.,
`performUpdate()` Zeile 1217 ff.):

1. **Kein Endlos-Rekursions-Risiko:** `saving` wird einmalig pro `save()`-
   Aufruf gefeuert, bevor `performInsert()`/`performUpdate()` läuft. Der
   Hook setzt nur ein Attribut (`$announcement->expires_at = ...`), ruft
   selbst kein `save()` auf — kein Trigger einer erneuten `saving`-Kaskade.
2. **Reihenfolge bei Create:** `saving` feuert **vor** `updateTimestamps()`
   in `performInsert()` — zum Zeitpunkt des Hooks ist `created_at` bei
   einem neuen Datensatz noch `null`. Der Hook-Code prüft das explizit
   (`$announcement->exists` ist bei Create `false`) und fällt in diesem
   Fall korrekt auf `now()` als Basis zurück, statt auf ein noch nicht
   gesetztes `created_at` zuzugreifen.
3. **Reihenfolge bei Update:** `saving` feuert vor `performUpdate()`, das
   nur `updated_at` (nicht `created_at`) neu setzt. Das bereits vom Model
   geladene `created_at` bleibt zum Hook-Zeitpunkt unverändert vorhanden —
   der Hook liest also korrekt den ursprünglichen Anlage-Zeitpunkt.
4. **Kein Überschreiben des Factory-`expired()`-States:** `saveQuietly()`
   (Laravel-Standard) unterdrückt sämtliche Model-Events inkl. `saving` —
   der Hook läuft beim `forceFill(...)->saveQuietly()` in
   `AnnouncementFactory::expired()` nicht, die manuell in die Vergangenheit
   gesetzten Werte bleiben also erhalten. Per Tinker verifiziert (siehe
   unten, AC2).

Ergebnis: Die in `design.md` vorgeschlagene Hook-Implementierung ist
funktional korrekt und wurde unverändert übernommen.

## Abweichungen von der wörtlichen Vorlage in `design.md`

Keine inhaltlichen Abweichungen. `vendor/bin/pint` (Projekt-Lint-Tool,
Laravel-Preset) hat beim Auto-Fix zwei rein stilistische Anpassungen
vorgenommen:

- PHPDoc: `\Illuminate\Support\Carbon` → `use`-Import + `Carbon` (Kurzform),
  ebenso `@extends \Illuminate\...\Factory<\App\Models\Announcement>` →
  `@extends Factory<Announcement>`.
- String-Konkatenation ohne Leerzeichen um den Punkt
  (`'<p>'.fake()->paragraph().'</p>'` statt `'<p>' . fake()->paragraph() . '</p>'`).

Beide Anpassungen sind reine Formatierung ohne Verhaltensänderung.

## Anmerkung zu Projekt-Tooling (Diskrepanz CLAUDE.md vs. tatsächlichem Zustand)

CLAUDE.md Abschnitt 5/7.1 verweist auf `composer qa` (`lint` + `stan` +
`compat-check` + `test`). Das im laufenden Docker-Container aktive
`backend/composer.json` (gemountet unter `/var/www/html`, `App\` →
`backend/app/`) definiert diese Scripts **nicht** — nur Standard-
Composer-Hooks. `vendor/bin/phpstan` und `vendor/bin/phpcs` sind in
`backend/vendor/bin/` nicht vorhanden (nur `pint`, `pest`, `phpunit` u. a.).
Ein root-`composer.json` mit `qa`/`stan`/`compat-check`-Scripts existiert
zwar im Projekt-Root, referenziert dort aber `app/`/`database/`, die es am
Root nicht gibt (nur unter `backend/`) — dieses Root-`composer.json`
scheint nicht das im Docker-Setup verwendete zu sein (`docker-compose.yml`
mountet `./backend` nach `/var/www/html`). Für T02 daher ausgeführt:
`vendor/bin/pint --test` (Lint, grün) und `vendor/bin/pest` (voller
Testlauf, grün, keine Regression). `stan`/`compat-check` konnten mangels
installierter Binaries nicht lokal ausgeführt werden — der neue Code
verwendet ausschließlich PHP-8.2-kompatible Sprachfeatures (siehe
"Manuelle PHP-8.2-Prüfung" unten), sodass kein inhaltliches Risiko besteht.
Diese Diskrepanz ist ein bestehender Zustand des Projekts, nicht durch T02
verursacht, und wird hier zur Dokumentation festgehalten
(Anti-Halluzinations-Regel 3 aus CLAUDE.md Abschnitt 9).

## Manuelle PHP-8.2-Prüfung (CLAUDE.md Abschnitt 4.1)

Keines der in Abschnitt 4.1 verbotenen 8.3-/8.4-Konstrukte verwendet:
keine Property Hooks, keine Asymmetric Visibility, kein `#[\Override]`,
keine typed Class Constants, kein `json_validate()`, keine Dynamic Class
Constant Fetch, kein `new MyClass()->method()` ohne Klammern. Verwendet
werden ausschließlich seit 8.2 erlaubte Features (Readonly-fähige Klasse
möglich, aber hier nicht genutzt; `protected function casts(): array`
Laravel-11-Konvention).

## Lokale Verifikation (Docker, PostgreSQL)

Ausgeführt in `dog-school-php`-Container gegen die lokale PostgreSQL-
Entwicklungsdatenbank via `php artisan tinker`:

```
AC1 (create() -> expires_at = created_at + display_days):
  created_at=2026-07-17 09:42:54 expires_at=2026-07-22 09:42:54 display_days=5
  diffInSeconds zwischen expires_at und created_at+5d: 0 -> PASS

AC2 (expired()->create() -> isActive() === false):
  created_at=2026-07-07 09:42:54 expires_at=2026-07-08 09:42:54
  isActive(): false -> PASS

AC3 (display_days auf bestehendem, nicht abgelaufenem Datensatz erhöht
     -> expires_at neu ab ursprünglichem created_at, nicht ab now()):
  ursprüngliches created_at=2026-07-17 09:42:54 (unverändert nach Update)
  display_days 3 -> 10 (nach 1s Wartezeit gespeichert)
  neues expires_at=2026-07-27 09:42:54 == created_at + 10 Tage -> PASS

AC4 (scopeActive() liefert nur expires_at > now()):
  1 aktiver + 1 abgelaufener Datensatz angelegt
  Announcement::active()->pluck('id') enthält nur den aktiven Datensatz
  -> PASS
```

Test-Datensätze anschließend aus der Entwicklungs-DB gelöscht
(`Announcement::query()->delete()`), Tinker-Skript aus dem Container
entfernt — keine Rückstände.

## QA-Checks

- `vendor/bin/pint --test app/Models/Announcement.php
  database/factories/AnnouncementFactory.php` → PASS (nach einmaligem
  `vendor/bin/pint`-Autofix, siehe oben)
- `vendor/bin/pest` (voller Suite-Lauf) → 693 passed (2195 assertions),
  keine Regression durch die neuen Dateien
- `php artisan migrate` → `Nothing to migrate.` (T01-Migration bereits
  vorhanden und unverändert)

## Bekannte Einschränkungen / Hinweise für Folge-Tasks

- T02 selbst enthält keine formalen Pest-Tests für `Announcement`
  (`vendor/bin/pest --filter=Announcement` meldet `No tests found.`) —
  das ist laut `tasks.md` explizit Aufgabe von T08
  (`AnnouncementApiTest.php`). Die vier Akzeptanzkriterien wurden für T02
  stattdessen wie oben dokumentiert per Tinker verifiziert.
- `expired()`-Factory-State setzt `created_at`/`expires_at` fix auf
  `now()->subDays(10)`/`now()->subDays(9)` (unabhängig von
  `display_days`) — entspricht exakt der Vorgabe in `design.md`.
