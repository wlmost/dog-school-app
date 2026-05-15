# Design: customer-booking-ui

**Change-ID:** customer-booking-ui  
**Stack:** Vue 3, `<script setup lang="ts">`, Composition API, Tailwind CSS  
**Backend:** keine Änderungen — alle Endpunkte bereits vorhanden

---

## 1. Übersicht der betroffenen Dateien

| Datei | Aktion | Grund |
|-------|--------|-------|
| `frontend/src/views/courses/CoursesView.vue` | Ändern | `authStore` fehlt; keine Rollenprüfungen |
| `frontend/src/components/CustomerBookingModal.vue` | Neu anlegen | Kunden-Buchungsflow |
| `frontend/src/views/CourseDetailView.vue` | Ändern | Buchungs-CTA für Kunden; Guard |

---

## 2. Architektur-Entscheidungen

### 2.1 Rollenprüfung in `CoursesView.vue`

**Ist-Zustand (belegt durch Code-Lektüre):**
- `frontend/src/views/courses/CoursesView.vue` Zeilen 14–19: „Neuer Kurs"-Button — kein `v-if`
- Zeilen 84–85: „Bearbeiten"/„Löschen" — kein `v-if`
- `authStore` wird nicht importiert

**Soll-Zustand:**

```ts
import { useAuthStore } from '@/stores/auth'
const authStore = useAuthStore()
const isTrainerOrAdmin = computed(() => authStore.isAuthenticated && authStore.isTrainer)
const isCustomer = computed(() => authStore.isAuthenticated && authStore.isCustomer)
```

> Hinweis: `authStore.isTrainer` gibt `true` für role `trainer` **und** `admin`
> (wie in `frontend/src/stores/auth.ts` Zeile 44 definiert). Diese Semantic ist
> konsistent mit `CourseDetailView.vue` (`isTrainerOrAdmin = authStore.isTrainer`).

### 2.2 Mehrfachbuchungs-Guard — Lade-Strategie

**Problem:** Kunden sehen in `CoursesView` viele Kurse. Wir müssen wissen,
welche Kurse der Kunde bereits aktiv gebucht hat, ohne für jede Kurs-Karte
einen API-Call zu machen.

**Entscheidung:** Einmalig `GET /api/v1/bookings` beim Mount der View laden
(role-filtered: Kunden sehen nur eigene). Aus der Antwort eine `Set<number>`
der `trainingSession.course.id`-Werte mit Status `confirmed` oder `pending`
aufbauen (`bookedCourseIds`).

```ts
// Nur wenn isCustomer
const bookedCourseIds = ref<Set<number>>(new Set())

async function loadOwnBookings() {
  const res = await apiClient.get('/api/v1/bookings')
  const bookings: any[] = res.data.data ?? []
  bookedCourseIds.value = new Set(
    bookings
      .filter(b => ['confirmed', 'pending'].includes(b.status))
      .map(b => b.trainingSession?.course?.id)
      .filter(Boolean)
  )
}
```

`loadOwnBookings()` wird in `onMounted` parallel zu `loadCourses()` aufgerufen.
Nach einer erfolgreichen Buchung wird `loadOwnBookings()` erneut aufgerufen.

### 2.3 `CustomerBookingModal.vue` — Komponenten-Design

**Props:**
```ts
interface Props {
  isOpen: boolean
  courseId: number
  courseName: string
}
```

**Emits:** `close`, `booked`

**Internes Lade-Sequenz beim Öffnen (`watch(isOpen)`):**
1. `GET /api/v1/courses/{courseId}/sessions` → verfügbare Sessions (Status `scheduled`)
2. `GET /api/v1/customers/profile` → `customerId` des eingeloggten Kunden
3. `GET /api/v1/dogs` → eigene Hunde des Kunden (role-filtered durch Backend)

**Serien- vs. Einzeltermin-Erkennung:**
- Wenn `sessions.length > 1`: Multi-Select mit Checkboxen, alle vorausgewählt
  (Terminserie-Verhalten)
- Wenn `sessions.length === 1`: Einzeltermin, automatisch selektiert (kein Auswahlbedarf)
- Wenn `sessions.length === 0`: Fehlermeldung „Keine buchbaren Termine verfügbar"

> Begründung: Statt `recurrenceRule` weiterzureichen (das in `CoursesView`
> nicht zuverlässig verfügbar ist), orientieren wir uns pragmatisch an der
> tatsächlichen Anzahl buchbarer Sessions. Das Ergebnis ist identisch, da
> Serienkurse stets mehrere Sessions haben.

