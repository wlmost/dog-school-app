# Review: T01 — CoursesView.vue

**Gesamtempfehlung:** APPROVED WITH NOTES

---

## Muss (blockiert Abnahme)

_Keine._

---

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Lesbarkeit/Konsistenz]** `CoursesView.vue` Template, `@close`-Handler des `CustomerBookingModal`:
  ```html
  @close="showBookingModal = false; selectedCourseForBooking = null"
  ```
  Der Rest der Datei verwendet für alle Template-Events ausschließlich extrahierte Methoden
  (`@close="closeFormModal"`, `@saved="handleCourseSaved"`, `@booked="onBookingCompleted"`).
  Dieser Handler weicht davon ab und enthält zwei Statements inline — schwerer zu testen,
  schwerer auf den ersten Blick zu lesen.
  Vorschlag: `closeBookingModal()` extrahieren analog zu `closeFormModal()`:
  ```ts
  function closeBookingModal(): void {
    showBookingModal.value = false
    selectedCourseForBooking.value = null
  }
  ```
  Dann im Template: `@close="closeBookingModal"`.

- **[Debugbarkeit]** `CoursesView.vue:162` — `loadOwnBookings()` schluckt alle Fehler
  vollständig lautlos:
  ```ts
  } catch {
    // Fehler ignorieren — kein blocking
  }
  ```
  Das ist bewusst non-blocking (per Design), aber ohne Logging ist ein Ladeausfall in
  Entwicklung/Staging komplett unsichtbar: Kunden sehen dann für alle Kurse den
  „Buchen"-Button, obwohl Buchungen existieren. Ein `console.warn` kostet nichts und
  erleichtert Diagnose erheblich:
  ```ts
  } catch (err) {
    console.warn('[CoursesView] loadOwnBookings fehlgeschlagen', err)
  }
  ```

---

## Könnte (optional)

- **[Barrierefreiheit]** `CoursesView.vue` Template, „Bereits gebucht"-Badge:
  ```html
  ✓ Bereits gebucht
  ```
  Das `✓`-Zeichen wird von Screenreadern als „Häkchen" oder „Checkmark" vorgelesen,
  bevor der Text kommt. Verbesserung: `<span aria-hidden="true">✓</span> Bereits gebucht`.

- **[Typsicherheit]** `selectedCourseForBooking` ist als `ref<any>(null)` deklariert.
  Da `CustomerBookingModal` explizit `courseId: number | undefined` und
  `courseName: string | undefined` erwartet, könnte ein minimales lokales Interface die
  Prop-Weitergabe absichern — optional, da `vue-tsc` die Props im Template trotzdem prüft
  und der Build grün ist.

- **[UX]** Wenn ein Kurs ausgebucht ist (`currentParticipants >= maxParticipants`), zeigt
  der Kunde weiterhin einen aktiven „Buchen"-Button. Das liegt außerhalb des T01-Scope,
  ist aber ein naheliegender Follow-up.

---

## Lob

- **Besserer Type Guard als im Design-Dokument:** Die Spec zeigt `.filter(Boolean)`, die
  Implementierung verwendet `.filter((id): id is number => typeof id === 'number')` —
  das ist streng korrekt und verhindert, dass `0` (ungültige ID) in das Set gelangt.

- **Echte Parallelität in `onMounted`:** Beide Calls werden ohne `await` abgefeuert, laufen
  also tatsächlich parallel — kein unbeabsichtigtes sequenzielles Warten.

- **`onBookingCompleted` setzt `selectedCourseForBooking = null`:** Nicht im Spec
  explizit verlangt, aber verhindert veraltete Modal-Daten bei erneutem Öffnen. Gute
  defensive Entscheidung.

- **`isTrainerOrAdmin` und `isCustomer` korrekt aus `authStore` abgeleitet:** Konsistent
  mit `CourseDetailView.vue` und der Store-Definition (`isTrainer` deckt `admin` ab, belegt
  durch `auth.ts:44`).

- **Spec-Konformität vollständig:** Alle 8 Spec-Anforderungen aus T01 sind umgesetzt,
  alle Akzeptanzkriterien erfüllt.
