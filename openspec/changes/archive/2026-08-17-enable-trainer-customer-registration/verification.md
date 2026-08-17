# Verification: enable-trainer-customer-registration

**Gesamtstatus:** ok

## Schritt 0: openspec validate

```
$ openspec validate enable-trainer-customer-registration
Change 'enable-trainer-customer-registration' is valid
```

Strukturell valide — inhaltlicher Realitätsabgleich wurde durchgeführt.

## Bestätigt

- `proposal.md` Z.5-10 / `design.md` Z.18-25: `CustomerPolicy::create()` (`backend/app/Policies/CustomerPolicy.php:43-47`) und `DogPolicy::create()` (`backend/app/Policies/DogPolicy.php:43-47`) erlauben Admin oder Trainer → bestätigt exakt, Zeilen 43-47 in beiden Dateien enthalten `public function create(User $user): bool { ... return $user->isAdmin() || $user->isTrainer(); }` bzw. `isAdminOrTrainer()`.
- `design.md` Z.22-23: `StoreCustomerRequest::authorize()` (`backend/app/Http/Requests/StoreCustomerRequest.php:20-24`) → bestätigt exakt, Trainer/Admin erlaubt.
- `design.md` Z.24-25: `StoreDogRequest::authorize()` (`backend/app/Http/Requests/StoreDogRequest.php:21-25`) → bestätigt exakt, `isAdminOrTrainer()`.
- `proposal.md` Z.14 / `design.md` Z.39-42: `CustomerFormModal.vue:518-527` ruft `POST /api/v1/auth/register` mit hart codiertem `role: 'customer'` (Zeile 523) → bestätigt exakt.
- `design.md` Z.39-42: `CustomerFormModal.vue:301-304` setzt `trainer_id` automatisch auf den eingeloggten Trainer, wenn `currentUser.role === 'trainer'` → bestätigt (Set-Logik in Zeilen 302-304, Kommentar in 301).
- `proposal.md` Z.16 / `design.md` Z.5-9: `RegisterRequest::authorize()` (`backend/app/Http/Requests/RegisterRequest.php:23-29`) prüft aktuell nur `$user && $user->isAdmin()` → bestätigt exakt.
- `proposal.md` Z.18 / `design.md` Z.7-9: Klassen-Docblock `RegisterRequest.php:12-16`: "Admins can create any user, trainers can only create customers." → bestätigt (Docblock liegt in Zeilen 12-17, Kernaussage in Zeile 16).
- `design.md` Z.10-12: `rules()` (`RegisterRequest.php:36-46`), Feld `role` aktuell `Rule::in(['admin', 'trainer', 'customer'])` ungefiltert → bestätigt exakt, Zeile 41.
- `proposal.md` Z.20 / `design.md` Z.13-14: `routes/api.php:84` Kommentar "User registration (Admins and Trainers only) - Authorization handled in RegisterRequest" → bestätigt exakt, Zeile 84.
- `design.md` Z.15-17: `AuthController::register()` (`AuthController.php:92-120`) übernimmt `$request->role` direkt in `User::create()`, keine eigene Rollenprüfung → bestätigt exakt (Methode Zeilen 92-120, `'role' => $request->role` in Zeile 100).
- `proposal.md` Z.22-24 / `design.md` Z.28-30: `AuthenticationTest.php:126-138` — Test `'non-admin cannot register new user'`, erstellt Trainer, erwartet 403 → bestätigt exakt.
- `design.md` Z.31-33: `AuthenticationTest.php:171-184` — Test `'registration validates role'`, erwartet 422 bei ungültigem `role`-Wert → bestätigt exakt (Precedent für 422-Idiomatik ist real vorhanden).
- `tasks.md` Z.58-59: `AuthenticationTest.php:89-124` — Test `'admin can register new user'` → bestätigt exakt.
- `design.md` Z.34-38: `EmailNotificationTest.php:124-150` — zwei admin-initiierte `/auth/register`-Aufrufe mit `role: 'trainer'`, via `$this->actingAs($this->admin)` → bestätigt exakt (Zeilen 124-135 und 137-150, `actingAs($this->admin)` in Zeile 125 bzw. 138).
- `design.md` Z.26-27: `User.php:92-119` — `isAdmin()`, `isTrainer()`, `isCustomer()`, `isAdminOrTrainer()` (String-Vergleich auf `role`) → bestätigt exakt.
- `design.md` Z.47 / `design.md` Z.150-155: `composer.lock:1507` — `laravel/framework` Version `v11.51.0` → bestätigt exakt.
- `design.md` Entscheidung 1 (Z.112-116): "Da authorize() bereits sicherstellt, dass ausschließlich Admin oder Trainer den Endpunkt überhaupt erreichen, deckt der else-Zweig ausschließlich den Trainer-Fall ab" → bestätigt durch Laravel-Framework-Code: `vendor/laravel/framework/src/Illuminate/Validation/ValidatesWhenResolvedTrait.php::validateResolved()` ruft `passesAuthorization()` **vor** `getValidatorInstance()` (die `rules()` aufruft) auf. `rules()` wird also nie für einen Aufrufer ausgeführt, der `authorize()` nicht passiert hat.
- `design.md` Z.150-152 (Entscheidung 4): `Rule::in()` und Nullsafe-Operator `?->` sind keine PHP-8.3/8.4-Features (CLAUDE.md Abschnitt 4.1) → bestätigt, beide seit PHP 8.0 bzw. als Laravel-Standard-API verfügbar, keine der in CLAUDE.md 4.1 gelisteten verbotenen Konstrukte (Property Hooks, Asymmetric Visibility, Typed Class Constants, `#[\Override]`, `json_validate()`, Dynamic Class Constant Fetch etc.) kommen im vorgeschlagenen Code vor.
- `tasks.md` T01 Akzeptanzkriterien Z.27-29: `composer compat-check` und `composer stan` sind reale, existierende Composer-Scripts → bestätigt in `backend/composer.json:66-70` (`test`, `lint`, `stan`, `compat-check`, `qa`).
- Sicherheitsanalyse (design.md Entscheidung 2, "kein Controller-Override"): Codebasis-weite Suche nach `User::create` und `'role' =>`-Zuweisungen (`backend/app/**/*.php`) ergibt nur zwei HTTP-erreichbare Stellen: `AuthController::register()` (dieser Change) und `TrainerController::store()` (`backend/app/Http/Controllers/Api/TrainerController.php:82-95`, Rolle dort hart auf `'trainer'` codiert, nicht client-gesteuert, und Route ausschließlich mit `can:admin`-Middleware geschützt, `routes/api.php:203-206`) → keine Umgehungsroute für Trainer gefunden, die `RegisterRequest` umgeht und eine client-kontrollierte Rolle setzt.
- Mass-Assignment-Check: `User::$fillable` (`User.php:47-60`) enthält `role`, aber `AuthController::register()` liest ausschließlich benannte Properties (`$request->email`, `->password`, `->role`, `->first_name`, `->last_name`, `->phone`), nicht `$request->all()` → kein Mass-Assignment-Bypass über zusätzliche erfundene Felder (`force_role`, `is_admin` etc.) möglich, da diese Felder gar nicht gelesen werden.

