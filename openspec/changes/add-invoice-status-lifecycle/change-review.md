# Review: add-invoice-status-lifecycle (Change 1 von 4)

**Gesamtempfehlung:** nacharbeit-nötig

Geprüft wurde der komplette Diff `git diff main` auf dem ausgecheckten Branch
`feature/add-invoice-status-lifecycle` (Backend T01–T06, Frontend T07–T09),
gegen `proposal.md`/`design.md`/`tasks.md`/`verification.md` sowie alle
`task-T0X.notes.md`. Migrationen wurden gegen das Präzedenzmuster
(`2026_05_04_110001_add_cancellation_requested_status_to_bookings_table.php`)
verglichen, die Retry/Savepoint-Behauptungen aus T04/T05 wurden gegen den
tatsächlichen `laravel/framework`-Vendor-Code verifiziert (`ManagesTransactions::transaction()`/
`rollBack()`/`performRollBack()`, `Grammar::supportsSavepoints()`). `npm run lint`
wurde lokal gegen den Diff ausgeführt, um die in T08 gemeldete
Warning-Anzahl (3091) und die Warning-Kategorien zu verifizieren.

## Muss (blockiert Abnahme)

- **[Korrektheit/Spec-Konformität]** `frontend/src/views/invoices/InvoicesView.vue:219`
  und `frontend/src/components/InvoiceDetailModal.vue:207`: `CANCELLABLE_STATUSES = ['sent', 'reminded', 'overdue', 'paid']`
  enthält `'overdue'`, aber `InvoicePolicy::cancel()`
  (`backend/app/Policies/InvoicePolicy.php:142-147`) lässt Storno nur für
  `['sent', 'paid', 'reminded']` zu — `overdue` ist dort bewusst **nicht**
  enthalten (ebenso nicht in
  `openspec/changes/add-invoice-status-lifecycle/specs/invoice-cancellation/spec.md:11-56`,
  die ausschließlich `sent`/`paid` als stornierbare Ausgangsstatus nennt).
  Für eine (laut D3 zwar seltene, aber laut Whitelist in `InvoicePolicy::view()`
  weiterhin sichtbare) Rechnung mit `status = 'overdue'` zeigt die UI also
  einen aktiven "Stornieren"-Button, dessen Klick serverseitig mit HTTP 403
  abgewiesen wird (`AuthorizationException`) — der Nutzer bekommt einen
  Fehler ohne erkennbaren Grund. Kein Test deckt diesen Fall ab (weder in
  `InvoicesView.test.ts` noch `InvoiceDetailModal.test.ts` kommt der
  Status `overdue` in Kombination mit dem Stornieren-Button vor). Vorschlag:
  `CANCELLABLE_STATUSES` in beiden Dateien auf `['sent', 'reminded', 'paid']`
  reduzieren (analog zur Policy), oder — falls Storno für `overdue`
  tatsächlich gewollt ist — `InvoicePolicy::cancel()` entsprechend erweitern
  und die Spec-Datei nachziehen. Die Inkonsistenz muss in eine Richtung
  aufgelöst werden, aktuell widersprechen sich UI, Policy und Spec.

