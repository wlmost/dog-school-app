# Task-Notes T04: FormRequests `StoreAnnouncementRequest` / `UpdateAnnouncementRequest`

**Change-ID:** add-announcement-banner
**Agent:** dev-php
**Status:** abgeschlossen

---

## Umgesetzte Dateien

- `backend/app/Http/Requests/StoreAnnouncementRequest.php` (neu)
- `backend/app/Http/Requests/UpdateAnnouncementRequest.php` (neu)

Beide Klassen wurden gemäß `design.md` Abschnitt 4.2/4.3 übernommen (Code
dort im Wesentlichen 1:1 vorgegeben), im Aufbau eng an
`StoreCourseRequest.php`/`UpdateCourseRequest.php` angelehnt (bestehendes
Vorbild für die `SanitizesHtmlContent`-Trait-Nutzung, siehe unten).

## Umsetzung im Detail

- `StoreAnnouncementRequest::authorize()`: `$this->user()?->isAdmin() ?? false`
  — identisches Muster wie `SettingPolicy`/andere Admin-only-Requests im
  Projekt (`User::isAdmin()`, `backend/app/Models/User.php:91`).
- `rules()`: `title` (`required|string|max:255`), `body`
  (`required|string|max:5000`, Grenze identisch zu
  `StoreCourseRequest.php:38`), `displayDays`
  (`required|integer|min:1|max:365`), `image`
  (`nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120`, identisch zu
  `DogController.php:185`).
- `validatedSnakeCase()` in `StoreAnnouncementRequest`: baut das Array
  **explizit** aus `title`/`body`/`display_days` auf (kein generisches
  `Str::snake()`-Mapping über alle validierten Keys wie in
  `StoreCourseRequest`) — dadurch landet `image` (camelCase `image`, es
  gibt hier ohnehin keine Snake-Case-Konvertierung nötig, da der Key schon
  `image` lautet) **strukturell nicht** im Rückgabe-Array. `body` wird vor
  der Rückgabe über `sanitizeHtmlDescription()` bereinigt.
- `UpdateAnnouncementRequest::rules()`: alle Felder mit `sometimes`
  (Teil-Update), `image` bleibt `nullable` (keine `sometimes`-Notwendigkeit
  bei einem ohnehin optionalen Feld).
- `UpdateAnnouncementRequest::validatedSnakeCase()`: baut das
  Rückgabe-Array iterativ per `array_key_exists()`-Prüfung pro Feld auf —
  nur tatsächlich im Request vorhandene Felder erscheinen im Ergebnis,
  `image` wird bewusst nie in dieses Array übernommen (Bild-Upload bleibt
  Aufgabe des Controllers in T06, `$request->hasFile('image')`).
- Beide Klassen nutzen `App\Http\Requests\Concerns\SanitizesHtmlContent`
  (bestehendes Trait, keine neue Abhängigkeit,
  `backend/app/Http/Requests/Concerns/SanitizesHtmlContent.php` gelesen und
  unverändert wiederverwendet).

## Abweichung von der wörtlichen Design-Vorlage

Keine inhaltliche Abweichung. `vendor/bin/pint` hat nach dem Schreiben der
Dateien automatisch zwei rein stilistische Fixer angewendet
(`fully_qualified_strict_types`, `ordered_imports`): der PHPDoc-Rückgabetyp
`\Illuminate\Contracts\Validation\ValidationRule` in `rules()` wurde durch
einen `use`-Import + Kurzform (`ValidationRule`) ersetzt. Dieselben zwei
Fixer schlagen mit identischem Befund auch bei den bestehenden Vorbildern
`StoreCourseRequest.php`/`UpdateCourseRequest.php` an (`vendor/bin/pint
--test` dort ebenfalls nicht grün) — vorbestehender Projektzustand, nicht
durch T04 verursacht.

## Lokale Verifikation (Docker-Container `dog-school-php`)

Ausgeführt über `php artisan tinker <script>` (Skript danach aus dem
Container entfernt bzw. lag unter `/tmp`, keine Repo-Änderung):

