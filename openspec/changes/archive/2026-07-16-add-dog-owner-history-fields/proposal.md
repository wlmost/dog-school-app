# Proposal: add-dog-owner-history-fields

**Change-ID:** add-dog-owner-history-fields
**Typ:** Feature (additiv, kein Breaking Change)
**Priorität:** mittel
**Datum:** 2026-07-16
**Triage:** `openspec/triage/20260716111058-dog-form-owner-history-fields.md`

---

## Why

Trainer/Admins erfassen Hunde, ohne die Vorgeschichte beim aktuellen Halter
dokumentieren zu können: seit wann der Hund beim Halter lebt, wie alt er bei
Einzug ungefähr war und woher er stammt (z. B. Züchter vs. Tierschutz).
Diese Information ist fachlich relevant für Trainer (z. B. Einschätzung von
Vorerfahrungen bei Tierschutzhunden), fehlt aber aktuell komplett im
Datenmodell und in beiden Formularen, über die Hundedaten erfasst werden
(Admin/Trainer-Formular und Kunden-Self-Service-Anmeldung).

## What Changes

- Drei neue nullable Felder auf `dogs` **und** `dog_registration_requests`:
  - `owner_since` (Datum) — seit wann der Hund beim aktuellen Halter ist
  - `age_at_acquisition` (Freitext, z. B. "ca. 2 Jahre") — manuell erfasstes
    Alter bei Einzug, **keine** Berechnung aus `date_of_birth`/`owner_since`
  - `origin` (feste Werteliste: `breeder`, `shelter`, `private`, `unknown`) —
    Herkunft des Hundes, als DB-`enum`-Spalte analog zum bestehenden
    `gender`-Muster
- `DogFormModal.vue` (Admin/Trainer) und `CustomerDogRequestModal.vue`
  (Kunden-Self-Service) erhalten je drei neue Eingabefelder.
- `DogRegistrationRequestController::approve()` reicht die drei Felder beim
  Anlegen des `Dog`-Datensatzes aus der Registrierungsanfrage durch.
- `DogResource` und `DogRegistrationRequestResource` liefern die drei neuen
  Felder in der API-Antwort (camelCase: `ownerSince`, `ageAtAcquisition`,
  `origin`).
- Kein Breaking Change: alle drei Felder sind nullable, bestehende
  API-Konsumenten sind nicht betroffen.

## Capabilities

### New Capabilities

- `dog-owner-history`: Erfassung, Validierung, Speicherung und Anzeige der
  Herkunfts-/Übernahme-Informationen eines Hundes (seit wann beim Halter,
  Alter bei Einzug, Herkunft) — sowohl im Admin/Trainer-Formular als auch im
  Kunden-Self-Service-Anmeldeflow inkl. Übernahme bei Genehmigung einer
  Anfrage.

### Modified Capabilities

*(keine — es gibt aktuell keine dokumentierte Capability für das
Dog-Basisdatenmodell oder den Registrierungsanfrage-Flow, die durch dieses
Feature in ihrem bestehenden Verhalten geändert würde; die Erweiterung von
`DogRegistrationRequestController::approve()` ist rein additiv, das
bestehende Genehmigungsverhalten — Statuswechsel, Mailversand — bleibt
unverändert.)*

## Impact

**Betroffene Dateien (siehe `design.md` für Details und Task-Zuordnung):**

- Backend: 2 neue Migrationen, `Dog`-Model, `DogRegistrationRequest`-Model,
  `StoreDogRequest`, `UpdateDogRequest`, `StoreDogRegistrationRequest`,
  `DogResource`, `DogRegistrationRequestResource`,
  `DogRegistrationRequestController::approve()`, zugehörige Factories und
  Feature-Tests.
- Frontend: `DogFormModal.vue`, `CustomerDogRequestModal.vue`, zugehöriger
  Vitest-Test `DogFormModal.test.ts`.

**Keine betroffenen Drittsysteme, keine Queue-/Scheduler-Änderungen, kein
Shell-Exec, keine WebSockets.**

**DB-Portabilität:** Beide Migrationen fügen ausschließlich additive,
nullable Spalten hinzu (`date`, `string`, `enum`) über Laravels
Blueprint-`Schema::table()`-Methoden — kein raw SQL, kein Driver-Switch
nötig (siehe `design.md`, Abschnitt "DB-Portabilität").

## Out of Scope