## Widerlegt

- Keine Behauptung wurde widerlegt. Alle referenzierten Datei:Zeile-Fundstellen sind exakt oder nahezu exakt (max. 1 Zeile Docblock-Rundung) korrekt.

## Nicht auffindbar

- Keine Kern-Behauptung war nicht auffindbar. Ein Detail außerhalb der explizit referenzierten Stellen: `frontend/src/stores/auth.ts:108-129` enthält eine weitere `register()`-Funktion, die ebenfalls `POST /api/v1/auth/register` aufruft. Diese Funktion wird aktuell in keiner `.vue`-Komponente aufgerufen (grep über `frontend/src` liefert keinen Treffer für `authStore.register` o.ä. — vermutlich totgelegter/vorbereiteter Code für eine nicht existierende öffentliche Registrierungsseite). Weder `proposal.md` noch `design.md` erwähnen diese Stelle. Da die Autorisierung ausschließlich serverseitig in `RegisterRequest` erzwungen wird, ändert dieser ungenutzte Frontend-Consumer nichts an der Sicherheitsbewertung — aber die Inventur "wer ruft `/auth/register` auf" in `proposal.md`/`design.md` ist insofern unvollständig, als sie nur `CustomerFormModal.vue` nennt.
- `AuthenticationTest.php` enthält aktuell **keinen** Test für "unauthenticated request to /auth/register → 401" (grep nach `assertStatus(401)` findet nur einen Test für `/auth/user`, nicht für `/auth/register`). `tasks.md` T01 listet dieses Verhalten als Akzeptanzkriterium ("Unauthentifizierter Aufruf erhält weiterhin HTTP 401"), aber T02 listet keinen expliziten neuen Testfall dafür (nur Customer/Trainer/Admin-Rollenfälle). Da dieses Verhalten unverändert von der Sanctum-Middleware kommt (nicht von der geänderten Logik), ist das Regressionsrisiko gering — aber die Spec-Anforderung `specs/user-registration/spec.md` Z.21-23 ("Unauthenticated request is unauthorized") bleibt ohne expliziten automatisierten Test in T02 abgedeckt.

## Neue Elemente (Plausibilität)

