## Context

Verifizierte Codestellen (per Read/Grep gelesen, Stand 2026-08-17):

- `backend/app/Http/Requests/RegisterRequest.php:23-29` — `authorize()`,
  aktuell nur `$user && $user->isAdmin()`.
- `backend/app/Http/Requests/RegisterRequest.php:12-16` — Klassen-Docblock:
  *"Admins can create any user, trainers can only create customers."*
  (bereits vorhandener, aber nicht umgesetzter Soll-Zustand).
- `backend/app/Http/Requests/RegisterRequest.php:36-46` — `rules()`, Feld
  `role` aktuell ungefiltert `Rule::in(['admin', 'trainer', 'customer'])`
  für jeden autorisierten Aufrufer (aktuell nur Admin erreichbar).
- `backend/routes/api.php:84` — Kommentar *"User registration (Admins and
  Trainers only) - Authorization handled in RegisterRequest"*.
- `backend/app/Http/Controllers/Api/AuthController.php:92-120` —
  `register()` übernimmt `$request->role` direkt in `User::create()`, ohne
  eigene Rollenprüfung — verlässt sich vollständig auf `RegisterRequest`.
- `backend/app/Policies/CustomerPolicy.php:43-47` — `create()`: `isAdmin()
  || isTrainer()`.
- `backend/app/Policies/DogPolicy.php:43-47` — `create()`:
  `isAdminOrTrainer()`.
- `backend/app/Http/Requests/StoreCustomerRequest.php:20-24` —
  `authorize()`: Admin oder Trainer.
- `backend/app/Http/Requests/StoreDogRequest.php:21-25` — `authorize()`:
  `isAdminOrTrainer()`.
- `backend/app/Models/User.php:92-119` — `isAdmin()`, `isTrainer()`,
  `isCustomer()`, `isAdminOrTrainer()` (String-Vergleich auf `role`-Spalte).
- `backend/tests/Feature/AuthenticationTest.php:126-138` — bestehender
  Test `'non-admin cannot register new user'`, erstellt einen Trainer und
  erwartet 403 (alter Soll-Zustand, muss angepasst werden).
- `backend/tests/Feature/AuthenticationTest.php:171-184` — Test
  `'registration validates role'`, erwartet bereits 422 für einen
  ungültigen `role`-Wert (Precedent für Entscheidung 3 unten).
- `backend/tests/Feature/EmailNotificationTest.php:124-150` — zwei Tests,
  die `/auth/register` admin-initiiert (`$this->actingAs($this->admin)`)
  mit `role: 'trainer'` aufrufen, um den Willkommens-E-Mail-Versand zu
  prüfen. Kein Regressionsrisiko, da Admin-Verhalten unverändert bleibt —
  trotzdem Teil der Regressionsprüfung in T02.
- `frontend/src/components/CustomerFormModal.vue:301-304` — `trainer_id`
  wird beim Öffnen des Formulars für einen neuen Kunden automatisch auf
  die ID des eingeloggten Trainers gesetzt, falls `currentUser.role ===
  'trainer'`.
- `frontend/src/components/CustomerFormModal.vue:518-527` — `handleSubmit()`
  ruft beim Anlegen eines neuen Kunden zuerst `POST /api/v1/auth/register`
  mit hart codiertem `role: 'customer'` auf, danach `POST /api/v1/customers`
  mit `trainerId: form.value.trainer_id`.
- `backend/composer.lock:1507` — `laravel/framework` Version `v11.51.0`.

## Goals / Non-Goals

**Goals:**
- Trainer können über `POST /api/v1/auth/register` einen Kunden-Account
  anlegen, exakt wie bisher Admins.
- Trainer können **niemals** über diesen Endpunkt einen `admin`- oder
  `trainer`-Account anlegen, unabhängig vom gesendeten `role`-Wert im
  Request-Body.
- Bestehendes Admin-Verhalten bleibt unverändert (Admin kann weiterhin
  alle drei Rollen registrieren).

**Non-Goals:**
- Keine Änderung an `CustomerPolicy`, `DogPolicy`, `StoreCustomerRequest`,
  `StoreDogRequest` — diese sind laut Repo-Verifikation bereits korrekt
  trainer-fähig, siehe "Context" oben.
- Keine Änderung am Frontend — `CustomerFormModal.vue` ruft bereits
  korrekt mit `role: 'customer'` auf und weist den Trainer bereits
  automatisch zu.
- Keine automatische Trainer-Zuweisung im Backend selbst (z. B. über ein
  `trainer_id`-Feld an `RegisterRequest`/`User`) — das bleibt Aufgabe des
  nachgelagerten `POST /api/v1/customers`-Aufrufs, wie heute bereits vom
  Frontend umgesetzt.

## Decisions

### Sicherheitskritische Änderung — besondere Aufmerksamkeit für Skeptiker/Reviewer

Dies ist eine Änderung an der Autorisierungslogik für User-Account-
Erstellung. Ein Fehler hier ermöglicht Privilege Escalation (z. B. könnte
sich ein Trainer selbst oder einen Dritten zu `admin` hochstufen). Der
Reviewer MUSS diesen Diff mit besonderer Sorgfalt gegen die
Anti-Halluzinations- und PHP-Kompatibilitätsregeln aus `CLAUDE.md`
prüfen. Der Skeptiker sollte in `verification.md` explizit bestätigen,
dass die Rollen-Einschränkung tatsächlich serverseitig und
nicht-client-beeinflussbar implementiert ist (z. B. durch einen
Test-Exploit-Versuch: Trainer sendet `role: 'admin'` und ein
zusätzliches, erfundenes Feld wie `force_role` oder `is_admin: true` —
beides darf keine Wirkung haben, weil `RegisterRequest`/`AuthController`
diese Felder gar nicht lesen).

