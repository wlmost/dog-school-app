# Test-Report: T01-T10 (add-invoice-dunning-dashboard)

**Status:** alle-gruen

Geprüft wurde der vollständige, bereits gemergte Feature-Branch
`feature/add-invoice-dunning-dashboard` gegen `main` (Change 4 von 4 des
Rechnungsworkflow-Umbaus). Produktivcode wurde nicht angefasst — dieser
Report deckt ausschließlich neue/erweiterte Testdateien ab, die zusätzlich
zu den bereits von den Entwickler-Agenten in `task-T0X.notes.md`
dokumentierten Tests geschrieben wurden.

## Vorgehen

1. `proposal.md`, `design.md`, `tasks.md` sowie alle zehn
   `task-T0X.notes.md` vollständig gelesen — insbesondere die jeweils
   dokumentierten "Offene Punkte für Reviewer/Tester"-Abschnitte.
2. `TESTING.md` als verbindliche Konvention zugrunde gelegt (Pest,
   `it()`-Stil, Factory-States, Groups, HTTP-Assertions Laravel-Style,
   Werte-Assertions Pest-`expect()`).
3. Bestehende Testdateien für das Mahnwesen inventarisiert
   (`InvoiceDunningApiTest.php`, `InvoiceDunningRecorderTest.php`,
   `InvoiceDunningNoticeMailTest.php`, `InvoiceDunningRecorderConcurrencyTest.php`,
   `DunningFeeScheduleTest.php`, `InvoiceDunningTest.php`,
   `DashboardApiTest.php`, `InvoicePaymentApiTest.php`, `InvoiceApiTest.php`,
   `InvoicesView.test.ts`, `InvoiceDetailModal.test.ts`, `DashboardView.test.ts`)
   — jeweils per `grep`/vollständigem Lesen verifiziert, was tatsächlich
   abgedeckt ist, nicht nur laut Notes angenommen.
4. Lücken identifiziert (siehe unten), neue Tests ergänzt, **keine**
   bestehenden Tests verändert oder entfernt.
5. Volle Testsuite ausgeführt (Backend `docker compose exec php composer
   test`/`lint`/`stan`/`compat-check`, Frontend `docker compose exec node
   npx vitest run`/`npm run lint`/`npm run build`).

## Identifizierte Lücken und Begründung

1. **Kein Test, der alle drei Mahnstufen end-to-end durchläuft** und dabei
   sowohl die 422-Ablehnung der vierten Mahnung als auch die vollständige
   3-stufige Historie in Detailansicht (`GET /invoices/{id}`) **und**
   Dashboard-Widget (`GET /dashboard`) prüft. Kein bestehender Test
   asserted `dunningLevel === 3` irgendwo im Repo (verifiziert per
   `grep -rn "dunningLevel.*3\|'level', 3" tests/`, kein Treffer).
   `InvoiceDunningApiTest.php` deckt Stufe 1→2 (eigener Test) und Stufe
   1→2→3→abgelehnte 4. Mahnung (separater Test) ab, aber nie in
   Kombination mit einer Prüfung von Detail-/Dashboard-Antwort.
2. **Interaktion Mahnung × Zahlung (Change 3 × Change 4):** Der einzige
   existierende Test dazu
   (`InvoicePaymentApiTest.php::'akzeptiert eine zahlung für eine
   gemahnte (reminded) rechnung'`) erzeugt die `reminded`-Rechnung
   ausschließlich per Factory-Status, ohne echte `InvoiceDunning`-
   Datensätze, und prüft nur den Statuswechsel zu `paid` — nicht, ob eine
   über `InvoiceDunningRecorder::trigger()` tatsächlich erzeugte
   Mahnhistorie (inkl. Gebührendokumente) eine Teilzahlung bzw. eine
   Vollzahlung unverändert übersteht.
3. **Interaktion Mahnung × Storno (Change 1 × Change 4):**
   `InvoicePolicy::cancel()` erlaubt den Status `reminded` explizit
   (`in_array($invoice->status, ['sent', 'paid', 'reminded'], true)`),
   aber weder `InvoiceApiTest.php` noch `InvoiceDunningApiTest.php`
   stornieren tatsächlich eine bereits gemahnte Rechnung mit
   existierenden Mahngebühren-Dokumenten. `design.md` Non-Goals legt
   fest, dass bereits erzeugte Gebührendokumente beim Stornieren der
   Original-Rechnung unangetastet bleiben — das war unverifiziert.
   Ebenfalls ungetestet: Storno-Versuch auf einem Mahngebühren-Dokument
   selbst (muss laut `InvoicePolicy::cancel()`s
   `original_invoice_id === null`-Prüfung mit 403 abgelehnt werden).
