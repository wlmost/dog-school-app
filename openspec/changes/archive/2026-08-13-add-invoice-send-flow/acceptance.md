# Abnahme: add-invoice-send-flow

**Status:** bereit-für-user-review

Change 2 von 4 im Rechnungsworkflow-Umbau. Change 1
(`add-invoice-status-lifecycle`) ist bereits auf `main` gemergt
(`72f9e43`). Dieser Change baut den Senden-Dialog auf dem in Change 1
geschaffenen Status-Lebenszyklus auf.

## 0. Strukturelle Validität

`openspec validate add-invoice-send-flow --strict` → **"Change
'add-invoice-send-flow' is valid"**.

## 1. Vollständigkeit der Tasks

Alle sechs Tasks (T01–T06) in `tasks.md` sind vollständig abgehakt (`[x]`
auf allen Akzeptanzkriterien). Zusätzlich wurden zwei Nacharbeits-Batches
durchgeführt (`task-fix-review-findings-backend.notes.md`,
`task-fix-review-findings-frontend.notes.md`) sowie ein direkt vom
Orchestrator angewendeter Fix (dazu unten mehr). Der Diff gegen `main`
(`git status --short`) deckt sich mit dem in `proposal.md` Abschnitt
"Impact" angekündigten Dateikatalog: Umbenennung
`InvoiceWasCreated`→`InvoiceWasSent`, `SendInvoiceCreatedEmail`→
`SendInvoiceEmail`, `InvoiceCreated`→`InvoiceSent` (inkl. Blade-View und
Testdatei), neuer `InvoicePdfRenderer`-Service, neue Route/Policy/
Controller-Methode `sendEmail()`, neue Komponente `InvoiceSendDialog.vue`,
Anpassungen an `InvoicesView.vue`/`InvoiceDetailModal.vue`. Keine
unerwarteten Dateien im Diff, keine Abweichung vom geplanten Scope.

## 2. Spec-Konformität

- `specs/invoice-send-flow/spec.md` (neue Capability) und das Delta in
  `specs/invoice-status-lifecycle/spec.md` sind vorhanden.
- Stichprobe gegen den Diff: `InvoiceController::sendEmail()` (verifiziert
  in `backend/app/Http/Controllers/Api/InvoiceController.php`) setzt
  keinen Statuswechsel, prüft `SENDABLE_STATUSES`, prüft
  Kunden-E-Mail-Präsenz, fängt `\Throwable` und liefert 502 mit
  Fallback-Hinweis — deckungsgleich mit `design.md` Decision D4/D7 und dem
  Code-Beispiel in `tasks.md` T03.
- `InvoiceSendDialog.vue` zeigt laut `InvoiceSendDialog.test.ts` (8 grüne
  Tests) immer beide Optionen (kein `hasEmail`-Zweig) — konform zu
  User-Gate-1-Entscheidung 4.

## 3. Review-Befunde (`review.md`) — Status

**Alle drei Muss-Befunde verifiziert behoben (im Code selbst geprüft, nicht
nur aus Notes übernommen):**

1. **Geteilter `selectedInvoice`-Ref** — behoben durch eigenen Ref
   `sendDialogInvoice` in `frontend/src/views/invoices/InvoicesView.vue`
   (Zeile 144 Template-Binding, Zeile 179 Ref-Deklaration, Zeilen 297/303
   in `openSendDialog()`/`closeSendDialog()`). `selectedInvoice` bleibt
   ausschließlich Form-/Detail-Modal vorbehalten. Verifiziert per `grep`
   im laufenden Code.
2. **502-Message-Durchreichung** — behoben in
   `frontend/src/utils/errorHandler.ts:57-59`: der `status >= 500`-Zweig
   zeigt jetzt `data.message || <generischer Fallback>` statt
   unbedingt den generischen Text. Mit erklärendem Kommentar zur
   Sicherheitsabwägung (Backend-Messages sind bewusst kurz und
   nutzerfreundlich, keine rohen Exception-Details). Verifiziert per
   direktem Read der Datei.
3. **TESTING.md-Konformität `InvoiceSendEmailTest.php`** — behoben:
   `grep` bestätigt ausschließlich `it('…', …)` (14 Vorkommen, deutsche,
   dritte-Person-Formulierungen) und `User::factory()->admin()/->trainer()
   /->customer()->create()` in `beforeEach()`, kein `test(...)` und keine
   `['role' => '…']`-Magic-Strings mehr.

**Sollte-Befund (SMTP-Timeout) umgesetzt:** `backend/config/mail.php:52`
setzt `'timeout' => env('MAIL_TIMEOUT', 10)` mit erklärendem Kommentar;
`backend/.env.example` ergänzt `MAIL_TIMEOUT=10`. Verifiziert per Read.

