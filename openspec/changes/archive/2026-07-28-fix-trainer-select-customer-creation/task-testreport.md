# Test-Report: fix-trainer-select-customer-creation (T01, T02, T03)

**Status:** alle-gruen

Ausgeführt auf Branch `feature/fix-trainer-select-customer-creation`, innerhalb
der Docker-Umgebung (`docker compose exec php ...` / `docker compose exec node ...`),
wie in `CLAUDE.md` Abschnitt 7.1 vorgeschrieben. Produktivcode wurde **nicht**
verändert — nur die beiden bestehenden Vitest-Dateien wurden um je einen
Grenzfall-Test ergänzt (siehe Abschnitt "Hinzugefügte Tests").

---

## 1. Backend — Pest

### 1.1 Gezielt: `--group=trainers`

```
docker compose exec php ./vendor/bin/pest --group=trainers
```

```
   PASS  Tests\Feature\TrainerApiTest
  ✓ Admin → it listet alle trainer auf                                   0.21s
  ✓ Admin → it erstellt einen neuen trainer                              0.05s
  ✓ Admin → it zeigt einen einzelnen trainer an                          0.02s
  ✓ Admin → it aktualisiert einen trainer                                0.02s
  ✓ Admin → it löscht einen trainer                                      0.02s
  ✓ Trainer-Rolle → it erhält 403 beim auflisten von trainern            0.02s
  ✓ Trainer-Rolle → it erhält 403 beim erstellen eines trainers          0.02s
  ✓ Trainer-Rolle → it erhält 403 beim anzeigen eines trainers           0.02s
  ✓ Trainer-Rolle → it erhält 403 beim aktualisieren eines trainers      0.02s
  ✓ Trainer-Rolle → it erhält 403 beim löschen eines trainers            0.02s
  ✓ Customer-Rolle → it erhält 403 beim auflisten von trainern           0.02s
  ✓ Customer-Rolle → it erhält 403 beim erstellen eines trainers         0.02s
  ✓ Customer-Rolle → it erhält 403 beim anzeigen eines trainers          0.02s
  ✓ Customer-Rolle → it erhält 403 beim aktualisieren eines trainers     0.02s
  ✓ Customer-Rolle → it erhält 403 beim löschen eines trainers           0.02s
  ✓ Unauthenticated → it erhält 401 beim auflisten von trainern          0.02s
  ✓ Unauthenticated → it erhält 401 beim erstellen eines trainers        0.02s
  ✓ Unauthenticated → it erhält 401 beim löschen eines trainers          0.02s
  ✓ Trainer Options Endpoint → it liefert für Admin reduzierte Trainer-… 0.02s
  ✓ Trainer Options Endpoint → it liefert für Trainer reduzierte Traine… 0.02s
  ✓ Trainer Options Endpoint → it erhält 403 für Customer-Rolle          0.02s
  ✓ Trainer Options Endpoint → it erhält 401 wenn unauthentifiziert      0.02s

  Tests:    22 passed (52 assertions)
```

Bestätigt insbesondere:
- Der bestehende 403-Test für Trainer auf `GET /api/v1/trainers` (`Trainer-Rolle → it erhält 403 beim auflisten von trainern`, `TrainerApiTest.php:57-61`) ist **weiterhin grün** — die volle Trainer-Route bleibt admin-only, unverändert von T01.
- Die vier neuen Tests für `GET /api/v1/trainers/options` (Admin 200, Trainer 200, Customer 403, unauthentifiziert 401) sind grün, inkl. der expliziten `not->toHaveKey()`-Checks für alle neun sensiblen Felder (`email`, `phone`, `mobilePhone`, `street`, `postalCode`, `city`, `country`, `qualifications`, `specializations`).

### 1.2 Volle Pest-Suite

```
docker compose exec php ./vendor/bin/pest
```

```
  Tests:    722 passed (2309 assertions)
  Duration: 26.95s
```

Keine Regression durch die neue Route/Resource.

---

## 2. Frontend — Vitest