4. **Fehlender 502-Pfad-Test**, von `task-T04.notes.md` selbst explizit
   als offener Punkt dokumentiert ("Kein dedizierter Test für den
   502-Pfad in `InvoiceDunningApiTest.php` ergänzt … als Hinweis für den
   Tester dokumentiert, falls zusätzliche Abdeckung gewünscht ist").
5. **Frontend-Event-Vertrag `InvoicesView.vue` ↔ `InvoiceDetailModal.vue`**
   wurde laut `task-T08.notes.md` ("Offene Punkte für Reviewer/Tester")
   nur durch Lesen der jeweils anderen Task-Beschreibung sichergestellt,
   nicht durch tatsächliches Zusammenspiel der beiden real gemounteten
   Komponenten. `InvoicesView.test.ts` stubbt `InvoiceDetailModal`
   vollständig (inkl. einer manuell in der `emits`-Liste nachgetragenen
   `'remind'`-Deklaration) und simuliert den Emit direkt per
   `detailModal.vm.$emit('remind', invoice)` — das beweist nicht, dass
   die echte Komponente dieses Event mit demselben Namen/derselben
   Payload tatsächlich auslöst.
6. **`DunningFeeSchedule`-Grenzfälle:** Stufe 0, eine lückenhafte
   Konfiguration (fehlender Zwischenwert) sowie ein von 3 abweichender
   `max_dunning_level`-Konfigurationswert (Regressionsschutz dagegen,
   dass `nextLevel()` den Wert 3 hart codiert statt `config(...)` zu
   lesen) waren ungetestet.

## Hinzugefügte / geänderte Tests

- `backend/tests/Feature/Api/InvoiceDunningFullFlowTest.php` (neu): 1 Test
  — vollständiger Mahn-Flow über alle drei Stufen inkl. abgelehnter
  vierter Mahnung, Detailansicht- und Dashboard-Verifikation.
- `backend/tests/Feature/Api/InvoiceDunningPaymentInteractionTest.php`
  (neu): 2 Tests — Teilzahlung auf eine gemahnte Rechnung (Historie
  bleibt erhalten, Status bleibt `reminded`), Vollzahlung (Historie
  bleibt erhalten, Status wechselt zu `paid`, danach keine weitere
  Mahnung mehr möglich).
- `backend/tests/Feature/Api/InvoiceDunningCancelInteractionTest.php`
  (neu): 2 Tests — Stornieren einer bereits gemahnten Rechnung lässt
  bestehende Mahngebühren-Dokumente unverändert; Stornieren eines
  Mahngebühren-Dokuments selbst wird mit 403 abgelehnt.
- `backend/tests/Feature/Api/InvoiceDunningMailFailureTest.php` (neu): 1
  Test — E-Mail-Transportfehler beim Mahnen liefert 502, die bereits
  erfasste Mahnstufe bleibt trotzdem bestehen (kein Rollback, Decision D7).
- `backend/tests/Unit/Support/DunningFeeScheduleTest.php` (erweitert): 3
  neue Tests — Stufe 0 liefert `null`, lückenhafte Konfiguration liefert
  für die fehlende Stufe `null`, ein abweichend konfigurierter
  `max_dunning_level` wird tatsächlich respektiert (kein hartcodierter
  Wert 3).
- `frontend/src/views/invoices/InvoicesView.integration.test.ts` (neu,
  Namenskonvention `*.integration.test.ts` von
  `AnnouncementBanner.integration.test.ts` übernommen): 2 Tests — echter
  Event-Vertrag zwischen `InvoicesView.vue` und dem **echten** (nicht
  gestubbten) `InvoiceDetailModal.vue`: Klick auf den echten
  "Mahnen"-Button löst den erwarteten `POST .../remind` in `InvoicesView`
  aus; der Button fehlt im echten Markup korrekt, wenn die Maximalstufe
  bereits erreicht ist.

Keine bestehende Testdatei wurde verändert oder gelöscht.

## Akzeptanzkriterien-Abdeckung (ergänzend zu den bereits in den
`task-T0X.notes.md` dokumentierten, dort bereits erfüllten Kriterien)

- [x] "Zwei nahezu gleichzeitige Mahnungs-Trigger führen zu genau einem
  Stufen-Fortschritt" (Goal aus `design.md`) — bereits durch
  `InvoiceDunningRecorderConcurrencyTest.php` (T02) abgedeckt, hier nicht
  erneut angefasst.
- [x] Holistischer 3-Stufen-Flow inkl. Detail-/Dashboard-Konsistenz — neu
  getestet in `InvoiceDunningFullFlowTest.php::'durchläuft alle drei
  mahnstufen und spiegelt die vollständige historie in detailansicht und
  dashboard wider'`.
- [x] Mahnung + Teilzahlung erhält die Mahnhistorie — getestet in
  `InvoiceDunningPaymentInteractionTest.php::'behält die vollständige
  mahnhistorie bei einer teilzahlung auf eine gemahnte rechnung'`.
- [x] Mahnung + Vollzahlung erhält die Mahnhistorie und wechselt korrekt
  zu `paid` — getestet in
  `InvoiceDunningPaymentInteractionTest.php::'erhält die mahnhistorie
  auch nach vollständiger zahlung und wechselt den status auf paid'`.
