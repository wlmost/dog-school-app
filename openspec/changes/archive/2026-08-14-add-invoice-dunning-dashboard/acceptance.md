# Abnahme: add-invoice-dunning-dashboard

**Status:** bereit-für-user-review

Geprüft auf `feature/add-invoice-dunning-dashboard`, Commit `20b7944`.

## 0. Strukturelle Validität

```
$ openspec validate add-invoice-dunning-dashboard --strict
Change 'add-invoice-dunning-dashboard' is valid
```

## 1. Vollständigkeit (tasks.md)

Alle zehn Tasks T01–T10 sind in `tasks.md` vollständig als `[x]` markiert,
keine offenen Akzeptanzkriterien. Für jede Task existiert ein
`task-T0X.notes.md` mit Datei:Zeile-Belegen.

## 2. Die vier bindenden User-Entscheidungen — verifiziert gegen Code

- **(a) Mahngebühr als eigenes Dokument statt `total_amount`-Mutation:**
  `backend/app/Services/InvoiceDunningRecorder.php` erzeugt über
  `createFeeInvoiceWithRetry()` eine neue `Invoice` mit eigener
  `invoice_number`, `document_type = 'dunning_fee'`,
  `original_invoice_id` auf die Ursprungsrechnung. Die Original-Rechnung
  wird ausschließlich per `$locked->update(['status' => 'reminded'])`
  verändert — kein Zugriff auf `total_amount`. Bestätigt durch
  `InvoiceDunningRecorderTest.php` (Akzeptanzkriterium (f): "`total_amount`
  bleibt unverändert") sowie den zugehörigen Spec-Delta-Scenario
  "Gesamtbetrag der Original-Rechnung bleibt unverändert"
  (`specs/invoice-dunning-trigger/spec.md:17-22`).
- **(b) Alter Cron-Mailer vollständig entfernt:**
  `grep -rn "SendPaymentReminders|PaymentReminder|invoices:send-reminders" backend/app backend/routes`
  liefert keine Treffer (selbst ausgeführt, bestätigt). Der Diff zeigt
  `SendPaymentReminders.php` (-94), `PaymentReminder.php`/
  `payment-reminder.blade.php` (-105) und die Scheduler-Blöcke in
  `routes/console.php` (-20) als vollständig entfernt; `SendTestEmail.php`
  nutzt jetzt `InvoiceDunningNotice`. Spec-Delta
  "Automatischer, unbeaufsichtigter Mahn-Mailversand entfällt"
  (`specs/invoice-dunning-trigger/spec.md:90-102`) spiegelt das.
- **(c) Genau 3 Mahnstufen mit festen Beträgen:**
  `backend/config/invoicing.php` (`max_dunning_level = 3`,
  `dunning_fees` 1/2/3 = 5.00/10.00/15.00 €, env-überschreibbar) +
  `backend/app/Support/DunningFeeSchedule.php` (`nextLevel()` liefert
  `null` sobald `max_dunning_level` überschritten würde,
  `feeForLevel()` liest ausschließlich aus der Config). Kein Frontend-
  Eingabefeld für den Betrag (Bestätigungsdialog zeigt den Wert nur an,
  laut `task-T07.notes.md`). Spec-Deltas "Feste Mahngebühren je Stufe"
  und "Obergrenze bei Mahnstufe 3" decken das ab.
- **(d) Automatischer E-Mail-Versand bei Mahnungsauslösung:**
  `InvoiceController::remind()` dispatcht `InvoiceDunningTriggered`
  synchron nach erfolgreichem `trigger()`-Aufruf; `SendInvoiceDunningEmail`
  versendet `InvoiceDunningNotice` mit dem Gebührendokument als
  PDF-Anhang, ohne `ShouldQueue` (1:1 `InvoiceWasSent`-Muster). Bestätigt
  durch `InvoiceDunningNoticeMailTest.php` und den neu ergänzten
  `InvoiceDunningMailFailureTest.php` (502-Pfad bei Mail-Fehler, Mahnstufe
  bleibt bestehen — Decision D7 korrekt umgesetzt und jetzt auch
  getestet).

## 3. Spec-Deltas vs. implementierter Code

`specs/invoice-dunning-trigger/spec.md` und
`specs/invoice-overdue-dashboard/spec.md` gelesen und stichprobenartig
gegen den Diff geprüft:

- Route `POST /invoices/{invoice}/remind` (`backend/routes/api.php:187`)
  und `InvoicePolicy::remind()` (rollenbasiert, Admin/Trainer) decken die
  Requirements "Nur Admin und Trainer können eine Mahnung auslösen"
  korrekt ab (403 für Kunden, per `InvoiceDunningApiTest.php` getestet).
- Dashboard-Requirement "Gebühren- und Korrekturdokumente erscheinen
  nicht als eigenständige Einträge": `DashboardController.php`
  filtert per `whereNull('document_type')` — bestätigt per Lesen und
  durch `DashboardApiTest.php`-Testfall "excludes a dunning-fee document
  even when its due date is in the past".
- `specs/invoice-status-lifecycle/spec.md` (Modified Capability, T01-Teil)
  wurde ebenfalls im Diff mitgeführt — konsistent mit der in `proposal.md`
  angekündigten Erweiterung um Trigger-Erzeugung/Obergrenze.

Keine Abweichung zwischen Spec-Delta und Implementierung gefunden.

## 4. Review- und Testbefunde

- `task-T01-T10.review.md`: Gesamtempfehlung "ok", keine "Muss"-Befunde.
  Zwei "Sollte"-Befunde, beide behoben:
  - Englische Test-Beschreibungen in `DashboardApiTest.php` →
    Commit `20b7944` stellt die fünf neuen `it(...)`-Beschreibungen auf
    Deutsch um (verifiziert per `git show --stat 20b7944`: exakt
    `DashboardApiTest.php`, 5 Zeilen geändert).
  - Fehlender 502-Pfad-Test für `remind()` → Commit `93a09ed` fügt
    `InvoiceDunningMailFailureTest.php` hinzu (im vollen Testlauf grün
    bestätigt).
  Die drei "Könnte"-Punkte sind dokumentierte, bewusste Trade-offs
  (Retry-Duplikation als Folge-Change vorgeschlagen, kein
  trainer-scope in `InvoicePolicy::remind()` — konsistent mit bestehendem
  projektweitem Muster, kleinerer Nullsafe-Lesbarkeitshinweis) und
  blockieren die Abnahme nicht.
- `task-T01-T10.test-report.md`: Status "alle-gruen". Fünf
  Cross-Task-/Cross-Change-Lücken identifiziert (voller 3-Stufen-Flow,
  Mahnung×Zahlung, Mahnung×Storno, 502-Pfad, echter
  `InvocesView`↔`InvoiceDetailModal`-Event-Vertrag) und durch neue Tests
  in Commit `93a09ed` geschlossen. Backend 892 bestanden / 3 übersprungen
  (Concurrency-Tests, erfordern echte MVCC-DB), Frontend 345/345
  bestanden — beides beim eigenen Nachlauf reproduziert (siehe Abschnitt
  5).

Keine offenen "Muss"-Punkte aus Review oder Test-Report.

## 5. Pre-Flight-Check (CLAUDE.md 7.1, "Vor User-Gate 2") — selbst ausgeführt

```
$ docker compose exec php composer qa
  Pint (Lint):    PASS, 334 files
  PHPStan:        [OK] No errors, 215/215
  compat-check:   keine Ausgabe (exit 0) — keine PHP-8.3/8.4-Verstöße
  Pest:           Tests: 3 skipped, 892 passed (2828 assertions)
                  (3 übersprungen: Concurrency-Tests, die eine echte
                  MVCC-DB benötigen — laut task-T02/T10.notes.md bereits
                  separat gegen PostgreSQL und MySQL verifiziert)

$ docker compose exec node npx vitest run
  Test Files  27 passed (27)
  Tests       345 passed (345)

$ git diff main...feature/add-invoice-dunning-dashboard --stat
  66 files changed, 6579 insertions(+), 526 deletions(-)
  (u.a. Entfernung: SendPaymentReminders.php, PaymentReminder.php,
  payment-reminder.blade.php, PaymentReminderEmailTest.php, sowie der
  Payment-Reminder-describe-Block in EmailNotificationTest.php — passt
  zu Decision D8; Neu: InvoiceDunningRecorder, DunningFeeSchedule,
  config/invoicing.php, zwei additive Migrationen, Mahn-E-Mail-Trio,
  Dashboard-Widget Backend+Frontend, Mahnen-Button in InvoicesView/
  InvoiceDetailModal, umfangreiche neue Tests)
```

Alle vier Ergebnisse decken sich mit den in `task-T10.notes.md` und
`task-T01-T10.test-report.md` dokumentierten Werten (Zuwachs um genau die
in `93a09ed` ergänzten 9 Backend- und 2 Frontend-Tests, keine Regression).

## 6. Interaktion mit bereits offenen/gemergten PRs

`gh pr list --state all` zeigt: PR #86 (Change 2,
`add-invoice-send-flow`) und PR #90 (Change 3,
`add-invoice-payment-entry`) sind **beide bereits in `main` gemergt**
(2026-08-13 bzw. 2026-08-14), ebenso die nachfolgenden Fix-PRs #91/#92.
`git merge-base --is-ancestor main feature/add-invoice-dunning-dashboard`
bestätigt: der aktuelle `main`-Stand ist bereits ein Vorfahre dieses
Feature-Branches — der Branch enthält also bereits alle Änderungen aus
#86/#90 (u. a. an `AppServiceProvider.php`/`InvoicePolicy.php`). Ein
künftiger Merge dieses Branches gegen `main` ist ein einfacher
Fast-Forward-fähiger Merge ohne die historisch bekannten
Konfliktdateien — **keine Konflikte zu erwarten**. Es gibt aktuell keine
weiteren offenen PRs, die mit diesem Change um dieselben Dateien
konkurrieren könnten (`gh pr list --state open` liefert eine leere
Liste).