### 2.1 Beide betroffenen Dateien zusammen (isoliert)

```
docker compose exec node sh -lc "npx vitest run src/components/CustomerFormModal.test.ts src/components/CourseFormModal.test.ts"
```

Vor der Ergänzung neuer Tests (Ausgangszustand, wie von T02/T03 übergeben):

```
✓ src/components/CustomerFormModal.test.ts (8 tests) 64ms
✓ src/components/CourseFormModal.test.ts (5 tests) 63ms

 Test Files  2 passed (2)
      Tests  13 passed (13)
```

Nach Ergänzung der beiden neuen Grenzfall-Tests (siehe Abschnitt 4):

```
✓ src/components/CustomerFormModal.test.ts (9 tests) 62ms
✓ src/components/CourseFormModal.test.ts (6 tests) 76ms

 Test Files  2 passed (2)
      Tests  15 passed (15)
```

### 2.2 Volle Vitest-Suite, mehrfach wiederholt (Stabilitäts-Check)

Wie im Auftrag gefordert, wurde die volle Suite **mehrfach isoliert**
ausgeführt, um die im T03-Notes vermerkte transiente Interferenz (Datei von
`CustomerFormModal.test.ts` wurde während eines parallelen Laufs der beiden
Entwickler-Agenten mitten im Testlauf verändert) auszuschließen, jetzt wo
beide Änderungen zusammen im Working Tree liegen:

```
docker compose exec node sh -lc "npx vitest run"     # 4x wiederholt
docker compose exec node sh -lc "npx vitest run --no-file-parallelism"  # zusätzlich sequenziell
```

Vor Ergänzung der neuen Tests: 4 aufeinanderfolgende Läufe, alle identisch:

```
 Test Files  20 passed (20)
      Tests  207 passed (207)
```

Nach Ergänzung der beiden neuen Grenzfall-Tests: 3 weitere Läufe (parallel)
plus 1 sequenzieller Lauf (`--no-file-parallelism`), alle identisch:

```
 Test Files  20 passed (20)
      Tests  209 passed (209)
```

**Ergebnis:** Keine Flakiness feststellbar. Beide Komponenten-Testdateien
interferieren nicht miteinander, weder im parallelen Standardmodus noch bei
erzwungener Sequenzialisierung. Der von T03 beschriebene Effekt war, wie
dort bereits vermutet, ein reiner Race-Condition-Artefakt des gleichzeitigen
Datei-Schreibens durch zwei Agenten während der Entwicklung — kein
struktureller Test- oder Komponentenfehler.

### 2.3 Build

```
docker compose exec node sh -lc "npm run build"
```

```
> vue-tsc -b && vite build
✓ 643 modules transformed.
✓ built in 2.29s
```

Kein TypeScript-Fehler, keine Build-Warnings. `vue-tsc -b` (strikte
Typprüfung) läuft sauber durch, insbesondere für die neuen
`TrainerOption`-Interfaces in beiden Komponenten.

---

## 3. Lückenanalyse (Auftrag Punkt 3)