**Könnte-Befunde:** dokumentiert, nicht umgesetzt (Testverzeichnis-Schema
`tests/Feature/Api/`, `@see`-Doc-Import-Richtung, `z-index`-Stacking) —
laut CLAUDE.md/Workflow korrekt als nicht-blockierend behandelt.

## 4. Zusätzlicher Fix außerhalb des regulären Task-/Review-Zyklus:
   Doppelter Event-Listener

Der Tester (`task-review.test-report.md`) deckte einen echten, durch T01s
Umstellung auf synchronen Mailversand neu **sichtbar gewordenen** Bug auf:
`App\Listeners\SendInvoiceEmail` war sowohl manuell in
`AppServiceProvider::boot()` per `Event::listen(...)` registriert als auch
über Laravels automatische Event-Discovery erfasst — dadurch wurde bei
jedem Klick auf "Aus der App versenden" die Rechnung **zweimal** real per
E-Mail verschickt (`Mail::assertSent` zählte 4 statt 2 bei zwei Aufrufen).

**Verifiziert im aktuellen Code (nicht nur aus Notes übernommen):**

- `backend/app/Providers/AppServiceProvider.php` enthält keinen manuellen
  `Event::listen(InvoiceWasSent::class, SendInvoiceEmail::class)`-Aufruf
  mehr; stattdessen ein erklärender Kommentar (Zeilen 71-85), der auf
  `task-review.test-report.md` verweist und einen konkreten
  Folge-Change-Namen (`fix-duplicate-event-listener-registration`)
  nennt.
- `docker compose exec php php artisan event:list | grep -A2 InvoiceWasSent`
  (selbst ausgeführt) zeigt genau **einen** Listener-Eintrag
  (`App\Listeners\SendInvoiceEmail@handle`), keine Duplizierung mehr.
- `docker compose exec php vendor/bin/pest --no-coverage --filter=InvoiceSendEmailTest`
  (selbst ausgeführt): 14/14 grün, inkl. `it verschickt bei zweimaligem
  aufruf zwei separate e-mails und antwortet beide male mit 200` — jetzt
  bestätigt genau 2 statt 4 Mails.
- Voller Backend-Testlauf (`vendor/bin/pest --no-coverage`, selbst
  ausgeführt): **830 Tests grün, 2603 Assertions**, u. a.
  `InvoiceSentMailBankDetailsTest`, `SendInvoiceEmailListenerTest`.

Es existiert dafür bewusst keine eigene `task-fix-*.notes.md` (Fix wurde
direkt vom Orchestrator angewendet und verifiziert, kein separater
dev-Agent-Zyklus) — der Fix ist im Diff und per `event:list`/Testlauf
nachvollziehbar, das genügt den Anti-Halluzinations-Anforderungen aus
CLAUDE.md Abschnitt 9.

## 5. Dringender, expliziter Folge-Befund (blockiert diese Abnahme NICHT)

Der Tester hat dokumentiert, dass dasselbe
Doppel-Registrierungsmuster (manuelles `Event::listen()` **und**
automatische Event-Discovery) auch für zwei weitere, von diesem Change
**nicht berührte** Event/Listener-Paare existiert:

- `App\Events\BookingCreated` / `App\Listeners\SendBookingConfirmationEmail`
- `App\Events\UserRegistered` / `App\Listeners\SendWelcomeEmail`

Beide implementieren aktuell noch `ShouldQueue`, wodurch der Effekt (zwei
identische Queue-Jobs statt einem) bisher praktisch unauffällig blieb —
er ist aber real vorhanden und wird bei einer künftigen Umstellung dieser
Listener auf synchronen Versand (oder bei `QUEUE_CONNECTION=sync`) exakt
denselben Doppel-Versand-Effekt erzeugen wie bei
`InvoiceWasSent`/`SendInvoiceEmail` vor dem hier dokumentierten Fix.
Zusätzlich verschärfend: das lokale Docker-`.env` nutzt
`QUEUE_CONNECTION=redis` mit einem dauerhaften Worker-Container
(`dog-school-queue`), was das Zeitfenster für sichtbare Doppel-Jobs in der
Praxis vergrößert.

**Empfehlung:** Ein eigener, dringender Bugfix-Change
(`fix-duplicate-event-listener-registration`, bereits im Kommentar in
`AppServiceProvider.php` vorgemerkt) sollte zeitnah — unabhängig vom
weiteren Rechnungsworkflow-Fortschritt (Change 3/4) — aufgesetzt werden,
um Buchungsbestätigungen und Willkommens-Mails vor demselben
Doppelversand-Risiko zu schützen. Dies betrifft **nicht** Rechnungen und
ist daher explizit **kein** Blocker für die Abnahme von Change 2.

## 6. Bezug zu den sechs Gate-1-Entscheidungen (`proposal.md`)

