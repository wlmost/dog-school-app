# Test-Report: add-announcement-banner (T01–T11, gesamter Change)

**Status:** alle-gruen

**Geprüft auf Branch:** `feature/add-announcement-banner`
**Geprüft gegen:** `openspec/changes/add-announcement-banner/proposal.md`,
`design.md`, `tasks.md`, `TESTING.md`

---

## Vorgehen

1. Volle Backend- und Frontend-Testsuite in der Docker-Umgebung als
   Baseline ausgeführt (nicht nur den Entwickler-Berichten vertraut).
2. Alle vorhandenen neuen Testdateien (`AnnouncementApiTest.php`,
   `announcements.test.ts`, `AnnouncementBanner.test.ts`,
   `AnnouncementsView.test.ts`) gegen die Akzeptanzkriterien aus
   `tasks.md` und gegen `design.md` abgeglichen.
3. Produktivcode gelesen (`Announcement.php`, `AnnouncementFactory.php`,
   `AnnouncementController.php`, `AnnouncementResource.php`,
   `StoreAnnouncementRequest.php`, `UpdateAnnouncementRequest.php`,
   `AnnouncementBanner.vue`, `useAnnouncements.ts`,
   `AnnouncementsView.vue`, `announcements.ts`) um echte Lücken von
   bereits abgedeckten Fällen zu unterscheiden.
4. Lücken identifiziert und mit neuen Tests geschlossen (keine
   Produktivcode-Änderung).
5. Beide Suiten erneut vollständig ausgeführt, inkl. `npm run build`.

---

## Baseline (vor Ergänzungen)

```
Backend:  Tests: 704 passed (2224 assertions)   — 25.92s
Frontend: Test Files: 15 passed (15) / Tests: 174 passed (174) — 2.23s
Build:    vue-tsc -b && vite build → erfolgreich, keine TS-Fehler
```

Deckt sich mit dem Bericht der Entwickler-Agenten — verifiziert, nicht nur
übernommen.

---

## Hinzugefügte / geänderte Tests

### Backend

- `backend/tests/Feature/Domain/AnnouncementModelTest.php` *(neu, 8 Tests,
  Gruppen `domain`, `announcement`)* — Model-Ebene, die
  `AnnouncementApiTest.php` (nur HTTP-Ebene) nicht abdeckt:
  - `expires_at` wird bei der Erstellung korrekt aus `created_at +
    display_days` berechnet
  - `isActive()` liefert `true`/`false` korrekt für aktive/abgelaufene
    Datensätze
  - **Kernlücke (explizit vom Auftrag verlangt):** Erhöhen **und**
    Verringern von `display_days` auf einem bestehenden, bereits
    persistierten Datensatz berechnet `expires_at` nachweislich ab dem
    **ursprünglichen `created_at`**, nicht ab `now()`
  - Ändern eines anderen Feldes (`title`) ohne `display_days`-Änderung
    lässt `expires_at` unverändert (dokumentiertes Verhalten aus
    `design.md` Abschnitt 11, Risikotabelle)
  - `scopeActive()` liefert nur zukünftige `expires_at`-Datensätze, auch
    im Leerfall (alle abgelaufen)

