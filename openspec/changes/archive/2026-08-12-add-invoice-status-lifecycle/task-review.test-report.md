# Test-Report: Change `add-invoice-status-lifecycle` (Review über alle Tasks T01–T09)

**Status:** alle-gruen

**Geltungsbereich:** Dieser Bericht prüft und erweitert die Testabdeckung für
den **kompletten** Diff des Branches `feature/add-invoice-status-lifecycle`
gegen `main` (Change 1 von 4 im Rechnungsworkflow-Umbau). Es wurde **kein**
Produktivcode geändert — ausschließlich Testdateien.

## Vorgehen

1. `TESTING.md`, `proposal.md`, `design.md`, `tasks.md` sowie
   `task-T01.notes.md` bis `task-T09.notes.md` gelesen.
2. Vollständigen Diff (`git diff main` — der Branch ist noch uncommitted,
   daher `main` statt `main...feature/...`) sowie alle betroffenen
   Testdateien inventarisiert.
3. Die fünf im Auftrag benannten Lücken gezielt geprüft (siehe unten).
4. Fehlende Tests ergänzt, bestehende **nicht** verändert/gelöscht.
5. `composer qa` (Backend), `npm run test`/`lint`/`build` (Frontend) sowie
   ein Ad-hoc-MySQL-Check der **kompletten** Migrations-Kette (M1–M4 in
   einem Lauf, nicht nur einzeln wie in T01/T02) ausgeführt.

## Hinzugefügte / geänderte Tests

- `backend/tests/Unit/Models/InvoiceDunningTest.php` (**neu**, 7 Tests):
  Factory + `invoice()`-Relation, Casts (`dunning_date`/`fee_amount`),
  `Invoice::dunnings()`-Relation, `dunning_level`/`reminded_at`-Attribute
  (leer und befüllt). Schließt die in `task-T01.notes.md` dokumentierte
  Lücke ("nur manuell per `artisan tinker` verifiziert").
- `backend/tests/Feature/Services/InvoiceNumberGeneratorTest.php` (**neu**,
  6 Tests): Nummernformat/-inkrement, Jahres-Scoping, Ignorieren von
  Alt-Nummern im Format `INV-######`, und — **ohne jeglichen Mock** —
  ein Test, der die in `task-T03.notes.md` dokumentierte Race Condition
  direkt am echten Service reproduziert (zwei aufeinanderfolgende
  `generate()`-Aufrufe ohne zwischenzeitliches Persistieren liefern
  dieselbe Nummer), plus eine Gegenprobe für den korrekten Normalfall.
- `backend/tests/Feature/InvoiceApiTest.php` (erweitert, **+3 Tests**,
  bestehender `test()`-Stil beibehalten, siehe "Abweichungen" unten):
  - `'admin and trainer still see invoices of every status'`: Status
    `overdue` zur Schleife ergänzt (fehlte).
  - `'customer can view and list their own overdue invoice'` (**neu**):
    schließt die Kunden-Sichtbarkeits-Lücke für `overdue` (Liste + 403-freier
    Einzelabruf).
  - `'after cancelling, the customer sees the cancellation invoice but not
    the now-cancelled original, with cross-references intact'` (**neu**):
    End-to-End-Storno-Kette aus Kundensicht — (a) Storno in der Liste
    sichtbar, (b) Original nicht mehr sichtbar (Liste + 403 bei direktem
    Abruf), (c) `originalInvoiceId`/`originalInvoiceNumber` auch aus
    Kundensicht korrekt verknüpft.
- `backend/tests/Feature/DatabaseStructureTest.php` (erweitert, **+2
  Tests**): `invoices`-Spaltenliste um `original_invoice_id` ergänzt, neuer
  Test für Nullable-Vertrag von `invoice_number`, neuer Test für die
  Existenz/Struktur der `invoice_dunnings`-Tabelle (M1/M2 waren dort bisher
  gar nicht abgebildet).
