# Review: T03 — Kurse & Buchungen

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)
(keine)

## Sollte (vor Merge erledigen, kann diskutiert werden)
- **[Konsistenz/Kontrast]** `frontend/src/views/bookings/BookingsView.vue:26`: `<table class="min-w-full divide-y divide-gray-200">` hat kein `dark:divide-gray-700`-Pendant, obwohl die drei strukturell identischen Nachbar-Views desselben Changes (`frontend/src/views/customers/CustomersView.vue:22`, `frontend/src/views/trainers/TrainersView.vue:143`, `frontend/src/views/invoices/InvoicesView.vue:26`) exakt dieses Pendant erhalten haben. `divide-y` auf dem `<table>`-Element erzeugt hier eine sichtbare Trennlinie zwischen `<thead>` und `<tbody>`; ohne `dark:`-Pendant bleibt diese Linie im Dark-Mode hell auf dunklem Hintergrund — ein kleiner, aber realer optischer Bruch, keine Lesbarkeitsgefährdung (keine Textfarbe betroffen). Vorschlag: `dark:divide-gray-700` ergänzen, analog zu den drei Schwester-Views.

## Könnte (optional, Verbesserung)
(keine)

## Lob
- Sehr sorgfältige Selbst-Verifikation in `task-T03.notes.md` (`git diff --numstat` mit identischen Insert-/Delete-Zahlen je Datei, Grep-Nachweis "jede geänderte Zeile enthält `class=`"), unabhängig gegen den tatsächlichen Diff nachvollzogen und bestätigt.
- Gute Nachverfolgbarkeit der Farbherkunft (Abschnitt "Referenzmuster-Herkunft") mit konkreten Datei:Zeile-Belegen für jede verwendete, nicht in D1 explizit genannte Farbkombination (Fehlerbox, Info-Box, Status-Badges) — entspricht dem Anti-Halluzinations-Prinzip.
- `CourseFormModal.vue` inkl. eingebettetem `CourseRecurrenceForm.vue` und `BookingsView.vue` (inkl. Status-Badge-Funktion `bookingStatusClass()`) wirken vollständig und konsistent zu D1.
- `npm run test` (191/191) und `npm run build` laufen grün und warnungsfrei (unabhängig nachvollzogen).