- Keine Änderung an `DashboardView.vue` (Admin-Übersicht der ausstehenden
  Registrierungsanfragen, `frontend/src/views/DashboardView.vue:112-151`) —
  die Übersicht zeigt bewusst nur Name/Rasse/Kunde/Datum (Kompaktliste), die
  drei neuen Felder sind dort nicht angefordert (YAGNI). Vollständige Details
  inkl. neuer Felder sind über die bestehende Detailansicht/das
  Bearbeiten-Formular nach Genehmigung einsehbar.
- Keine Berechnungslogik zwischen `date_of_birth`, `owner_since` und
  `age_at_acquisition` — laut User-Antwort explizit nicht gewünscht (Feld 2
  bleibt manuelle Freitexteingabe).
- Kein neuer zentraler TypeScript-`interface Dog`-Typ — das Projekt hat
  aktuell keinen zentralen Typ-Ort für `Dog` (`DogFormModal.vue` nutzt
  `dog?: any`, siehe Triage); die drei neuen Felder werden lokal in den
  betroffenen Komponenten typisiert, ohne diesen bestehenden Zustand zu
  verändern (kein Scope-Creep).
- Keine Migration eines PHP-Backed-Enums (`enum DogOrigin: string { ... }`)
  — das Projekt nutzt für vergleichbare feste Wertelisten (`gender`,
  `status`) durchgehend Plain-String-Spalten mit `in:...`-Validierung statt
  PHP-Enum-Klassen; `origin` folgt demselben Muster (User-Antwort 3 bestätigt
  "analog zum bestehenden gender/status-Muster").
- Keine Änderung an `frontend/src/views/dogs/DogsView.vue` (Karten-Liste
  aller Hunde, `frontend/src/views/dogs/DogsView.vue:34-71`) — die Karten
  zeigen bereits heute nur eine Teilmenge der Hundedaten (Name, Rasse,
  Halter, Geburtsdatum, Chipnummer; nicht einmal das bestehende Feld
  `gender` wird dort angezeigt). Die drei neuen Felder in dieser Kompaktliste
  zu ergänzen ist analog zur `DashboardView.vue`-Begründung nicht angefordert
  (YAGNI). Vollständige Details sind weiterhin über das
  Bearbeiten-Formular (`DogFormModal.vue`, siehe T11) einsehbar, das über
  denselben Klick-Handler (`editDog(dog)`) aus dieser Ansicht geöffnet wird.
- **Fehlende QA-Scripts (Feststellung des Skeptikers, User-Gate 1
  bestätigt):** `backend/composer.json` enthält aktuell **keine** Scripts
  `test`, `lint`, `stan`, `qa` oder `compat-check` (nur Laravel-Standard-
  Hooks wie `post-autoload-dump`); das Dev-Package
  `phpcompatibility/php-compatibility` ist nicht installiert. Die
  tatsächlich in der CI verwendeten Befehle sind `./vendor/bin/pest
  --no-coverage` (Backend, `.github/workflows/ci.yml:112`) und `npm run
  test` (Frontend, `.github/workflows/ci.yml:143`) — kein `npm run lint`
  (kein ESLint im Projekt konfiguriert). Die in CLAUDE.md Abschnitt 5/7.1
  referenzierten Befehle (`composer test/lint/stan/qa/compat-check`, `npm
  run lint`) existieren somit im aktuellen Projektstand **nicht** und sind
  auch **nicht** Teil dieses Changes — das Einrichten dieser QA-Scripts
  (inkl. `phpcompatibility/php-compatibility`, Larastan, PHP-CS-Fixer/Pint
  als Composer-Scripts, ESLint fürs Frontend) ist ein eigenständiges,
  künftiges Vorhaben und sollte als separater openspec-Change angestoßen
  werden. Die Akzeptanzkriterien in `tasks.md` referenzieren daher nur
  tatsächlich existierende Befehle (`./vendor/bin/pest`, `php artisan
  migrate`, `npm run test`, `npm run build`) bzw. verlangen an den Stellen,
  an denen CLAUDE.md ein automatisiertes Tooling vorsieht, das aber nicht
  existiert (PHP-8.3/8.4-Kompatibilität, Abschnitt 4.1), eine manuelle
  Prüfung durch den Entwickler-Agenten.

## Referenzen

- Triage: `openspec/triage/20260716111058-dog-form-owner-history-fields.md`
- Neue Capability `dog-owner-history` (siehe
  `specs/dog-owner-history/spec.md`)
