# Abnahme: add-invoice-payment-entry

**Status:** bereit-für-user-review

## 0. Strukturelle Validität

```
openspec validate add-invoice-payment-entry --strict
→ Change 'add-invoice-payment-entry' is valid
```

## 1. Vollständigkeit der Tasks

Alle Akzeptanzkriterien in `tasks.md` (T01–T08) sind als `[x]` markiert.
Stichprobenartig gegen den tatsächlichen Code verifiziert (nicht nur aus
Notes übernommen):

- `backend/app/Services/InvoicePaymentRecorder.php` existiert, implementiert
  `record()`/`completeExisting()`/`syncStatus()` exakt wie in T02
  beschrieben, mit `DB::transaction()` + `Invoice::query()->lockForUpdate()`.
- `backend/app/Http/Controllers/Api/PaymentController.php`: `store()`
  (Zeile 99–131) nutzt den Service, prüft `PAYABLE_STATUSES` (Zeile 36,
  105) und Überzahlung (Zeile 120–128); `handlePaymentCaptureCompleted()`
  und `markAsCompleted()` nutzen laut Grep ebenfalls
  `InvoicePaymentRecorder` (per `InvoiceOverpaymentException`-Import,
  Zeile 7).
- `grep -rn "markAsPaid|mark-paid|canMarkAsPaid" backend/app backend/routes
  frontend/src` liefert **keine Treffer** — T04 vollständig umgesetzt,
  auch die Route `POST /payments/{payment}/mark-completed`
  (`backend/routes/api.php:192`) ist eine andere, unveränderte,
  vorbestehende Route (nicht zu verwechseln mit dem entfernten
  `mark-paid`).
- `frontend/src/components/InvoicePaymentDialog.vue` (neu),
  `InvoicesView.vue`/`InvoiceDetailModal.vue` enthalten `canRecordPayment()`
  mit identischer `PAYABLE_STATUSES`-Konstante (`['sent','reminded',
  'overdue']`, verifiziert wertgleich an allen drei Stellen inkl. Backend).
- `openspec/changes/add-invoice-payment-entry/specs/` enthält die im
  Proposal angekündigten Deltas (`invoice-payment-entry` neu,
  `invoice-status-lifecycle` modifiziert) — strukturell passend.

## 2. TOCTOU-Muss-Befund aus `task-T03.review.md` — verifiziert behoben

Der Reviewer-Befund `[Korrektheit/Sicherheit — TOCTOU]` (Überzahlung durch
zwei nahezu gleichzeitige `store()`-Aufrufe, da die Restbetrags-Prüfung nur
außerhalb des Locks stattfand) ist im Code, nicht nur laut Notes, behoben:

- `InvoicePaymentRecorder::record()` (`backend/app/Services/
  InvoicePaymentRecorder.php:71-86`) prüft `$amount > $locked->
  remaining_balance` **nach** `Invoice::query()->lockForUpdate()->
  findOrFail()`, **vor** `Payment::create()`, und wirft
  `InvoiceOverpaymentException`.
- `backend/app/Exceptions/InvoiceOverpaymentException.php` (neu) ist eine
  dedizierte `RuntimeException`-Subklasse mit `readonly`-Properties
  (`invoiceId`, `attemptedAmount`, `remainingBalance`) — konform zu
  CLAUDE.md Abschnitt 6 ("eigene Exception-Klassen pro Domäne") und
  PHP-8.2-kompatibel (Constructor Promotion, kein 8.3/8.4-Feature).
- `PaymentController::store()` (Zeile 124–128) fängt die Exception per
  `try/catch` und liefert über `overpaymentResponse()` (Zeile 138ff.)
  HTTP 422 mit derselben Nachricht wie der Vorab-Check — DRY, ein einziger
  Response-Pfad für beide Fälle.
- Neuer Zwei-Prozess-Test in `backend/tests/Concurrency/Domain/Payment/
  InvoicePaymentRecorderConcurrencyTest.php` (per `pcntl_fork()`) beweist
  den Fix gegen echtes MVCC: selbst ausgeführt gegen PostgreSQL,
  **PASS** (siehe Abschnitt 5).