## Erfüllt

- Alle zehn Tasks vollständig, spec-konform implementiert und getestet.
- Alle vier bindenden User-Entscheidungen im Code nachweisbar umgesetzt
  und durch dedizierte Tests abgesichert.
- Beide "Sollte"-Befunde des Reviewers behoben (Commits `93a09ed`,
  `20b7944`), keine "Muss"-Befunde offen.
- Fünf vom Tester gefundene Cross-Task-/Cross-Change-Lücken geschlossen,
  volle Suite grün (Backend 892/895, Frontend 345/345).
- `openspec validate --strict` erfolgreich.
- Kein Konfliktrisiko mit anderen PRs — `main` ist bereits vollständig in
  diesen Branch integriert.

## Offen / Nacharbeit

- **Kein Muss-Punkt.** Ein reiner Prozess-Hinweis: `task-T01-T10.review.md`
  lag zum Zeitpunkt dieser Abnahme noch **ungetrackt** im Arbeitsverzeichnis
  (nicht committet, siehe `git status`). Inhaltlich wurde die Datei bereits
  vollständig gelesen und ihre "Sollte"-Befunde sind nachweislich behoben
  — das Dokument selbst fehlte aber im Commit-Verlauf des Feature-Branches.
  Ich committe sie zusammen mit `acceptance.md`, damit der Review-Nachweis
  Teil der Historie ist (kein Code, reine Dokumentation — daher innerhalb
  meiner Abnahme-Rolle vertretbar).
