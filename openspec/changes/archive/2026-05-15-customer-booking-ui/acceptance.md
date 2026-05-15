# Abnahme: customer-booking-ui

**Status:** bereit-für-user-review  
**Datum:** 2026-05-15  
**Architekt:** architect-agent (Modus B)

---

## Summary-Scorecard

| Dimension | Bewertung | Begründung |
|-----------|-----------|------------|
| **Completeness** | ✅ 9/10 | T01–T03 implementiert + Build + 62/62 Tests grün; T01 ohne eigene Test-Datei |
| **Correctness** | ✅ mit Vorbehalt | Alle funktionalen Anforderungen erfüllt; 1 offene Review-Aufgabe (`showSuccess`-Signatur); Flicker-Guard lückenhaft |
| **Coherence** | ✅ mit Vorbehalt | Designentscheidungen konsistent umgesetzt; T03-CTA-Struktur weicht vom Spec-Aufbau ab (funktional äquivalent) |

---

## Offene Befunde

### WARNING · W-1: `CoursesView.vue` ohne Vitest-Abdeckung — kein `task-T01.test-report.md`

**Betrifft:** `frontend/src/views/courses/CoursesView.vue` (T01)  
**Befund:** Es existiert keine Test-Datei für CoursesView. Das Test-Artefakt `task-T01.test-report.md` fehlt vollständig. Alle 7 verhaltensbezogenen Akzeptanzkriterien (Rollenbutton-Sichtbarkeit, `bookedCourseIds`-Guard, Modal-Öffnen, Refresh nach Buchung) sind ausschließlich manuell verifiziert — via `vue-tsc`-Build und Reviewer-Code-Review.

Zum Vergleich: T03 (`CourseDetailView`) hat 14 dedizierte Tests für exakt die gleichen Muster (Rollenprüfung, Guard-Logik, Modal-Interaktion).

**Empfehlung:** `CoursesView.test.ts` mit mindestens 4–5 Tests nachliefern: (a) Trainer sieht Buttons, (b) Kunde sieht nur Buchungsblock, (c) Gast sieht nichts, (d) `bookedCourseIds`-Badge vs. Buchen-Button. Dann `task-T01.test-report.md` erstellen. → `dev-javascript`

---

### WARNING · W-2: `CustomerBookingModal.vue:129` — `showSuccess` mit falscher Argumentanzahl

**Betrifft:** `frontend/src/components/CustomerBookingModal.vue` (T02)  
**Befund (belegt):** Zeile 129:
```ts
showSuccess(`${successCount} Termin(e) erfolgreich gebucht.`)  // ← einstellig
```
Signatur (`frontend/src/utils/errorHandler.ts:85`):
```ts
export function showSuccess(title: string, message?: string): void
```
Der Zählstring wird als *Toast-Titel* übergeben — kein separater Nachrichtentext. Die Spec (`tasks.md` T02, Submit-Logik) sieht zweistufige Form vor:
```ts
showSuccess('Buchung erfolgreich', `${successCount} Termin(e) gebucht.`)
```
Im selben Submit-Block wurde `showWarning` korrekt auf zweistufige Form gebracht (Zeile 135: `showWarning('Einige Termine konnten nicht gebucht werden', errors.join('\n'))`). Die `showSuccess`-Korrektur fehlt.

**Reviewer T02** hat dies als "Sollte" markiert; der Befund ist unaufgelöst.  
**Empfehlung:** Einzeilige Korrektur. → `dev-javascript`

---

### WARNING · W-3: `CourseDetailView.vue:59` — `bookingStatusLoading` initial `false` — Flicker-Schutz lückenhaft

**Betrifft:** `frontend/src/views/CourseDetailView.vue` (T03)  
**Befund (belegt):** Zeile 59: `const bookingStatusLoading = ref(false)`.  
Im Template (Zeile 336): `<div v-else-if="!bookingStatusLoading">` — zeigt den „Buchen"-Button.

Sequenz für einen bereits gebuchten Kunden:
1. Mount: `alreadyBooked=false`, `bookingStatusLoading=false` → „Buchen"-Button sichtbar ✗
2. `loadBookingStatus()` startet: `bookingStatusLoading=true` → Button ausgeblendet
3. API antwortet: `alreadyBooked=true`, `bookingStatusLoading=false` → Badge erscheint

Der „Buchen"-Button flackert kurz auf, bevor der Buchungsstatus bekannt ist. Reviewer T03 hat dies als "Sollte" markiert und `bookingStatusLoading = ref(true)` als Initialwert vorgeschlagen. Die Implementierung hat die `bookingStatusLoading`-Variable zwar eingeführt (Verbesserung gegenüber dem Spec-Entwurf ohne Flag), aber den Initialwert falsch gesetzt.