1. **Kein "zuletzt gesendet am"-Zeitstempel** — umgesetzt: keine neue
   Migration/Spalte im Diff (`git status --short` bestätigt keine
   `database/migrations`-Änderung).
2. **Synchroner statt asynchroner Mailversand** — umgesetzt:
   `SendInvoiceEmail` implementiert kein `ShouldQueue` mehr
   (`SendInvoiceEmailListenerTest.php`, im Backend-Testlauf grün
   bestätigt), `Mail::to(...)->send(...)` statt `->queue(...)`.
3. **PDF als Anhang** — umgesetzt: `InvoiceSent::attachments()` liefert
   den PDF-Anhang über `InvoicePdfRenderer`
   (`InvoiceSentMailBankDetailsTest.php`, grün).
4. **Kein Frontend-`hasEmail`-Zweig (YAGNI)** — umgesetzt:
   `InvoiceSendDialog.vue` zeigt beide Optionen immer, serverseitige
   422-Prüfung bleibt als Defense-in-Depth (`InvoiceSendEmailTest.php`
   deckt beide Seiten ab).
5. **Umbenennung Created→Sent** — umgesetzt und vollständig (T01, per
   Grep verifiziert keine Alt-Referenzen mehr außer einer bewusst
   unangetasteten, unabhängigen Altlast `InvoiceCreatedMail`).
6. **Senden-Dialog für `sent`/`reminded`/`overdue`** — umgesetzt und
   getestet (`InvoiceSendEmailTest.php` deckt alle drei Status ab).

## 7. Selbst durchgeführte QA-Läufe (Architekt, Abnahme-Zeitpunkt)

- `docker compose exec php vendor/bin/pest --no-coverage` → **830 passed
  (2603 assertions)**.
- `docker compose exec php composer lint` → PASS, 310 Dateien.
- `docker compose exec php composer stan` → No errors, 206 Dateien.
- `docker compose exec php composer compat-check` → keine Ausgabe/exit 0
  (keine PHP-8.3/8.4-Verstöße).
- `docker compose exec php php artisan event:list` → `InvoiceWasSent` hat
  genau einen Listener.
- `docker compose exec node sh -lc "npx vitest run"` → **24 Testdateien,
  269 Tests, alle grün** (inkl. `InvoiceSendDialog.test.ts`,
  `errorHandler.test.ts`).
- `docker compose exec node sh -lc "npm run lint"` → 0 Fehler, 3122
  Warnings (ausschließlich bestandsweit vorbestehend, keine neuen
  Verstöße in geänderten Dateien).
- `docker compose exec node sh -lc "npm run build"` → `vue-tsc -b && vite
  build` erfolgreich, keine Fehler.

`composer qa` als aggregiertes Ein-Schritt-Kommando wurde bewusst nicht
verwendet (bekanntes 300s-Prozess-Timeout, siehe
`task-T03.notes.md`/`task-fix-review-findings-backend.notes.md`) —
stattdessen alle vier Phasen einzeln ausgeführt, alle grün, wie im Auftrag
vorgegeben.

## Erfüllt

- Alle sechs Tasks vollständig und akzeptanzkonform umgesetzt.
- Alle drei Muss-Befunde und der Sollte-Befund aus `review.md` im Code
  verifiziert behoben.
- Der vom Tester gefundene Doppelversand-Bug ist behoben und per
  `event:list` sowie grünem Backend-Testlauf (830/830) bestätigt.
- Backend- und Frontend-QA-Suiten laufen vollständig grün (eigenständig
  nachvollzogen, nicht nur aus Notes übernommen).
- `openspec validate --strict` erfolgreich.
- Alle sechs Gate-1-Entscheidungen sind nachweisbar wie beschlossen
  umgesetzt.

## Offen / Nacharbeit

- Keine Nacharbeit an Change 2 selbst nötig.
- **Dringender, eigenständiger Folge-Change empfohlen:**
  `fix-duplicate-event-listener-registration` für
  `BookingCreated`/`SendBookingConfirmationEmail` und
  `UserRegistered`/`SendWelcomeEmail` (siehe Abschnitt 5) — sollte zeitnah
  angestoßen werden, ist aber kein Blocker für dieses User-Gate 2.
- Könnte-Befunde aus `review.md` (Testverzeichnis-Schema,
  `@see`-Import-Richtung, `z-index`-Stacking) bleiben dokumentiert,
  optional für spätere Boy-Scout-Gelegenheiten.

## Empfehlung an den User

Change 2 ist vollständig, spec-konform und durch eigenständig
nachvollzogene QA-Läufe bestätigt — bereit für User-Gate 2. Bitte zusätzlich
kurzfristig den Folge-Bugfix für die doppelte Event-Registrierung bei
Buchungsbestätigungen/Willkommens-Mails einplanen (unabhängig von Change
3/4 im Rechnungsworkflow).
