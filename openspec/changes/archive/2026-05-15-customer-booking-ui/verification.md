# Verification: customer-booking-ui

**Gesamtstatus:** ok — mit zwei Hinweisen (beide nicht blockierend)

---

## Bestätigt

### CoursesView.vue

- **proposal.md / design.md: „`authStore` wird nicht importiert"**
  → bestätigt in `frontend/src/views/courses/CoursesView.vue`
  Script-Imports (ab Zeile 97): `{ ref, onMounted }`, `apiClient`, `CourseFormModal`, `handleApiError`,
  `showSuccess`, `DOMPurify` — kein `useAuthStore`.

- **design.md: „Neuer Kurs"-Button ohne `v-if`, Zeile 14"**
  → bestätigt: `frontend/src/views/courses/CoursesView.vue:14`
  `<button @click="openCreateModal" class="btn btn-primary">` — kein `v-if`.

- **design.md: „Bearbeiten"/„Löschen"-Buttons ohne `v-if`, Zeilen 84–85"**
  → bestätigt mit 1-Zeile Versatz: tatsächlich `CoursesView.vue:85–86`
  ```
  85: <button @click="editCourse(course)" ...>Bearbeiten</button>
  86: <button @click="deleteCourse(course)" ...>Löschen</button>
  ```
  Kein `v-if` an beiden Buttons. (Spec-Zeilenangabe ist 1 zu früh — nicht kritisch.)

- **design.md: `handleApiError`, `showSuccess` aus `@/utils/errorHandler` vorhanden in CoursesView**
  → bestätigt: `CoursesView.vue:101` (import-Zeile im Script).

- **design.md: DOMPurify / `sanitizeHtml` vorhanden in CoursesView**
  → bestätigt: `CoursesView.vue:41` (`v-html="sanitizeHtml(course.description)"`).

### Auth-Store

- **design.md Abschnitt 2.1: "`authStore.isTrainer` gibt `true` für role `trainer` **und** `admin`"**
  → bestätigt in `frontend/src/stores/auth.ts:45`:
  `const isTrainer = computed(() => user.value?.role === 'trainer' || user.value?.role === 'admin')`
  (Spec nennt Zeile 44; tatsächlich Zeile 45 — `isAdmin` steht auf 44. Inhaltlich korrekt.)

- **design.md: `isCustomer` gibt `true` nur für role `customer`"**
  → bestätigt in `auth.ts:46`:
  `const isCustomer = computed(() => user.value?.role === 'customer')`

- **design.md: `isAuthenticated` als Computed vorhanden**
  → bestätigt in `auth.ts:43`.

### CourseDetailView.vue

- **triage.md / design.md: `useAuthStore` bereits importiert**
  → bestätigt: `CourseDetailView.vue:4`
  `import { useAuthStore } from '@/stores/auth'`