- **[Korrektheit]** `backend/app/Http/Controllers/Api/InvoiceController.php:209-224`
  (`markAsPaid()`) und `backend/app/Policies/InvoicePolicy.php:110-126`
  (`markAsPaid()`): Weder Policy noch Controller schließen den Status
  `draft` aus. `InvoicePolicy::markAsPaid()`s eigener Docblock
  (`InvoicePolicy.php:110-121`) argumentiert explizit, dass "marking an
  invoice paid is only meaningful for a non-draft invoice
  (sent/overdue/reminded)" — das wird aber nirgends erzwungen. Gleichzeitig
  zeigt `frontend/src/components/InvoiceDetailModal.vue:163`
  (`v-if="!authStore.isCustomer && (invoice.status === 'draft' || invoice.status === 'sent')"`)
  den Button "Als bezahlt markieren" weiterhin für `draft`-Rechnungen an
  (unverändert aus T08 übernommen, nicht Teil der T07-Entfernung aus
  `InvoicesView.vue`). Ein Admin/Trainer kann also über das Detail-Modal
  einen Entwurf direkt auf `status = 'paid'` setzen, **ohne** je
  `finalize()` durchlaufen zu haben — die Rechnung bleibt dabei dauerhaft
  ohne `invoice_number` (`invoice_number` ist laut T02/T03 nur beim
  Entwurf `NULL` und wird ausschließlich in `finalize()`/`cancel()`
  vergeben). Das verletzt direkt die in
  `openspec/changes/add-invoice-status-lifecycle/specs/invoice-status-lifecycle/spec.md:3-8`
  formulierte Anforderung ("Die invoice_number SHALL ausschließlich beim
  Übergang von draft zu sent … vergeben werden") im Ergebnis: eine bezahlte
  Rechnung ohne Nummer ist steuerlich/fachlich unsinnig (vgl. `proposal.md`
  Zeile 9-13 zur Bedeutung der Nummernvergabe). Kein Test deckt "trainer
  cannot mark a draft invoice as paid" ab (`backend/tests/Feature/InvoiceApiTest.php:391-435`
  testet nur `sent`/`paid` als Ausgangsstatus). Vorschlag: entweder
  `InvoicePolicy::markAsPaid()` um `$invoice->status !== 'draft'`
  ergänzen (mit passendem 403/422) oder den Button in
  `InvoiceDetailModal.vue:163` auf `sent`/`reminded`/`overdue` beschränken
  (analog zur bereits vorhandenen `SENDABLE_STATUSES`-Konstante) —
  idealerweise beides, da Policy/Controller die serverseitige
  Durchsetzung sind (Defense in Depth, wie es dieser Change selbst für
  `view()`/`index()` in D8 fordert).

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Konsistenz]** `backend/app/Http/Resources/InvoiceResource.php:51-56`:
  `remindedAt`/`dunningLevel` greifen unbedingt auf `$this->dunning_level`/
  `$this->reminded_at` zu (nicht per `whenLoaded()` abgesichert wie
  `originalInvoiceNumber`/`cancellationInvoiceNumber` direkt darunter),
  wodurch bei fehlendem Eager-Load der `dunnings`-Relation (z. B.
  `InvoiceController::markAsPaid()`,
  `InvoiceController.php:224` — `fresh(['customer.user', 'items', 'payments'])`
  lädt `dunnings` **nicht** mit, im Gegensatz zu `index()`/`show()`/
  `finalize()`/`cancel()`) pro Response ein zusätzlicher Lazy-Load
  ausgelöst wird. Für Einzelressourcen unkritisch, aber inkonsistent zum
  Muster der übrigen neuen Felder in derselben Methode. Vorschlag: entweder
  `markAsPaid()`s `fresh([...])`-Aufruf um `originalInvoice`,
  `cancellationInvoice`, `dunnings` ergänzen (analog zu `finalize()`/
  `cancel()`, die das bereits tun), oder bewusst dokumentieren, warum
  `markAsPaid()` hiervon ausgenommen ist.

- **[Testbarkeit/Konventionen]** `backend/tests/Feature/InvoiceApiTest.php`
  (457 neue Zeilen) und `backend/tests/Feature/EmailNotificationTest.php`:
  keine der beiden Dateien hat eine `uses()->group(...)`-Zeile
  (`TESTING.md` Abschnitt 7 fordert das "für alle neuen Tests", auch wenn
  Abschnitt 10 den mechanischen Reviewer-Check nur für "neue Dateien"
  formuliert). Da hier keine neue Datei, sondern eine bestehende,
  bereits-`group()`-lose Datei erweitert wurde, ist das nach der
  "Bestand wird nicht rückwirkend angepasst"-Klausel im Kopf von
  `TESTING.md` vertretbar — angesichts des Umfangs (14 neue Tests allein
  in `InvoiceApiTest.php` für `finalize`/`cancel`) wäre eine
  Nachrüstung von `uses()->group('api', 'invoice')` an dieser Stelle aber
  ein sinnvoller Zeitpunkt gewesen (Boy-Scout-Regel). Kein Blocker, da
  `TESTING.md` das nicht zwingend fordert, aber zur Diskussion für
  Architekt/User-Gate 2.

