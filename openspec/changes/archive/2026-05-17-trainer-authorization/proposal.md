# Proposal: trainer-authorization

**Change-ID:** trainer-authorization
**Datum:** 2026-05-17
**Status:** draft

---

## Why

Die Trainer-CRUD-Endpunkte (`GET/POST /api/trainers`, `GET/PUT/DELETE /api/trainers/{trainer}`) liegen zwar im `auth:sanctum`-Block und sind damit gegen unauthentifizierte Zugriffe geschützt — jedoch nicht gegen authentifizierte Nicht-Admins. Jeder eingeloggte User (Trainer, Kunde) kann aktuell Trainer anlegen, bearbeiten und löschen.

Die bisherige Sicherheit ist **Security by Obscurity**: Das UI blendet diese Aktionen für Nicht-Admins zwar aus, aber die API-Endpunkte selbst prüfen keine Rolle. Ein einfacher API-Aufruf mit einem gültigen Sanctum-Token eines Trainers oder Kunden reicht aus, um Admin-Aktionen auszuführen.

Das ist eine Autorisierungslücke (OWASP A01: Broken Access Control). Sie muss auf Routen-Ebene geschlossen werden — nicht auf UI-Ebene.

---

## What Changes

### `routes/api.php`

Die `Route::apiResource('trainers', TrainerController::class)`-Deklaration wird aus dem allgemeinen `auth:sanctum`-Block heraus und in den bereits vorhandenen `can:admin`-Middleware-Block verschoben (analog zu den Settings- und PricingItem-Routen). Damit greifen beide Middleware-Schichten: erst Authentifizierung, dann Admin-Autorisierung.

Keine Änderung am `TrainerController` selbst — keine `authorize()`-Aufrufe nötig, weil die Route bereits vollständig abgesichert ist.

### `tests/Feature/TrainerApiTest.php` (neu)

Neuer Feature-Test, der alle 5 CRUD-Actions (`index`, `store`, `show`, `update`, `destroy`) gegen vier User-Rollen prüft:

| Rolle            | Erwarteter HTTP-Status |
|------------------|------------------------|
| Admin            | 200 / 201 / 204        |
| Trainer          | 403                    |
| Kunde            | 403                    |
| Unauthentifiziert | 401                   |

---

## Capabilities

### New Capabilities

**`trainer-authorization`**
Admin-only-Zugriff auf die Trainer-CRUD-API. Alle fünf REST-Endpunkte (`index`, `store`, `show`, `update`, `destroy`) sind ausschließlich für Admins zugänglich. Trainer, Kunden und unauthentifizierte Requests werden mit 403 bzw. 401 abgewiesen.

### Modified Capabilities

Keine. Es gibt keine bestehende `trainer-*`-Spec. Die Änderung berührt keine der existierenden Capabilities (`course-management`, `auth-store-type-safety` etc.).

---

## Impact

- **Scope:** Backend only
- **Frontend:** Keine Änderungen — das UI hat die Aktionen ohnehin schon für Nicht-Admins ausgeblendet
- **Datenbank:** Keine Migration, kein Schema-Änderung
- **API-Shape:** Keine Breaking Changes — Endpunkte existieren weiterhin mit identischer Request/Response-Struktur; lediglich der Zugriffsschutz wird verschärft
- **Bestehende Tests:** Keine bestehenden Trainer-Tests vorhanden; kein Regressionsrisiko
- **Deployment:** Kein besonderer Rollout-Aufwand; Änderung ist ein Ein-Zeiler in `routes/api.php`