Der zugehörige, ursprünglich fehlerhafte T02-Test (der Überzahlung als
Feature dokumentierte) wurde korrekt zu einer Ablehnungs-Erwartung
umgeschrieben, ohne den unabhängigen "Summe exakt = Gesamtbetrag"-Test aus
T02 zu berühren — nachvollziehbar in
`task-fix-overpayment-race.notes.md`.

**Bewertung:** Muss-Befund vollständig und korrekt behoben.

## 3. Die vier Gate-1-Entscheidungen — verifiziert umgesetzt

1. **Überzahlung → HTTP 422:** Doppelt abgesichert (Fail-Fast-Vorab-Check
   + autoritative Prüfung im Lock, siehe Abschnitt 2). Bestätigt.
2. **`markAsPaid()` ersatzlos entfernt:** Grep-bestätigt keine Treffer
   mehr in Backend, Routes oder Frontend (siehe Abschnitt 1). Bestätigt.
3. **Keine Korrektur-UI für Zahlungen:** `PaymentController::update()`/
   `destroy()` unverändert und weiterhin ohne Admin/Trainer-UI-Anbindung
   — kein neuer Code dafür im Diff gefunden. Bestätigt.
4. **"Zahlung erfassen"-Button sichtbar für `sent`/`reminded`/`overdue`:**
   `canRecordPayment()` in beiden Frontend-Dateien und
   `PAYABLE_STATUSES` im Backend stimmen exakt überein (Abschnitt 1).
   Bestätigt.

## 4. PR-#89-Koordinationsfrage

`gh pr view 89 --json state` → **`OPEN`**, noch nicht gemerged
(`baseRefName: main`, `headRefName: fix/payment-index-missing-authorization-scope`).

