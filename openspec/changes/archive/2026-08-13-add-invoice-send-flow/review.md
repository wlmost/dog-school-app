# Review: add-invoice-send-flow (T01–T06, gesamter Branch-Diff)

**Gesamtempfehlung:** nacharbeit-nötig

Geprüft: `git diff main` gegen den Arbeitsstand von `feature/add-invoice-send-flow`
(Änderungen sind noch nicht committet — Working-Tree-Diff, keine
Commit-Historie auf dem Branch). `TESTING.md` wurde vorab gelesen, da der
Diff neue Testdateien enthält.

## Muss (blockiert Abnahme)

- **[Korrektheit]** `frontend/src/views/invoices/InvoicesView.vue:290-298`:
  `InvoiceFormModal`, `InvoiceDetailModal` und `InvoiceSendDialog` teilen
  sich denselben `selectedInvoice`-Ref (Bindings Zeile 122, 129, 143).
  `design.md` Decision D8 sieht explizit vor, dass der `InvoiceSendDialog`
  **über** dem bereits geöffneten `InvoiceDetailModal` geöffnet wird (Klick
  auf "Senden" im Detail-Modal → `send`-Event → `openSendDialog()`, beide
  Modals danach gleichzeitig offen). `closeSendDialog()` (Zeile 295-298)
  setzt aber unbedingt `selectedInvoice.value = null`, ohne zu prüfen, ob
  `showDetailModal` noch `true` ist. Reproduktion: Detail-Modal öffnen →
  "Senden" klicken (Send-Dialog öffnet sich darüber) → "Aus der App
  versenden" klicken → bei Erfolg ruft `sendInvoiceEmail()`
  (Zeile 394-404) `closeSendDialog()` auf → `selectedInvoice` wird `null`,
  während `showDetailModal` weiterhin `true` bleibt. `InvoiceDetailModal.vue`
  hat zwar ein `v-if="invoice"`-Guard (Zeile 36) und stürzt daher nicht ab,
  aber der Nutzer sieht danach ein leeres, wirkungslos wirkendes
  Detail-Modal (nur Titel + Schließen-Button, keine Rechnungsdaten mehr) —
  direkt nach einer erfolgreichen Aktion. Kein bestehender Test deckt dieses
  Szenario ab, weil `InvoiceDetailModal` in `InvoicesView.test.ts` als Stub
  eingebunden ist, der das `isOpen`/`v-if`-Verhalten nicht nachbildet.
  Vorschlag: `closeSendDialog()` darf `selectedInvoice` nur dann auf `null`
  setzen, wenn kein anderes Modal (`showDetailModal`, `showFormModal`) noch
  offen ist, oder — sauberer — jedes Modal bekommt sein eigenes
  `selectedInvoice`-Pendant bzw. der Send-Dialog wird nicht über den
  gemeinsamen Ref, sondern rein lokal befüllt.

