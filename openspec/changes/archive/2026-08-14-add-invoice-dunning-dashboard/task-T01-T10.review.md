# Review: T01–T10 (add-invoice-dunning-dashboard)

**Gesamtempfehlung:** ok

Geprüft: `git diff main...feature/add-invoice-dunning-dashboard`, `proposal.md`,
`design.md` (D1–D8), `tasks.md`, alle `task-T0X.notes.md`, `TESTING.md`.
Der Change ist inhaltlich vollständig, spec-konform und durchgängig sauber
begründet. Ich habe keine "Muss"-Befunde gefunden. Zwei "Sollte"- und ein
paar "Könnte"-Punkte, überwiegend bereits von den Entwicklern selbst in den
Notes vorab dokumentiert.

## Muss (blockiert Abnahme)

Keine.

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Test-Konventionen]** `backend/tests/Feature/DashboardApiTest.php:102-171,250-277,317-323`:
  Die fünf neuen `it(...)`-Beschreibungen ("lists overdue and reminded
  invoices of all customers for admin", "excludes a dunning-fee document
  even when its due date is in the past", "excludes paid and cancelled
  invoices even when overdue", "only shows overdue or reminded invoices of
  assigned customers for trainer", "does not include the overdue or
  reminded invoices widget for customers") sind auf Englisch formuliert.
  `TESTING.md` Abschnitt 2.1 verlangt für neue Tests explizit "Dritte
  Person Indikativ, kleinschreibung, **Deutsch**". Die Datei ist zwar
  durchgängig Bestand in Englisch (siehe z. B. Zeile 29 `it('returns
  dashboard data for admin users', ...)`, nicht rückwirkend anzupassen
  laut `TESTING.md`-Präambel), aber da diese Datei ohnehin angefasst wird,
  greift die Boy-Scout-Regel ("wer eine alte Test-Datei sowieso anfasst,
  bringt sie bei der Gelegenheit auf den neuen Stand") zumindest für die
  neu hinzugefügten Blöcke. Alle anderen neuen Testdateien dieses Changes
  (`InvoiceDunningApiTest.php`, `InvoiceDunningRecorderTest.php`,
  `InvoiceDunningNoticeMailTest.php`, `DunningFeeScheduleTest.php`,
  `Unit/Models/InvoiceTest.php`) halten die deutsche Konvention bereits
  korrekt ein — nur diese fünf Tests weichen ab. Vorschlag: Beschreibungen
  auf Deutsch umformulieren (z. B. "listet überfällige und gemahnte
  rechnungen aller kunden für den admin auf"), analog zum Rest des
  Changes. Kein Blocker, da konsistent mit dem unmittelbaren
  Datei-Kontext, aber ein klarer, mechanisch prüfbarer Verstoß gegen die
  in `TESTING.md` Abschnitt 10 explizit für den Reviewer vorgeschriebene
  Checkliste.

- **[Testbarkeit/Reviewer-Hinweis, bereits von T02 selbst dokumentiert]**
  `backend/app/Http/Controllers/Api/InvoiceController.php:502-509`
  (`remind()`, 502-Pfad bei fehlgeschlagenem Mail-Versand): Es gibt keinen
  dedizierten Test für diesen Zweig in `InvoiceDunningApiTest.php` (die
  Datei deckt nur den Erfolgspfad und die beiden 422-Pfade ab). Das
  Verhalten ist zwar 1:1 vom bereits getesteten `sendEmail()`-Muster
  übernommen und daher risikoarm, aber da dieser Change explizit "keine
  Rücknahme der Datenmutation bei Mail-Fehler" als bewusste
  Design-Entscheidung (D7) hervorhebt, wäre ein Regressionstest hier
  wertvoll, um diese Entscheidung dauerhaft abzusichern. In
  `task-T04.notes.md` "Offene Punkte für Reviewer/Tester" bereits als
  Hinweis an den Tester vermerkt — hiermit für den Tester noch einmal
  bestätigt/verstärkt.

## Könnte (optional, Verbesserung)

- **[Duplikation, bereits bewusst in Kauf genommen]** `InvoiceDunningRecorder::createFeeInvoiceWithRetry()`
  (`backend/app/Services/InvoiceDunningRecorder.php:107-139`) dupliziert
  strukturell `InvoiceController::createCancellationInvoiceWithRetry()`
  (`backend/app/Http/Controllers/Api/InvoiceController.php:344-370`) fast
  vollständig (Retry-Schleife, `UniqueConstraintViolationException`-Fang,
  genestete `DB::transaction()`). In `design.md` Decision D2/Risks bereits
  als bewusster Trade-off dokumentiert (Diff-Größe nicht zusätzlich durch
  ein Controller-Refactoring aufblähen) — kein neuer Befund, nur zur
  Vollständigkeit im gebündelten Review aufgeführt. Empfehlung bleibt: als
  eigenständigen Folge-Change umsetzen, wie in `design.md` "Ausblick"
  bereits vorgeschlagen.

- **[Autorisierung, Bestandsmuster]** `InvoicePolicy::remind()`
  (`backend/app/Policies/InvoicePolicy.php:159-162`) ist – wie
  `finalize()`/`cancel()`/`send()` – rein rollenbasiert und schränkt einen
  Trainer nicht auf seine zugewiesenen Kunden ein (ein Trainer kann
  grundsätzlich jede Rechnung mahnen, nicht nur die seiner eigenen
  Kunden). Das ist ein bestehendes, projektweites Muster über die gesamte
  `InvoicePolicy` hinweg (verifiziert: `finalize()`, `cancel()`, `send()`
  haben dieselbe Eigenschaft) und keine von diesem Change neu eingeführte
  Lücke — anders als die in Change 3 real gefundene IDOR-Lücke bei
  `PaymentController::index()` (fehlender Scope komplett), die inzwischen
  behoben ist (siehe `git log`: "fix: payment-index-missing-authorization-scope").
  Kein Blocker für diesen Change, aber falls trainerspezifisches Scoping
  irgendwann gewünscht ist, müsste es konsistent für alle vier
  Statusaktions-Policies (`finalize`/`cancel`/`send`/`remind`) eingeführt
  werden, nicht nur für `remind()`.

- **[Lesbarkeit]** `backend/app/Http/Controllers/Api/DashboardController.php:355-366`
  (`mapOverdueOrRemindedInvoice()`): `$invoice->customer?->user->full_name`
  nutzt einen Nullsafe-Operator nur für `customer`, nicht für `user`
  (bewusst, laut `task-T06.notes.md`, um keinen zweiten
  Baseline-Eintrag für redundante Nullsafe-Verkettung zu erzeugen). Sollte
  `customer` tatsächlich `null` sein (worauf der Nullsafe-Operator
  hindeutet), würde `?->user` ebenfalls `null` liefern und der
  nachfolgende `->full_name`-Zugriff auf `null` einen Fehler werfen statt
  sauber auf `'Unbekannt'` zurückzufallen (das `?? 'Unbekannt'` greift nur,
  wenn der gesamte Ausdruck `null` ergibt, nicht bei einem Fehler mitten in
  der Kette). Da `customer_id` auf `invoices` eine `NOT NULL`-FK ist und
  `Customer::user_id` ebenfalls verpflichtend, ist das Risiko in der
  Praxis vernachlässigbar — nur als Hinweis, falls das bestehende
  `pendingDogRegistrations`-Muster (Vorbild) hier künftig doch angepasst
  wird.

## Lob (kurz, was gut gelöst wurde)

- **Decision D3 (TOCTOU) korrekt umgesetzt:** Sowohl die Eligibility- als
  auch die Stufen-Prüfung in `InvoiceDunningRecorder::trigger()`
  (`backend/app/Services/InvoiceDunningRecorder.php:66-77`) laufen
  nachweislich *nach* `Invoice::query()->lockForUpdate()->findOrFail()`
  und *vor* jeder Schreiboperation — exakt wie in Decision D3 gefordert.
  Der zugehörige Concurrency-Test
  (`backend/tests/Concurrency/Domain/Invoice/InvoiceDunningRecorderConcurrencyTest.php`)
  ist außergewöhnlich sorgfältig dokumentiert (inkl. Sanity-Check mit
  temporär entfernter Sperre, laut `task-T02.notes.md` reproduzierbar
  fehlgeschlagen) und wurde laut `task-T02.notes.md`/`task-T10.notes.md`
  tatsächlich gegen echtes PostgreSQL **und** MySQL ausgeführt, nicht nur
  geschrieben.
- **Der in T01 selbst gefundene `document_type`-Bug in
  `createCancellationInvoiceWithRetry()`** wurde korrekt und vollständig
  nachgezogen: `backend/app/Http/Controllers/Api/InvoiceController.php:352`
  setzt jetzt `'document_type' => 'cancellation'`, die zugehörigen
  Bestandstests (`InvoiceApiTest.php:591-596,749-752`) wurden konsistent
  angepasst, und T10 bestätigt die Behebung explizit noch einmal per Grep.
- **D8 (Cron-Mailer-Entfernung) ist vollständig und sauber:** Grep-Check
  auf `SendPaymentReminders|PaymentReminder|invoices:send-reminders` in
  `backend/app/` und `backend/routes/` liefert keine Treffer mehr; der
  `queue:prune-failed`-Scheduler-Block in `backend/routes/console.php`
  bleibt unangetastet stehen; verwaiste `phpstan-baseline.neon`-Einträge
  wurden korrekt mitentfernt.
- **Keine PHP-8.3/8.4-Konstrukte, kein raw SQL, beide Migrationen additiv**
  über den gesamten Diff verifiziert (Grep gegen die in CLAUDE.md
  Abschnitt 4.1/4.2 verbotenen Muster: keine Treffer).
- **Mahn-E-Mail korrekt synchron ohne `ShouldQueue`**
  (`backend/app/Listeners/SendInvoiceDunningEmail.php`), keine manuelle
  `Event::listen()`-Registrierung in `AppServiceProvider.php` (Diff dort
  leer) — der in Change 2 real aufgetretene Doppel-Mail-Bug kann hier
  nicht erneut auftreten.
- **`InvoiceDunningResource` exponiert keine sensiblen Daten** — nur
  Stufe/Datum/Gebühr/verlinkte Rechnungsnummer, keine Zahlungs- oder
  Kundendaten.
- Durchgängig sehr transparente, mit Datei:Zeile belegte
  `task-T0X.notes.md`-Dokumentation, die eigene Abweichungen und offene
  Punkte proaktiv benennt (z. B. der T01-Fund selbst) — macht diesen
  Review deutlich verlässlicher nachvollziehbar.
