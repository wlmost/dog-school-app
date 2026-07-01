# Proposal: fix-settings-upload-put-multipart

**Change-ID:** fix-settings-upload-put-multipart
**Typ:** Bugfix
**Pfad:** klein
**Status:** entwurf

---

## Was wird geändert?

`settingsApi.updateSettings()` in `frontend/src/api/settings.ts:35-54` sendet
Firmenlogo, Favicon und alle übrigen Settings-Felder aktuell als
`multipart/form-data`-Body via echtem HTTP-`PUT`
(`apiClient.put('/api/v1/settings', formData, …)`, Zeile 48).

Der Fix ändert den Transportweg auf ein HTTP-`POST` mit Laravels
Method-Override-Konvention: Der `FormData` erhält ein zusätzliches Feld
`_method=PUT`, und der Request wird via `apiClient.post(...)` statt
`apiClient.put(...)` gesendet. Route, Controller (`SettingsController::update()`,
`backend/app/Http/Controllers/SettingsController.php:37-79`) und
`UpdateSettingsRequest` (`backend/app/Http/Requests/UpdateSettingsRequest.php`)
bleiben unverändert — Laravel wendet das Method-Override
(`$request->enableHttpMethodParameterOverride()`,
`backend/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:143`)
vor dem Routing an, sodass die Route `PUT /api/v1/settings`
(`backend/routes/api.php:195`) weiterhin korrekt matcht.

---

## Warum wird es geändert?

Der Admin lädt Firmenlogo oder Favicon in den Einstellungen hoch und drückt
Speichern — die Datei "verschwindet" kommentarlos, ohne Fehlermeldung, weil
alle Felder in `UpdateSettingsRequest` `sometimes`/`nullable` sind.

**Root Cause (siehe Triage
`openspec/triage/20260701195331-settings-upload-put-multipart-lost.md` und
`design.md` dieses Changes):** PHP befüllt `$_POST`/`$_FILES` nativ nur bei
der HTTP-Methode `POST`. Für echte `PUT`-Requests mit
`multipart/form-data`-Body hängt die korrekte Body-Parsung von der
PHP-Version ab (`request_parse_body()`, verfügbar erst ab PHP 8.4) — und,
wie die Prüfung für diesen Change ergeben hat, zusätzlich vom tatsächlichen
Server-Setup, das von der CLAUDE.md-Matrix abweichen kann (Details siehe
`design.md`, Abschnitt "Widerspruch: Bug tritt auch in Produktion auf").

**Wichtig — der Fix ist unabhängig davon, welche PHP-Version tatsächlich in
Produktion läuft.** Er wechselt lediglich die Transportmethode (POST statt
PUT), sodass PHP `$_POST`/`$_FILES` in jedem Fall nativ befüllt — unabhängig
von PHP-Version, `request_parse_body()`-Verfügbarkeit oder sonstigen
Body-Parsing-Eigenheiten des jeweiligen Hosters.

---

## Umfang

| Bereich | Datei | Art |
|---------|-------|-----|
| Frontend | `frontend/src/api/settings.ts` | Änderung (Methode + FormData-Feld) |
| Frontend | `frontend/src/api/settings.test.ts` | Neu (Vitest) |

Kein Backend-Code ist zwingend nötig — Route, Controller und
`UpdateSettingsRequest` funktionieren mit Method-Override bereits korrekt
(siehe `design.md`, Abschnitt "Warum keine Backend-Änderung nötig ist").

---

## Offene Punkte für den Skeptiker

1. **Prod/PHP-Diskrepanz — Dokumentations-Inkonsistenz im Repo selbst:**
   Bei der Code-Prüfung für diesen Change wurde ein Widerspruch zwischen
   zwei Server-Anforderungsprüfungen im Repo gefunden:
   - `backend/requirements-check.php:58-60` verlangt hart PHP `>= 8.4`
     (`status = 'fail'` sonst).
   - `install.php:851-863` (`checkServerRequirements()`, tatsächlich vom
     Shared-Hosting-Installer aus `DEPLOYMENT.md` Schritt 3.2 aufgerufen)
     verlangt nur PHP `>= 8.2` (`'required' => '8.2'`,
     `version_compare(PHP_VERSION, '8.2.0', '>=')`).

   Das bedeutet: Eine reale Shared-Hosting-Installation über den
   dokumentierten Installer-Weg kann **klaglos auf PHP 8.2 oder 8.3**
   abgeschlossen werden, obwohl `DEPLOYMENT.md` und
   `PRODUCTION-SETUP.md` PHP 8.4 als Ziel nennen. Das erklärt plausibel,
   warum der User den Bug auch "in Produktion" beobachtet hat — die
   tatsächlich installierte PHP-Version auf dem konkreten Hoster
   (`PRODUCTION-SETUP.md:13`: `www.leisoft.de`) wurde nicht durch das
   Repo selbst erzwungen oder verifiziert.
   **Empfehlung an den Skeptiker/User:** zu klären, ob
   `install.php:857` auf `8.4.0` angehoben werden soll, damit Installer
   und `requirements-check.php` konsistent sind — das wäre aber ein
   **eigener, unabhängiger Change** (Infra/Deployment-Thema, nicht Teil
   dieses Bugfixes) und wird hier bewusst nicht mit-repariert (YAGNI /
   Scope-Trennung).
2. **Test-Lücke bei künftigen PUT+Multipart-Regressionen:** Die bestehenden
   Pest-`putJson`-Tests in
   `backend/tests/Feature/Api/SettingsValidationTest.php` durchlaufen
   Laravels In-Prozess-Test-Client und **nicht**
   `Request::createFromGlobals()` — sie hätten diesen Bug nie erkannt und
   werden es strukturell auch künftig nicht tun. Ein echter
   HTTP-Ebenen-Test (z. B. gegen einen laufenden PHP-Built-in-Server oder
   via Browser-/E2E-Test) wäre nötig, um diese Bug-Klasse dauerhaft
   abzusichern. Das ist als Empfehlung in `design.md` dokumentiert, aber
   **nicht Teil dieses kleinen Changes** — Umsetzung würde eine neue
   Test-Infrastruktur-Kategorie einführen und sprengt den Rahmen eines
   "kleinen" Fixes.

## Nicht im Scope

- Keine Änderung an Backend-Validierung oder Datenmodell.
- Keine Änderung an `frontend/src/api/trainingAttachments.ts` (verwendet
  bereits korrekt `POST`, ist nicht betroffen).
- Keine Erhöhung der Mindest-PHP-Version im Installer (`install.php`) oder
  Vereinheitlichung mit `requirements-check.php` — siehe "Offene Punkte"
  oben.
- Keine neue HTTP-Ebenen-Test-Infrastruktur (Playwright o. ä.) — nur
  Empfehlung, kein Umsetzungsauftrag in diesem Change.