```
rules keys: title,body,displayDays,image
displayDays=0 fails: true
displayDays=366 fails: true
displayDays=365 fails: false
sanitized: <p>Hallo</p>alert(1)<strong>bold</strong>
update rules: {"title":["sometimes","string","max:255"],"body":["sometimes","string","max:5000"],"displayDays":["sometimes","integer","min:1","max:365"],"image":["nullable","image","mimes:jpg,jpeg,png,gif,webp","max:5120"]}
authorize() mit ungebundenem User (kein Sanctum-Auth im Testkontext): false
```

- `displayDays=0`/`displayDays=366` → Validierung schlägt fehl (führt bei
  echtem Request zu HTTP 422 über den Standard-Laravel-Validation-Flow) —
  AC "displayDays außerhalb 1–365 wird abgelehnt" bestätigt.
- `displayDays=365` (Grenzwert, inklusive) → Validierung erfolgreich —
  bestätigt `max:365` als inklusive Obergrenze.
- `sanitizeHtmlDescription()` entfernt `<script>`-Tag vollständig und
  streift das `onclick`-Attribut vom erlaubten `<strong>`-Tag, behält aber
  erlaubte Tags (`<p>`, `<strong>`) — AC "body wird bereinigt" bestätigt.
- `authorize()` liefert `false`, wenn kein Sanctum-authentifizierter Admin
  gebunden ist — AC bestätigt (der Positivfall "ist Admin → `true`" folgt
  direkt aus `$this->user()?->isAdmin()` und wird in T08
  (`AnnouncementApiTest`) end-to-end gegen echte Admin-/Nicht-Admin-User
  abgedeckt).
- `image` erscheint in keinem der beiden `validatedSnakeCase()`-Codepfade
  als Schlüssel — durch Code-Inspektion bestätigt (explizite
  Allowlist-Konstruktion des Rückgabe-Arrays in beiden Klassen, siehe
  oben), nicht separat per Tinker nachgestellt, da kein Dateiupload ohne
  echten `UploadedFile`/Multipart-Request sinnvoll simulierbar ist — wird
  in T08 mit `Storage::fake('public')` end-to-end getestet.

## QA-Checks

- `php -l` auf beiden neuen Dateien → keine Syntaxfehler.
- `vendor/bin/pint --test app/Http/Requests/StoreAnnouncementRequest.php
  app/Http/Requests/UpdateAnnouncementRequest.php` → PASS (nach einmaligem
  `vendor/bin/pint`-Autofix, siehe oben).
- `vendor/bin/pest --filter=Course` (Regressionstest der strukturell
  ähnlichsten bestehenden FormRequests/Feature-Tests, da T04 selbst noch
  keine eigenen Tests hat — die sind T08 vorbehalten) → 116 passed (330
  assertions), keine Regression.
- `stan`/`compat-check`: wie bereits in `task-T02.notes.md` dokumentiert,
  im Docker-Container nicht als `vendor/bin/*`-Binary vorhanden (Diskrepanz
  CLAUDE.md vs. tatsächlichem Projekt-Setup, vorbestehend, nicht durch T04
  verursacht). Manuelle PHP-8.2-Prüfung: keines der in CLAUDE.md
  Abschnitt 4.1 verbotenen 8.3-/8.4-Konstrukte verwendet (nur Nullsafe-
  Operator `?->`, First-Class-Standardsyntax, Standard-Typdeklarationen —
  alle seit PHP 8.0/8.1 verfügbar).

## Bekannte Einschränkungen / Hinweise für Folge-Tasks

- T04 selbst enthält keine formalen Pest-Tests für die neuen
  FormRequest-Klassen — das ist laut `tasks.md` explizit Aufgabe von T08
  (`AnnouncementApiTest.php`, inkl. `assertJsonValidationErrors(['displayDays'])`
  und Sanitizing-Verifikation über `assertDatabaseHas`).
- T06 (Controller) ist die einzige Stelle, die `$request->hasFile('image')`
  auswertet und den Upload-Pfad separat in `image_path` überführt — T04
  liefert dafür bewusst nur die validierte `UploadedFile`-Instanz über
  `$request->file('image')` (nicht Teil von `validatedSnakeCase()`).
