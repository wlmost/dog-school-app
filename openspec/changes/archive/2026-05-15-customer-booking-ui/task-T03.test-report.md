# Test-Report: T03

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

- `frontend/src/views/CourseDetailView.test.ts` *(neu)* — 14 neue Test-Cases

## Akzeptanzkriterien-Abdeckung

- [x] Nicht-eingeloggte Besucher sehen nach wie vor „Kontakt aufnehmen / Anmelden" — getestet in `CTA-Block::zeigt dem Gast Kontakt- und Login-Links aber keinen Buchen-Button`
- [x] Eingeloggte Kunden sehen „Jetzt buchen"-Button — getestet in `CTA-Block::zeigt dem nicht-gebuchten Kunden den Buchen-Button aber kein Bereits-gebucht-Badge`
- [x] Eingeloggte Trainer/Admins sehen weder „Kontakt"- noch „Buchen"-Block — getestet in `CTA-Block::zeigt Trainern weder Buchen-Button noch Kontakt-Links noch Bereits-gebucht-Badge`
- [x] Für bereits gebuchten Kurs zeigt der Kunde „Bereits gebucht" — getestet in `CTA-Block::zeigt dem gebuchten Kunden das Bereits-gebucht-Badge ohne Buchen-Button` und `loadBookingStatus::setzt alreadyBooked auf true wenn ein bestätigtes Booking für diesen Kurs vorliegt`
- [x] Das Modal öffnet sich und schließt korrekt — getestet in `Modal-Interaktion::öffnet das CustomerBookingModal beim Klick auf den Buchen-Button` und `Modal-Interaktion::schließt das Modal und ruft loadBookingStatus nach dem booked-Event erneut auf`
- [x] Nach erfolgreicher Buchung aktualisiert sich der Guard-Status — getestet in `Modal-Interaktion::schließt das Modal und ruft loadBookingStatus nach dem booked-Event erneut auf` (dritter mockResolvedValueOnce mit `[mockBooking]` → `alreadyBooked = true`)
- [x] `npm run build` läuft ohne TypeScript-Fehler — nicht via Test prüfbar, aber der Build wurde separat verifiziert

## Tests im Detail

| # | Describe | Testbeschreibung | Akzeptanzkriterium |
|---|----------|------------------|--------------------|
| 1 | Grundrendering | zeigt den Lade-Spinner während loadCourse läuft | Loading-State |
| 2 | Grundrendering | zeigt die 404-Meldung wenn der Kurs nicht gefunden wird | Fehlerbehandlung |
| 3 | Grundrendering | zeigt den Kursnamen nach erfolgreichem Laden | Happy Path |
| 4 | API-Endpoint-Auswahl | verwendet den Trainer-API-Endpunkt wenn isTrainerOrAdmin true ist | Rollenbasierter Endpoint |
| 5 | API-Endpoint-Auswahl | verwendet den öffentlichen API-Endpunkt wenn isTrainerOrAdmin false ist | Rollenbasierter Endpoint |
| 6 | CTA-Block | zeigt dem Gast Kontakt- und Login-Links aber keinen Buchen-Button | Gast-CTA |
| 7 | CTA-Block | zeigt dem nicht-gebuchten Kunden den Buchen-Button aber kein Bereits-gebucht-Badge | Kunden-CTA |
| 8 | CTA-Block | zeigt dem gebuchten Kunden das Bereits-gebucht-Badge ohne Buchen-Button | Bereits-gebucht |
| 9 | CTA-Block | zeigt Trainern weder Buchen-Button noch Kontakt-Links noch Bereits-gebucht-Badge | Trainer hat keinen CTA |
| 10 | loadBookingStatus | wird nicht aufgerufen wenn der Nutzer kein Kunde ist | Guard-Logik |
| 11 | loadBookingStatus | setzt alreadyBooked auf true wenn ein bestätigtes Booking für diesen Kurs vorliegt | alreadyBooked-Logik |
| 12 | loadBookingStatus | ruft console.warn auf und wirft keine Exception wenn der API-Call fehlschlägt | Fehlerfall |
| 13 | Modal-Interaktion | öffnet das CustomerBookingModal beim Klick auf den Buchen-Button | Modal-Open |
| 14 | Modal-Interaktion | schließt das Modal und ruft loadBookingStatus nach dem booked-Event erneut auf | Modal-Close + Refresh |

## Ausführungs-Ergebnis

```
RUN  v4.1.6 /var/www/html/frontend

 ✓ src/composables/usePricingItems.test.ts (9 tests) 4ms
 ✓ src/components/CourseRecurrenceForm.test.ts (16 tests) 103ms
 ✓ src/components/CustomerBookingModal.test.ts (14 tests) 77ms
 ✓ src/components/PricingItemForm.test.ts (9 tests) 45ms
 ✓ src/views/CourseDetailView.test.ts (14 tests) 54ms

 Test Files  5 passed (5)
      Tests  62 passed (62)
   Start at  19:18:31
   Duration  1.35s (transform 1.21s, setup 0ms, import 2.68s, tests 283ms, environment 2.01s)
```

## Fehler

Keine.

## Technische Anmerkungen

- **axios-Mock:** `axios.isAxiosError` wird gemockt, da `CourseDetailView` axios direkt importiert für die 404-Erkennung. Der Mock prüft `err?.isAxiosError === true` auf dem Error-Objekt.
- **CustomerBookingModal-Stub:** Der Stub enthält `name: 'CustomerBookingModal'`, damit `findComponent({ name: 'CustomerBookingModal' })` funktioniert und Props geprüft sowie `vm.$emit('booked')` ausgelöst werden kann.
- **RouterLink-Stub:** Als `<a><slot /></a>` gestubbt über `global.stubs`, da `vue-router`-Mock nur `useRoute` bereitstellen muss.
- **loadBookingStatus-Reihenfolge:** Da `onMounted` beide Calls sequenziell initiiert (`loadCourse()` dann `loadBookingStatus()`), liefert `mockResolvedValueOnce` die Responses in der korrekten Reihenfolge.