- `backend/tests/Feature/Api/AnnouncementApiTest.php` *(erweitert, 6 neue
  Tests)*:
  - `displayDays = 1` und `displayDays = 365` als Randwerte werden
    akzeptiert (bisher nur der Fehlerfall `400` getestet)
  - Fehlendes `title` → 422 mit `title`-Validierungsfehler
  - Leerer `body` (`''`) → 422 mit `body`-Validierungsfehler
  - Fehlendes `body`-Feld → 422 mit `body`-Validierungsfehler
  - Öffentlicher Endpunkt liefert exakt die im Vertrag (`design.md`
    Abschnitt 5.1) definierten Felder, keine zusätzlichen/verborgenen
    Schlüssel (Kontrolle der bewussten Design-Entscheidung "keine
    getrennte Public/Admin-Resource")

### Frontend

- `frontend/src/composables/useAnnouncements.test.ts` *(neu, 11 Tests)* —
  **fehlte komplett**, obwohl das Projekt für das strukturell identische
  `usePricingItems`-Composable einen dedizierten Test hat
  (`usePricingItems.test.ts`). Deckt `loadPublic`/`loadAll`/
  `createAnnouncement`/`updateAnnouncement`/`deleteAnnouncement` jeweils
  im Erfolgs- und Fehlerfall ab (API-Schicht gemockt, echte
  Composable-Logik getestet — nicht wie in den View-/Komponenten-Tests
  nur die Composable-Oberfläche gemockt).
- `frontend/src/components/AnnouncementBanner.integration.test.ts` *(neu,
  2 Tests)* — `AnnouncementBanner.test.ts` mockt das komplette
  `useAnnouncements`-Composable, wodurch der reale Fehlerpfad
  (`loadPublic()` wirft, `try/catch` im Composable) nie durchlaufen
  wird. Diese neue Datei mockt stattdessen nur die API-Schicht
  (`announcementsApi`) und bindet das echte Composable ein:
  - `loadPublic()` wirft einen Fehler → kein Crash beim Mounten, kein
    sichtbarer `<section>`-Bereich (explizit angefragtes Szenario)
  - `loadPublic()` liefert Daten → Karte wird korrekt gerendert
    (Kontrollfall, dass der reale Wiring-Pfad Komponente↔Composable↔API
    funktioniert, nicht nur mit gemocktem Composable)
- `frontend/src/views/AnnouncementsView.test.ts` *(erweitert, 2 neue
  Tests)*:
  - Fehlgeschlagenes `updateAnnouncement` hält das Formular offen und
    zeigt die Fehlermeldung (bisher nur für `create` getestet)
  - Fehlgeschlagenes `deleteAnnouncement` zeigt eine Fehlermeldung an

---

## Akzeptanzkriterien-Abdeckung (Ergänzung/Vertiefung ggü. bestehenden Tests)

- [x] T02 AK3 "Wird `display_days` auf einem bestehenden, nicht
      abgelaufenen Datensatz erhöht und gespeichert, wird `expires_at`
      neu ab dem ursprünglichen `created_at` berechnet (nicht ab
      `now()`)" — bisher **nicht** auf Model-Ebene getestet (nur
      implizit durch das Fehlen eines gegenteiligen Tests). Jetzt
      getestet in `AnnouncementModelTest.php::it berechnet expires_at
      beim erhöhen von display_days …` (und zusätzlich für den
      Verringerungsfall)
- [x] T02 AK1/AK2/AK4 — abgedeckt in `AnnouncementModelTest.php`
- [x] T04 AK4 "`displayDays` außerhalb von 1–365 wird mit HTTP 422
      abgelehnt" — Randwerte 1 und 365 jetzt zusätzlich als
      Erfolgsfall abgedeckt (`AnnouncementApiTest.php`)
- [x] Pflichtfeld-Validierung `title`/`body` — jetzt explizit getestet
      (vorher nicht abgedeckt, obwohl `required` in den Regeln steht)
- [x] "Öffentliche Route leakt keine Admin-only-Felder" — verifiziert:
      `AnnouncementResource` ist laut `design.md` Abschnitt 5.1
      **bewusst identisch** für Public/Admin (kein `created_by`, keine
      internen Notizen im Datenmodell). Test bestätigt, dass die Public-
      Response exakt die neun dokumentierten Felder enthält, keine
      zusätzlichen. Kein Bug, sondern eine dokumentierte,
      nachvollziehbare Design-Entscheidung (YAGNI, siehe `design.md`
      Zeile 414–419).
- [x] `AnnouncementBanner.vue` bei Fehler in `loadPublic()`: kein Crash,
      kein sichtbarer Bereich — jetzt mit echtem Composable getestet in
      `AnnouncementBanner.integration.test.ts`
- [x] `AnnouncementsView.vue` zeigt sinnvolle Fehlermeldungen bei
      fehlgeschlagenem Create (bereits vorhanden), Update (neu) und
      Delete (neu)

---

## Ausführungs-Ergebnis (nach Ergänzung)

### Backend — `docker compose exec php vendor/bin/pest`

```
Tests:    718 passed (2259 assertions)
Duration: 26.65s
```

Davon 25 im Bereich Announcement (`--group=announcement`):

