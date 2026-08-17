# Review: T01 + T02 (enable-trainer-customer-registration)

Dieses Review deckt beide eng zusammenhängenden Tasks ab (Autorisierungslogik
in `RegisterRequest.php` und die zugehörigen Tests in
`AuthenticationTest.php`), da sie denselben sicherheitskritischen Diff bilden.

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

Keine.

Die Kernanforderung — Rollen-Einschränkung für Trainer ausschließlich
serverseitig aus `$this->user()` abgeleitet, nicht aus Client-Input — ist
korrekt umgesetzt:

- `backend/app/Http/Requests/RegisterRequest.php:29`: `authorize()` lässt
  Admin **oder** Trainer durch, Customer/unauthentifiziert weiterhin
  blockiert.
- `backend/app/Http/Requests/RegisterRequest.php:43-45`: `$allowedRoles`
  wird ausschließlich aus `$this->user()?->isAdmin()` abgeleitet, nicht aus
  `$this->input('role')` oder einem anderen Client-Feld — ein manipulierter
  Request (zusätzliche Felder wie `force_role`, `is_admin`) hat keinen
  Angriffsvektor, da `AuthController::register()`
  (`backend/app/Http/Controllers/Api/AuthController.php:92-107`)
  ausschließlich benannte Properties (`$request->email`, `->role`, …) liest,
  nicht `$request->all()` — kein Mass-Assignment-Bypass möglich.
- Kein Case-/Type-Bypass: `Rule::in($allowedRoles)` vergleicht exakt
  (case-sensitiv, typisiert durch die vorgeschaltete `'string'`-Regel in
  `RegisterRequest.php:50`); ein Trainer, der z. B. `role: 'Customer'` oder
  `role: ['admin']` sendet, bekommt 422 (fail-safe), keine
  Privilegienausweitung.
- `authorize()` läuft laut Laravel-Framework
  (`ValidatesWhenResolvedTrait::validateResolved()`) vor `rules()` — der
  `else`-Zweig in `rules()` ist damit nachweislich nur für Trainer
  erreichbar, wie in `design.md` Entscheidung 1 und `verification.md`
  Zeile 32 dokumentiert.
- PHP-Kompatibilität (CLAUDE.md 4.1): `Rule::in()` und `?->` sind
  PHP-8.0-Standard, keine der verbotenen 8.3/8.4-Konstrukte im Diff.
  `declare(strict_types=1)` unverändert vorhanden
  (`RegisterRequest.php:3`).
- Tests decken die relevanten Rollen×Akteur-Kombinationen ab
  (`AuthenticationTest.php:126-218`) und prüfen jeweils sowohl den
  HTTP-Status als auch `assertDatabaseHas`/`assertDatabaseMissing` — damit
  wird nicht nur der Statuscode, sondern auch "kein User wurde angelegt"
  verifiziert, was für eine Privilege-Escalation-Prüfung der relevante
  Fakt ist.

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Konsistenz/Doku]** `backend/app/Http/Controllers/Api/AuthController.php:90`:
  Der Docblock über `register()` lautet weiterhin `"Register a new user
  (Admin only)."` — das ist nach dieser Änderung sachlich falsch (Trainer
  dürfen den Endpunkt jetzt für `role: 'customer'` aufrufen) und
  widerspricht dem bereits korrekten Kommentar in `routes/api.php:84`
  ("User registration (Admins and Trainers only)"). Da dies eine
  sicherheitsrelevante Autorisierungsänderung ist, sollte der irreführende
  Docblock korrigiert werden, auch wenn die Datei sonst nicht Teil des
  Task-Scopes war (T01 betrifft laut `proposal.md`/`tasks.md` nur
  `RegisterRequest.php`). Vorschlag: `"Register a new user (Admin: any
  role, Trainer: customer only)."` oder Verweis auf `RegisterRequest`.

- **[Testabdeckung]** `backend/tests/Feature/AuthenticationTest.php:89-124`
  ("admin can register new user"): Die neue Spec-Requirement "Admin retains
  unrestricted role assignment"
  (`openspec/changes/enable-trainer-customer-registration/specs/user-registration/spec.md`,
  Abschnitt "Role assignment for Trainer-initiated registrations is
  restricted to customer") verlangt explizit, dass Admin weiterhin
  `role: 'admin'`, `role: 'trainer'` **und** `role: 'customer'` vergeben
  kann. Der einzige Admin-Test in dieser Datei registriert ausschließlich
  `role: 'trainer'` (Zeile 97) — es gibt keinen automatisierten Test, der
  belegt, dass ein Admin nach dieser Änderung noch `role: 'admin'` oder
  `role: 'customer'` vergeben kann (der `$allowedRoles`-Array für Admin
  enthält zwar alle drei Werte, aber das ist aktuell nur durch Code-Lesen,
  nicht durch einen Test abgesichert). Empfehlung: mindestens einen
  zusätzlichen Testfall (oder ein Pest-`dataset`) ergänzen, der Admin +
  `role: 'admin'` bzw. `role: 'customer'` abdeckt, um die im Spec-Delta
  selbst benannte Anforderung automatisiert zu verifizieren.

## Könnte (optional, Verbesserung)

- **[Testabdeckung/Sicherheit]** Kein automatisierter Test für das in
  `design.md:84-87` explizit beschriebene Exploit-Szenario (Trainer sendet
  zusätzliche, erfundene Felder wie `force_role` oder `is_admin: true`
  zusätzlich zu `role: 'customer'`). Der Schutz ist durch Code-Lesen
  (`verification.md:36`, `AuthController.php` liest nur benannte
  Properties) und manuelle `curl`-Empirie
  (`task-T01.notes.md:51-66`) belegt, aber nicht durch einen Test
  festgeschrieben, der eine zukünftige Regression (z. B. ein versehentliches
  `$request->all()` in `AuthController::register()`) automatisiert
  auffangen würde. Ein einzelner Test dafür wäre günstige
  Regressionsversicherung für eine als sicherheitskritisch markierte
  Änderung.

## Lob

- Die zentrale Sicherheitsentscheidung — Rollen-Einschränkung ausschließlich
  aus serverseitigem Auth-Zustand (`$this->user()`), nicht aus
  Client-Input, mit explizit dokumentierter Begründung in
  `RegisterRequest.php:39-42` — ist lehrbuchmäßig sauber umgesetzt und
  bereits gegen die Laravel-Ausführungsreihenfolge
  (`authorize()` vor `rules()`) verifiziert.
- Die Tests in `AuthenticationTest.php:126-218` prüfen konsequent sowohl
  HTTP-Status als auch DB-Zustand (`assertDatabaseHas`/`assertDatabaseMissing`)
  statt sich auf den Statuscode allein zu verlassen — genau die richtige
  Tiefe für einen Privilege-Escalation-relevanten Testfall.
- Sehr sorgfältige, mit Datei:Zeile belegte Dokumentation in
  `task-T01.notes.md`/`task-T02.notes.md`, inkl. begründeter, expliziter
  Abgrenzung des Scopes (kein Umbau auf `it()`/Groups im Bestand, keine
  Änderung an `RegisterRequest.php` durch T02) statt stillschweigendem
  Scope-Creep.