- **tasks.md T03: `isTrainerOrAdmin` als Computed (tasks.md sagt „Zeile 50")**
  → bestätigt mit leichtem Versatz: tatsächlich ca. Zeile 55
  `const isTrainerOrAdmin = computed(() => authStore.isAuthenticated && authStore.isTrainer)`

- **triage.md: „Kurs bearbeiten" hinter `v-if="isTrainerOrAdmin"` (Zeile 186)"**
  → bestätigt: `CourseDetailView.vue` Template, Zeile ~183:
  `<div v-if="isTrainerOrAdmin">` + darin `<button ...>Kurs bearbeiten</button>`

- **triage.md: „Kein ‚Löschen'-Button in der Detailansicht vorhanden"**
  → bestätigt: kein Löschen-Button im gesamten Template von `CourseDetailView.vue` gefunden.

- **design.md Abschnitt 2.4: CTA-Block `v-if="!isTrainerOrAdmin"` mit Interesse/Anmelden-Inhalt, Zeilen 305–325**
  → bestätigt mit leichtem Versatz: Block beginnt ca. Zeile 295:
  `<div v-if="!isTrainerOrAdmin" class="bg-primary-50 ...">Interesse an diesem Kurs?...`
  Enthält RouterLink zu `/contact` und `/login` — kein rollenspezifischer Branch,
  Kunden sehen den selben Block wie nicht-eingeloggte Besucher. Ist exakt der
  beschriebene Bug.

### Backend — Bookings

- **proposal.md: `BookingPolicy::create` — alle authentifizierten User können buchen**
  → bestätigt: `BookingPolicy.php:46`: `return true;` (Kommentar: "All authenticated users can create bookings")

- **proposal.md: `BookingPolicy::cancel` — Kunden können eigene Buchungen stornieren**
  → bestätigt: `BookingPolicy.php:66–72`: Customer-Branch prüft `$booking->customer->user_id === $user->id`.

- **proposal.md / design.md: `GET /api/v1/bookings` — Kunden sehen nur eigene Buchungen**
  → bestätigt: `BookingController.php` `index`-Methode, Customer-Branch:
  ```php
  $customer = Customer::where('user_id', $user->id)->first();
  $query->where('customer_id', $customer->id);
  ```
  `BookingPolicy::viewAny` gibt `true` für alle, Filterung erfolgt im Controller.

- **design.md Abschnitt 2.2: `booking.trainingSession?.course?.id` im Booking-Response verfügbar**
  → bestätigt durch Kette:
  `BookingController::index` lädt `with(['trainingSession.course', 'customer.user', 'dog'])`;
  `BookingResource` enthält `'trainingSession' => new TrainingSessionResource(...)`;
  `TrainingSessionResource` enthält `'course' => new CourseResource(...)`;
  `CourseResource` enthält `'id' => $this->id`.
  Der optionale Chaining-Pfad `b.trainingSession?.course?.id` ist damit valide.

### Backend — POST /api/v1/bookings Payload

- **design.md Abschnitt 2.3: Payload `trainingSessionId`, `customerId`, `dogId`, `notes`**
  → bestätigt: `StoreBookingRequest.php:34–37`
  ```php
  'trainingSessionId' => ['required', 'integer', 'exists:training_sessions,id'],
  'customerId'        => ['required', 'integer', 'exists:customers,id'],
  'dogId'             => ['required', 'integer', 'exists:dogs,id'],
  'notes'             => ['nullable', 'string', 'max:1000'],
  ```

- **design.md: `notes` max 1000 Zeichen**
  → bestätigt: `StoreBookingRequest.php:37`: `'max:1000'`

### Backend — GET /api/v1/customers/profile

- **design.md / tasks.md T02: Endpunkt existiert**
  → bestätigt: `backend/routes/api.php:91`:
  `Route::get('/customers/profile', [CustomerController::class, 'profile']);`
  (Geschützter Bereich unter `auth:sanctum`-Middleware)

- **tasks.md T02: „belegt durch `ProfileView.vue` Zeile 298"**
  → bestätigt: `frontend/src/views/ProfileView.vue:298`:
  `const response = await apiClient.get('/api/v1/customers/profile')`

- **design.md: Response-Pfad `response.data.data.id` für customerId**
  → bestätigt: `CustomerController::profile` gibt `new CustomerResource($customer)` zurück;
  `CustomerResource.php:26`: `'id' => $this->id`. Laravel API Resource wrapping ergibt `{ data: { id: ... } }`.

- **design.md: `/customers/profile` nur für Kunden (403 für andere Rollen)**
  → bestätigt: `CustomerController.php:258`:
  `if (!$user->isCustomer()) { return response()->json(['message' => 'Nur für Kunden verfügbar.'], 403); }`

### Backend — GET /api/v1/dogs

- **proposal.md / design.md: Kunden sehen nur eigene Hunde**
  → bestätigt: `DogController.php` `index`-Methode, Customer-Branch:
  ```php
  $customer = Customer::where('user_id', $user->id)->first();
  $query->where('customer_id', $customer->id);
  ```

### Backend — GET /api/v1/courses/{id}/sessions

- **proposal.md: `CoursePolicy::view` erlaubt allen authentifizierten Usern den Zugriff**
  → bestätigt: `CoursePolicy.php:31`: `return true;` (Kommentar: "All authenticated users can view courses")
  `CourseController::sessions()` ruft `$this->authorize('view', $course)` auf — läuft für alle
  eingeloggten Rollen durch.

- **design.md Abschnitt 2.3: Session-Response enthält `status`-Feld zum Filtern auf `scheduled`**
  → bestätigt: `TrainingSessionResource.php:30`: `'status' => $this->status`

### Backend — CourseController::index

- **triage.md: „`CourseController::index` enthält expliziten `isCustomer()`-Zweig" (Zeile 56–57)**
  → bestätigt mit ca. 1-Zeile Versatz, tatsächlich ca. Zeile 55–58:
  `elseif ($user->isCustomer()) { $query->where('status', 'active'); }`

### BookingFormModal.vue (bestehend)

- **triage.md / design.md: Komponente hat Trainer-Kontext — zeigt alle Kunden-Hunde und Status-Dropdown**
  → bestätigt: `BookingFormModal.vue:43`: Hunde-Dropdown zeigt
  `{{ dog.name }} ({{ dog.customer?.user?.fullName }})` — impliziert Multi-Kunden-Sicht.
  `BookingFormModal.vue:71–76`: Status-Dropdown mit `pending/confirmed/cancelled/attended`.
  Bestätigt: nicht für Kunden-Self-Service geeignet.

### CourseResource — kein recurrenceRule-Feld

- **design.md Abschnitt 2.3: „`recurrenceRule` in `CoursesView` nicht zuverlässig verfügbar"**
  → bestätigt: `CourseResource.php` enthält **kein** `recurrenceRule`-Feld.
  Die Entscheidung, stattdessen `sessions.length` zur Serie/Einzel-Erkennung zu nutzen, ist korrekt.

### Routing-Reihenfolge customers/profile

- `Route::get('/customers/profile', ...)` ist in `api.php:91` **vor**
  `Route::apiResource('customers', ...)` (Zeile 92) registriert.
  → `profile` wird nicht als `{customer}`-Parameter aufgelöst. Kein Konflikt. ✓

---

## Widerlegt

*Keine inhaltlich widerlegten Behauptungen gefunden.*

---

## Nicht auffindbar

*Keine Behauptungen, die sich nicht verifizieren ließen.*

---

## Hinweise (nicht blockierend)

### H1 — Zeilenangaben leicht ungenau (nicht kritisch)

Mehrere Zeilenangaben in Spec weichen 1–5 Zeilen vom tatsächlichen Code ab:

| Spec-Angabe | Tatsächlich | Datei |
|-------------|-------------|-------|
| `CoursesView.vue` Zeilen 84–85 (Bearbeiten/Löschen) | 85–86 | `CoursesView.vue` |
| `auth.ts` Zeile 44 (`isTrainer`) | 45 | `auth.ts` |
| `CourseDetailView.vue` Zeile 50 (`isTrainerOrAdmin`) | ~55 | `CourseDetailView.vue` |
| `CourseDetailView.vue` Zeilen 305–325 (CTA-Block) | ~295 | `CourseDetailView.vue` |

Alle Inhalte sind korrekt; Zeilenabweichungen entstehen durch Zählunterschiede
bei Leerzeilen und Interface-Definitionen. Kein Handlungsbedarf für den Entwickler.

### H2 — `GET /api/v1/courses/{id}/sessions` lädt `course`-Relation nicht mit

`CourseController::sessions()` ruft `$course->sessions()->with(['trainer', 'bookings'])` auf —
**nicht** `course`. Das ist für die Spec kein Problem, weil das Modal die Sessions über
diesen Endpunkt holt und `course.id` bereits als Prop bekannt ist (kein Traversal
`session.course.id` nötig). Nur im Booking-Guard (`loadOwnBookings` via `GET /api/v1/bookings`)
wird `trainingSession.course` traversiert — dort ist die Relation durch den
`with(['trainingSession.course', ...])` im `BookingController::index` korrekt geladen.

---

## Neue Elemente (Plausibilität)

- **T02: `frontend/src/components/CustomerBookingModal.vue` (neu anlegen)**
  → `frontend/src/components/` existiert und enthält u. a. `BookingFormModal.vue`.
  Dateiname ist konsistent mit Namenskonvention (PascalCase SFC). Kein Konflikt. ✓

---

## Empfehlung

Die Spec ist inhaltlich vollständig verlässlich und bereit für die Implementierung.
Die leichten Zeilenversätze (H1) sind für den Entwickler irrelevant — der Code ändert
sich, Zeilen verschieben sich. H2 ist eine nützliche Klarstellung, kein Fehler.
