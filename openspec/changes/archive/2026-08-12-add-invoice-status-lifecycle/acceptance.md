# Abnahme: add-invoice-status-lifecycle

**Status:** bereit-für-user-review

**Change 1 von 4** im Rechnungsworkflow-Umbau (`add-invoice-status-lifecycle`
→ `add-invoice-send-flow` → `add-invoice-payment-entry` →
`add-invoice-dunning-dashboard`).

## Strukturelle Prüfung

`openspec validate add-invoice-status-lifecycle --strict` → `Change
'add-invoice-status-lifecycle' is valid`.

## Vollständigkeit der Tasks

Alle Akzeptanzkriterien in `tasks.md` (T01–T09) sind mit `[x]` abgehakt, mit
einer Ausnahme: `tasks.md:202` (T03, Concurrency-Kriterium
"Zwei parallele Aufrufe … erzeugen keine doppelte Nummer") ist bewusst
unchecked geblieben, weil `InvoiceNumberGenerator::generate()` allein
(`lockForUpdate()` auf einer potenziell leeren Ergebnismenge) diese
Garantie nicht geben kann — dokumentiert in `task-T03.notes.md` als
"Wichtiger Befund". Das Kriterium wird **funktional** durch die in T04/T05
nachgezogene Retry-on-Conflict-Logik in `InvoiceController::finalize()`
(verifiziert: `backend/app/Http/Controllers/Api/InvoiceController.php`,
Diff-Ausschnitt Zeile ~126–180, `FINALIZE_MAX_ATTEMPTS`) und `cancel()`
erfüllt, plus einem eigenen, ungemockten Race-Condition-Test in
`backend/tests/Feature/Services/InvoiceNumberGeneratorTest.php` (siehe
`task-review.test-report.md`). Der offene Checkbox-Zustand ist damit korrekt
dokumentierte Historie, keine offene Lücke — akzeptiert.

Die drei Nacharbeits-Tasks (`task-fix-overdue-cancel-button.notes.md`,
`task-fix-markaspaid-draft.notes.md`,
`task-fix-markaspaid-draft-button.notes.md`) sind vollständig, mit
QA-Nachweis pro Fix.

## Verifikation der zwei Muss-Befunde aus `change-review.md`

Beide im Code (nicht nur laut Notes) verifiziert:

1. **`CANCELLABLE_STATUSES` ohne `'overdue'`.** Bestätigt per `grep`:
   `frontend/src/views/invoices/InvoicesView.vue:229` und
   `frontend/src/components/InvoiceDetailModal.vue:221` →
   `const CANCELLABLE_STATUSES = ['sent', 'reminded', 'paid']` — deckungsgleich
   mit `InvoicePolicy::cancel()`
   (`backend/app/Policies/InvoicePolicy.php`, Diff bestätigt:
   `in_array($invoice->status, ['sent', 'paid', 'reminded'], true)`).
2. **`markAsPaid()` lehnt `draft` ab.** Bestätigt per `git diff main --
   backend/app/Http/Controllers/Api/InvoiceController.php`: neue Prüfung
   `if ($invoice->status === 'draft') { return response()->json([...], 422); }`
   direkt vor der bestehenden `isPaid()`-Prüfung in `markAsPaid()`.
   Frontend-Teil bestätigt: `InvoiceDetailModal.vue:237`
   `function canMarkAsPaid(invoice) { return !authStore.isCustomer &&
   invoice.status === 'sent' }`, Button nutzt `v-if="canMarkAsPaid(invoice)"`
   (Zeile 163) — `draft` ist aus der sichtbaren Menge entfernt.

Beide Fixes sind über eigene, gezielte Regressionstests abgesichert
(`InvoiceApiTest.php::'cannot mark a draft invoice as paid'`,
`InvoicesView.test.ts`/`InvoiceDetailModal.test.ts` Blöcke zu `overdue` +
`draft`).

## Diff-Konsistenz gegen Proposal/Design/Tasks

`git diff main --stat` zeigt exakt die in `proposal.md` Abschnitt "Impact"
gelisteten Backend-Dateien (`Invoice.php`, `StoreInvoiceRequest.php`,
`UpdateInvoiceRequest.php`, `InvoiceController.php`, `InvoicePolicy.php`,
`InvoiceResource.php`, `routes/api.php`) sowie die drei Frontend-Dateien
(`InvoicesView.vue`, `InvoiceDetailModal.vue`, `InvoiceFormModal.vue`) plus
die vier neuen Migrationen, `InvoiceDunning`-Model/-Factory und
`InvoiceNumberGenerator`-Service als neue, unstaged Dateien — keine
unerwarteten Änderungen (z. B. bleibt `pdf/invoice.blade.php` unangetastet,
konsistent mit dem dokumentierten Non-Goal). Migrations-Reihenfolge M1→M2→M3→M4
entspricht `design.md`.

