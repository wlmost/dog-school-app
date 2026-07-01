# Verification: fix-settings-upload-put-multipart

**Gesamtstatus:** ok

## Schritt 0: Strukturelle Validierung

```
$ openspec validate fix-settings-upload-put-multipart
Change 'fix-settings-upload-put-multipart' is valid
```

Strukturell einwandfrei. Inhaltlicher Realitätsabgleich folgt.

---

## Bestätigt

- `proposal.md` Z.12/`design.md` Z.14: "`frontend/src/api/settings.ts:35-54`,
  `settingsApi.updateSettings()`" → bestätigt, Funktion beginnt exakt bei
  Zeile 35 (`async updateSettings(...)`) und endet bei Zeile 54 (`},`) in
  `frontend/src/api/settings.ts`.
- `design.md` Z.29 / proposal.md Z.15: "`apiClient.put('/api/v1/settings',
  formData, …)`, Zeile 48" → bestätigt exakt in
  `frontend/src/api/settings.ts:48`: `apiClient.put<SettingsResponse>('/api/v1/settings', formData, {`.
- `design.md` Z.30: `headers: { 'Content-Type': 'multipart/form-data' }` →
  bestätigt in `frontend/src/api/settings.ts:49-51`.
- `proposal.md` Z.27 / `design.md` Z.177-178: "Route `PUT /api/v1/settings`
  (`backend/routes/api.php:195`)" → bestätigt wortwörtlich:
  `backend/routes/api.php:195`: `Route::put('/settings', [SettingsController::class, 'update']);`
  (innerhalb einer `prefix('settings')`- bzw. Admin-Middleware-Gruppe ab Z.192-194).
- `design.md` Z.50 / proposal.md Z.20-21: "`SettingsController::update()`,
  `backend/app/Http/Controllers/SettingsController.php:37-79`" → bestätigt:
  Methode beginnt Z.37 (`public function update(UpdateSettingsRequest $request): JsonResponse`)
  und endet Z.79 (schließende Klammer vor Leerzeile).
- `design.md` Z.51: "`$request->hasFile($key)` (Zeile 45)" → bestätigt in
  `backend/app/Http/Controllers/SettingsController.php:45`.
- `design.md` Z.46 / proposal.md Z.22: "`UpdateSettingsRequest::rules()`
  (`backend/app/Http/Requests/UpdateSettingsRequest.php:32-59`)" →
  bestätigt: `rules()`-Array beginnt Z.32 (`return [`) und endet Z.59 (`];`).
- `design.md` Z.44/47: alle Felder in `rules()` beginnen mit `sometimes`
  bzw. `sometimes, nullable` → bestätigt für alle 20 Felder in
  `UpdateSettingsRequest.php:34-58` (inkl. `company_logo` Z.45 und
  `company_favicon` Z.46, beide `sometimes, nullable`).
- `design.md` Z.24-25 / proposal.md Z.24-25: "`$request->enableHttpMethodParameterOverride()`,
  `backend/vendor/laravel/framework/.../Kernel.php:143`" → bestätigt exakt:
  `backend/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:143`
  innerhalb `handle()` (beginnt Z.138).
- Laravel-Version: `backend/composer.lock` meldet `laravel/framework`
  `v11.51.0`. Für Laravel 11 gibt es standardmäßig **kein**
  `app/Http/Kernel.php` mehr (bestätigt: `ls backend/app/Http/` enthält
  keine `Kernel.php`), stattdessen `backend/bootstrap/app.php`
  (`Application::configure(...)->withMiddleware(...)`). Geprüft, ob die
  `Illuminate\Foundation\Http\Kernel`-Klasse trotzdem verbindlich gebunden
  ist: `backend/vendor laravel/framework/.../Configuration/ApplicationBuilder.php:61`
  bindet `Illuminate\Contracts\Http\Kernel::class` an die Default-Klasse.
  `backend/bootstrap/app.php` überschreibt diese Bindung nicht (kein
  eigener Kernel referenziert). Der Method-Override-Aufruf in
  `Kernel::handle()` ist also tatsächlich aktiv — die Behauptung ist auch
  unter Laravel 11 korrekt, nicht nur "prinzipiell aus dem Framework-Gedächtnis".
- `design.md` Z.39-42 / Symfony-Body-Parsing-Behauptung: "`Request::createFromGlobals()`
  (`backend/vendor/symfony/http-foundation/Request.php:286-312`)... erst ab
  `PHP_VERSION_ID >= 80400` wird der Body generisch über `request_parse_body()`
  geparst" → bestätigt exakt: Methode beginnt Z.286, endet Z.312;
  Z.288 prüft `in_array($_SERVER['REQUEST_METHOD'], ['PUT','DELETE','PATCH','QUERY'])`,
  Z.292 `if (\PHP_VERSION_ID < 80400)`, Z.305 `request_parse_body()`. Für
  PHP < 8.4 und PUT wird bei Z.298 `$post = $_POST` übernommen (für PUT vom
  PHP-SAPI nicht befüllt) und bei Z.301 `$_FILES` direkt durchgereicht (für
  PUT ebenfalls von PHP nicht befüllt) — bestätigt exakt den behaupteten
  Mechanismus des Datenverlusts.
- `design.md` Z.159-160: Vorgeschlagener Fix-Code (POST statt PUT,
  `formData.append('_method', 'PUT')`) ist syntaktisch konsistent mit dem
  Ist-Zustand in `frontend/src/api/settings.ts` und verändert nur die
  behaupteten zwei Stellen (Methode + ein neues Feld).
- proposal.md Z.110-111 / "Nicht im Scope": "Keine Änderung an
  `frontend/src/api/trainingAttachments.ts` (verwendet bereits korrekt
  `POST`, ist nicht betroffen)" → bestätigt:
  `frontend/src/api/trainingAttachments.ts:54`:
  `const response = await apiClient.post('/api/v1/training-attachments', formData, {`.
  Kein `put`/`patch` mit FormData in dieser Datei.
- proposal.md Z.70-93 / `install.php`/`requirements-check.php`-Widerspruch:
  - `backend/requirements-check.php:58-60` → bestätigt exakt:
    `if ($major < 8 || ($major == 8 && $minor < 4)) { ... 'PHP 8.4.0 or higher is required'`.
  - `backend/requirements-check.php:1-19` Kopfkommentar "Upload this file...
    DELETE THIS FILE after successful installation" → bestätigt (Z.6, Z.10).
  - `install.php:851-863` (`checkServerRequirements()`) → bestätigt:
    Funktion beginnt Z.851, `'required' => '8.2'` und
    `version_compare(PHP_VERSION, '8.2.0', '>=')` exakt in Z.855-857.
  - Behauptung "`$canProceed` wird nicht durch die PHP-Version blockiert,
    solange `>= 8.2`" → bestätigt: `install.php:894-896` setzt
    `can_proceed = false` **nur** wenn `php_version.status === 'fail'`
    (also nur unter PHP 8.2). Bei PHP 8.2/8.3 bleibt `status = 'pass'`
    und `can_proceed = true` (sofern Extensions vorhanden).
  - `install.php:786`: `checkServerRequirements()` wird tatsächlich im
    Installer-Flow aufgerufen (nicht totes Code) → bestätigt.
- `design.md` Z.60-61: "`docker/php/Dockerfile:1` → `FROM php:8.4-fpm-alpine`"
  → bestätigt exakt.
- `design.md` Z.99, proposal.md (implizit): `DEPLOYMENT.md` Zeilen
  19/105/268/424/447 nennen PHP 8.4 → bestätigt: Z.19 "PHP 8.4+ mit
  benötigten Extensions", Z.105 "✓ PHP Version (8.4+)", Z.268 "Stellen
  Sie sicher, dass PHP 8.4+ aktiv ist", Z.424 "✓ PHP Version (8.4.x
  erforderlich)", Z.447 "**PHP**: 8.4 oder höher".
- `design.md` Z.99 / `PRODUCTION-SETUP.md:13` → bestätigt: Zeile 13 ist
  `- **PHP**: 8.4`. `PRODUCTION-SETUP.md:5` nennt zusätzlich
  `www.leisoft.de` als Ziel-URL (Bezug in `design.md` Z.102-104 auf
  "`PRODUCTION-SETUP.md:1-15`" ist damit im referenzierten Bereich korrekt).
- `design.md` Z.230-238: "Pest-Tests in
  `backend/tests/Feature/Api/SettingsValidationTest.php` verwenden
  `->putJson(...)` ... durchlaufen nicht `Request::createFromGlobals()`"
  → bestätigt doppelt: (1) `putJson` kommt in
  `SettingsValidationTest.php` an mehreren Stellen vor (Z.24, 34, 46, 58,
  71, 82, 94 u.a.); (2) `MakesHttpRequests::call()`
  (`backend/vendor/laravel/framework/.../Concerns/MakesHttpRequests.php:596-618`)
  baut den Request über `SymfonyRequest::create(...)` (Z.602), **nicht**
  über `createFromGlobals()` — die Behauptung, dass diese Tests die
  betroffene Bug-Klasse strukturell nicht erkennen können, ist technisch
  korrekt.
- proposal.md "Nicht im Scope" / tasks.md T01: Testmuster-Referenz
  `frontend/src/views/CourseDetailView.test.ts:16-30` → im Wesentlichen
  bestätigt: Der `vi.mock('@/api/client', ...)`-Block liegt tatsächlich in
  dieser Datei bei Z.16-22 (nicht exakt bis Z.30 — der Mock-Block für
  `@/api/client` endet bei Z.22, weitere `vi.mock`-Blöcke für
  `errorHandler`/`axios` folgen bis Z.34). Kleinere Zeilenungenauigkeit,
  Kernaussage (Muster existiert, mockt `@/api/client` mit `vi.fn()`) ist
  korrekt.

## Widerlegt

Keine widerlegten Tatsachenbehauptungen gefunden. Alle konkret mit
Datei:Zeile belegten Aussagen in `proposal.md` und `design.md` wurden
gegen den Code geprüft und stimmen.

## Nicht auffindbar

Keine. Alle referenzierten Dateien, Zeilen und Funktionen wurden gefunden.

## Neue Elemente (Plausibilität)

- `tasks.md` T01: legt `frontend/src/api/settings.test.ts` neu an →
  `frontend/src/api/` existiert bereits (`settings.ts`,
  `trainingAttachments.ts`, `client.ts` u.a.), keine gleichnamige Datei
  vorhanden (`find frontend/src/api -iname "settings.test.ts"` → kein
  Treffer). Pfad ist konsistent mit bestehenden Testdateien im Projekt
  (z. B. `frontend/src/views/CourseDetailView.test.ts` liegt neben der
  Quelldatei — gleiches Muster für `frontend/src/api/*.test.ts` wurde
  nicht gegengeprüft, da im Scope nicht referenziert; Pfadwahl ist aber
  plausibel und kollisionsfrei).

## Zusätzliche Prüfungen über den Auftrag hinaus (Vollständigkeit)

- **Weitere FormData-Aufrufstellen im Frontend:** `grep -rl "FormData"
  frontend/src` findet nur drei Dateien:
  `frontend/src/components/DogFormModal.vue`,
  `frontend/src/api/settings.ts`, `frontend/src/api/trainingAttachments.ts`.
  Geprüft, ob `DogFormModal.vue` denselben Bug hat (PUT + FormData): **nein**
  — `DogFormModal.vue:397` sendet den Hund-Datensatz per
  `apiClient.put(...)` aber mit einem reinen JSON-`payload`-Objekt (kein
  FormData); der Bild-Upload erfolgt getrennt per
  `apiClient.post('/api/v1/dogs/{id}/upload-image', formData, ...)`
  (`DogFormModal.vue:411`). Es gibt also **keinen zweiten,
  unentdeckten PUT+Multipart-Fall** im aktuell durchsuchten Frontend-Code
  — proposal.md/design.md haben den einzigen betroffenen Call-Site
  korrekt identifiziert (Bestätigung von design.md Z.259-261, YAGNI-Begründung
  "aktuell gibt es nur einen betroffenen Call-Site").
- **`settings-favicon-upload`-Capability-Konflikt geprüft:**
  `openspec/specs/settings-favicon-upload/spec.md` existiert und
  beschreibt Backend-Validierungsszenarien auf Basis von
  `PUT /api/v1/settings` (Z.22, 28) — das ist die logische Route und bleibt
  durch den Method-Override-Fix unverändert, kein Widerspruch zur neuen
  Spec `settings-file-upload-transport`.

## Empfehlung

Alle konkreten Tatsachenbehauptungen in `proposal.md`, `design.md` und
`tasks.md` wurden mit Datei:Zeile-Beleg gegengeprüft und treffen exakt zu
— inklusive der eher ungewöhnlichen, tiefgehenden Behauptungen (Symfony
`createFromGlobals()`-Verhalten, Laravel-11-Kernel-Bindung ohne
`app/Http/Kernel.php`, `install.php`/`requirements-check.php`-Widerspruch,
Test-Client-Limitation von `putJson`). Der Fix-Ansatz (POST +
`_method=PUT`-Override) ist architektonisch korrekt und durch die
Laravel-11-Kernel-Bindung tatsächlich aktiv, nicht nur behauptet. Die
Spec ist verlässlich genug zum Fortfahren — keine Korrekturen am Design
nötig. Einzige Randnotiz (keine Blocker): Der Testmuster-Verweis auf
`CourseDetailView.test.ts:16-30` ist um wenige Zeilen ungenau, das ist
für die Umsetzung irrelevant.