Damit ist der in `task-T03.review.md` unter "Sollte" dokumentierte
Koordinationspunkt aktuell **kein** akuter Konflikt (beide Branches
basieren auf demselben `main`-Stand, textuelle Überschneidung in
`PaymentPolicy.php` ist laut Reviewer unwahrscheinlich, da unterschiedliche
Methoden betroffen sind: `create()` in diesem Branch, `view()`/`update()`
in PR #89). Er bleibt aber ein **offener Merge-Koordinationspunkt**, der
beim tatsächlichen Zusammenführen beider Branches explizit zu prüfen ist:

- `backend/app/Policies/PaymentPolicy.php` — beide Branches ändern
  unterschiedliche Methoden derselben Datei; Merge-Konflikt
  unwahrscheinlich, aber nach dem Merge beider Branches erneut lesen.
- `backend/tests/Feature/PaymentApiTest.php` — dieser Branch setzt
  `trainer_id` zentral im `beforeEach`, PR #89 setzt es laut Reviewer in
  mehreren neuen Tests zusätzlich lokal. Nach dem Merge redundant, aber
  nicht falsch (idempotent) — trotzdem beim Zusammenführen explizit auf
  doppelte/widersprüchliche `beforeEach`-Annahmen prüfen.

**Empfehlung:** Diese Abnahme wird davon nicht blockiert (PR #89 ist ein
unabhängiger, bereits separat freigegebener Sicherheits-Fix). Der
Koordinationspunkt ist beim Mergen beider Branches in der vom User
gewählten Reihenfolge zu berücksichtigen.

## 5. Selbst ausgeführte QA-Checks (nicht nur aus Notes übernommen)

**Backend, SQLite (Docker):**
```
docker compose exec php vendor/bin/pest --no-coverage
→ Tests: 2 skipped, 847 passed (2643 assertions)
(die 2 Skips sind die beiden Concurrency-Tests, die auf SQLite mangels
echter Zeilensperren korrekt übersprungen werden)
```

**Backend, echtes PostgreSQL (`dog_school_test`):**
```
docker compose exec php sh -c "DB_CONNECTION=pgsql DB_DATABASE=dog_school_test php artisan migrate:fresh --force"
→ alle Migrationen inkl. 2026_08_13_100001_add_notes_to_payments_table erfolgreich

docker compose exec php sh -c "DB_CONNECTION=pgsql DB_DATABASE=dog_school_test vendor/bin/pest --no-coverage"
→ Tests: 849 passed (2652 assertions), keine Skips — beide Concurrency-Tests
  liefen real gegen MVCC-Zeilensperren, inkl. dem neuen Race-Beweis für den
  TOCTOU-Fix.

(Test-DB danach wieder auf migrate:fresh zurückgesetzt.)
```

**Backend, Einzel-QA-Kommandos:**
```
composer lint          → PASS, 316 files
composer stan           → No errors, 208 files
composer compat-check   → exit 0, keine Ausgabe (kein 8.3/8.4-Verstoß)
```

**Frontend:**
```
npx vitest run   → 25 Testdateien, 308 Tests, alle grün
npm run lint      → 0 Fehler, 3179 Warnings
npm run build     → vue-tsc -b + vite build erfolgreich, keine Typ-/Buildfehler
```

**Abweichung von den Notes — dokumentiert, nicht blockierend:** T08s
Notes behaupten "unveränderte Bestandscode-Baseline, keine neue Kategorie"
für die Lint-Warnings. Eigener Vergleich (`git stash` gegen den Stand vor
diesem Change, identischer `npm run lint`-Lauf) zeigt **3167 Warnings vor,
3179 nach** diesem Change — 12 zusätzliche Warnings, alle in den neuen/
geänderten Dateien (`InvoicePaymentDialog.vue`, `InvoiceDetailModal.vue`,
`InvoicesView.vue`). Es handelt sich ausschließlich um bereits im gesamten
Bestand massenhaft vorkommende Regelkategorien (`vue/max-attributes-per-line`,
`vue/attributes-order`, `@typescript-eslint/no-explicit-any`) im selben
Stil wie das Vorbild `InvoiceSendDialog.vue` — keine neue Regelkategorie,
0 Fehler weiterhin. Da CLAUDE.md/`TESTING.md` nur "0 Fehler" als hartes
Gate definieren und Warnings projektweit toleriert werden (3167 Bestand),
wird dies als **Sollte/Dokumentationsungenauigkeit** eingestuft, nicht als
Muss-Befund. Empfehlung: T08-Notes-Formulierung in künftigen Changes
präziser fassen ("keine neue Warn-Kategorie" statt "unveränderte
Baseline").

## 6. Review-Befunde — Status

- **Muss** (TOCTOU-Race): behoben, siehe Abschnitt 2. ✅
- **Sollte** (Merge-Koordination PR #89): dokumentiert, siehe Abschnitt 4,
  nicht blockierend. ⚠ offen für Merge-Zeitpunkt.
- **Sollte** (`concurrency`-Testgruppe fehlt in `TESTING.md` Abschnitt 7.1):
  **nicht behoben** — `grep -n "concurrency" TESTING.md` liefert keinen
  Treffer. Nicht blockierend (Sollte), aber offener Punkt für einen
  kleinen Folge-Change oder eine direkte Ergänzung vor dem nächsten
  Concurrency-Test-Bedarf.
- **Sollte** (`store()`-Methode wächst, `assertPayable()`-Extraktion):
  bewusst zurückgestellt laut `task-fix-overpayment-race.notes.md`,
  nachvollziehbar begründet (Scope-Disziplin). Nicht blockierend.
- **Könnte** (`PAYABLE_STATUSES`-Dreifach-Duplikation): dokumentiert,
  verifiziert wertgleich, kein Drift. Empfehlung für kleinen Folge-Change
  (zentrale Frontend-Konstante) bleibt bestehen.
- **Könnte** (PayPal-Webhook Doppel-Präfix-Route): siehe Abschnitt 7.

## 7. Zusätzlicher, potenziell produktionskritischer Fund (aus T03-Notes/Review übernommen und selbst verifiziert)

`backend/routes/api.php:73` registriert
`POST /api/v1/payments/paypal/webhook` **explizit**, während
`backend/bootstrap/app.php:13` (`withRouting(api: __DIR__.'/../routes/api.php', ...)`)
`routes/api.php` bereits automatisch unter dem Laravel-Standardpräfix
`api` mountet. Dadurch ist die Route tatsächlich unter
`api/api/v1/payments/paypal/webhook` erreichbar — der von PayPal
konfigurierte, dokumentierte Pfad (`/api/v1/payments/paypal/webhook`, ohne
doppeltes Präfix) liefert vermutlich 404. Diese Route wird von diesem
Change **nicht berührt** (vorbestehend), der neue Test in
`InvoicePaymentApiTest.php` testet laut Review korrekt gegen den
tatsächlich registrierten (kaputten) Pfad. Ich habe die doppelte
`api`-Bindung selbst gegen `bootstrap/app.php:13` nachvollzogen — der Fund
ist korrekt. **Dies ist noch nicht andernorts als eigener Change erfasst**
(kein `openspec/changes/*paypal*webhook*` oder ähnliches im Repo
gefunden) und sollte als eigenständiger, dringender Fix-Change
vorgeschlagen werden, sobald PayPal-Zahlungen produktiv genutzt werden —
unabhängig von Change 3/4 des Rechnungsworkflow-Umbaus.

## Erfüllt

- Alle 8 Tasks (T01–T08) vollständig, Akzeptanzkriterien im Code verifiziert.
- TOCTOU-Muss-Befund aus dem Review nachweislich behoben, inkl. echtem
  Zwei-Prozess-Beweis gegen PostgreSQL.
- Alle vier bindenden Gate-1-Entscheidungen im Code umgesetzt.
- Backend-QA (Pest gegen SQLite und echtes PostgreSQL, lint, stan,
  compat-check) grün, selbst ausgeführt.
- Frontend-QA (Vitest, Lint mit 0 Fehlern, Build) grün, selbst ausgeführt.
- `openspec validate --strict` erfolgreich.
- PR-#89-Status geprüft: offen, kein aktueller Blocker, Koordinationspunkt
  dokumentiert.

## Offen / Nacharbeit

Keine blockierende Nacharbeit für diesen Change. Für Folge-Changes/
User-Entscheidung vorgemerkt:

- Merge-Reihenfolge/-Prüfung PR #89 ↔ `feature/add-invoice-payment-entry`
  bezüglich `PaymentPolicy.php`/`PaymentApiTest.php` (Abschnitt 4).
- `TESTING.md` Abschnitt 7.1 um die Testgruppe `concurrency`
  (`tests/Concurrency/`) ergänzen (Reviewer-Sollte-Befund, nicht erledigt).
- Kleiner Refactoring-Folge-Change: zentrale Frontend-Konstante für
  `PAYABLE_STATUSES` statt Dreifach-Duplikation.
- Eigenständiger, produktionskritischer Fix-Change: PayPal-Webhook-Route
  doppeltes `api/api/v1/...`-Präfix (Abschnitt 7) — bislang nicht als
  eigener Change erfasst, sollte vor produktivem PayPal-Einsatz behoben
  werden.
- Bekannte, seit Change 1 dokumentierte Lücke: kein
  `docker-compose.mysql.yml` im Repo (MySQL-Verifikation lief laut
  `task-T08.notes.md` über einen Ad-hoc-Container) — weiterhin offen,
  eigener Change wert.
- Kleinere Dokumentationsungenauigkeit in `task-T08.notes.md` zu den
  Lint-Warnings (Abschnitt 5) — kein Code-Nacharbeit nötig.

## Empfehlung an den User

Change 3 von 4 ist inhaltlich und qualitativ abnahmereif: der einzige
Muss-Befund ist im Code nachweislich behoben, alle vier Gate-1-Entscheidungen
sind korrekt umgesetzt, und sämtliche QA-Läufe (inkl. echtem PostgreSQL-
Concurrency-Beweis) sind grün. Freigabe für User-Gate 2 empfohlen; die
oben gelisteten offenen Punkte sind für spätere, unabhängige Changes
vorzumerken, nicht für diesen Change.