**Hinweis Commit-Historie:** Der Feature-Branch `feature/add-invoice-status-lifecycle`
enthält bislang keine Commits über die Merge-Historie von `main` hinaus —
alle Änderungen liegen als unstaged Working-Tree-Diff vor. Das widerspricht
dem in `WORKFLOW.md` Schritt 8/10 vorgesehenen Muster ("Commit nach jeder
Task"). Fachlich blockiert das die Abnahme nicht (Diff ist vollständig
prüfbar), sollte aber vor Archivierung (Schritt 13) nachgeholt werden, damit
die Task-Historie im Git-Log nachvollziehbar bleibt.

## Prüfung der nicht-blockierenden Review-Befunde

Beide "Sollte"-Punkte sind zu Recht nicht als "Muss" eingestuft:

- **`InvoiceResource`: `remindedAt`/`dunningLevel` ohne `whenLoaded()`-Schutz
  in `markAsPaid()`.** Führt im schlimmsten Fall zu einem zusätzlichen
  Lazy-Load pro Einzelantwort — keine Fehlfunktion, kein Sicherheitsproblem,
  kein N+1 in Listen (nur Einzelressource betroffen). Akzeptabel für Change 1,
  guter Kandidat für einen kleinen Folge-Fix.
- **Fehlende `uses()->group(...)` in erweiterten (nicht neuen) Testdateien.**
  Laut `TESTING.md`-Kopfzeile ("Bestand wird nicht rückwirkend angepasst")
  korrekt nicht nachgezogen; alle drei neuen Testdateien dieses Change sind
  vollständig TESTING.md-konform. Kein Blocker.

Die "Könnte"-Punkte (DRY-Extraktion der Retry-Schleife, toter
Verteidigungscode in `destroy()`) sind reine Verbesserungsvorschläge ohne
funktionalen Bezug — zu Recht nicht blockierend, für Change 2/3 vermerkt.

## Eigene QA-Ausführung (nicht nur Notes vertraut)

- `docker compose exec php composer qa` → Exit 0. Pint: keine Style-Issues
  (307 Dateien). PHPStan: `[OK] No errors`. PHPCompatibility (testVersion
  8.2): keine Verstöße. Pest: **813 passed (2553 assertions)**, Duration
  29.18s.
- `docker compose exec node sh -c "npm run lint"` → Exit 0, **0 Errors**,
  3092 Warnings (identische Bestandscode-Baseline, keine neuen Fehler).
- `docker compose exec node sh -c "npx vitest run"` → Exit 0, **22 Testdateien,
  249 Tests, alle grün**.
- `docker compose exec node sh -c "npm run build"` → Exit 0, `vue-tsc -b`
  + `vite build` erfolgreich, keine TS-Fehler.

Alle vier Läufe wurden in dieser Sitzung frisch ausgeführt (nicht aus
Notes übernommen) und bestätigen den in `task-review.test-report.md`
gemeldeten Zustand.

## Abgleich bindende User-Entscheidungen (2026-08-12)

1. **Rechnungsnummer fest erst bei Entwurf → Offen.** Umgesetzt via
   `InvoiceController::finalize()` + `InvoiceNumberGenerator` (T03/T04),
   verifiziert: `POST /invoices/{id}/finalize` vergibt die Nummer, `store()`
   vergibt keine mehr (`StoreInvoiceRequest` setzt `status` fest auf
   `draft`, keine `invoice_number`).
2. **Storno als vollwertiges Korrekturdokument.** Umgesetzt via
   `InvoiceController::cancel()` (T05): eigene `Invoice` mit eigener
   Nummer, negierte `InvoiceItem`-Positionen, Original wird `cancelled`,
   `original_invoice_id`-Verknüpfung. Spec-Deltas in
   `specs/invoice-cancellation/spec.md` decken alle Szenarien ab
   (inkl. Storno-von-Storno-Sperre, Kunde darf nicht stornieren).