- `proposal.md` Abschnitt "Offene Fragen für Skeptiker/User" (5 Punkte,
  u. a. Umsatzsteuer-Behandlung der Mahngebühr mit `tax_rate = 0`, kein
  Trigger-Button direkt im Dashboard-Widget, feste Beträge 5/10/15 €) war
  bereits vor Implementierungsbeginn dokumentiert und wurde laut
  `verification.md` als "nicht blockierend für Gate 1" eingestuft; die
  Implementierung setzt durchgängig genau die dort vorgeschlagenen
  Standardwerte um. Empfehlung: der User bestätigt diese fünf Punkte
  explizit bei Gate 2 final (insbesondere Punkt 1, Umsatzsteuer — das ist
  eine steuerrechtliche Einschätzung, keine rein technische, und liegt
  außerhalb dessen, was Architekt/Reviewer/Tester validieren können).

## Empfehlung an den User

Der Change ist inhaltlich, testtechnisch und strukturell vollständig und
bereit für User-Gate 2. Vor der finalen Freigabe nur noch kurz die fünf
in `proposal.md` dokumentierten offenen Fachfragen bestätigen
(insbesondere die Umsatzsteuer-Behandlung der Mahngebühr) — technisch
und prozessual steht der Archivierung/dem PR nichts entgegen.