**Buchungs-Payload pro Session:**
```ts
{
  trainingSessionId: session.id,  // number
  customerId: customerId,          // number aus /customers/profile
  dogId: selectedDogId,            // number
  notes: notes ?? undefined        // optional string
}
```
(Entspricht `StoreBookingRequest::rules()`: `trainingSessionId`, `customerId`,
`dogId`, `notes` — belegt durch `backend/app/Http/Requests/StoreBookingRequest.php`)

**Serienbuchung — Submit-Logik:**
- Iteriere über alle selektierten Sessions
- Sende für jede Session einen separaten `POST /api/v1/bookings`
- Sammle Erfolge und Fehler
- Bei gemischtem Ergebnis: zeige Fehlermeldung für fehlgeschlagene Sessions,
  emitte `booked` für erfolgreiche

**Abbrechen-Button:** `@click="$emit('close')"` — kein API-Call, modal wird
geschlossen, Formular wird zurückgesetzt.

### 2.4 `CourseDetailView.vue` — Kunden-CTA

**Ist-Zustand (belegt durch Code-Lektüre, Zeilen 305–325):**  
Ein generischer Block `v-if="!isTrainerOrAdmin"` zeigt „Interesse an diesem Kurs?
Kontakt aufnehmen / Anmelden" — auch für bereits eingeloggte Kunden.

**Soll-Zustand:**

```html
<!-- Für nicht-eingeloggte Besucher -->
<div v-if="!authStore.isAuthenticated">
  <!-- Bisheriger "Kontakt / Anmelden"-Block -->
</div>

<!-- Für eingeloggte Kunden -->
<div v-else-if="isCustomer">
  <button v-if="!isAlreadyBooked" @click="isBookingModalOpen = true"
    class="btn btn-primary px-6 py-2">Jetzt buchen</button>
  <span v-else class="...">Bereits gebucht</span>
</div>

<CustomerBookingModal
  :is-open="isBookingModalOpen"
  :course-id="courseId"
  :course-name="course.name"
  @close="isBookingModalOpen = false"
  @booked="onBooked"
/>
```

**Guard in `CourseDetailView`:**
`isAlreadyBooked` wird via `GET /api/v1/bookings` nach gleichem Muster wie in
`CoursesView` geprüft. Da es hier nur um einen einzelnen Kurs geht:
```ts
const isAlreadyBooked = ref(false)

async function checkOwnBooking() {
  if (!isCustomer.value) return
  const res = await apiClient.get('/api/v1/bookings')
  const bookings: any[] = res.data.data ?? []
  isAlreadyBooked.value = bookings.some(b =>
    ['confirmed', 'pending'].includes(b.status) &&
    b.trainingSession?.course?.id === courseId.value
  )
}
```

---

## 3. Verwendete Patterns (konsistent mit Codebase)

| Pattern | Quelle |
|---------|--------|
| `withDefaults(defineProps<...>())` | `BookingFormModal.vue`, `CourseFormModal.vue` |
| `apiClient` aus `@/api/client` | alle Views |
| `handleApiError`, `showSuccess` aus `@/utils/errorHandler` | `CoursesView.vue`, `CourseDetailView.vue` |
| `useAuthStore()` aus `@/stores/auth` | `CourseDetailView.vue` |
| `class="btn btn-primary"` | alle Views |
| Abbrechen: `class="btn bg-gray-100 hover:bg-gray-200 text-gray-700"` | `BookingFormModal.vue` |
| Modal-Struktur mit `TransitionRoot`/`Dialog` (Headless UI) | `BookingFormModal.vue` |

---

## 4. DB- / Backend-Portabilität

Dieser Change ist ein reiner Frontend-Change. Keine Migrations, kein PHP-Code.
PHP-Kompatibilitäts-Regeln aus CLAUDE.md Abschnitt 4.1 nicht anwendbar.

---

## 5. Sicherheitsbetrachtung

- **Keine clientseitige Autorisierung:** Die `v-if`-Guards dienen ausschließlich
  der UX. Das Backend (Policies) ist die einzige Quelle der Wahrheit für Berechtigungen.
- **XSS:** Das bestehende `sanitizeHtml`/DOMPurify-Pattern in `CoursesView` wird
  nicht verändert. `CustomerBookingModal` rendert keine HTML-Inhalte via `v-html`.
- **customerId:** Wird vom Backend via `GET /api/v1/customers/profile` bezogen —
  nie aus dem authStore direkt übernommen, da der authStore nur `user.id` (User-ID),
  nicht die `customer.id` (Customer-Record-ID) enthält.

---

## 6. Nicht geplante Erweiterungen (YAGNI)

- Kein Warte-Listen-Feature (obwohl das Backend einen 422 zurückgibt, wenn
  die Session voll ist — wird als Fehlerfall im Modal behandelt).
- Keine Echtzeit-Verfügbarkeitsanzeige.
- Kein Buchungs-Storno aus dem Modal heraus (separate Change).
