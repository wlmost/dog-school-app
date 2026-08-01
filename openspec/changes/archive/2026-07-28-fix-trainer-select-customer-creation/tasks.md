# Tasks für fix-trainer-select-customer-creation

## T01: Schlanker, rollen-offener Trainer-Options-Endpoint

- **Agent:** dev-php
- **Dateien:**
  - `backend/routes/api.php` (neue Route vor der `can:admin`-`apiResource('trainers', ...)`-Gruppe, ca. Zeile 193)
  - `backend/app/Http/Controllers/Api/TrainerController.php` (neue Methode `options()`)
  - `backend/app/Http/Resources/TrainerOptionResource.php` (neu)
  - `backend/tests/Feature/TrainerApiTest.php` (Ergänzung um neue `describe`-Blöcke für `/api/v1/trainers/options`; **bestehende Tests unverändert lassen**)
- **Abhängigkeiten:** keine
- **Beschreibung:**
  Neue Route `GET /api/v1/trainers/options`, registriert **vor** der
  bestehenden `Route::apiResource('trainers', TrainerController::class)`
  innerhalb der `can:admin`-Gruppe (sonst Kollision mit der
  `{trainer}`-Wildcard der `show`-Route — siehe `design.md`, Decision 1,
  und das Präzedenzmuster bei `/customers/profile` in
  `backend/routes/api.php:100-101`). Middleware `can:trainer` (bestehendes
  Gate aus `backend/app/Providers/AppServiceProvider.php:65-67`, deckt
  Trainer **und** Admin ab — kein neues Gate anlegen).
  Neue Methode `TrainerController::options()`: lädt
  `User::query()->where('role', 'trainer')->orderBy('last_name')->orderBy('first_name')->get()`
  (identische Filterlogik wie `TrainerController::index()`, aber ohne
  Such-Parameter) und gibt sie über die neue `TrainerOptionResource`
  zurück.
  Neue Resource-Klasse `TrainerOptionResource` liefert **ausschließlich**
  `id`, `firstName`, `lastName`, `fullName` — keine `email`, `phone`,
  `mobilePhone`, `street`, `postalCode`, `city`, `country`,
  `qualifications`, `specializations` (siehe `UserResource` als
  Negativ-Beispiel, `backend/app/Http/Resources/UserResource.php:24-45`,
  dessen volle Feldliste hier bewusst NICHT übernommen wird).
  Bestehende Route `GET /api/v1/trainers` (voller `UserResource`,
  `can:admin`) und `backend/tests/Feature/TrainerApiTest.php:56-61`
  bleiben **unverändert**.
  PHP-Kompatibilität: keine 8.3/8.4-Features (kein `#[\Override]`, keine
  typed class constants, keine Property Hooks) — `composer compat-check`
  muss grün bleiben.
- **Akzeptanzkriterien:**
  - [x] `GET /api/v1/trainers/options` liefert für Admin HTTP 200 mit reduzierten Feldern (`id`, `firstName`, `lastName`, `fullName`)
  - [x] `GET /api/v1/trainers/options` liefert für Trainer HTTP 200 mit denselben reduzierten Feldern
  - [x] `GET /api/v1/trainers/options` liefert für Customer HTTP 403
  - [x] `GET /api/v1/trainers/options` liefert für unauthentifizierte Requests HTTP 401
  - [x] Response-Payload enthält **nachweislich nicht** `email`, `phone`, `mobilePhone`, `street`, `postalCode`, `city`, `country`, `qualifications`, `specializations` (expliziter Assert im Test, z. B. `assertJsonMissing`/Struktur-Assert)
  - [x] Bestehende Tests in `backend/tests/Feature/TrainerApiTest.php` bleiben unverändert und weiterhin grün (insb. Zeilen 56-61: Trainer erhält weiterhin 403 auf `GET /api/v1/trainers`)
  - [x] `composer qa` (lint + stan + compat-check + pest) läuft grün innerhalb der Docker-Umgebung — siehe `task-T01.notes.md`: Script `composer qa` existiert nicht im Projekt (vorbestehende Lücke), ersatzweise Pint/Pest/`php -l` grün ausgeführt

