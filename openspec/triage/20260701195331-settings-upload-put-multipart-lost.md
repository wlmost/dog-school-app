# Triage: Firmenlogo/Favicon-Upload verschwindet nach Speichern (PUT + multipart)

**Pfad:** klein
**Geschätzter Umfang:** ~1-2 Kern-Dateien (Frontend), Sprachen: JavaScript/TypeScript (Vue), keine PHP-Änderung zwingend nötig
**Risiko:** mittel — betrifft Firmen-Branding (Logo/Favicon auf Rechnungen/E-Mails), Datenverlust ist still (kein Fehler wird angezeigt), Bug ist PHP-Versions-abhängig und trifft genau die in CLAUDE.md Abschnitt 3 als kritisch markierte Demo-Umgebung (PHP 8.2/8.3).
**Klarheit:** klar — der gemeldete Effekt (Dateien "verschwinden" ohne Fehlermeldung nach Speichern) ist eindeutig, und die Ursache konnte im Code zweifelsfrei nachvollzogen werden.

## Anforderung (Zusammenfassung)

Der Admin lädt Firmenlogo und Favicon in den Einstellungen hoch, drückt
Speichern — die Dateien werden augenscheinlich nicht gespeichert
("verschwinden"). Es soll geklärt werden, ob dies eine Regression/Fortsetzung
des kürzlich gemergten Fixes (PR #64, Commit d80d7ec, Change
`fix-favicon-ico-upload-bug`) ist oder ein neues, eigenständiges Problem.

## Untersuchung / Befund

**Betroffene Stellen:**
- `frontend/src/api/settings.ts:35-54` (`settingsApi.updateSettings`) — sendet
  `FormData` (inkl. Files) via `apiClient.put(...)`, also als echtes HTTP-PUT
  mit `Content-Type: multipart/form-data`.
- `frontend/src/api/client.ts` — kein Interceptor für Method-Override
  vorhanden.
- `backend/routes/api.php:195` — `Route::put('/settings', [SettingsController::class, 'update'])`.
- `backend/app/Http/Controllers/SettingsController.php` — verarbeitet
  `$request->hasFile($key)` korrekt, sofern die Datei überhaupt im Request
  ankommt.
- `backend/vendor/symfony/http-foundation/Request.php:286-312`
  (`createFromGlobals`) — **Kernbefund:** Für die Methoden `PUT`/`PATCH`/`DELETE`
  parst Symfony den Body nur bei `PHP_VERSION_ID >= 80400` generisch via
  die neue PHP-8.4-Funktion `request_parse_body()` (inkl. Multipart/Dateien).
  Auf **PHP < 8.4** wird der Body für `multipart/form-data` **gar nicht**
  geparst (`$post = $_POST` bzw. `$_FILES`, die von PHP selbst nur bei
  Methode `POST` befüllt werden) — Felder und Dateien gehen also komplett
  verloren, ohne dass eine Validierungsfehlermeldung entsteht (alle Felder
  in `UpdateSettingsRequest` sind `sometimes`/`nullable`).
- **Projektrelevanz:** Laut CLAUDE.md Abschnitt 3 läuft die Demo-Umgebung
  auf **PHP 8.2/8.3** (Produktion und lokales Docker auf 8.4,
  `docker/php/Dockerfile:1` → `php:8.4-fpm-alpine`). Die lokale Host-PHP-CLI
  in dieser Umgebung meldet `PHP 8.3.30` — d.h. der Bug ist auf genau den
  Umgebungen reproduzierbar, die CLAUDE.md als kritischen kleinsten
  gemeinsamen Nenner definiert.
- **Warum die bestehenden Tests den Bug nicht fangen:** Die in PR #64
  hinzugefügten Tests (`backend/tests/Feature/Api/SettingsValidationTest.php`)
  nutzen Laravels `->putJson(...)`-Test-Helper. Dieser baut das
  `Illuminate\Http\Request`-Objekt direkt im Prozess auf und durchläuft
  **nicht** `Request::createFromGlobals()` bzw. die reale PHP-SAPI-
  Body-Parsung — die Tests sind also grün, unabhängig von der PHP-Version
  und unabhängig von diesem Bug.