- **[Spec-Konformität/Korrektheit]** `frontend/src/utils/errorHandler.ts:53-56`
  in Kombination mit `frontend/src/views/invoices/InvoicesView.vue:399-403`:
  Ein `502`-Fehler von `POST /invoices/{id}/send-email`
  (`backend/app/Http/Controllers/Api/InvoiceController.php:458-460`, Nachricht
  "Die Rechnung konnte nicht per E-Mail versendet werden. Bitte laden Sie das
  PDF herunter und versenden Sie es manuell.") fällt in
  `handleApiError()` in den generischen `status >= 500`-Zweig
  (`errorHandler.ts:53-56`), der **vor** dem `data.message`-Zweig
  (`errorHandler.ts:58-60`) `return`-t. Der Nutzer sieht also nur "Server-Fehler:
  Ein interner Fehler ist aufgetreten. Bitte versuchen Sie es später erneut" —
  der vom Backend extra formulierte Fallback-Hinweis auf den manuellen
  Download geht verloren. Das widerspricht direkt dem in `design.md`
  (Goals, Decision D4) formulierten Ziel: "Ein fehlgeschlagener App-Mail-Versand
  wird dem Bedienenden unmittelbar und verständlich zurückgemeldet, mit
  klarem Hinweis auf den Fallback-Weg (manueller Download)." Kein Test deckt
  das ab: der einzige 502-nahe Test in `InvoicesView.test.ts`
  ("zeigt bei fehlgeschlagenem Versand einen Fehler-Toast …", Zeile
  ~1126-1136) mockt nur ein generisches `new Error('fail')`, keinen
  axios-502-Response mit `data.message`. Vorschlag: entweder
  `handleApiError()` so anpassen, dass `data.message` auch bei `status >= 500`
  bevorzugt angezeigt wird (falls vorhanden), oder `sendInvoiceEmail()`
  im Catch-Block das `error.response?.data?.message` gezielt selbst
  auslesen und anzeigen, bevor auf den generischen Fallback zurückgefallen
  wird.

- **[Testkonventionen, TESTING.md]** `backend/tests/Feature/InvoiceSendEmailTest.php`
  (neue Datei, T03) verstößt mehrfach gegen die verbindlichen
  Test-Konventionen für **neue** Tests:
  - Verwendet durchgängig `test('…', function () {…})` (7 Vorkommen, z. B.
    Zeile 24, 39, 51, 66, 82, 96, 106) statt `it('…', …)`. `TESTING.md` §9
    listet das explizit als verboten ("`test('...', …)` statt
    `it('...', …)` für neue Tests verwenden"), §2.1 verlangt zusätzlich
    deutsche, dritte-Person-Indikativ-Formulierungen — die Beschreibungen
    sind hier durchgehend Englisch ("admin can send an invoice email …").
  - `beforeEach()` (Zeile 15-17) nutzt `User::factory()->create(['role' =>
    'admin'])` / `'trainer'` / `'customer'` — genau das in `TESTING.md`
    §3.1 als "Falsch (nicht mehr verwenden)" markierte Magic-String-Muster,
    obwohl `UserFactory` bereits die vorgeschriebenen States besitzt
    (`backend/database/factories/UserFactory.php:54,64,74`:
    `admin()`/`trainer()`/`customer()`), die im Nachbar-Test
    `InvoicePdfTest.php` korrekt verwendet werden
    (`User::factory()->admin()->create()`).
  Beide Verstöße gemeinsam sind laut `TESTING.md` §10 mindestens
  "Muss"-Niveau ("bei mehreren Fehlschlägen Muss-Befund"). Die inhaltliche
  Testabdeckung selbst ist gut (alle Akzeptanzkriterien aus `tasks.md` T03
  sind abgedeckt), nur Stil/Konvention müssen nachgezogen werden.

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Robustheit/Shared-Hosting]** `backend/config/mail.php:48` setzt
  `'timeout' => null` für den SMTP-Mailer — kein Zeitlimit konfiguriert.
  `design.md` (Decision D4, Risks) akzeptiert den synchronen, blockierenden
  Mailversand bewusst als Trade-off; das ist eine getroffene
  Architektur-Entscheidung und wird hier nicht infrage gestellt. Da
  CLAUDE.md Abschnitt 4.3 aber explizit vor Long-Running-Blockierungen auf
  Shared Hosting warnt, wäre ein knapper, expliziter SMTP-Timeout (z. B.
  `env('MAIL_TIMEOUT', 10)`) eine günstige zusätzliche Absicherung gegen
  einen unbegrenzt hängenden PHP-FPM-Worker, ohne die grundsätzliche
  Sync-Entscheidung zu ändern. Kein Blocker, da das Risiko bereits bewusst
  dokumentiert und vom User akzeptiert wurde — aber eine naheliegende,
  kostengünstige Härtung, die noch nicht umgesetzt ist.

## Könnte (optional, Verbesserung)

- **[Konsistenz]** `backend/tests/Feature/InvoiceSendEmailTest.php` führt
  reine HTTP-Endpunkt-Tests (`postJson`) aus, liegt aber unter
  `tests/Feature/` mit Gruppe `feature` statt gemäß `TESTING.md` §7.1-Schema
  unter `tests/Feature/Api/` mit Gruppe `api`. Folgt damit dem bestehenden
  (selbst nicht schema-konformen) Präzedenzfall `InvoiceApiTest.php`/
  `InvoicePdfTest.php` im selben Verzeichnis — keine neue Abweichung, aber
  auch keine Verbesserung. Bei Gelegenheit (Boy-Scout) könnte die gesamte
  Invoice-HTTP-Testfamilie nach `tests/Feature/Api/` migriert werden;
  eigener, kleiner Change, kein Blocker für diesen.
- **[Stil]** `backend/app/Services/InvoicePdfRenderer.php:8` importiert
  `App\Http\Controllers\Api\InvoiceController` ausschließlich für eine
  `@see`-Doc-Referenz (Zeile 16). Ungewöhnliche Abhängigkeitsrichtung
  (Service → Controller), rein dokumentarisch und ohne Laufzeitwirkung —
  optisch etwas irritierend für die Schichtentrennung, aber keine
  funktionale Kopplung.
- **[Stacking]** `InvoiceSendDialog.vue:3` und `InvoiceDetailModal.vue:3`
  verwenden beide `class="relative z-50"` für den `<Dialog>`-Root. Wenn
  beide gleichzeitig offen sind (design.md D8, der vorgesehene Fall
  "Send-Dialog über Detail-Modal"), entscheidet aktuell nur die
  DOM-Reihenfolge (Send-Dialog steht im Template nach dem Detail-Modal,
  Zeile 127-147) darüber, dass er optisch obenauf liegt — funktioniert
  zufällig, ist aber nicht durch unterschiedliche z-Index-Werte
  abgesichert.

## Lob

- **T01 (Umbenennung):** Außergewöhnlich gründlich — der Diff belegt, dass
  wirklich **alle** Referenzen auf `InvoiceWasCreated`/
  `SendInvoiceCreatedEmail`/`InvoiceCreated` verschwunden sind (verifiziert
  per `grep -rn` über `backend/app`, `backend/tests`, `backend/resources`,
  `backend/routes`, `backend/config` — 0 Treffer), inklusive dreier
  Bestandsdateien außerhalb der ursprünglichen Task-Liste (Console-Commands,
  `phpstan-baseline.neon`), die sonst `composer stan`/`composer test`
  gebrochen hätten. Sauber im `task-T01.notes.md` begründet und
  nachvollziehbar dokumentiert, einschließlich der bewusst unangetasteten
  Alt-Referenz `InvoiceCreatedMail` (nicht Teil dieser Umbenennung).
- **T02 (`InvoicePdfRenderer`):** Die Entscheidung für
  `app(InvoicePdfRenderer::class)` statt Constructor-Injection in
  `InvoiceSent::attachments()` ist empirisch statt nur behauptet abgesichert
  — alle sechs `new InvoiceSent(...)`-Aufrufstellen
  (`SendInvoiceEmail.php:34`, `SendTestEmail.php:130`, fünf Testfälle in
  `InvoiceSentMailBankDetailsTest.php`) laufen tatsächlich außerhalb des
  Containers, eine verpflichtende Constructor-Injection hätte sie alle
  gebrochen.
- **[Korrektheit]** `sendEmail()` (`InvoiceController.php:432-466`) ändert
  den Rechnungsstatus nachweislich nicht (kein `$invoice->update(...)`),
  der Policy-Split (`InvoicePolicy::send()`, rollen-only) ist konsistent
  mit dem etablierten `finalize()`/`markAsPaid()`-Muster, und die 422-/
  502-Fehlerpfade sind sauber mit sprechenden deutschen Nachrichten
  getrennt.

## Weitere geprüfte Punkte ohne Befund

- PHP-8.2-Kompatibilität (CLAUDE.md 4.1): kein Treffer auf verbotene
  8.3-/8.4-Konstrukte in den neuen/geänderten Dateien, `declare(strict_types=1);`
  überall vorhanden.
- Autorisierung/IDOR: `InvoicePolicy::send()` rollen-only, Kunden erhalten
  403 (durch `InvoiceSendEmailTest.php` abgedeckt); kein IDOR-Vektor über
  die Route, konsistent mit dem bestehenden Admin/Trainer-Vollzugriffsmuster
  auf Rechnungen.
- Vue-Konventionen: `InvoiceSendDialog.vue` nutzt `<script setup lang="ts">`,
  PascalCase-Dateiname, `defineProps`/`defineEmits` mit Typen — konform.
- 422-Fall "keine E-Mail-Adresse": wird über `handleApiError()` korrekt via
  `data.message`-Zweig angezeigt (kein 502-artiges Verschlucken, da `422 < 500`).
- Kein neuer Statuswechsel, keine DB-Schreiboperation, keine
  Retry-/Transaktionslogik in `sendEmail()` — Punkt 8 der Prüfliste
  (Postgres-Aborted-Transaction-Risiko aus Change 1) ist damit nicht
  einschlägig.