## Könnte (optional, Verbesserung)

- **[Lesbarkeit]** `backend/app/Http/Controllers/Api/InvoiceController.php:283-322`
  (`createCancellationInvoiceWithRetry()`) und die Retry-Schleife in
  `finalize()` (`InvoiceController.php:249-264`) sind strukturell
  identisch (for-Schleife mit `UniqueConstraintViolationException`-Catch,
  Re-Throw beim letzten Versuch). Eine kleine private Hilfsmethode
  `retryOnUniqueConstraintViolation(int $maxAttempts, callable $attempt)`
  würde die Duplikation zwischen `finalize()` und `cancel()` auflösen
  (DRY) — aktuell zweimal fast wortgleicher Code mit zwei separaten
  `*_MAX_ATTEMPTS`-Konstanten, die immer synchron auf `3` gehalten werden
  müssen. Kein Blocker, da beide Stellen gut dokumentiert und getestet
  sind; für Change 2/3 (die laut `design.md` ebenfalls
  `InvoiceNumberGenerator` weiterverwenden könnten) aber ein guter
  Kandidat für eine Extraktion.

- **[Stil]** `backend/app/Http/Controllers/Api/InvoiceController.php:187-193`
  (`destroy()`): Die Prüfung `$invoice->payments()->completed()->exists()`
  ist nach der T06-Verschärfung (`delete()` nur noch für `status === 'draft'`)
  faktisch totes Verteidigungscode, da eine Draft-Rechnung praktisch nie
  abgeschlossene Zahlungen haben sollte. Kein funktionaler Fehler (schadet
  nicht), aber ein Kandidat zur Bereinigung in einem der Folge-Changes,
  falls die Draft-Invariante tatsächlich lückenlos gilt.

## Lob

- Die Retry/Savepoint-Lösung in `cancel()`
  (`InvoiceController.php:283-322`, dokumentiert in `task-T05.notes.md`)
  ist außergewöhnlich sorgfältig hergeleitet: die Behauptung, dass
  verschachteltes `DB::transaction()` unter Postgres via `SAVEPOINT`
  korrekt nur die fehlgeschlagene Ebene zurückrollt, wurde tatsächlich
  gegen den Laravel-Vendor-Code verifiziert (`ManagesTransactions::rollBack()`/
  `performRollBack()`) und hält bei eigener Prüfung stand — kein
  Vermutungscode.
- Die durchgängige, ehrliche Dokumentation abweichender Entscheidungen
  (T03 Concurrency-Lücke, T04 Policy/Controller-Split für 403 vs. 422,
  T06 nachträgliche `markAsPaid()`-Policy, T07 Entfernung des
  "Bezahlt"-Buttons) in den `task-T0X.notes.md`-Dateien macht die Review
  erheblich effizienter und nachvollziehbarer — Abweichungen wurden nicht
  verschwiegen, sondern explizit zur Diskussion gestellt.
- Migrationen (M1–M4) sind sorgfältig treiberspezifisch abgesichert und
  1:1 konsistent mit dem etablierten Repo-Präzedenzfall für
  Enum-Erweiterungen; Spaltenreihenfolge in der SQLite-Rebuild-Migration
  passt exakt zur ursprünglichen `create_invoices_table`-Migration
  (wichtig für die positionsbasierte `INSERT INTO ... SELECT *`-Kopie).
- Kunden-Sichtbarkeit für Stornorechnungen (Punkt 5 der Review-Vorgabe)
  funktioniert wie in `design.md` Decision D5/D8 vorgesehen: Storno wird
  mit `status = 'sent'` angelegt, `InvoicePolicy::view()`/`index()`
  whitelisten `sent` für Kunden — verifiziert durch Code-Lesen von
  `InvoicePolicy.php:29-47`, `InvoiceController.php:64-72` und der
  Cancel-Logik in `InvoiceController.php:283-322`.