**Abgrenzung zum vorherigen Fix (d80d7ec / `fix-favicon-ico-upload-bug`):**
Der vorherige Fix behob (a) eine MIME-Type-Ablehnung von `.ico`-Dateien in
`UpdateSettingsRequest` und (b) einen UI-Bug, bei dem ein 422-Speicherfehler
das gesamte Formular ausblendete. Der jetzt gemeldete Effekt zeigt **keine**
Fehlermeldung — die Dateien verschwinden kommentarlos. Das ist konsistent
mit der neuen Ursache (Request-Body kommt gar nicht erst mit Dateien beim
Server an) und **nicht** mit dem vorherigen Bug. **Einschätzung: neues,
eigenständiges Problem**, keine Regression des d80d7ec-Fixes — beide Bugs
betreffen zufällig denselben Feature-Bereich (Settings-Datei-Upload), haben
aber unterschiedliche Root Causes.

**Weitere Prüfung:** `frontend/src/api/trainingAttachments.ts` verwendet für
Datei-Uploads korrekt `apiClient.post(...)` — dort besteht das Problem nicht.
Es ist aktuell der einzige weitere FormData-Uploadpfad im Frontend.

## Rückfragen an den User

_(Klarheit = klar, daher keine zwingenden Rückfragen. Optional zur
Priorisierung:)_
- Wurde der Bug auf der Demo-Umgebung (Shared Hosting, PHP 8.2/8.3)
  beobachtet, oder auch lokal/produktiv (PHP 8.4)? Das würde die Diagnose
  zusätzlich bestätigen, ist aber für den Fix nicht blockierend.

## Empfohlene nächste Aktion

`@architect` (Modus A) soll einen kleinen Change (`fix-settings-upload-put-multipart`)
aufsetzen mit voraussichtlich folgenden Tasks:

1. **dev-javascript:** `frontend/src/api/settings.ts` — `updateSettings()`
   so ändern, dass statt eines echten HTTP-PUT mit Multipart-Body ein
   POST mit Laravel-Method-Override (`_method=PUT` als zusätzliches
   FormData-Feld) gesendet wird. Das ist PHP-versionsunabhängig, da es nicht
   von `request_parse_body()` (PHP 8.4) abhängt, sondern von Laravels
   `Request::enableHttpMethodParameterOverride()` (bereits aktiv, siehe
   `backend/vendor/laravel/framework/.../Http/Kernel.php:143`).
2. **Test-Strategie (wichtig, an Tester/Architekt weiterzugeben):** Die
   bestehenden Pest-`putJson`-Tests reichen NICHT aus, um diese Bug-Klasse
   zu verhindern (siehe Befund oben). Empfehlung: Vitest-Test auf
   `settingsApi.updateSettings`, der prüft, dass tatsächlich ein POST mit
   `_method=PUT` gesendet wird; optional ergänzend ein echter
   HTTP-Ebenen-Test (z. B. Playwright E2E gegen den echten PHP-Server) statt
   nur Laravels In-Prozess-Test-Client.
3. Kein Backend-Code zwingend nötig (Controller verarbeitet `hasFile()`
   bereits korrekt), aber Reviewer soll bestätigen, dass die PUT-Route in
   `backend/routes/api.php:195` mit method-override kompatibel bleibt
   (ist sie, da Laravel das Override vor dem Routing anwendet).

Kein Skeptiker-Overkill nötig aufgrund der geringen Dateizahl, aber wegen
der PHP-Versions-Kritikalität sollte der Architekt im `design.md` explizit
vermerken, dass die Lösung auf PHP 8.2, 8.3 und 8.4 identisch funktionieren
muss (CLAUDE.md Abschnitt 3/9.5).
