# Design: fix-settings-upload-put-multipart

## Architekturübersicht

Ein einzelner, isolierter Fix in einer Frontend-API-Funktion. Keine
Abhängigkeit zu anderen Modulen außer dem bereits vorhandenen
`apiClient` (`frontend/src/api/client.ts`) und der bestehenden Backend-Route.
Keine Schema-, keine DB-Änderung.

---

## Root-Cause-Analyse

**Datei:** `frontend/src/api/settings.ts:35-54`

```ts
async updateSettings(settings: Record<string, any>) {
  const formData = new FormData()
  Object.entries(settings).forEach(([key, value]) => {
    if (value !== null && value !== undefined) {
      if (value instanceof File) {
        formData.append(key, value)
      } else {
        formData.append(key, String(value))
      }
    }
  })

  const response = await apiClient.put<SettingsResponse>('/api/v1/settings', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return response.data
},
```

`apiClient.put(...)` (Axios) sendet ein echtes HTTP-`PUT` mit
`Content-Type: multipart/form-data`. PHP füllt `$_POST` und `$_FILES` nur
bei der SAPI-Methode `POST` automatisch. Bei `PUT`/`PATCH`/`DELETE` hängt
die Body-Parsung von Symfonys `Request::createFromGlobals()`
(`backend/vendor/symfony/http-foundation/Request.php:286-312`) ab: erst ab
`PHP_VERSION_ID >= 80400` wird der Body generisch über die native
PHP-8.4-Funktion `request_parse_body()` geparst (inkl. Multipart/Dateien).
Auf PHP < 8.4 bleiben `$_POST` und `$_FILES` für `PUT`-Requests leer — die
Felder und Dateien gehen ohne Fehlermeldung verloren, weil alle Regeln in
`UpdateSettingsRequest::rules()`
(`backend/app/Http/Requests/UpdateSettingsRequest.php:32-59`) mit
`sometimes`/`nullable` beginnen: ein fehlendes Feld ist aus Validierungssicht
kein Fehler.

`backend/app/Http/Controllers/SettingsController.php:37-79` prüft korrekt
per `$request->hasFile($key)` (Zeile 45) — das Problem liegt ausschließlich
davor, in der Body-Parsung durch PHP/Symfony, nicht im Anwendungscode des
Controllers.

---

## Widerspruch: Bug tritt auch in Produktion auf

Die ursprüngliche Triage-Annahme war: Produktion läuft laut CLAUDE.md
Abschnitt 3 auf PHP 8.4 (`docker/php/Dockerfile:1` →
`FROM php:8.4-fpm-alpine` für die lokale/Docker-Referenzumgebung), also
sollte `request_parse_body()` dort den Multipart-PUT-Body korrekt parsen
und der Bug auf Produktion nicht auftreten. Der User berichtet jedoch
ausdrücklich, dass der Effekt **auch in Produktion** auftritt.

**Befund aus der Code-Prüfung — konkrete, im Repo nachweisbare Ursache für
den Widerspruch:**

Es gibt **zwei widersprüchliche PHP-Versionsprüfungen** für den
Shared-Hosting-Weg im Repo selbst:

