# Tasks: customer-booking-ui

**Change-ID:** customer-booking-ui  
**Gesamtaufwand:** 3 Tasks, alle `dev-javascript`  
**Reihenfolge:** T02 zuerst (Modal), dann T01 und T03 (nutzen Modal)

---

## T01: CoursesView.vue — Rollenbasierte Button-Steuerung + Buchungs-CTA

- **Agent:** dev-javascript
- **Dateien:**
  - `frontend/src/views/courses/CoursesView.vue`
- **Abhängigkeiten:** T02 (CustomerBookingModal muss existieren)
- **Beschreibung:**

  1. `useAuthStore` aus `@/stores/auth` importieren.
  2. `isTrainerOrAdmin` und `isCustomer` als `computed`-Werte definieren
     (analog zu `CourseDetailView.vue` Zeile 50):
     ```ts
     const isTrainerOrAdmin = computed(() => authStore.isAuthenticated && authStore.isTrainer)
     const isCustomer = computed(() => authStore.isAuthenticated && authStore.isCustomer)
     ```
  3. Den „Neuer Kurs"-Button (aktuell Zeile 14) mit `v-if="isTrainerOrAdmin"` umschließen.
  4. Die „Bearbeiten"- und „Löschen"-Buttons (aktuell Zeile 84–85) mit
     `v-if="isTrainerOrAdmin"` umschließen.
  5. **Buchungs-Guard:** Wenn `isCustomer`, beim Mount `GET /api/v1/bookings` laden
     und `bookedCourseIds: Set<number>` aus aktiven Buchungen (`status: confirmed | pending`)
     aufbauen (Mapping: `booking.trainingSession?.course?.id`).
     `loadOwnBookings()` parallel zu `loadCourses()` in `onMounted` aufrufen.
  6. Pro Kurs-Karte: wenn `isCustomer`, statt der Trainer-Buttons einen Kunden-Block
     anzeigen:
     - Wenn `bookedCourseIds.has(course.id)`: Badge „Bereits gebucht"
       (Styling: `class="px-4 py-2 text-sm font-medium text-green-700 bg-green-100 rounded-lg"`)
     - Sonst: Button „Buchen" (`class="btn btn-primary flex-1"`)
       der `selectedCourseForBooking = course` setzt und `showBookingModal = true`.
  7. `CustomerBookingModal` importieren und am Ende des Templates einbinden:
     ```html
     <CustomerBookingModal
       :is-open="showBookingModal"
       :course-id="selectedCourseForBooking?.id"
       :course-name="selectedCourseForBooking?.name"
       @close="showBookingModal = false"
       @booked="onBookingCompleted"
     />
     ```
  8. `onBookingCompleted()`: `showBookingModal = false`, `loadOwnBookings()` aufrufen.

- **Akzeptanzkriterien:**
  - [ ] Trainer/Admin sehen „Neuer Kurs", „Bearbeiten", „Löschen" — kein „Buchen"
  - [ ] Kunde sieht keinen der drei Trainer-Buttons
  - [ ] Kunde sieht pro Kurs-Karte einen „Buchen"-Button
  - [ ] Für bereits gebuchte Kurse zeigt der Kunde „Bereits gebucht" (kein Button)
  - [ ] Das Buchungsmodal öffnet sich beim Klick auf „Buchen"
  - [ ] Nach erfolgreicher Buchung wird `bookedCourseIds` aktualisiert
  - [ ] Nicht-eingeloggte Benutzer sehen weder Trainer- noch Kunden-Buttons
  - [ ] `npm run build` läuft ohne TypeScript-Fehler oder Warnings durch

---

## T02: CustomerBookingModal.vue — Neues Kunden-Buchungsmodal

- **Agent:** dev-javascript
- **Dateien:**
  - `frontend/src/components/CustomerBookingModal.vue` *(neu anlegen)*