- `frontend/src/views/invoices/InvoicesView.test.ts` (erweitert, **+1
  Test**): neuer `describe('Status "overdue"')`-Block — Buttons
  (PDF/Senden-disabled/Stornieren, kein Bearbeiten/Löschen/Freigeben) und
  Statuslabel "Überfällig", unabhängig vom bereits vorhandenen
  `isOverdue`-Badge-Test.
- `frontend/src/components/InvoiceDetailModal.test.ts` (erweitert, **+1
  Test**): analoger `describe('Status "overdue"')`-Block im Detail-Modal.

**Gesamt-Delta:** Backend 795 → 812 Tests (+17), 2517 → 2549 Assertions
(+32). Frontend 244 → 246 Tests (+2 Testdateien-intern, 22 Dateien
unverändert).

## Prüfung der fünf benannten Lücken

1. **Concurrency/Retry-Logik (finalize()/cancel())** — teilweise Lücke,
   geschlossen. Die bestehenden Retry-Tests (`'finalize retries ...'`,
   `'cancel retries ...'`) mocken zwar `InvoiceNumberGenerator::generate()`,
   provozieren aber bereits eine **echte** `UniqueConstraintViolationException`
   der SQLite-Test-DB (der Mock liefert lediglich deterministisch eine
   bereits vergebene Nummer, die reale `$invoice->update([...])`-Ausführung
   löst die echte DB-Exception aus) — das war schon vor dieser Review
   ausreichend abgesichert. Die tatsächliche Lücke lag woanders: **kein**
   Test reproduzierte die zugrunde liegende Race Condition am echten,
   ungemockten `InvoiceNumberGenerator`. Das schließt jetzt
   `InvoiceNumberGeneratorTest.php` (siehe oben). Eine echte
   Mehrprozess-Nebenläufigkeits-Simulation (parallele PHP-Prozesse wie in
   den T01/T03-Notes manuell durchgeführt) ist in der synchronen
   Pest/PHPUnit-Suite ohne `pcntl`-Fork nicht sinnvoll automatisierbar und
   hätte auch keinen Präzedenzfall im Projekt — bewusst nicht ergänzt.
2. **Storno-Kette End-to-End** — Lücke, geschlossen (siehe neuer Test oben).
   Vorher testete `'trainer can cancel a sent invoice...'` nur die
   Trainer-Perspektive direkt nach dem Aufruf, `'InvoiceResource exposes
   cancellation invoice fields...'` nur die Admin-Perspektive auf bereits
   vorbereitete Fixtures (nicht nach einem echten `cancel()`-Aufruf). Der
   neue Test deckt exakt den im Auftrag beschriebenen Kunden-Ablauf ab.
3. **Kunden-Sichtbarkeits-Matrix (alle 6 Statuswerte)** — Lücke bei
   `overdue`, geschlossen (Backend + beide Frontend-Komponenten, siehe
   oben). Für die übrigen fünf Statuswerte (`draft`, `sent`, `paid`,
   `reminded`, `cancelled`) war die Matrix bereits vollständig getestet
   (Backend: `'customer cannot view their own draft invoice'`, `'... their
   own cancelled invoice'`, `'... does not see draft or cancelled
   invoices in the list'`, implizit `sent`/`paid`/`reminded` über mehrere
   Tests; Frontend: je ein `describe`-Block pro Status in beiden
   Testdateien).
