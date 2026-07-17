# Task T03: Policy `AnnouncementPolicy`

**Agent:** dev-php
**Status:** abgeschlossen

## Implementiert

- `backend/app/Policies/AnnouncementPolicy.php` (neu)

## Vorgehen

Policy 1:1 analog zu `backend/app/Policies/SettingPolicy.php` erstellt: alle
fünf Standard-Methoden (`viewAny`, `view`, `create`, `update`, `delete`)
prüfen ausschließlich `$user->isAdmin()`. Code entspricht exakt dem in
`design.md` Abschnitt 4.1 vorgegebenen Muster, mit PHPDoc-Kommentaren im
Stil von `DogPolicy.php`/`SettingPolicy.php` ergänzt.

`declare(strict_types=1);` gesetzt, PSR-12-konform (`vendor/bin/pint --test`
bestätigt).

## Policy-Discovery geprüft

`backend/app/Providers/` enthält ausschließlich `AppServiceProvider.php` —
kein `AuthServiceProvider` mit manueller `$policies`-Map. Laravel 11 löst
`App\Models\Announcement` → `App\Policies\AnnouncementPolicy` daher über die
Standard-Namenskonvention (`Model`-Namespace → `Policies`-Namespace,
Suffix `Policy`) automatisch auf, identisch zum bestehenden Verhalten von
`SettingPolicy`/`DogPolicy`. Kein manueller Eintrag nötig, keine Änderung an
`AppServiceProvider.php` vorgenommen.

## Abhängigkeit T02

`App\Models\Announcement` existierte bereits vor Beginn dieser Task unter
`backend/app/Models/Announcement.php` (T02 bereits erledigt) — Policy
referenziert dieses Model per `use App\Models\Announcement;`.

## Pre-Flight-Checks (Docker-Umgebung, `docker compose exec php ...`)

- `php -l app/Policies/AnnouncementPolicy.php` → keine Syntaxfehler
- `vendor/bin/pint --test app/Policies/AnnouncementPolicy.php` → `PASS`
- Kein `composer qa`/`composer stan`/`composer compat-check`-Script im
  Repo vorhanden (siehe bereits in T01 dokumentiert, `composer.json`
  enthält aktuell keine dieser Scripts) — daher nur Pint (Lint) und
  `php -l` ausführbar. Manuelle Prüfung gegen CLAUDE.md Abschnitt 4.1:
  keine PHP-8.3/8.4-Konstrukte verwendet (nur Standard-Klassen-Syntax,
  typisierte Methoden-Parameter/Returns, kein `#[\Override]`, keine
  Property Hooks, kein Readonly-Class-Level-Konstrukt nötig).
- Kein Test-Lauf nötig/vorgesehen für T03 (Tests für die Policy sind
  implizit Teil von T08, `AnnouncementApiTest.php`, über die
  Authorization-Checks in den Controller-Aktionen).

## Abweichungen von design.md

Keine. Code entspricht 1:1 dem in `design.md` Abschnitt 4.1 spezifizierten
Muster (Methodensignaturen identisch, nur um PHPDoc-Blöcke ergänzt,
analog zum bestehenden Stil in `SettingPolicy.php`/`DogPolicy.php`).