- **Abhängigkeiten:** keine
- **Beschreibung:**

  Neue, schlanke Komponente für den Kunden-Buchungsflow. Orientiert sich am
  Aufbau von `BookingFormModal.vue` (Headless UI `TransitionRoot`/`Dialog`),
  aber ohne Trainer-Kontext (kein Status-Dropdown, keine Fremdkunden-Auswahl).

  **Props:**
  ```ts
  interface Props {
    isOpen: boolean
    courseId: number | undefined
    courseName: string | undefined
  }
  const props = withDefaults(defineProps<Props>(), {
    courseId: undefined,
    courseName: undefined,
  })
  ```

  **Emits:** `close`, `booked`

  **Internes State:**
  ```ts
  const sessions = ref<Session[]>([])       // GET /api/v1/courses/{id}/sessions
  const dogs = ref<Dog[]>([])               // GET /api/v1/dogs
  const customerId = ref<number | null>(null) // GET /api/v1/customers/profile
  const selectedSessionIds = ref<Set<number>>(new Set())
  const selectedDogId = ref<number | null>(null)
  const notes = ref('')
  const loading = ref(false)
  const submitting = ref(false)
  ```

  **Interfaces (lokal definiert):**
  ```ts
  interface Session {
    id: number
    sessionDate: string
    startTime: string | null
    endTime: string | null
    status: string
  }
  interface Dog {
    id: number
    name: string
  }
  ```

  **Lade-Logik (`watch(() => props.isOpen)`):**
  Wenn `isOpen` wird `true` und `courseId` gesetzt:
  1. `GET /api/v1/courses/${courseId}/sessions` — nur Sessions mit `status === 'scheduled'`
     in `sessions` speichern; alle in `selectedSessionIds` vorselektieren.
  2. `GET /api/v1/customers/profile` → `customerId.value = response.data.data.id`
     (Endpunkt belegt durch `ProfileView.vue` Zeile 298)
  3. `GET /api/v1/dogs` → `dogs.value = response.data.data`
  4. Falls nur ein Hund: automatisch selektieren.
  5. Falls `sessions.length === 0`: Fehlermeldung anzeigen.

  **Template-Struktur:**
  - Modal-Wrapper via Headless UI (analog `BookingFormModal.vue`)
  - Titel: `Kurs buchen — {{ courseName }}`
  - **Session-Auswahl (Checkboxen):** bei `sessions.length > 1` alle anzeigen,
    pre-checked; bei `sessions.length === 1` Session-Info anzeigen (kein Checkbox
    nötig, automatisch gewählt).
    - Checkbox deaktiviert wenn `session.status !== 'scheduled'`
    - Session-Label: `DD.MM.YYYY HH:mm – HH:mm (Ort)`
  - **Hund-Auswahl (Select):** Dropdown `<select>` mit eigenem Hunden; falls keine
    Hunde vorhanden: Hinweis „Bitte zuerst einen Hund anlegen."
  - **Notizen:** optionales `<textarea>`, max. 1000 Zeichen
  - **Buttons:**
    - „Abbrechen" (`class="btn bg-gray-100 hover:bg-gray-200 text-gray-700"`)
      → `$emit('close')`, Formular zurücksetzen
    - „Buchen" (`class="btn btn-primary"`, `type="submit"`, `:disabled="submitting || !canSubmit"`)

  **Submit-Logik:**
  ```ts
  async function handleSubmit() {
    // canSubmit = selectedSessionIds.size > 0 && selectedDogId && customerId
    submitting.value = true
    const errors: string[] = []
    let successCount = 0
    for (const sessionId of selectedSessionIds.value) {
      try {
        await apiClient.post('/api/v1/bookings', {
          trainingSessionId: sessionId,
          customerId: customerId.value,
          dogId: selectedDogId.value,
          notes: notes.value || undefined,
        })
        successCount++
      } catch (err) {
        errors.push(`Termin #${sessionId}: ${extractErrorMessage(err)}`)
      }
    }
    submitting.value = false
    if (successCount > 0) {
      showSuccess('Buchung erfolgreich', `${successCount} Termin(e) gebucht.`)
      emit('booked')
      resetForm()
      emit('close')
    }
    if (errors.length > 0) {
      showWarning('Teilweise fehlgeschlagen', errors.join('\n'))
    }
  }
  ```

  `resetForm()`: Alle `ref`-Werte zurücksetzen.

- **Akzeptanzkriterien:**
  - [ ] Modal öffnet sich mit Kursname im Titel
  - [ ] Sessions werden geladen; bei mehreren Terminen sind alle vorausgewählt
  - [ ] Einzelne Sessions können ab-/angewählt werden
  - [ ] Eigene Hunde erscheinen im Dropdown (keine fremden Hunde)
  - [ ] Buchen-Button ist deaktiviert solange kein Hund gewählt oder keine Session selektiert
  - [ ] Abbrechen schließt das Modal ohne Buchung
  - [ ] Bei erfolgreicher Buchung wird `booked` emittiert und Modal geschlossen
  - [ ] Bei voller Session (422 vom Backend) erscheint eine Fehlermeldung
  - [ ] Bei Serienbuchung mit gemischtem Ergebnis (ein Termin OK, einer voll)
        werden Erfolge und Fehler separat gemeldet
  - [ ] `npm run build` läuft ohne TypeScript-Fehler oder Warnings durch

---

## T03: CourseDetailView.vue — Kunden-Buchungs-CTA

- **Agent:** dev-javascript
- **Dateien:**
  - `frontend/src/views/CourseDetailView.vue`
- **Abhängigkeiten:** T02 (CustomerBookingModal muss existieren)
- **Beschreibung:**

  `CourseDetailView.vue` importiert `useAuthStore` bereits (Zeile 3) und hat
  `isTrainerOrAdmin` als Computed (Zeile 50). Ergänzungen:

  1. **`isCustomer` computed hinzufügen:**
     ```ts
     const isCustomer = computed(() => authStore.isAuthenticated && authStore.isCustomer)
     ```

  2. **Buchungs-Guard:**
     ```ts
     const isAlreadyBooked = ref(false)
     const isBookingModalOpen = ref(false)

     async function checkOwnBooking() {
       if (!isCustomer.value) return
       try {
         const res = await apiClient.get('/api/v1/bookings')
         const bookings: any[] = res.data.data ?? []
         isAlreadyBooked.value = bookings.some(
           b => ['confirmed', 'pending'].includes(b.status) &&
                b.trainingSession?.course?.id === courseId.value
         )
       } catch {
         // Guard-Fehler sind nicht kritisch, still ignorieren
       }
     }
     ```
     `checkOwnBooking()` in `onMounted` nach `loadCourse()` aufrufen.

  3. **Template-Änderung:** Den bestehenden Block
     `<div v-if="!isTrainerOrAdmin" class="bg-primary-50 ...">` (ca. Zeile 305)
     aufteilen in:

     ```html
     <!-- Nicht eingeloggt: bisheriger Kontakt/Anmelden-Block -->
     <div v-if="!authStore.isAuthenticated" class="bg-primary-50 ...">
       <!-- ... bisheriger Inhalt bleibt unverändert ... -->
     </div>

     <!-- Eingeloggter Kunde: Buchungs-CTA -->
     <div v-else-if="isCustomer" class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 rounded-lg p-6 text-center">
       <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
         Kurs buchen
       </h3>
       <div v-if="isAlreadyBooked" class="inline-block px-5 py-2 text-sm font-medium text-green-700 bg-green-100 dark:bg-green-900/30 dark:text-green-300 rounded-lg">
         Bereits gebucht
       </div>
       <button
         v-else
         type="button"
         class="btn btn-primary px-6 py-2"
         @click="isBookingModalOpen = true"
       >
         Jetzt buchen
       </button>
     </div>
     ```

  4. **`CustomerBookingModal` einbinden** (nach `CourseFormModal`):
     ```html
     <CustomerBookingModal
       v-if="isCustomer && course"
       :is-open="isBookingModalOpen"
       :course-id="course.id"
       :course-name="course.name"
       @close="isBookingModalOpen = false"
       @booked="onBooked"
     />
     ```

  5. **`onBooked()`:**
     ```ts
     async function onBooked() {
       isBookingModalOpen.value = false
       await checkOwnBooking()
     }
     ```

  6. **Import** oben ergänzen: `import CustomerBookingModal from '@/components/CustomerBookingModal.vue'`

- **Akzeptanzkriterien:**
  - [ ] Nicht-eingeloggte Besucher sehen nach wie vor „Kontakt aufnehmen / Anmelden"
  - [ ] Eingeloggte Kunden sehen „Jetzt buchen"-Button
  - [ ] Eingeloggte Trainer/Admins sehen weder „Kontakt"- noch „Buchen"-Block
        (weil `isTrainerOrAdmin` weiterhin den Edit-Button steuert und die neuen
        Blöcke nur für `!isAuthenticated` bzw. `isCustomer` sichtbar sind)
  - [ ] Für bereits gebuchten Kurs zeigt der Kunde „Bereits gebucht"
  - [ ] Das Modal öffnet sich und schließt korrekt
  - [ ] Nach erfolgreicher Buchung aktualisiert sich der Guard-Status
  - [ ] `npm run build` läuft ohne TypeScript-Fehler oder Warnings durch