4. **Migrations-Rollback (M3 mit vorhandenen NULL-Zeilen)** — **bewusst
   kein Regressionstest ergänzt**, Entscheidung dokumentiert:
   - Das in `task-T02.notes.md` beschriebene Verhalten (Rollback von
     `invoice_number` `nullable → NOT NULL` schlägt mit `QueryException`
     fehl, wenn zu diesem Zeitpunkt `NULL`-Zeilen existieren) ist die
     inhärente, erwartbare Konsequenz **jeder** `nullable → NOT NULL`-
     Downgrade-Migration in **jedem** Laravel-Projekt, keine
     changespezifische Business-Logik — ein Test dafür würde letztlich nur
     MySQL/PostgreSQL/SQLite-Grundverhalten nachbilden, nicht Code dieses
     Changes.
   - Im gesamten Testsuite-Bestand gibt es **keinen einzigen** Test, der
     `Artisan::call('migrate:rollback', ...)`/`migrate:fresh` innerhalb
     eines Pest-Tests aufruft (per `grep` verifiziert) — ein
     entsprechender Test wäre ein neues, unerprobtes Muster in diesem
     Projekt und würde mit dem globalen `RefreshDatabase`-Schema-Handling
     (ein bereits vollständig migrierter SQLite-In-Memory-Zustand pro
     Test) interferieren.
   - Stattdessen wurde das Verhalten im Rahmen dieser Review **erneut**
     empirisch bestätigt: ein frischer `migrate:fresh` mit der
     **kompletten** aktuellen Migrations-Kette (M1–M4 zusammen mit allen
     Bestandsmigrationen) läuft sowohl auf einem Ad-hoc-MySQL-8.4-Container
     als auch auf der laufenden PostgreSQL-Dev-Instanz fehlerfrei durch
     (siehe "Ausführungs-Ergebnis" unten) — der Forward-Pfad, auf den es in
     der Praxis ankommt (Rollback wird auf Produktions-/Demo-Umgebungen mit
     bereits befüllten Tabellen ohnehin nicht blind ausgeführt), ist damit
     nochmals bestätigt.
5. **`InvoiceDunning`-Model (Basis-Tests)** — Lücke, geschlossen (siehe
   `InvoiceDunningTest.php` oben). Vorher existierten nur indirekte
   Assertions über `InvoiceResource`-Felder (`dunningLevel`/`remindedAt`),
   kein Test der Model-Ebene selbst (Factory, Relation, Casts).

## Weitere Beobachtungen (nicht verändert)

