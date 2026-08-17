# Triage: Customer Booking UI

**Pfad:** standard
**Geschätzter Umfang:** 4–6 Dateien, TypeScript/Vue (Frontend-Schwerpunkt), kein Backend-Änderungsbedarf
**Risiko:** mittel — Berührt Auth-gesteuertes UI und Buchungsflow; Backend-Policies sind bereits korrekt, aber neue Komponente mit Session-Auswahl muss korrekt Rollen-kontext trennen
**Klarheit:** mehrdeutig — Kernflow ist klar, aber 3 offene Detailfragen (siehe unten)

## Anforderung (Zusammenfassung)

Die Kursübersicht (`CoursesView.vue`) zeigt aktuell allen Nutzern die Buttons „Neuer Kurs",
„Bearbeiten" und „Löschen", obwohl Kunden diese nie sehen dürfen. Die Buttons sollen hinter
`v-if="isTrainerOrAdmin"` versteckt werden. Kunden sollen stattdessen „Buchen"- und
„Abbrechen"-Buttons pro Kurs sehen und darüber einen eigenen Buchungsflow anstoßen, der die
passenden Sessions (Terminserie oder Einzeltermine) auswählt und über den bestehenden
`POST /api/v1/bookings`-Endpunkt abschließt.

## Codebase-Befunde (geprüft)

### Frontend (Änderungsbedarf)
- `frontend/src/views/courses/CoursesView.vue` — Zeilen 14–19: „Neuer Kurs"-Button **ohne** Rollencheck.
  Zeilen 84–85: „Bearbeiten"/„Löschen"-Buttons **ohne** Rollencheck. `authStore` wird **nicht**
  importiert. → Hauptbefund.
- `frontend/src/views/CourseDetailView.vue` — „Kurs bearbeiten" bereits hinter
  `v-if="isTrainerOrAdmin"` (Zeile 186). Kein „Löschen"-Button in der Detailansicht vorhanden.
  Kunden-Buchungsbutton fehlt dort noch (falls gewünscht, s. Rückfragen).
- `frontend/src/stores/auth.ts` — `isTrainer`, `isCustomer`, `isAuthenticated` als Computeds
  vorhanden und exportiert.
- `frontend/src/components/BookingFormModal.vue` — Bestehende Komponente erwartet
  Trainer-Kontext (Hundeauswahl aller Kunden, Status-Dropdown). Für Kunden-Buchung zu komplex;
  neue, schlankere Komponente (`CustomerBookingModal.vue`) empfohlen.

### Backend (kein Änderungsbedarf — bereits korrekt)
- `GET /api/v1/courses` — `CourseController::index` enthält expliziten `isCustomer()`-Zweig
  (Zeile 56–57): Kunden sehen verfügbare Kurse.
- `GET /api/v1/courses/{id}/sessions` — `CoursePolicy::view` erlaubt allen authentifizierten
  Usern den Zugriff; Customers können Sessions abrufen.
- `POST /api/v1/bookings` — `BookingPolicy::create`: „All authenticated users can create
  bookings" (Zeile 46). Customers können direkt buchen.
- `POST /api/v1/bookings/{id}/cancel` — `BookingPolicy` erlaubt Customers das Stornieren
  ihrer eigenen Buchungen (Zeile 70).

## Geschätzte Dateien

| Datei | Aktion |
|-------|--------|
| `frontend/src/views/courses/CoursesView.vue` | Ändern: `authStore` importieren, Buttons rollenabhängig |
| `frontend/src/components/CustomerBookingModal.vue` | Neu: Buchungsflow für Kunden (Session-Auswahl, eigene Hunde, Submit) |
| `frontend/src/views/CourseDetailView.vue` | Ändern: „Buchen"-Button für Kunden hinzufügen (abhängig von Klärung F1) |
| `frontend/src/views/bookings/BookingsView.vue` | Evtl. Ändern: falls „Abbrechen" auf Umleitung zur Buchungsliste hinausläuft |

## Rückfragen an den User (Klarheit = mehrdeutig)

**F1 — Scope: Gilt das auch für `CourseDetailView.vue`?**
Der Screenshot zeigt laut Beschreibung die Detailansicht — aber in der Detailansicht ist
„Kurs bearbeiten" bereits hinter `isTrainerOrAdmin` versteckt und es gibt keinen „Löschen"-Button.
Soll die Detailansicht (`/courses/:id`) ebenfalls einen „Buchen"-Button für Kunden erhalten,
oder reicht die Kursübersicht (`/courses`)?

**F2 — „Abbrechen"-Button-Semantik: Was bedeutet er?**
Zwei mögliche Bedeutungen:
- (a) „Buchungsvorgang abbrechen" — schließt das Buchungsmodal, falls es gerade offen ist
- (b) „Bestehende Buchung stornieren" — cancel-Button pro Kurs, sichtbar wenn der Kunde
  diesen Kurs bereits gebucht hat
Welche Variante ist gemeint? Bei Variante (b) muss `CoursesView` den Buchungsstatus des
Kunden je Kurs wissen (erfordert API-Abgleich mit `GET /api/v1/bookings?status=…`).

**F3 — Mehrfachbuchungs-Guard: Soll „Buchen" deaktiviert werden, wenn der Kurs bereits gebucht ist?**
Wenn ein Kunde einen Kurs bereits gebucht hat, soll der „Buchen"-Button dann deaktiviert /
durch „Bereits gebucht" ersetzt werden — oder ist das kein Scope dieser Anforderung?

## Empfohlene nächste Aktion

**Architekt** aufrufen mit diesem Triage-Dokument als Basis.

Falls F2 und F3 vorab vom User beantwortet werden, kann der Architekt direkt ein vollständiges
`design.md` + `tasks.md` erstellen. Andernfalls empfiehlt sich eine kurze Klärungsrunde vor
dem Architekten-Schritt, damit keine Fehl-Annahmen in die Tasks einfließen.

Empfohlene Task-Struktur (vorläufig):
- **T01 (dev-javascript):** `CoursesView.vue` — Auth-Import, Rollen-Guards für bestehende
  Buttons, Kunden-seitige Button-Slots
- **T02 (dev-javascript):** Neue `CustomerBookingModal.vue` — Session-Auswahl, eigene Hunde
  laden, Submit via `POST /api/v1/bookings`
- **T03 (dev-javascript):** `CourseDetailView.vue` — Kunden-Buchungsbutton (nur wenn F1
  bejaht wird)
