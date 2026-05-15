# Test-Report: T01

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

- `frontend/src/views/courses/CoursesView.test.ts`: 5 neue Tests (neue Datei)

## Akzeptanzkriterien-Abdeckung

- [x] Kriterium 1 — Trainer sieht "Neuer Kurs"-Button → `CoursesView > Trainer-Ansicht > zeigt den "Neuer Kurs"-Button wenn der Nutzer Trainer ist`
- [x] Kriterium 2 — Kunde sieht KEINEN "Neuer Kurs"-Button → `CoursesView > Kunden-Ansicht > versteckt den "Neuer Kurs"-Button wenn der Nutzer Kunde ist`
- [x] Kriterium 3 — Trainer sieht "Bearbeiten"/"Löschen"-Buttons → `CoursesView > Trainer-Ansicht > zeigt die "Bearbeiten"- und "Löschen"-Buttons für Kurse wenn der Nutzer Trainer ist`
- [x] Kriterium 4 — Kunde sieht "Buchen"-Button wenn nicht gebucht → `CoursesView > Kunden-Ansicht > zeigt den "Buchen"-Button wenn der Kurs noch nicht gebucht ist`
- [x] Kriterium 5 — Kunde sieht "Bereits gebucht"-Badge wenn bereits gebucht → `CoursesView > Kunden-Ansicht > zeigt das "Bereits gebucht"-Badge und versteckt den "Buchen"-Button wenn der Kurs bereits gebucht ist`

## Ausführungs-Ergebnis

```
 RUN  v4.1.6 /var/www/html/frontend

 ✓ src/composables/usePricingItems.test.ts (9 tests) 5ms
 ✓ src/components/CourseRecurrenceForm.test.ts (16 tests) 59ms
 ✓ src/components/CustomerBookingModal.test.ts (14 tests) 65ms
 ✓ src/components/PricingItemForm.test.ts (9 tests) 48ms
 ✓ src/views/CourseDetailView.test.ts (14 tests) 62ms
 ✓ src/views/courses/CoursesView.test.ts (5 tests) 41ms

 Test Files  6 passed (6)
      Tests  67 passed (67)
   Start at  19:27:11
   Duration  1.50s
```

## Fehler

Keine.