- Keine neuen Dateien/Pfade geplant — reine Änderung an zwei bestehenden Dateien (`RegisterRequest.php`, `AuthenticationTest.php`). Kein Konflikt mit bestehenden Pfaden möglich, da nichts neu angelegt wird.
- `specs/user-registration/spec.md`: neue Capability `user-registration` — Pfad `openspec/changes/.../specs/user-registration/spec.md` kollidiert nicht mit vorhandenen Capabilities; `proposal.md` begründet plausibel, warum `trainer-authorization` (falls vorhanden) nicht zuständig ist.

## Bewertung Entscheidung 3 (422 vs. 403) — Skeptiker-Einschätzung

Design.md wirft explizit die Frage auf, ob 422 (Validation Error via `Rule::in()`) oder 403 (Authorization Error) die richtige Antwort ist, wenn ein Trainer `role: 'admin'`/`'trainer'` sendet.

**Sicherheitsperspektive:** Beide Varianten sind gleichwertig sicher, sofern korrekt implementiert — in beiden Fällen wird kein User angelegt und die Rollen-Einschränkung basiert serverseitig auf `$this->user()`. Es gibt keinen Sicherheitsunterschied zwischen 422 und 403 an dieser Stelle, da der Fehlerfall in beiden Varianten vor jeder DB-Schreiboperation abgefangen wird (durch die vorher verifizierte Reihenfolge `passesAuthorization()` → `getValidatorInstance()`/Validierung → Controller). Ein Angreifer lernt in beiden Fällen nur "dieser role-Wert ist für mich nicht erlaubt", keine zusätzliche Informationspreisgabe.

**Laravel-Idiomatik:** Die 422-Variante ist tatsächlich idiomatischer für `FormRequest`: `authorize()` beantwortet "darf dieser User diesen Endpunkt grundsätzlich aufrufen" (grobkörnig, pro Request), `rules()` beantwortet "sind die gesendeten Feldwerte für diesen Request gültig" (feldspezifisch). Ein Trainer *darf* grundsätzlich registrieren — nur der konkrete `role`-Wert ist ungültig für ihn. Das ist exakt der Anwendungsfall von `Rule::in()` mit dynamisch berechneten erlaubten Werten und deckt sich mit dem bereits bestehenden Präzedenzfall `'registration validates role'` (`AuthenticationTest.php:171-184`), der für einen schlicht ungültigen `role`-Wert bereits 422 liefert — nach der neuen Logik wird 'admin'/'trainer' aus Trainer-Sicht ebenfalls schlicht "kein gültiger role-Wert", konsistent mit dem bestehenden Verhalten.

**Empfehlung:** 422 beibehalten. Es ist Laravel-idiomatischer, konsistent mit bestehendem Testverhalten, und sicherheitstechnisch gleichwertig zu 403. Einziges Gegenargument wäre reine API-Semantik-Präferenz des Auftraggebers (403 kommuniziert klarer "diese Aktion ist dir nicht erlaubt" statt "dieser Feldwert ist ungültig") — das ist aber eine reine Produktentscheidung, kein technischer oder sicherheitstechnischer Zwang. Empfehlung: 422 durchwinken, sofern der User in User-Gate 1 keine explizite 403-Präferenz äußert.

## Bewertung Entscheidung 2 (kein Controller-Override)

Bestätigt ausreichend robust. Codebasisweite Suche nach `User::create(` und Rollen-Zuweisungen findet nur `RegisterRequest`/`AuthController::register()` (dieser Change) und `TrainerController::store()` (admin-only via Middleware, Rolle hart codiert, kein Client-Input). Kein weiterer HTTP-erreichbarer Pfad legt User mit client-beeinflusster Rolle an. Ein zusätzliches Controller-Override wäre redundant, nicht falsch — YAGNI-Argumentation ist nachvollziehbar. Kein Einwand.

## Empfehlung

Spec ist verlässlich und durch Code belegt — alle geprüften Datei:Zeile-Referenzen sind korrekt, die zentrale Sicherheitsbehauptung (serverseitige, nicht client-beeinflussbare Rollen-Einschränkung, kein Bypass über andere Endpunkte, kein Mass-Assignment-Risiko) ist durch Framework-Code (`ValidatesWhenResolvedTrait`) und Codebasis-Grep verifiziert. **Freigabe für User-Gate 1 empfohlen**, mit zwei kleinen, nicht-blockierenden Auflagen für T02: (1) einen Test für "unauthenticated → 401" ergänzen, um Spec-Requirement `specs/user-registration/spec.md` Scenario "Unauthenticated request is unauthorized" auch automatisiert abzudecken (aktuell nicht durch einen Test belegt); (2) optional in `design.md`/`proposal.md` nachtragen, dass `frontend/src/stores/auth.ts:108-129` ebenfalls `/auth/register` aufruft (aktuell ungenutzt, kein Sicherheitsrisiko, aber Vollständigkeit der Impact-Analyse).