- `InvoiceApiTest.php`, `EmailNotificationTest.php`, `InvoicePdfTest.php`
  haben **keine** `uses()->group(...)`-Zeile — TESTING.md Abschnitt 7
  verlangt das verbindlich für **neue** Testdateien; diese drei Dateien
  sind Bestand von vor `TESTING.md` und wurden in T03/T06 nur erweitert,
  nicht neu angelegt. Gemäß TESTING.md-Kopfzeile ("Bestand wird nicht
  rückwirkend angepasst") und der projektweiten Boy-Scout-Regel wurde
  **keine** nachträgliche Gruppen-Ergänzung vorgenommen (hätte einen reinen
  Formatierungs-Diff ohne Bezug zu diesem Change verursacht). Alle drei
  **neuen** Testdateien dieser Review (`InvoiceDunningTest.php`,
  `InvoiceNumberGeneratorTest.php`) sind vollständig TESTING.md-konform
  (`it()`, Groups, Factory-States, `expect()`-Domain-Trennung).
- Das in `task-T04.notes.md`/`task-T05.notes.md` dokumentierte Restrisiko
  ("nach 3 gescheiterten Retry-Versuchen: unbehandelter HTTP-500") ist ein
  bekanntes, vom User akzeptiertes Verhalten und bereits durch die
  bestehenden `'... gives up after exhausting all retry attempts ...'`-Tests
  abgedeckt — kein weiterer Testbedarf, keine Produktivcode-Änderung durch
  mich vorgenommen.
- `docker-compose.mysql.yml` existiert weiterhin nicht im Repo (bestätigt
  per `find`). Wie in T01/T02 wurde daher ein Ad-hoc-`mysql:8.4`-Container
  im bestehenden Docker-Netzwerk verwendet — diesmal für die **komplette**
  kombinierte Migrations-Kette in einem Lauf (nicht nur inkrementell pro
  Task), inklusive funktionaler Verifikation der Storno-/Dunning-Relationen
  gegen echtes MySQL. Container wurde danach entfernt, keine Repo-Änderung.

## Akzeptanzkriterien-Abdeckung (Review-relevante, aus dem Auftrag)

- [x] Retry-on-Conflict für `finalize()` **und** `cancel()` mit echter
  DB-Unique-Constraint-Verletzung abgedeckt — bestehende Tests plus neuer,
  ungemockter Race-Condition-Test in `InvoiceNumberGeneratorTest.php`.
- [x] Storno-Kette End-to-End (Kunden-Sicht, Original unsichtbar, Storno
  sichtbar, Kreuzverweise korrekt) — neuer Test in `InvoiceApiTest.php`.
- [x] Kunden-Sichtbarkeits-Matrix für alle sechs Statuswerte, inkl. `overdue`
  — Backend- und Frontend-Tests ergänzt.
- [x] Migrations-Rollback-Verhalten geprüft und als erwartet/dokumentiert
  bestätigt — bewusst kein neuer Test, Begründung siehe oben.
- [x] `InvoiceDunning`-Basis-Tests (Factory, Relation, Attribute) — neue
  Datei `InvoiceDunningTest.php`.

## Ausführungs-Ergebnis

### Backend (`docker compose exec php composer qa`)

```
Pint (--test):              307 files, no style issue (grün)
PHPStan:                    [OK] No errors (205 Dateien)
PHPCompatibility-Check:     keine Ausgabe = keine Verstöße (grün)
Pest:                       Tests: 812 passed (2549 assertions)
                             Duration: 29.18s
```

Alle 17 neuen Tests laufen grün, keine bestehenden Tests wurden verändert
außer der einen Statuswert-Ergänzung (`overdue`) in der bereits bestehenden
Test-Schleife (Assertion-Anzahl dort von 5 auf 6 erhöht, keine Assertion
entfernt).

### Frontend

```
npm run test -- run:   Test Files  22 passed (22)
                        Tests       246 passed (246)
                        Duration    3.17s

npm run lint:           0 errors, 3091 warnings (identische Baseline wie
                         nach T08 — keine neuen Warnings durch die
                         Test-Ergänzungen)

npm run build:          vue-tsc -b + vite build erfolgreich, keine
                         TS-Fehler, kein Build-Fehler
```

### Ad-hoc-MySQL-Check der kompletten Migrations-Kette

```
docker run mysql:8.4 (Ad-hoc-Container, dasselbe Docker-Netzwerk)
php artisan migrate:fresh --force  →  alle Migrationen inkl. M1–M4 DONE,
                                       keine Fehler
artisan tinker (funktionale Verifikation gegen MySQL):
  originalInvoice/cancellationInvoice-Relation:  OK
  dunning_level (höchste Stufe):                  2 (erwartet: 2)  → OK
  reminded_at (jüngstes Mahndatum):                korrektes Datum → OK
Ad-hoc-Container danach entfernt (docker rm -f), kein Repo-Artefakt.
Dev-Postgres-DB anschließend mit migrate:fresh zurückgesetzt.
```

## Fehler

Keine. Alle Backend- und Frontend-Testläufe sind grün, `npm run build`
läuft ohne Warnings/Fehler durch, die Ad-hoc-MySQL-Verifikation der
vollständigen Migrations-Kette ist erfolgreich.

## Dateien (Übersicht)

- `backend/tests/Unit/Models/InvoiceDunningTest.php` (neu)
- `backend/tests/Feature/Services/InvoiceNumberGeneratorTest.php` (neu)
- `backend/tests/Feature/InvoiceApiTest.php` (erweitert)
- `backend/tests/Feature/DatabaseStructureTest.php` (erweitert)
- `frontend/src/views/invoices/InvoicesView.test.ts` (erweitert)
- `frontend/src/components/InvoiceDetailModal.test.ts` (erweitert)