1. `backend/requirements-check.php:18` (Kommentar) und `:58-60` (Logik):
   ```php
   if ($major < 8 || ($major == 8 && $minor < 4)) {
       $results['php_version']['status'] = 'fail';
       $results['php_version']['message'] = 'PHP 8.4.0 or higher is required';
   ```
   Dieses Skript verlangt hart PHP `>= 8.4` und lehnt alles darunter ab.
   Es ist aber ein **optionales, manuell hochzuladendes Diagnose-Tool**
   (siehe Kopfkommentar `requirements-check.php:1-19`: "Upload this file
   ... DELETE THIS FILE after successful installation") — es wird nicht
   zwingend ausgeführt.

2. `install.php:851-863` (`checkServerRequirements()`), der Installer, der
   laut `DEPLOYMENT.md` Schritt 2 ("Schritt 2: Server-Anforderungen") den
   eigentlichen, dokumentierten Installationsweg für Shared Hosting bildet:
   ```php
   'php_version' => [
       'required' => '8.2',
       'current' => PHP_VERSION,
       'status' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'pass' : 'fail'
   ],
   ```
   Dieser **tatsächlich verwendete** Installer akzeptiert PHP `>= 8.2` ohne
   Warnung (`status: 'pass'`) und lässt die Installation ungehindert
   fortsetzen (`$canProceed` wird nicht durch die PHP-Version blockiert,
   solange `>= 8.2`).

`DEPLOYMENT.md:19,105,268,424,447` und `PRODUCTION-SETUP.md:13` behaupten
"PHP 8.4 erforderlich" bzw. nennen 8.4 als Ziel-Version — das ist jedoch
reine Dokumentation, **nicht durch den tatsächlich benutzten Installer
erzwungen**. Ein Shared-Hosting-Kunde (konkret: die in
`PRODUCTION-SETUP.md:1-15` beschriebene Installation auf
`www.leisoft.de`, PHP laut Dokument "8.4") kann den Installations-Assistenten
klaglos auf einem Hoster durchlaufen, dessen tatsächliche PHP-Version im
Control-Panel auf 8.2 oder 8.3 steht — der Installer meldet dort `pass`,
nicht `fail` oder auch nur `warning`. Da Shared-Hosting-Anbieter PHP-Versionen
über ein Control-Panel setzen (vgl. CLAUDE.md Abschnitt 3, "Verschiedene
Shared-Hosting-Anbieter führen unterschiedliche PHP-Versionen"), ist es
plausibel, dass die tatsächlich konfigurierte Produktions-PHP-Version von
der in `PRODUCTION-SETUP.md` dokumentierten Absicht (8.4) abweicht, ohne
dass dies irgendwo im Installationsprozess auffällt.

**Einordnung:** Damit erklärt sich der Widerspruch am plausibelsten durch
Erklärung 1 aus dem Architekten-Auftrag ("tatsächlich laufende PHP-Version
weicht von der CLAUDE.md-Matrix/Dokumentation ab") — mit einer konkreten,
im Code nachweisbaren Ursache dafür: der produktiv genutzte Installer
(`install.php`) prüft nur auf `>= 8.2`, nicht auf `>= 8.4`, im Widerspruch
zu `requirements-check.php` und den Markdown-Dokumenten. Ob zusätzlich
Erklärung 2 (Symfony-/`request_parse_body()`-Edge-Cases auch unter 8.4) oder
Erklärung 3 (Reverse-Proxy/.htaccess-Eigenheiten) eine Rolle spielen, lässt
sich ohne Zugriff auf die konkrete Produktionsumgebung nicht abschließend
verifizieren — für den Fix ist das aber irrelevant (siehe nächster
Abschnitt).

**Konsequenz für diesen Change:** Die Lösung darf sich **nicht** auf die
Annahme "betrifft nur PHP < 8.4" stützen. Sie muss unabhängig von der
tatsächlichen PHP-Version in jeder Umgebung (Entwicklung, Demo, Produktion)
funktionieren.

---

## Lösungsansatz: POST + Method-Override statt echtem PUT

**Datei:** `frontend/src/api/settings.ts:35-54`

```ts
async updateSettings(settings: Record<string, any>) {
  const formData = new FormData()
  formData.append('_method', 'PUT')

  Object.entries(settings).forEach(([key, value]) => {
    if (value !== null && value !== undefined) {
      if (value instanceof File) {
        formData.append(key, value)
      } else {
        formData.append(key, String(value))
      }
    }
  })

  const response = await apiClient.post<SettingsResponse>('/api/v1/settings', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return response.data
},
```

Änderungen im Detail:
1. `apiClient.put(...)` → `apiClient.post(...)`.
2. Zusätzliches `FormData`-Feld `_method` mit Wert `'PUT'` (Laravels
   Konvention für Method-Spoofing).

### Warum das PHP-versionsunabhängig funktioniert

Der Request kommt jetzt als echtes HTTP-`POST` mit
`multipart/form-data`-Body an. PHP füllt `$_POST`/`$_FILES` bei `POST` immer
nativ, unabhängig von der PHP-Version — das ist SAPI-Standardverhalten seit
PHP 4 und hat nichts mit `request_parse_body()` (PHP 8.4) zu tun. Laravels
`Illuminate\Foundation\Http\Kernel::handle()` ruft
`$request->enableHttpMethodParameterOverride()` auf
(`backend/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:143`),
**bevor** der Request geroutet wird. Symfonys
`Request::getMethod()`/`Request::setMethod()`-Override-Mechanismus liest
dafür das `_method`-Feld aus dem geparsten `$_POST`-Body und behandelt den
Request intern als `PUT` — der Router matcht also weiterhin gegen
`Route::put('/settings', [SettingsController::class, 'update'])`
(`backend/routes/api.php:195`), ohne dass Route oder Controller geändert
werden müssen.

Damit ist der Fix unabhängig davon, ob die tatsächliche Produktions-PHP-Version
8.2, 8.3 oder 8.4 ist, und unabhängig davon, ob die in "Widerspruch"
beschriebene Installer-Inkonsistenz behoben wird oder nicht — der Fix
umgeht die eigentliche Fehlerquelle (PUT + Multipart-Body-Parsing) komplett,
statt sich auf eine bestimmte PHP-Version zu verlassen.

### Warum keine Backend-Änderung nötig ist

- Die Route bleibt `PUT` (`backend/routes/api.php:195`) — das ist korrekt
  und muss nicht geändert werden, weil Laravel das Override vor dem Routing
  anwendet.
- `SettingsController::update(UpdateSettingsRequest $request)`
  (`backend/app/Http/Controllers/SettingsController.php:37`) erhält
  weiterhin ein `Request`-Objekt, bei dem `$request->method()` `'PUT'`
  zurückgibt (durch das Override), `$request->hasFile($key)` funktioniert
  unverändert.
- `UpdateSettingsRequest` benötigt keine Anpassung — Method-Override
  betrifft nur die HTTP-Methode, nicht die Validierungsregeln.

---

## Kompatibilitätsprüfung (CLAUDE.md Abschnitt 3/4)

- **PHP-Kompatibilität:** Keine PHP-Code-Änderung in diesem Change. Der Fix
  wirkt rein auf Transport-Ebene (Frontend). Betrifft PHP 8.2, 8.3, 8.4
  identisch (siehe oben).
- **DB-Kompatibilität:** Keine DB-Änderung, kein raw SQL, keine Migration.
  Nicht zutreffend.
- **Shared-Hosting:** Kein neues Server-Feature nötig, kein Cron, keine
  Queue, kein Worker. Method-Override ist reines Laravel-Framework-Verhalten,
  bereits aktiv.
- **Frontend/Vite:** Keine neue Abhängigkeit, keine Build-Konfigurationsänderung.

---

## Test-Strategie

### In diesem Change umgesetzt

**Vitest-Test** für `settingsApi.updateSettings()`
(`frontend/src/api/settings.test.ts`, neu): mockt `@/api/client` (Muster wie
in `frontend/src/views/CourseDetailView.test.ts:16-30`) und prüft:
- `apiClient.post` wird aufgerufen (nicht `apiClient.put`).
- Das `FormData`-Objekt im Aufruf enthält ein Feld `_method` mit Wert
  `'PUT'`.
- Datei- und Text-Felder landen weiterhin korrekt im `FormData`.

### Bewusst nicht in diesem Change umgesetzt (Empfehlung für später)

Die bestehenden Pest-Tests in
`backend/tests/Feature/Api/SettingsValidationTest.php` verwenden Laravels
`->putJson(...)`-Helper. Dieser baut das `Illuminate\Http\Request`-Objekt
direkt im Prozess auf (`Illuminate\Foundation\Testing\Concerns\MakesHttpRequests`)
und durchläuft **nicht** `Request::createFromGlobals()` bzw. die reale
PHP-SAPI-Body-Parsung — solche Tests bleiben grün, unabhängig von der
PHP-Version und unabhängig von dieser ganzen Bug-Klasse. Sie sind also
weder ein Nachweis dafür, dass der ursprüngliche Bug bestand, noch dass der
Fix wirkt.

**Empfehlung (nicht Teil dieses Changes):** Ein echter HTTP-Ebenen-Test, der
die reale Server-Verarbeitung abbildet — z. B. ein Test, der einen
laufenden PHP-Built-in-Server oder PHP-FPM-Prozess über einen echten HTTP-Client
(cURL/Guzzle) anspricht, statt Laravels In-Prozess-Test-Client zu verwenden,
oder ein Playwright-/Browser-E2E-Test gegen die Docker-Umgebung. Das ist ein
eigenständiges Test-Infrastruktur-Thema (neue Test-Kategorie, ggf. neue
CI-Pipeline-Stufe) und sprengt den Rahmen dieses kleinen Bugfix-Changes —
siehe `proposal.md`, Abschnitt "Offene Punkte für den Skeptiker".

---

## Entwurfsmuster-Bezug

- **KISS:** Minimalinvasiver Fix auf Transport-Ebene, keine neue
  Abstraktion, keine Interceptor-Logik in `client.ts` für alle Requests
  (das wäre unnötige globale Kopplung für ein Problem, das nur einen
  einzigen Upload-Pfad betrifft).
- **YAGNI:** Keine generische "Multipart-PUT-zu-POST"-Middleware in
  `apiClient` gebaut, obwohl das denkbar wäre — aktuell gibt es nur einen
  betroffenen Call-Site (`settings.ts`). Falls künftig weitere
  PUT+Multipart-Fälle entstehen, kann diese Middleware bei Bedarf
  extrahiert werden (Boy-Scout-Prinzip, nicht vorab spekulativ).
- **Single Responsibility:** `settingsApi.updateSettings()` bleibt allein
  dafür zuständig, Settings-Daten korrekt zu serialisieren und zu senden;
  die Transport-Fix-Logik gehört genau dorthin, nicht in eine
  projektweite Axios-Konfiguration.