3. **Mahnstufen mehrstufig mit Gebühren — nur Datenmodell.** Umgesetzt via
   `invoice_dunnings`-Tabelle + `InvoiceDunning`-Model (T01), keine
   Trigger-Logik — wie vereinbart Scope von Change 4.
4. **Auto-Mailing entfernt.** `InvoiceWasCreated::dispatch()` aus `store()`
   entfernt (T03), Event/Listener/Mailable bleiben für Change 2 bestehen —
   bestätigt per `Mail::assertNothingSent()`-Tests.
5. **Überfällig weiterhin zur Anzeigezeit berechnet, kein Cron.** Bestätigt:
   keine neue Scheduler-/Cron-Logik in diesem Change, `isOverdue` bleibt
   datumsbasiertes Attribut.
6. **Teilzahlungen unterstützt.** Bestehendes `Payment`-Modell unverändert
   nutzbar, `getTotalPaidAttribute()`/`getRemainingBalanceAttribute()` nicht
   angetastet — Statusmodell kompatibel, UI folgt in Change 3.

**User-Gate-1-Zusatzentscheidungen:**
- Stornorechnung für Kunden sichtbar → umgesetzt (Storno wird mit
  `status = 'sent'` angelegt, das für Kunden sichtbar ist; End-to-End durch
  Tester-Test bestätigt, siehe `task-review.test-report.md`).
- "Freigeben"-Button eingeführt → umgesetzt in `InvoicesView.vue`/
  `InvoiceDetailModal.vue` (T07/T08), löst ausschließlich `finalize()` aus,
  kein Mailversand.

## Erfüllt

- Beide Muss-Befunde aus `change-review.md` sind im Code nachweislich
  behoben, mit Regressionstests abgesichert.
- Alle 9 Kern-Tasks plus 3 Nacharbeits-Fixes vollständig, Diff deckt sich
  mit Proposal/Design/Tasks, keine unerwarteten Änderungen.
- `openspec validate --strict` grün.
- Backend- und Frontend-QA in dieser Sitzung eigenständig grün bestätigt
  (nicht nur den Notes vertraut).
- Alle sechs bindenden User-Entscheidungen sowie die beiden
  User-Gate-1-Zusatzentscheidungen sind nachvollziehbar umgesetzt.
- Der deaktivierte "Senden"-Button-Stub in `sent`/`reminded`/`overdue` ist
  erwartungsgemäß vorhanden (kein Klick-Handler) — das ist laut `design.md`
  Decision D1 und den Non-Goals **bewusst** so und wird in Change 2
  (`add-invoice-send-flow`) mit echter Logik verdrahtet.

## Offen / Nacharbeit (nicht blockierend für dieses Change, für User-Gate 2 zur Kenntnis)

- **Commit-Historie fehlt.** Der Feature-Branch hat noch keine Commits
  (alles unstaged). Vor Schritt 13 (`openspec archive`) sollten die
  Änderungen gemäß `WORKFLOW.md` in nachvollziehbare Commits pro
  Task/Fix aufgeteilt werden (oder mindestens ein sinnvoller
  Gesamt-Commit je Kategorie), damit die PR-Historie brauchbar bleibt.
- **`InvoiceResource`-`whenLoaded()`-Inkonsistenz** bei `markAsPaid()`
  (Sollte-Befund) — kein Blocker, Kandidat für kleinen Folge-Fix.
- **Fehlende Test-Groups** in erweiterten Bestands-Testdateien (Sollte-
  Befund) — laut `TESTING.md` vertretbar, kein Blocker.
- **DRY-Duplikation der Retry-Schleife** zwischen `finalize()`/`cancel()`
  (Könnte-Befund) — für Change 2/3 als Extraktionskandidat vermerkt.
- **PDF-Layout für Stornorechnungen** ohne "Stornorechnung"-Kennzeichnung
  — explizit als Non-Goal/offene Frage 3 in `proposal.md` dokumentiert,
  ggf. eigener kleiner Folge-Change falls buchhalterisch nötig.

## Empfehlung an den User

Der Change ist inhaltlich vollständig, spec-konform und durch eigene
QA-Läufe bestätigt — beide Muss-Befunde sind im Code sauber behoben. Vor
der Archivierung (Schritt 13) sollte lediglich die Commit-Historie auf dem
Feature-Branch nachgeholt werden; das ist ein rein prozeduraler Punkt ohne
Bezug zur fachlichen Korrektheit. Empfehlung: **Freigabe für User-Gate 2**.
