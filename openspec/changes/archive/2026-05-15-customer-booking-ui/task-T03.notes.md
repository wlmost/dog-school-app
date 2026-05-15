# Task T03 — CourseDetailView.vue anpassen

**Status:** ✅ Abgeschlossen  
**Datei:** `frontend/src/views/CourseDetailView.vue`

---

## Durchgeführte Änderungen

### 1. Import ergänzt
`CustomerBookingModal` aus `@/components/CustomerBookingModal.vue` importiert.

### 2. Neue reaktive States
- `isCustomer` — `computed(() => authStore.isAuthenticated && authStore.isCustomer)`
- `alreadyBooked` — `ref(false)`
- `showBookingModal` — `ref(false)`

### 3. `loadBookingStatus()` hinzugefügt
- Nur ausgeführt wenn `isCustomer.value && courseId.value`
- Lädt `/api/v1/bookings`, filtert nach `confirmed`/`pending`, setzt `alreadyBooked`
- Fehler werden mit `console.warn(...)` geloggt (kein stilles catch)

### 4. `onMounted` erweitert
`loadBookingStatus()` wird parallel zu `loadCourse()` aufgerufen.

### 5. Modal-Handler als Methoden extrahiert
- `closeBookingModal()` — setzt `showBookingModal = false`
- `onBookingCompleted()` — setzt Modal zu und ruft `loadBookingStatus()` neu

### 6. CTA-Block ersetzt
- Eingeloggter Kunde + bereits gebucht → grünes Häkchen + Hinweistext
- Eingeloggter Kunde + noch nicht gebucht → "Jetzt buchen"-Button → öffnet `CustomerBookingModal`
- Gast (nicht eingeloggt) → bisheriger CTA mit Kontakt/Anmelden-Links

`CustomerBookingModal` wird mit `:course-id`, `:course-name`, `@close` und `@booked` eingebunden.  
`@close` → `closeBookingModal`, `@booked` → `onBookingCompleted` (keine Inline-Handler).

---

## Reviewer-Anmerkungen beachtet
- ✅ Keine Inline-Multi-Statement-Handler im Template
- ✅ Kein stilles `catch {}` — `console.warn` gesetzt
- ✅ Optionales Chaining bei `course?.id` und `course?.name`
- ✅ `@close="isEditModalOpen = false"` bei `CourseFormModal` bleibt single-statement — akzeptabel

---

## Build + Test
- `npm run build` → ✅ fehlerfrei (631 Module, `CourseDetailView` 18.42 kB)
- `npx vitest run` → ✅ 48/48 Tests grün