```
PASS  Tests\Feature\Api\AnnouncementApiTest      (17 Tests)
PASS  Tests\Feature\Domain\AnnouncementModelTest  (8 Tests)
Tests: 25 passed (64 assertions)
```

### Frontend — `npm run test -- --run`

```
Test Files  17 passed (17)
Tests       189 passed (189)
Duration    2.19s
```

Neu/relevant:

```
✓ src/composables/useAnnouncements.test.ts (11 tests)
✓ src/components/AnnouncementBanner.integration.test.ts (2 tests)
✓ src/components/AnnouncementBanner.test.ts (8 tests)
✓ src/views/AnnouncementsView.test.ts (17 tests)
✓ src/api/announcements.test.ts (17 tests)
```

### Frontend — `npm run build`

```
vue-tsc -b && vite build
✓ 643 modules transformed.
✓ built in 2.22s
```
Keine TypeScript-/Build-Fehler.

---

## Fehler (falls vorhanden)

Keine — alle Backend- (718) und Frontend-Tests (189) sind grün,
`npm run build` läuft ohne Fehler.

**Hinweis, kein Bug:** Beim Aufdecken der `expires_at`-Model-Tests trat
zunächst ein Testfehler auf (`Failed asserting that false is true.` beim
Vergleich mit vollem Mikrosekunden-Carbon-`eq()`). Ursache war **kein**
Produktivcode-Bug, sondern eine Eigenheit des Test-Setups: die
`timestamp`-Spalte in der Migration (`2026_07_17_100000_create_
announcements_table.php:20`) hat keine Sub-Sekunden-Präzision, und
Eloquents Datetime-Cast wandelt zugewiesene `Carbon`-Werte beim Setzen
sofort in einen String ohne Mikrosekunden um (`fromDateTime()`), während
ein manuell in der Testdatei erzeugter Vergleichswert (`now()->subDays(5)`)
volle Mikrosekundenpräzision behält. Behoben durch Vergleich auf
Sekundengenauigkeit (`->format('Y-m-d H:i:s')` statt `->eq()`) — betrifft
nur die Testassertion, keine Produktivlogik. Dokumentiert als Kommentar im
Test.

---

## Nicht behobene Beobachtung für Reviewer/User (kein Test-Fix, nur Hinweis)

`AnnouncementsView.vue`: Schlägt `deleteAnnouncement()` fehl, wird
`error.value` in der Composable gesetzt. Da das Template
(`v-else-if="error"`) den Fehlerblock **anstelle der gesamten Liste**
rendert (nicht nur als zusätzlichen Hinweis), verschwindet nach einem
fehlgeschlagenen Löschversuch kurzzeitig die komplette Ankündigungsliste
zugunsten der Fehlermeldung — obwohl die übrigen (nicht gelöschten)
Einträge weiterhin in `announcements` vorhanden sind. Das ist funktional
kein Fehler (Daten gehen nicht verloren, nächstes `loadAll()`/Neuladen
stellt die Liste wieder her) und in `TESTING.md`/`tasks.md` nicht als
Akzeptanzkriterium ausgeschlossen — daher **kein** Testfehler, aber eine
UX-Beobachtung, die dem Reviewer/User zur Kenntnis gebracht wird. Test
`AnnouncementsView.test.ts::zeigt eine Fehlermeldung an wenn das Löschen
fehlschlägt` verifiziert nur, dass die Fehlermeldung angezeigt wird (wie
angefordert), nicht das Beibehalten der Liste.

---

## Zusammenfassung

- 14 neue Backend-Tests (8 Model-Tests in neuer Datei
  `AnnouncementModelTest.php` + 6 API-Tests in bestehender
  `AnnouncementApiTest.php`; Baseline 704 → 718 grüne Tests)
- 15 neue Frontend-Tests (11 Composable + 2 Integration + 2
  View-Fehlerfälle; 189−174=15)
- Keine Produktivcode-Änderung vorgenommen.
- Keine echten Bugs in der Implementierung gefunden — die einzige
  Auffälligkeit (Listen-Ausblendung bei Lösch-Fehler in
  `AnnouncementsView.vue`) ist eine UX-Beobachtung, kein funktionaler
  Defekt, und wird dem Reviewer/User zur Entscheidung vorgelegt statt
  eigenmächtig behoben.