## T02: CustomerFormModal.vue auf neuen Endpoint umstellen + Fehler-Feedback

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/CustomerFormModal.vue` (Funktion `loadTrainers()`, Zeile 338-345)
  - `frontend/src/components/CustomerFormModal.test.ts` (neu — für diese Komponente existiert noch keine Testdatei)
- **Abhängigkeiten:** T01 (benötigt den Endpoint-Vertrag `GET /api/v1/trainers/options`; kann mit gemocktem `apiClient` parallel entwickelt werden, End-to-End-Verifikation erst nach T01)
- **Beschreibung:**
  `loadTrainers()` ruft künftig `GET /api/v1/trainers/options` statt
  `GET /api/v1/trainers` auf. Response-Parsing bleibt unverändert
  (`response.data.data || response.data`, Zeile 341).
  Der `catch`-Block (aktuell Zeile 342-343, nur `console.error(...)`) ruft
  zusätzlich `handleApiError(err, 'Fehler beim Laden der Trainerliste')`
  auf — `handleApiError` ist bereits importiert
  (`frontend/src/components/CustomerFormModal.vue:240`) und an anderer
  Stelle in derselben Datei bereits im Einsatz (z. B. `saveDog`,
  `removeDog`). `console.error` darf zusätzlich zur Entwickler-Diagnose
  stehen bleiben.
  Die bestehende Vorauswahl-Logik (Zeile 291-294:
  `form.value.trainer_id = currentUser.value.id`, wenn
  `currentUser.value?.role === 'trainer'`) bleibt unverändert — sie wird
  durch den nun funktionierenden Datenabruf automatisch wirksam. Das
  Select-Feld bleibt änderbar (kein `disabled`).
- **Akzeptanzkriterien:**
  - [x] `loadTrainers()` ruft `/api/v1/trainers/options` auf
  - [x] Bei Erfolg wird die Trainer-Select-Box für die Rolle `trainer` (nicht nur `admin`) mit Optionen befüllt
  - [x] Bei einem fehlschlagenden Request wird `handleApiError` mit einer Nutzer-verständlichen Fehlermeldung aufgerufen (Toast sichtbar), nicht nur `console.error`
  - [x] Vorauswahl-Test: Für `currentUser.role === 'trainer'` ist `form.trainer_id` nach dem Laden auf die eigene User-ID vorbelegt, das Select bleibt aber bedienbar (keine `disabled`-Eigenschaft)
  - [x] Neue Vitest-Tests in `CustomerFormModal.test.ts` decken: erfolgreiches Laden, 403-Fehlerfall mit Toast, Vorauswahl-Verhalten für Trainer-Rolle
  - [~] `npm run lint`, `npm run test`, `npm run build` laufen ohne Fehler/Warnings — siehe `task-T02.notes.md`: Script `lint` existiert nicht im Projekt (vorbestehende Lücke), `test` und `build` grün

## T03: CourseFormModal.vue auf neuen Endpoint umstellen + Fehler-Feedback

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/CourseFormModal.vue` (Funktion `loadTrainers()`, Zeile 276-283; Template-Fallback Zeile 45)
  - `frontend/src/components/CourseFormModal.test.ts` (neu — für diese Komponente existiert noch keine Testdatei)
- **Abhängigkeiten:** T01 (wie T02)
- **Beschreibung:**
  Gleiches Muster wie T02: `loadTrainers()` ruft künftig
  `GET /api/v1/trainers/options` auf; Response-Parsing bleibt unverändert
  (`response.data.data`, Zeile 279).
  Der `catch`-Block (aktuell Zeile 280-281, nur `console.error(...)`) ruft
  zusätzlich `handleApiError(err, 'Fehler beim Laden der Trainerliste')`
  auf — bereits importiert (`frontend/src/components/CourseFormModal.vue:210`).
  Zusätzlich: Der Anzeige-Fallback im Template
  (`frontend/src/components/CourseFormModal.vue:45`:
  `{{ trainer.fullName || trainer.email }}`) wird auf
  `` {{ trainer.fullName || `${trainer.firstName} ${trainer.lastName}` }} ``
  umgestellt, weil `email` im neuen, reduzierten Endpoint nicht mehr
  geliefert wird (Datenschutz-Entscheidung aus T01) und der bisherige
  Fallback sonst `undefined` anzeigen würde, falls `fullName` einmal leer
  ist. Analog zum bereits bestehenden Muster in
  `CustomerFormModal.vue:106`.
- **Akzeptanzkriterien:**
  - [x] `loadTrainers()` ruft `/api/v1/trainers/options` auf
  - [x] Bei Erfolg wird die Trainer-Select-Box für die Rolle `trainer` (nicht nur `admin`) mit Optionen befüllt
  - [x] Bei einem fehlschlagenden Request wird `handleApiError` mit einer Nutzer-verständlichen Fehlermeldung aufgerufen (Toast sichtbar), nicht nur `console.error`
  - [x] Anzeige-Fallback nutzt `firstName`/`lastName` statt `email`
  - [x] Neue Vitest-Tests in `CourseFormModal.test.ts` decken: erfolgreiches Laden, 403-Fehlerfall mit Toast, korrekte Namensanzeige ohne `email`-Feld
  - [~] `npm run lint`, `npm run test`, `npm run build` laufen ohne Fehler/Warnings — `npm run lint` existiert projektweit nicht (vorbestehende Lücke, siehe `task-T03.notes.md`), `test`/`build` grün