**Empfehlung:** `const bookingStatusLoading = ref(true)` — ein-Zeichen-Korrektur. → `dev-javascript`

---

## Suggestions (nicht blockierend)

**S-1 · T03 CTA-Guard-Struktur weicht von Spec ab**  
`CourseDetailView.vue` Zeile 321: äußeres `v-if="!isTrainerOrAdmin"` mit inneren `v-if="isCustomer"` / `v-else-if="!authStore.isAuthenticated"`. Spec sah zwei separate Sibling-Blöcke vor. Funktional äquivalent im Produktivbetrieb (keine Rolle ohne trainer/admin/customer), aber weniger robust. Könnte in separatem Refactoring angepasst werden.

**S-2 · T01 `@close`-Handler wurde korrekt extrahiert** ← Reviewer-Befund "Sollte" erledigt ✅  
Implementierung verwendet `@close="closeBookingModal"` — benannte Methode, kein Inline-Multi-Statement.

**S-3 · T03 Variablennamen weichen von Spec ab** (`alreadyBooked` statt `isAlreadyBooked`, `showBookingModal` statt `isBookingModalOpen`) — keine funktionale Auswirkung, "Könnte"-Level.

**S-4 · T02 `axios`-Default-Import** nur für `isAxiosError` genutzt — auf benannten Import umstellbar (`import { isAxiosError } from 'axios'`), "Kann"-Level.

---

## Erfüllt

**Completeness:**
- [x] T01: `CoursesView.vue` — alle 8 Akzeptanzkriterien implementiert, `vue-tsc` + Build fehlerfrei
- [x] T02: `CustomerBookingModal.vue` — neue Komponente, alle 8 Kern-Akzeptanzkriterien implementiert + 14 Tests grün
- [x] T03: `CourseDetailView.vue` — alle Akzeptanzkriterien implementiert + 14 Tests grün
- [x] Gesamte Test-Suite: 62/62 grün (5 Test-Dateien)
- [x] `npm run build` fehlerfrei (T01-Notes: 631 Module, T03-Notes: 18.42 kB CourseDetailView)

**Correctness:**
- [x] Rollenprüfung `isTrainerOrAdmin` / `isCustomer` korrekt aus `authStore` abgeleitet (belegt: `auth.ts:45–46`)
- [x] `bookedCourseIds`-Set korrekt via `/api/v1/bookings` gefüllt, `trainingSession?.course?.id`-Pfad gegen Backend-Response-Struktur verifiziert (`BookingController` + `TrainingSessionResource` + `CourseResource`)
- [x] `/api/v1/customers/profile` für `customerId` verwendet — nie aus `authStore` (korrekt)
- [x] Booking-Payload `trainingSessionId`, `customerId`, `dogId`, `notes` gegen `StoreBookingRequest::rules()` verifiziert
- [x] Partial-Success-Logik: `successCount > 0` und `errors.length > 0` unabhängig geprüft; `showWarning` korrekt zweistufig
- [x] `alreadyBooked`-Guard in T03 korrekt: filtert `confirmed|pending`, prüft `course.id`
- [x] Kein `v-html` ohne Sanitization (`DOMPurify` in CoursesView bereits vorhanden; CustomerBookingModal hat gar kein `v-html`)

**Coherence:**
- [x] Headless UI Modal-Struktur konsistent mit `BookingFormModal.vue` (TransitionRoot → Dialog → DialogPanel → DialogTitle)
- [x] T03 `bookingStatusLoading`-Flag implementiert (Reviewer-Sollte aus T03 umgesetzt, Initialwert jedoch lückenhaft — W-3)
- [x] `console.warn` statt stilles `catch {}` in T03 (Reviewer-Sollte aus T03 und T01 review umgesetzt)
- [x] `closeBookingModal()`-Methode in T01 extrahiert (Reviewer-Sollte aus T01 umgesetzt)

---

## Empfehlung an den User

Der Change ist **funktional vollständig und in allen Kernpfaden korrekt** — Build und 62 Tests bestätigen das. Drei "Sollte"-Befunde aus den Reviewer-Reports sind unaufgelöst: eine fehlende Test-Datei für CoursesView (W-1), eine einzeilige `showSuccess`-Korrektur (W-2) und ein falscher `ref(false)`-Initialwert für den Flicker-Guard (W-3). Alle drei sind kleine, isolierte Korrekturen. Empfehlung: vor dem Merge durch `dev-javascript` beheben, dann archivieren.