- [x] Stornieren einer gemahnten Rechnung lässt bestehende
  Mahngebühren-Dokumente unangetastet (design.md Non-Goals) — getestet
  in `InvoiceDunningCancelInteractionTest.php::'lässt eine bereits
  gemahnte rechnung stornieren ohne die bestehenden
  mahngebühren-dokumente zu verändern'`.
- [x] Ein Mahngebühren-Dokument selbst kann nicht storniert werden —
  getestet in `InvoiceDunningCancelInteractionTest.php::'verbietet das
  stornieren eines mahngebühren-dokuments selbst'`.
- [x] 502-Pfad bei E-Mail-Transportfehler beim Mahnen (von T04 als offen
  dokumentiert) — getestet in
  `InvoiceDunningMailFailureTest.php::'gibt bei einem
  e-mail-transportfehler beim mahnen 502 zurück, behält aber die bereits
  erfasste mahnstufe'`.
- [x] Frontend-Event-Vertrag `remind` zwischen echten Komponenten (von T08
  als offen dokumentiert) — getestet in
  `InvoicesView.integration.test.ts` (beide Tests).
- [x] `DunningFeeSchedule`-Grenzfälle (Stufe 0, lückenhafte Config,
  abweichender `max_dunning_level`) — getestet in den drei neuen
  `DunningFeeScheduleTest.php`-Fällen.

Kein Akzeptanzkriterium aus `tasks.md` war nicht testbar — alle
identifizierten Lücken lagen außerhalb der wörtlichen, pro-Task
formulierten Akzeptanzkriterien (die isoliert bereits erfüllt sind, siehe
`task-T0X.notes.md`) und betrafen ausschließlich das Zusammenspiel
mehrerer Tasks/Changes, wie in der Aufgabenstellung gefordert.

## Ausführungs-Ergebnis

### Backend — gezielter Lauf der neuen/geänderten Dateien

```
PASS  Tests\Unit\Support\DunningFeeScheduleTest            8 passed
PASS  Tests\Feature\Api\InvoiceDunningApiTest              12 passed (Bestand, unverändert grün)
PASS  Tests\Feature\Api\InvoiceDunningCancelInteractionTest 2 passed
PASS  Tests\Feature\Api\InvoiceDunningFullFlowTest          1 passed
PASS  Tests\Feature\Api\InvoiceDunningMailFailureTest       1 passed
PASS  Tests\Feature\Api\InvoiceDunningPaymentInteractionTest 2 passed
PASS  Tests\Feature\Domain\Invoice\InvoiceDunningRecorderTest 8 passed (Bestand, unverändert grün)
PASS  Tests\Feature\InvoiceDunningNoticeMailTest             3 passed (Bestand, unverändert grün)
WARN  Tests\Concurrency\Domain\Invoice\InvoiceDunningRecorderConcurrencyTest
  - benötigt echte MVCC-DB, korrekt auf SQLite übersprungen (bereits aus T02 bekannt)

Tests: 1 skipped, 37 passed (166 assertions)
```

### Backend — volle Suite (`docker compose exec php composer test`)

```
Tests:    3 skipped, 892 passed (2828 assertions)
Duration: 33.27s
```

(zuvor laut `task-T10.notes.md`: 883 passed — Zuwachs um genau die 9 neu
hinzugefügten Testfälle: 1 FullFlow + 2 PaymentInteraction + 2
CancelInteraction + 1 MailFailure + 3 DunningFeeSchedule-Erweiterungen.
Die 3 weiterhin übersprungenen Concurrency-Tests sind unverändert aus T02
bekannt und benötigen PostgreSQL/MySQL statt SQLite.)

```
docker compose exec php composer lint          → PASS, 334 files
docker compose exec php composer stan          → [OK] No errors, 215/215
docker compose exec php composer compat-check  → keine Ausgabe, exit 0
```

### Frontend — volle Suite (`docker compose exec node npx vitest run`)

```
Test Files  27 passed (27)
Tests       345 passed (345)
```

(zuvor laut `task-T10.notes.md`: 343 passed über 26 Dateien — Zuwachs um
genau die 2 neuen Tests in der neuen Datei
`InvoicesView.integration.test.ts`.)

```
docker compose exec node npm run lint    → 0 errors, 3222 warnings
                                            (zuvor 3221 laut task-T10.notes.md;
                                            +1 Warning ausschließlich
                                            `@typescript-eslint/no-explicit-any`
                                            in der neuen Testdatei, Zeile 120,
                                            konsistent mit dem bereits
                                            projektweit etablierten
                                            `any`-Nutzungsmuster in Test-Dateien
                                            — keine neue Fehlerkategorie)
docker compose exec node npm run build   → vue-tsc -b && vite build, erfolgreich,
                                            keine TypeScript-Fehler
```

Kein MySQL/PostgreSQL-Zusatzlauf für die neuen Tests nötig — keine der
neuen Testdateien enthält Migrationen oder DB-treiberspezifisches
Verhalten; sie nutzen ausschließlich bereits in T01/T02 verifiziertes,
additives Schema über Eloquent.

## Fehler

Keine. Alle neuen und bestehenden Tests sind grün, keine Regression.
