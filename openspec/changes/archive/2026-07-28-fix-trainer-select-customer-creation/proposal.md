## Why

Beim Anlegen eines neuen Kunden (und ebenso eines neuen Kurses) soll ein
Trainer zugewiesen werden können. Die Trainer-Select-Box zeigt für
eingeloggte **Trainer** (nicht Admins) aktuell nur "Kein Trainer
zugewiesen", obwohl Trainer im System existieren.

Root Cause (durch `triage` mit Datei:Zeile belegt,
`openspec/triage/20260728-trainer-select-kundenanlage.md`):

- `frontend/src/components/CustomerFormModal.vue:338-345` (`loadTrainers()`)
  ruft `GET /api/v1/trainers` auf. Diese Route liegt in
  `backend/routes/api.php:193-196` hinter `Route::middleware('can:admin')`
  — nur Admins dürfen sie aufrufen. Das Gate ist definiert in
  `backend/app/Providers/AppServiceProvider.php:61-63`
  (`Gate::define('admin', fn ($user) => $user->isAdmin());`).
- Für eingeloggte Trainer schlägt der Aufruf mit HTTP 403 fehl. Der Fehler
  wird in `CustomerFormModal.vue:342-343` nur mit `console.error(...)`
  geloggt und verschluckt — `trainers.value` bleibt leer, die Select-Box
  zeigt keine Optionen.
- Die bereits vorhandene Vorauswahl-Logik
  (`CustomerFormModal.vue:291-294`: `form.value.trainer_id =
  currentUser.value.id`, wenn `currentUser.value?.role === 'trainer'`)
  läuft dadurch ins Leere, weil kein passendes `<option>` existiert.
- `frontend/src/components/CourseFormModal.vue:276-283` hat dieselbe
  Abhängigkeit von `GET /api/v1/trainers` und denselben 403-Fehler für die
  Rolle `trainer` bei der Trainerzuweisung in der Kurserstellung.
- `UserResource` (`backend/app/Http/Resources/UserResource.php:24-45`)
  liefert volle Profildaten (E-Mail, Telefon, Adresse, Qualifikationen,
  Spezialisierungen) — vermutlich der Grund, warum die bisherige Route
  bewusst auf Admins beschränkt wurde
  (getestet in `backend/tests/Feature/TrainerApiTest.php:56-61`).

Der Admin-Fall funktioniert bereits korrekt
(`backend/tests/Feature/TrainerApiTest.php:17-22`) und ist **nicht**
Gegenstand dieses Changes.

## What Changes

- **Neuer, schlanker Endpoint** `GET /api/v1/trainers/options`, zugänglich
  für die Rollen `admin` **und** `trainer` (nicht `customer`, nicht
  unauthentifiziert). Liefert ausschließlich `id`, `firstName`, `lastName`,
  `fullName` — keine E-Mail, kein Telefon, keine Adresse, keine
  Qualifikationen/Spezialisierungen.
- Die bestehende Route `GET /api/v1/trainers` (voller `UserResource`,
  `can:admin`) bleibt **unverändert** bestehen; der bestehende Test
  `backend/tests/Feature/TrainerApiTest.php` wird nicht verändert, nur um
  Tests für den neuen Endpoint **ergänzt**.
- `frontend/src/components/CustomerFormModal.vue` und
  `frontend/src/components/CourseFormModal.vue` werden auf den neuen
  Endpoint umgestellt.
- Fehlerbehandlung beim Laden der Trainerliste in beiden Formularen: statt
  nur `console.error(...)` wird der bestehende Error-Toast-Mechanismus
  (`handleApiError` aus `frontend/src/utils/errorHandler.ts`, bereits in
  beiden Dateien importiert bzw. leicht ergänzbar) genutzt, damit der User
  einen Ladefehler sichtbar mitgeteilt bekommt.
- `CourseFormModal.vue` Zeile 45 verwendet aktuell `trainer.fullName ||
  trainer.email` als Anzeige-Fallback. Da `email` im neuen, reduzierten
  Endpoint nicht mehr geliefert wird, wird der Fallback auf
  `` `${trainer.firstName} ${trainer.lastName}` `` umgestellt (analog zu
  `CustomerFormModal.vue:106`).
- Die Vorauswahl-Logik in `CustomerFormModal.vue:291-294` bleibt inhaltlich
  unverändert (Default, änderbar) — sie funktioniert nach dem Fix wieder,
  weil die Trainerliste jetzt tatsächlich lädt.

## Capabilities

### New Capabilities
- `trainer-assignment-forms`: Trainer-Auswahl in den Formularen zur
  Kunden- und Kurserstellung — erfolgreiches Laden der Trainerliste für
  Admin **und** Trainer, Vorauswahl-Verhalten für Trainer, und
  Fehler-Feedback bei Ladefehlern.

### Modified Capabilities
- `trainer-authorization`: Ergänzung um einen zusätzlichen,
  rollen-offenen (Admin + Trainer) Endpoint mit reduziertem Datenumfang.
  Die bestehenden Requirements zur Admin-only-CRUD-API bleiben
  unverändert bestehen.

## Impact

- **Betroffene Dateien Backend:** `backend/routes/api.php`,
  `backend/app/Http/Controllers/Api/TrainerController.php`,
  `backend/app/Http/Resources/TrainerOptionResource.php` (neu),
  `backend/tests/Feature/TrainerApiTest.php` (Ergänzung).
- **Betroffene Dateien Frontend:**
  `frontend/src/components/CustomerFormModal.vue`,
  `frontend/src/components/CourseFormModal.vue`, neue Testdateien
  `frontend/src/components/CustomerFormModal.test.ts` und
  `frontend/src/components/CourseFormModal.test.ts` (beide Komponenten
  haben bisher keine Vitest-Testdatei).
- **Datenbank:** Keine Migration, keine Schemaänderung — reine
  Eloquent-Query (`User::query()->where('role', 'trainer')`), identisch
  zur bestehenden `TrainerController::index()`-Query
  (`backend/app/Http/Controllers/Api/TrainerController.php:25`). Kein
  raw SQL, keine Plattform-spezifischen Konstrukte. DB-Portabilität somit
  unkritisch (Abschnitt 4.2 CLAUDE.md).
- **API-Shape:** Additiv, kein Breaking Change. Bestehende Route/Response
  von `GET /api/v1/trainers` bleibt exakt wie zuvor.
- **Sicherheit/Datenschutz:** Trainer sehen künftig Namen (nicht aber
  Kontakt-/Qualifikationsdaten) anderer Trainer — bewusste, vom User
  bestätigte Entscheidung (siehe Rückfragen in der Triage-Datei).
- **PHP-Kompatibilität:** Neuer Code verwendet ausschließlich ab PHP 8.2
  verfügbare Features (siehe `design.md`, Abschnitt "Decisions").