**Entscheidung 1 — Enforcement-Mechanismus:** Die Rollen-Einschränkung für
Trainer wird ausschließlich in `RegisterRequest::rules()` umgesetzt,
dynamisch abgeleitet aus `$this->user()` (dem durch Sanctum
authentifizierten Aufrufer) — nicht aus einem vom Client mitgeschickten
Feld. Damit ist die einzige Quelle der Wahrheit für "wer darf welche Rolle
vergeben" serverseitig und vom Client nicht beeinflussbar:

```php
public function rules(): array
{
    $allowedRoles = $this->user()?->isAdmin()
        ? ['admin', 'trainer', 'customer']
        : ['customer'];

    return [
        'email' => [...],
        'password' => [...],
        'role' => ['required', 'string', Rule::in($allowedRoles)],
        // ...
    ];
}
```

Da `authorize()` bereits sicherstellt, dass ausschließlich Admin oder
Trainer den Endpunkt überhaupt erreichen, deckt der `else`-Zweig
ausschließlich den Trainer-Fall ab. Ein `Customer` oder ein
unauthentifizierter Aufrufer erreicht `rules()` gar nicht erst
(`authorize()` liefert vorher `false`/401 bzw. 403).

**Entscheidung 2 — kein zusätzliches Override im Controller:** Erwogen
wurde, zusätzlich in `AuthController::register()` die Rolle für
Trainer-Aufrufer hart zu überschreiben (`role = 'customer'`, unabhängig
vom Validierungsergebnis) als Defense-in-Depth. Dagegen entschieden
(YAGNI/KISS): Ein einziger, klar verifizierbarer Enforcement-Punkt ist
wartbarer als zwei Stellen, die synchron gehalten werden müssten, und
`rules()` ist bereits nicht vom Client beeinflussbar, weil sie auf
`$this->user()` basiert statt auf dem Request-Payload. **Der Skeptiker
sollte diese Abwägung explizit gegenprüfen** — falls er ein zusätzliches
Controller-Override für sinnvoll hält, ist das ein legitimer Einwand für
`verification.md`.

**Entscheidung 3 — HTTP-Statuscode bei Regelverstoß:** Wenn ein Trainer
`role: 'admin'` oder `role: 'trainer'` sendet, liefert der Endpunkt neu
**422** (Validation Error auf dem `role`-Feld) statt weiterhin 403. Das
weicht vom in der Triage vorgeschlagenen Testszenario ("Trainer versucht
Admin/Trainer zu registrieren → 403") ab.

Begründung: Der Trainer *darf* den Endpunkt grundsätzlich aufrufen
(`authorize()` liefert `true`) — nur der konkrete `role`-Wert ist für ihn
ungültig. Das folgt der Laravel-`FormRequest`-Idiomatik (`authorize()` =
grobkörnige Endpunkt-Berechtigung, `rules()` = feldspezifische
Validierung) und ist konsistent mit dem bereits bestehenden Test
`'registration validates role'` (`AuthenticationTest.php:171-184`), der
für einen ungültigen `role`-Wert bereits 422 erwartet.

> **Offene Frage für Skeptiker/User-Gate 1:** Falls der User explizit 403
> statt 422 für diesen Fall erwartet, ist das mit geringem Aufwand
> nachrüstbar (zusätzliche Prüfung in `authorize()` statt ausschließlich
> in `rules()`). Diese Design-Entscheidung schlägt 422 vor, ist aber keine
> abschließende Festlegung ohne Bestätigung durch Skeptiker/User.

**Entscheidung 4 — PHP-/Laravel-Kompatibilität:** `Rule::in()` und der
Nullsafe-Operator `?->` sind seit PHP 8.0 bzw. als Laravel-Standard-API
verfügbar — keine PHP-8.3/8.4-Features betroffen (siehe `CLAUDE.md`
Abschnitt 4.1). Laravel-Version laut `backend/composer.lock:1507`:
`v11.51.0` — `Rule::in()` und die `FormRequest`-API sind seit Laravel 10
unverändert, keine Migrationsrisiken zwischen Laravel-Versionen.

## Risks / Trade-offs

- **Privilege Escalation (Hauptrisiko dieses Changes):** Mitigiert durch
  (a) Enforcement basiert ausschließlich auf serverseitigem Auth-Zustand,
  nicht auf Client-Input, (b) explizite Testfälle für alle
  Rollenkombinationen (siehe `tasks.md`, T02), (c) expliziter
  Reviewer-Fokus gemäß diesem Dokument.
- **Trade-off Statuscode-Änderung:** 403 → 422 für Trainer mit ungültiger
  Rolle weicht von der Triage-Empfehlung ab (siehe Entscheidung 3) —
  dokumentiert zur Skeptiker-Prüfung, mit Nachrüstoption falls gewünscht.
- **Bestehender Test muss geändert werden:** `AuthenticationTest.php:126-138`
  testet aktuell den alten (jetzt falschen) Soll-Zustand. Das ist
  beabsichtigt. Ein weiterer Test-Konsument von `/auth/register` existiert
  in `backend/tests/Feature/EmailNotificationTest.php:124-150` (zwei Tests
  im `describe('User Registration Emails', ...)`-Block, prüfen den
  Willkommens-E-Mail-Versand) — diese Tests registrieren jedoch über
  `$this->actingAs($this->admin)` mit `role: 'trainer'`, also
  Admin-initiiert. Admins bleiben von dieser Änderung unberührt (weiterhin
  alle drei Rollen erlaubt), daher besteht dort kein Regressionsrisiko.
  Trotzdem MUSS `composer test` (bzw. `php artisan test`) vollständig
  grün laufen, bevor T02 als abgeschlossen gilt (siehe `tasks.md`).
