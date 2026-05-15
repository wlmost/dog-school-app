# Proposal: customer-booking-ui

**Change-ID:** customer-booking-ui  
**Datum:** 2026-05-15  
**Status:** bereit für Entwicklung  
**Triage:** `openspec/triage/20260515172031-customer-booking-ui.md`

---

## Problem

Die Kursübersicht (`CoursesView.vue`) und die Kursdetailansicht (`CourseDetailView.vue`)
unterscheiden aktuell nicht nach Benutzerrolle. Konkret:

- Der „Neuer Kurs"-Button sowie die „Bearbeiten"- und „Löschen"-Buttons in
  `CoursesView.vue` sind **ohne Rollenprüfung** für alle Nutzer sichtbar, obwohl
  kein `authStore`-Import existiert.
- Kunden haben **keinen Buchungsflow**: Sie können zwar Kurse einsehen (Backend-Policy
  bereits korrekt), aber es gibt keine UI, um eine Buchung anzustoßen.
- Die Detailansicht (`CourseDetailView.vue`) zeigt nicht-eingeloggten und
  Kunden-Nutzern nur einen generischen „Kontakt aufnehmen / Anmelden"-Block statt
  eines echten Buchungs-CTA.

---

## Lösung

1. **Rollenbasierte Button-Steuerung in `CoursesView.vue`:**  
   `authStore` importieren, `isTrainerOrAdmin`/`isCustomer` ableiten; Trainer-Buttons
   hinter `v-if="isTrainerOrAdmin"`, Kunden-„Buchen"-Button hinter `v-if="isCustomer"`.

2. **Neues Buchungsmodal `CustomerBookingModal.vue`:**  
   Schlanke, kunden-spezifische Komponente, die Sessions lädt, eigene Hunde des Kunden
   zeigt und eine oder mehrere Buchungen via `POST /api/v1/bookings` abschließt.
   Kein Trainer-Kontext (kein Status-Dropdown, keine Fremdkunden-Auswahl).

3. **Kunden-„Buchen"-Button in `CourseDetailView.vue`:**  
   Den bestehenden „Interesse?"-Platzhalterblock für authentifizierte Kunden durch
   einen echten „Buchen"-Button ersetzen, der dasselbe Modal öffnet.

4. **Mehrfachbuchungs-Guard:**  
   In beiden Views: wenn der Kunde einen Kurs bereits aktiv gebucht hat
   (`status: confirmed | pending`), wird der „Buchen"-Button durch eine
   „Bereits gebucht"-Badge ersetzt.

---

## Nicht im Scope

- Backend-Änderungen — alle Policies und Endpunkte sind bereits korrekt:
  - `GET /api/v1/courses/{id}/sessions` — Customers haben Zugriff
  - `POST /api/v1/bookings` — erlaubt allen authentifizierten Nutzern
  - `GET /api/v1/bookings` — Kunden sehen nur eigene (role-filtered)
  - `GET /api/v1/dogs` — Kunden sehen nur eigene Hunde
  - `GET /api/v1/customers/profile` — gibt eigene Customer-ID zurück
- Buchungs-Storno-Flow (separate Change)
- Admin-Dashboard / Buchungsübersicht
- Gast-Buchung ohne Login

---

## Erfolgsmetriken

- Kunden sehen in `CoursesView` weder „Neuer Kurs", „Bearbeiten" noch „Löschen".
- Trainer/Admins sehen den „Buchen"-Button nicht.
- Ein Kunde kann über das Modal einen Termin für einen seiner Hunde buchen.
- Ein bereits gebuchter Kurs zeigt „Bereits gebucht" statt „Buchen".
