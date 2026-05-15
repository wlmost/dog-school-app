# Review: T03 — CourseDetailView.vue

**Reviewer:** reviewer-agent  
**Datum:** 2026-05-15  
**Gesamtempfehlung:** APPROVED WITH NOTES

---

## Muss (blockiert Abnahme)

_Keine Befunde._

---

## Sollte (vor Merge erledigen, kann diskutiert werden)

### [Korrektheit / UX] Race condition: "Buchen"-Button flackert für bereits gebuchte Kurse

`CourseDetailView.vue`, `onMounted` (Zeile 148–151):

```ts
onMounted(() => {
  loadCourse()
  loadBookingStatus()
})
```

Beide Requests laufen ohne `await` parallel. Wenn `loadCourse()` vor `loadBookingStatus()` auflöst (wahrscheinlich, da beide unabhängige Requests sind), rendert das Template den CTA-Block mit `alreadyBooked = false` — zeigt also kurz den „Buchen"-Button, obwohl der Kunde bereits gebucht hat. Dann setzt `loadBookingStatus()` `alreadyBooked = true` und korrigiert die Darstellung.

Das ist kein Datenintegritätsproblem (das Backend blockt Doppelbuchungen), aber ein sichtbarer Flicker für Stammkunden.

**Vorschlag:** Ein `bookingStatusLoading = ref(true)`-Flag einführen, das in `loadBookingStatus()` am Anfang gesetzt und im `finally`-Block zurückgesetzt wird. Den Buchungs-CTA hinter `v-if="\!bookingStatusLoading"` oder durch einen kleinen Inline-Spinner ersetzen, bis beide Loads abgeschlossen sind.

---

### [Spec-Konformität] CTA-Guard-Logik weicht von Spec ab

`CourseDetailView.vue`, Template (Zeile 312–366):

Implementierung:
```html
<div v-if="\!isTrainerOrAdmin" ...>
  <template v-if="isCustomer">...</template>
  <template v-else><\!-- Gast: Kontakt / Anmelden --></template>
</div>
```

Spec (tasks.md T03, Punkt 3):
```html
<div v-if="\!authStore.isAuthenticated" ...><\!-- Gast --></div>
<div v-else-if="isCustomer" ...><\!-- Kunde --></div>
```

Im aktuellen Code fällt ein eingeloggter Nutzer, der weder Trainer/Admin noch Customer ist, in den `v-else`-Zweig und sieht „Kontakt aufnehmen / Anmelden"-Links — obwohl er bereits eingeloggt ist. Die Spec würde für diesen Fall gar nichts anzeigen, was korrekter wäre.

In der Praxis gibt es wahrscheinlich keine solchen Accounts, dennoch weicht die Guard-Semantik vom vereinbarten Design ab. Eine direkte Umsetzung der Spec-Struktur (zwei separate Blöcke) wäre robuster und leichter nachzuvollziehen.

---

## Könnte (optional, Verbesserung)

- **[Konsistenz]** Variablen-Namen weichen von Spec-Namen ab: `alreadyBooked` statt `isAlreadyBooked`, `showBookingModal` statt `isBookingModalOpen`, `loadBookingStatus` statt `checkOwnBooking`, `onBookingCompleted` statt `onBooked`. Keine funktionalen Auswirkungen, aber `isAlreadyBooked`/`isBookingModalOpen` signalisieren den Boolean-Charakter deutlicher (konsistent mit `isTrainerOrAdmin`, `isCustomer` im gleichen File).

- **[Spec-Konformität]** Button-Beschriftung: Spec (tasks.md T03, Punkt 3) sagt „Jetzt buchen"; implementiert ist „Buchen" (Zeile 340). Minimale Copy-Abweichung.

- **[Konsistenz]** `:course-id="course?.id"` (Zeile 355) vs. Design-Spec `:course-id="course.id"` mit Guard `v-if="isCustomer && course"`: Im aktuellen Code ist `course` durch den äußeren `v-else-if="course"` bereits garantiert non-null, das optionale Chaining ist redundant. `courseId` (der route-abgeleitete Computed) wäre semantisch sauberer: er ist ohne API-Roundtrip sofort verfügbar und macht die Abhängigkeit von der Route explizit.

- **[Typsicherheit]** `const bookings: any[] = response.data.data ?? []` in `loadBookingStatus()` (Zeile 120): Die folgende `.some()`-Vergleichskette `b.trainingSession?.course?.id === courseId.value` arbeitet damit untypisiert. Eine lokale Mini-Interface würde zumindest den `id`-Zugriff sicher machen:
  ```ts
  interface BookingStub { status: string; trainingSession?: { course?: { id?: number } } }
  const bookings: BookingStub[] = response.data.data ?? []
  ```

---

## Lob

- **`console.warn` statt stilles `catch {}`:** Der Entwickler hat den Spec-Vorschlag (`// still ignorieren`) bewusst verbessert — `console.warn('loadBookingStatus fehlgeschlagen', err)` (Zeile 127) macht Fehler debuggbar, ohne den Nutzer zu stören. Genau richtig.
- **Handler-Extraktion konsequent:** `closeBookingModal()` und `onBookingCompleted()` als benannte Methoden im `<script setup>`; kein einziger Multi-Statement-Inline-Handler im Template.
- **`v-if="isCustomer"` auf dem `CustomerBookingModal`** (Zeile 352): Verhindert, dass die Komponente für Nicht-Kunden gemountet wird (keine unnötigen Watches/Watchers aktiv). Sauber.
- **`loadBookingStatus()` nach erfolgreicher Buchung** in `onBookingCompleted()` (Zeile 143): State wird korrekt aktualisiert — nach Buchung sieht der Kunde sofort das Badge statt des Buttons.
- **Kein Vitest-Test für diese View vorhanden** (kein `CourseDetailView.spec.*` gefunden). Das liegt außerhalb des Scope dieser Task, sollte aber als technische Schuld festgehalten werden — insbesondere die drei CTA-Zustände wären gut automatisch prüfbar.
