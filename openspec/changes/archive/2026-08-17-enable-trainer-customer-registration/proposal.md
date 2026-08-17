## Why

Trainer sollen selbst neue Kunden ("Owner") und Hunde anlegen können. Für
einen **Hund bei einem bereits existierenden Kunden** funktioniert das für
Trainer bereits vollständig: `CustomerPolicy::create()`
(`backend/app/Policies/CustomerPolicy.php:43-47`) und `DogPolicy::create()`
(`backend/app/Policies/DogPolicy.php:43-47`) erlauben `isAdmin() ||
isTrainer()` bzw. `isAdminOrTrainer()`, ebenso `StoreCustomerRequest::authorize()`
(`backend/app/Http/Requests/StoreCustomerRequest.php:20-24`) und
`StoreDogRequest::authorize()` (`backend/app/Http/Requests/StoreDogRequest.php:21-25`).

Der eigentliche Blocker liegt beim Anlegen eines **neuen** Kunden: Das
Frontend ruft dafür zuerst `POST /api/v1/auth/register` auf, um den
zugehörigen User-Account zu erzeugen (`frontend/src/components/CustomerFormModal.vue:518-527`),
bevor `POST /api/v1/customers` folgt. `RegisterRequest::authorize()`
(`backend/app/Http/Requests/RegisterRequest.php:23-29`) prüft aktuell
ausschließlich `$user->isAdmin()` und lehnt Trainer mit 403 ab — obwohl der
Klassen-Docblock direkt darüber (`RegisterRequest.php:12-16`) bereits
festhält: *"Admins can create any user, trainers can only create
customers"*, und der Routen-Kommentar in `backend/routes/api.php:84`
ebenfalls "User registration (Admins and Trainers only)" verspricht. Der
vorhandene Test `AuthenticationTest.php:126-138` ("non-admin cannot
register new user") zementiert aktuell den alten, jetzt zu ändernden
Soll-Zustand, indem er explizit einen Trainer erstellt und 403 erwartet.

## What Changes

- `RegisterRequest::authorize()` erweitern: Admin **oder** Trainer dürfen
  den Endpunkt aufrufen (statt nur Admin).
- `RegisterRequest::rules()`: Die erlaubten Werte für das `role`-Feld
  werden dynamisch anhand des **authentifizierten Aufrufers**
  (`$this->user()`) eingeschränkt — Trainer dürfen ausschließlich
  `role: 'customer'` registrieren, Admins weiterhin alle drei Rollen
  (`admin`, `trainer`, `customer`). Die Einschränkung basiert
  ausschließlich auf serverseitigem Auth-Zustand, nicht auf einem vom
  Client mitgeschickten Wert, und ist damit nicht durch einen manipulierten
  Request umgehbar (siehe `design.md`, Abschnitt "Sicherheitskritische
  Änderung").
- `backend/tests/Feature/AuthenticationTest.php` anpassen: Der bestehende
  Test `'non-admin cannot register new user'` wird präzisiert (Customer
  bleibt weiterhin 403). Neue Testfälle ergänzen: Trainer registriert
  `role: 'customer'` → 201; Trainer versucht `role: 'admin'` bzw.
  `role: 'trainer'` → 422 (Validierungsfehler auf `role`); Admin registriert
  weiterhin beliebige Rolle → 201 (Regressionsschutz für bestehendes
  Verhalten).
- **Kein Frontend-Task nötig.** `CustomerFormModal.vue:523` sendet beim
  Anlegen eines neuen Kunden bereits hart codiert `role: 'customer'`, und
  weist den neuen Kunden bereits automatisch dem aktuell eingeloggten
  Trainer zu (`CustomerFormModal.vue:301-304`, `trainer_id` im
  nachfolgenden `POST /api/v1/customers`). Beides bleibt unverändert.

## Capabilities

### New Capabilities
- `user-registration`: Autorisierungs- und Validierungsregeln für
  `POST /api/v1/auth/register` — wer darf neue User-Accounts anlegen und
  welche Rollen dabei vergeben werden dürfen. Es existiert bislang keine
  Spec-Capability, die diesen Endpunkt beschreibt.

### Modified Capabilities
- keine — `trainer-authorization` deckt die Trainer-CRUD-API ab (Verwaltung
  von Trainer-Entitäten durch Admins), nicht die User-Registrierung, und
  bleibt unberührt.

## Impact

- **Scope:** Backend only (PHP), keine Migration, keine Frontend-Änderung.
- **Betroffene Dateien:**
  `backend/app/Http/Requests/RegisterRequest.php`,
  `backend/tests/Feature/AuthenticationTest.php`.
- **API-Shape:** Response-Struktur unverändert. Statuscode-Änderungen
  (bewusst, dokumentiert):
  - Trainer + `role: 'customer'`: 403 → 201
  - Trainer + `role: 'admin'` / `role: 'trainer'`: 403 → 422
    (Validierungsfehler statt Autorisierungsfehler — siehe `design.md`,
    Entscheidung 3)
  - Admin: unverändert (201 für alle drei Rollen)
  - Customer / unauthentifiziert: unverändert (403 / 401)
- **Sicherheitsrelevant:** JA. Änderung an der Autorisierung für
  User-Account-Erstellung — Fehler hier ermöglichen Privilege Escalation
  (ein Trainer könnte sich sonst selbst oder Dritte zu Admin machen). Siehe
  `design.md`, Abschnitt "Sicherheitskritische Änderung", für die
  Skeptiker-Prüfung mit besonderem Fokus.