| Geprüfter Fall | Ergebnis |
|---|---|
| Vorauswahl-Test für Trainer-Rolle in `CustomerFormModal.test.ts` (`trainer_id` korrekt gesetzt + passende `<option>` gefunden) | **Bereits abgedeckt.** `describe('Vorauswahl für die Rolle trainer', ...)`, drei Tests: `trainer_id` wird auf eigene ID gesetzt (`select.element.value === '7'`), Select bleibt bedienbar (kein `disabled`, Wechsel auf anderen Trainer funktioniert), Gegenprobe für `admin` (keine Vorauswahl). Kein Ergänzungsbedarf. |
| Fehlerfall-Anzeige (Toast/Error-State) statt nur Mock-Verifikation | **Kein Gap, sondern konsistent mit Projekt-Konvention.** In beiden neuen Testdateien wird `handleApiError` per `vi.mock('@/utils/errorHandler', ...)` gemockt und nur der Aufruf mit den richtigen Argumenten verifiziert (`expect(handleApiError).toHaveBeenCalledWith(...)`). Das entspricht exakt dem bestehenden Muster in `DogFormModal.test.ts:6,20-21,128` und `CustomerBookingModal.test.ts:6,15-16`. Ein Test, der den tatsächlichen Toast-DOM (Pinia `useToastStore`) durchrendert, existiert in **keiner** Komponenten-Testdatei des Projekts — das wäre ein Bruch mit der etablierten Teststrategie (Unit-Test der Komponente mit gemocktem Error-Handler, nicht Integration bis zum Toast-Rendering) und daher **nicht** ergänzt, um TESTING.md-Konsistenz und Scope zu wahren. |
| Randfall "keine Trainer im System" (leere Liste `data: []`, aber kein Fehler) | **Echter Gap — ergänzt.** Weder `CustomerFormModal.test.ts` noch `CourseFormModal.test.ts` hatten einen expliziten Test für ein erfolgreiches (200er) Laden mit leerem Array. Ohne diesen Test wäre unklar, ob eine leere Trainerliste versehentlich als Fehler behandelt wird. Zwei neue Tests ergänzt (siehe Abschnitt 4), beide grün. |

---

## 4. Hinzugefügte Tests

- `frontend/src/components/CustomerFormModal.test.ts`:
  1 neuer Case in `describe('Trainerliste laden', ...)`:
  `'zeigt nur den Platzhalter an und ruft handleApiError nicht auf, wenn keine Trainer im System vorhanden sind'`
  — mockt `apiClient.get` mit `{ data: { data: [] } }`, prüft, dass nur die
  Platzhalter-Option (`"Kein Trainer zugewiesen"`) im Select vorhanden ist
  und `handleApiError` **nicht** aufgerufen wird (Abgrenzung zum
  Fehlerpfad).

- `frontend/src/components/CourseFormModal.test.ts`:
  1 neuer Case in `describe('Laden der Trainerliste', ...)`:
  `'zeigt nur den Platzhalter an und ruft handleApiError nicht auf, wenn keine Trainer im System vorhanden sind'`
  — analog, prüft Platzhalter-Text `"Trainer auswählen..."` und dass
  `handleApiError` nicht aufgerufen wird.

Beide neuen Tests folgen dem bestehenden Datei-Stil (Arrange/Act/Assert,
`vi.mocked(apiClient.get).mockResolvedValue(...)`, deutschsprachige
Beschreibung im Pest-`it`-Stil analog zu den Vitest-Konventionen der
Nachbardateien). Kein Produktivcode wurde angefasst — `CustomerFormModal.vue`
und `CourseFormModal.vue` setzen bei leerer Antwort bereits korrekt nur
`trainers.value = []`, ohne Sonderbehandlung, daher bestand kein
Implementierungsbedarf, nur ein Test-Coverage-Gap.

Kein Backend-Test wurde ergänzt — die vier von T01 geschriebenen Tests decken
alle vier Akzeptanzkriterien (200/200/403/401) plus den expliziten
Feld-Ausschluss-Check bereits vollständig ab; ein Leerlisten-Fall ist beim
Backend-Endpoint kein Akzeptanzkriterium und würde keinen zusätzlichen
Codepfad prüfen (`options()` verwendet dieselbe `where('role', 'trainer')`-Query
wie `index()`, ein leeres Ergebnis wird identisch wie bei `index()` per
Collection-Resource behandelt).

---

## Akzeptanzkriterien-Abdeckung

### T01 (Backend-Endpoint)
- [x] Admin erhält 200 mit reduzierten Feldern — `TrainerApiTest.php::liefert für Admin reduzierte Trainer-Daten`
- [x] Trainer erhält 200 mit denselben reduzierten Feldern — `TrainerApiTest.php::liefert für Trainer reduzierte Trainer-Daten`
- [x] Customer erhält 403 — `TrainerApiTest.php::erhält 403 für Customer-Rolle`
- [x] Unauthentifiziert erhält 401 — `TrainerApiTest.php::erhält 401 wenn unauthentifiziert`
- [x] Payload enthält nachweislich nicht die neun sensiblen Felder — `not->toHaveKey()`-Checks in beiden 200er-Tests
- [x] Bestehende Tests unverändert und weiterhin grün — `TrainerApiTest.php::Trainer-Rolle → it erhält 403 beim auflisten von trainern` (volle Suite: 722 passed)

