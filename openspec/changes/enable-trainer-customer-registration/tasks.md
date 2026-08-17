## 1. Backend-Autorisierung anpassen (sicherheitskritisch)

### T01: RegisterRequest — Trainer-Zugriff mit Rollen-Einschränkung
- **Agent:** dev-php
- **Dateien:** `backend/app/Http/Requests/RegisterRequest.php`
- **Abhängigkeiten:** keine
- **Beschreibung:** `authorize()` erweitern, sodass authentifizierte
  Admins **und** Trainer den Endpunkt aufrufen dürfen (`$user &&
  ($user->isAdmin() || $user->isTrainer())`). `rules()` anpassen, sodass
  die erlaubten Werte für `role` dynamisch aus `$this->user()` abgeleitet
  werden: Admin → `['admin', 'trainer', 'customer']`, Trainer →
  `['customer']` (siehe `design.md`, Entscheidung 1, für den konkreten
  Code-Vorschlag). Kein zusätzliches Controller-Override (siehe `design.md`,
  Entscheidung 2). Klassen-Docblock (`RegisterRequest.php:12-16`) bleibt
  unverändert korrekt, da er den Ziel-Zustand bereits beschreibt.
- **Akzeptanzkriterien:**
  - [ ] Trainer kann `POST /api/v1/auth/register` mit `role: 'customer'`
        aufrufen → HTTP 201, User wird mit `role: 'customer'` angelegt.
  - [ ] Trainer, der `role: 'admin'` oder `role: 'trainer'` sendet, erhält
        HTTP 422 mit Validierungsfehler auf dem Feld `role`; es wird kein
        User angelegt.
  - [ ] Admin kann weiterhin `role: 'admin'`, `role: 'trainer'` und
        `role: 'customer'` registrieren → HTTP 201 (Regressionsschutz,
        unverändertes Verhalten).
  - [ ] Customer (nicht Admin/Trainer) erhält weiterhin HTTP 403.
  - [ ] Unauthentifizierter Aufruf erhält weiterhin HTTP 401.
  - [ ] `composer compat-check` bleibt grün (keine PHP-8.3/8.4-Features,
        siehe `CLAUDE.md` Abschnitt 4.1).
  - [ ] `composer stan` bleibt grün.

## 2. Tests anpassen und erweitern

### T02: AuthenticationTest — bestehenden Test präzisieren, neue Rollen-Fälle abdecken
- **Agent:** dev-php
- **Dateien:** `backend/tests/Feature/AuthenticationTest.php`
- **Abhängigkeiten:** T01
- **Beschreibung:** Den bestehenden Test `'non-admin cannot register new
  user'` (`AuthenticationTest.php:126-138`) anpassen: Er soll nur noch den
  Customer-Fall abdecken (weiterhin 403) — dafür ggf. umbenennen (z. B.
  `'customer cannot register new user'`), da "non-admin" nach dieser
  Änderung nicht mehr präzise ist (Trainer ist jetzt auch "non-admin",
  aber teilweise erlaubt). Neue Testfälle ergänzen für: Trainer registriert
  `role: 'customer'` (201), Trainer versucht `role: 'admin'` (422),
  Trainer versucht `role: 'trainer'` (422). Zusätzlich einen Test für den
  unauthentifizierten Fall ergänzen (kein Bearer-Token) → HTTP 401, da
  `specs/user-registration/spec.md` dieses Szenario fordert, es aber
  bislang in keinem bestehenden Test abgedeckt ist (Befund aus
  `verification.md`, Skeptiker-Prüfung). Vor Abschluss `composer test`
  vollständig laufen lassen und insbesondere
  `backend/tests/Feature/EmailNotificationTest.php` (admin-initiierte
  `/auth/register`-Aufrufe, siehe `design.md` Abschnitt "Risks /
  Trade-offs") auf Regressionsfreiheit prüfen.
- **Akzeptanzkriterien:**
  - [ ] Test für Customer → 403 vorhanden und grün (Nachfolger von
        `'non-admin cannot register new user'`).
  - [ ] Neuer Test: Trainer registriert `role: 'customer'` → 201, inkl.
        `assertDatabaseHas` für den neuen User mit `role: 'customer'`.
  - [ ] Neuer Test: Trainer versucht `role: 'admin'` → 422 mit
        `assertJsonValidationErrors(['role'])`.
  - [ ] Neuer Test: Trainer versucht `role: 'trainer'` → 422 mit
        `assertJsonValidationErrors(['role'])`.
  - [ ] Bestehender Test `'admin can register new user'`
        (`AuthenticationTest.php:89-124`) bleibt unverändert grün.
  - [ ] Neuer Test: unauthentifizierter Aufruf (kein Bearer-Token) →
        HTTP 401, kein User wird angelegt.
  - [ ] `backend/tests/Feature/EmailNotificationTest.php` bleibt
        vollständig grün (kein Regressionsverhalten für
        admin-initiierte Registrierungen).
  - [ ] `composer qa` (lint + stan + compat-check + pest) läuft
        vollständig grün durch, siehe `CLAUDE.md` Abschnitt 7.1.
