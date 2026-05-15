# Task T01 — Notes: CoursesView.vue anpassen

**Status:** Implementiert ✅  
**Datei:** `frontend/src/views/courses/CoursesView.vue`

---

## Durchgeführte Änderungen

### Script

1. **Import erweitert:** `computed` aus `vue` hinzugefügt.
2. **Neue Imports:** `useAuthStore` aus `@/stores/auth` und `CustomerBookingModal` aus `@/components/CustomerBookingModal.vue`.
3. **Auth-Store + Computed:**
   - `authStore = useAuthStore()`
   - `isTrainerOrAdmin` — `true` wenn `isAuthenticated && isTrainer` (isTrainer deckt auch admin ab laut Store-Definition)
   - `isCustomer` — `true` wenn `isAuthenticated && isCustomer`
4. **Neue Refs:** `bookedCourseIds: Set<number>`, `showBookingModal`, `selectedCourseForBooking`
5. **`onMounted` erweitert:** Lädt `loadOwnBookings()` parallel zu `loadCourses()` wenn Nutzer Kunde ist.
6. **`loadOwnBookings()`:** Lädt `/api/v1/bookings`, filtert auf `confirmed`/`pending` und baut ein `Set` aus Kurs-IDs. Fehler werden ignoriert (non-blocking).
7. **`openBookingModal()`** und **`onBookingCompleted()`** hinzugefügt.

### Template

- `<button>Neuer Kurs</button>` bekommt `v-if="isTrainerOrAdmin"`.
- Kurs-Karten-Footer: Bearbeiten/Löschen-Buttons in `<template v-if="isTrainerOrAdmin">` gewrappt; Kunden sehen `<template v-else-if="isCustomer">` mit "Bereits gebucht"-Badge oder "Buchen"-Button.
- `<CustomerBookingModal>` nach `<CourseFormModal>` eingefügt (Props: `:course-id`, `:course-name`; Events: `@close`, `@booked`).

---

## Verifikation

| Prüfung | Ergebnis |
|---------|----------|
| `vue-tsc` + `vite build` | ✅ Fehlerfrei |
| `npx vitest run` | ✅ 48/48 Tests grün |

---

## Beobachtungen / Offene Punkte

- `isTrainer` im Auth-Store deckt bereits `admin` ab (`role === 'trainer' || role === 'admin'`), daher ist `isTrainerOrAdmin` korrekt benannt und verhält sich wie erwartet.
- `bookedCourseIds` verwendet Kurs-IDs aus `b.trainingSession?.course?.id` — setzt voraus, dass die Booking-API diese Nesting-Struktur liefert. Falls die API-Antwort anders strukturiert ist, muss `loadOwnBookings` angepasst werden.
- `CustomerBookingModal` erwartet laut Test `:course-id` und `:course-name` als Props — diese werden korrekt übergeben.