### T02 (CustomerFormModal)
- [x] `loadTrainers()` ruft `/api/v1/trainers/options` auf — `CustomerFormModal.test.ts::ruft GET /api/v1/trainers/options auf`
- [x] Select wird für Rolle `trainer` befüllt — `CustomerFormModal.test.ts::befüllt die Trainer-Select-Box auch für die Rolle trainer`
- [x] Fehlschlag ruft `handleApiError` (Toast) auf — `CustomerFormModal.test.ts::ruft handleApiError statt nur console.error auf`
- [x] Vorauswahl für Trainer-Rolle, Select bleibt bedienbar — `CustomerFormModal.test.ts::describe('Vorauswahl für die Rolle trainer')`, 3 Tests
- [x] Leere Trainerliste kein Fehler — **neu ergänzt**, `CustomerFormModal.test.ts::zeigt nur den Platzhalter an und ruft handleApiError nicht auf`

### T03 (CourseFormModal)
- [x] `loadTrainers()` ruft `/api/v1/trainers/options` auf — `CourseFormModal.test.ts::ruft GET /api/v1/trainers/options auf`
- [x] Select wird befüllt — `CourseFormModal.test.ts::befüllt die Trainer-Select-Box bei Erfolg`
- [x] Fehlschlag ruft `handleApiError` auf — `CourseFormModal.test.ts::ruft handleApiError mit einer verständlichen Meldung auf`
- [x] Anzeige-Fallback nutzt `firstName`/`lastName` statt `email` — `CourseFormModal.test.ts::zeigt den Namen aus firstName/lastName an, wenn fullName fehlt`
- [x] Leere Trainerliste kein Fehler — **neu ergänzt**, `CourseFormModal.test.ts::zeigt nur den Platzhalter an und ruft handleApiError nicht auf`

---

## Ausführungs-Ergebnis (Zusammenfassung)

```
Backend (Pest, voll):        722 passed (2309 assertions)
Backend (Pest, --group=trainers): 22 passed (52 assertions)
Frontend (Vitest, voll, nach Ergänzung): 209 passed (20 Testdateien) — 4x reproduziert (3x parallel, 1x --no-file-parallelism)
Frontend (Vitest, CustomerFormModal + CourseFormModal zusammen): 15 passed (2 Testdateien)
Frontend Build (vue-tsc -b && vite build): erfolgreich, keine Warnings
```

## Fehler

Keine. Alle Läufe grün, keine Flakiness über mehrere Wiederholungen hinweg
feststellbar.

## Anmerkung zu TESTING.md-Konformität (nicht blockierend, nur beobachtet)

`backend/tests/Feature/TrainerApiTest.php` verwendet `uses()->group('feature', 'trainers')`
bei einem Pfad unter `tests/Feature/` (nicht `tests/Feature/Api/`) für
HTTP-Endpunkt-Tests, was laut TESTING.md Abschnitt 7.1 eigentlich der Group
`api` (+ Pfad `tests/Feature/Api/`) entspräche. Das ist **vorbestehende
Struktur** der Datei (nicht durch T01 eingeführt — T01 hat nur einen neuen
`describe`-Block am Dateiende ergänzt, ohne die Gruppen-Deklaration
anzufassen, wie in `tasks.md` explizit gefordert: "bestehende Tests
unverändert lassen"). Kein Handlungsbedarf innerhalb dieses Changes; bei
einer künftigen größeren Überarbeitung dieser Datei könnte das nach der
Boy-Scout-Regel aus TESTING.md korrigiert werden.
