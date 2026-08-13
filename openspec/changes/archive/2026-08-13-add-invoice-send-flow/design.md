## Context

**Ist-Zustand Backend:**

- `backend/app/Http/Controllers/Api/InvoiceController.php:262-294` —
  `finalize()` überführt eine Rechnung von `draft` zu `sent` und vergibt
  die `invoice_number`. **Kein** Mail-Dispatch in dieser Methode (siehe
  Kommentar Zeile 234-235: "No email is dispatched here (see Change 2).").
- `backend/app/Http/Controllers/Api/InvoiceController.php:402-417` —
  `downloadPdf()` lädt `customer.user, items, payments`, prüft
  `$this->authorize('view', $invoice)` und rendert das PDF:
  ```php
  $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
      ->setPaper('a4', 'portrait')
      ->setOption('isHtml5ParserEnabled', true)
      ->setOption('isRemoteEnabled', true);
  return $pdf->download($invoice->invoice_number.'.pdf');
  ```
  Diese Methode ist über `backend/routes/api.php:181`
  (`GET /invoices/{invoice}/pdf`) bereits vollständig funktionsfähig und
  wird von diesem Change **nicht** fachlich verändert, nur intern
  refaktoriert (siehe Decision D6).
- `backend/app/Events/InvoiceWasCreated.php` (24 Zeilen) — einfache
  `Dispatchable`-Event-Klasse mit `public Invoice $invoice`. Seit Change 1
  an keiner Stelle mehr `dispatch()`-t (verifiziert:
  `grep -rn "InvoiceWasCreated::dispatch"` liefert keinen Treffer in
  `backend/app/`).
- `backend/app/Listeners/SendInvoiceCreatedEmail.php` (62 Zeilen) —
  implementiert `ShouldQueue` (Zeile 13), lädt `customer.user, items`
  (Zeile 31-34) und verschickt `Mail::to($event->invoice->customer->user
  ->email)->queue(new InvoiceCreated($event->invoice))` (Zeile 37-38).
  `shouldQueue()` (Zeile 44-48) und `failed()` (Zeile 53-61, loggt über
  `logger()->error()`) sind bereits vorhanden.
- `backend/app/Mail/InvoiceCreated.php` (75 Zeilen) — `Mailable` mit
  `Queueable`. `envelope()` (Zeile 34-49) lädt `company_email`/
  `company_name` über `Cache::remember('email_settings', 3600, fn () =>
  Setting::whereIn('key', ['company_email', 'company_name'])
  ->pluck('value', 'key')->toArray())` (Zeile 35-38) und setzt daraus den
  Absender, mit Fallback auf `env('MAIL_FROM_ADDRESS'/'MAIL_FROM_NAME')`
  falls der jeweilige Setting-Wert fehlt, Betreff `'Rechnung
  '.$this->invoice->invoice_number`. `content()` (Zeile 54-64) rendert
  `emails.invoice-created` mit allen `Setting`-Werten. `attachments()`
  (Zeile 71-74) liefert **aktuell eine leere Liste** — kein PDF-Anhang.
- `backend/resources/views/emails/invoice-created.blade.php` — HTML-Mail
  mit Rechnungsdetails, Positionstabelle, Bankverbindungs-/Zahlungsziel-Block
  (`.payment-info`). Einleitungssatz Zeile 41: "anbei erhalten Sie Ihre
  Rechnung für unsere Dienstleistungen." — passt bereits zu einem
  PDF-Anhang, obwohl aktuell keiner mitgeschickt wird.
- `backend/app/Providers/AppServiceProvider.php:74-87` — registriert
  `Event::listen(InvoiceWasCreated::class, SendInvoiceCreatedEmail::class)`
  neben zwei weiteren, unveränderten Event/Listener-Paaren
  (`BookingCreated`/`SendBookingConfirmationEmail`,
  `UserRegistered`/`SendWelcomeEmail`).
- `backend/config/queue.php:16` — `'default' => env('QUEUE_CONNECTION',
  'database')`; `backend/.env.example` setzt `QUEUE_CONNECTION=database`.
  Das bestätigt: dieses Projekt nutzt bereits den in CLAUDE.md 4.3
  vorgesehenen `database`-Queue-Treiber (Cron-getriggert via
  `queue:work --stop-when-empty`), nicht `sync`, für die **bisherige**
  `ShouldQueue`-Listener-Klasse. Diese Entscheidung war für das
  Hoch-Frequenz-Szenario "bei jeder Rechnungserstellung" sinnvoll; für
  den neuen, seltenen, blockierenden Senden-Button ist sie das nicht
  mehr (siehe Decision D4).
- `backend/tests/Feature/InvoiceApiTest.php:556-567` — Test
  `'finalizing an invoice does not send an email'` nutzt bereits
  `Mail::fake()` + `Mail::assertNothingSent()`/`assertNothingQueued()`
  als etabliertes Muster für Mail-Assertions in diesem Bestandscode.
- `backend/tests/Feature/InvoiceCreatedMailBankDetailsTest.php` (65
  Zeilen) — vier grüne Tests, die `new InvoiceCreated($invoice)` direkt
  instanziieren und `assertSeeInHtml()`/`assertDontSeeInHtml()` auf den
  gerenderten HTML-Body anwenden (Bankdaten-Block, Zahlungsziel-Zeile).
  Diese Tests prüfen ausschließlich den Mail-**Inhalt**, nicht den
  Auslöser — bleiben nach der Umbenennung (Decision D5) inhaltlich
  unverändert gültig, nur der Klassenname/Dateiname ändert sich.
- `backend/database/migrations/0001_01_01_000000_create_users_table.php:16`
  — `$table->string('email')->unique();` — implizit `NOT NULL` (Laravel
  Schema-Builder-Default für `string()` ohne `->nullable()`).
- `backend/database/migrations/2025_12_22_184738_create_customers_table.php:18`
  — `$table->foreignId('user_id')->constrained()->onDelete('cascade');`
  — implizit `NOT NULL` FK, jeder `Customer` hat also zwingend einen
  `User` mit (nicht-leerer) E-Mail-Adresse.
  `backend/app/Models/Customer.php:82-85` — `user(): BelongsTo`.
- `backend/app/Http/Resources/CustomerResource.php:47` verschachtelt
  `new UserResource($this->whenLoaded('user'))`, und
  `backend/app/Http/Resources/UserResource.php:29` liefert darin
  `'email' => $this->email`. `invoice.customer.user.email` ist also
  bereits im JSON-Payload enthalten, wenn `customer.user` geladen ist
  (`InvoiceController::index()`/`show()` laden bereits `'customer.user'`,
  siehe `InvoiceController.php:55,168`).
- `backend/app/Policies/InvoicePolicy.php:101-118` (`finalize()`) und
  die zugehörige Methoden-Doc erläutern das etablierte Split-Muster
  dieses Projekts: **Policy = darf diese Rolle grundsätzlich handeln,
  Controller = ist die Aktion im aktuellen Zustand gültig (422 mit
  Nachricht)**. `markAsPaid()` (Policy-Datei, letzter Abschnitt vor
  `cancel()`) folgt demselben Muster. Dieser Change übernimmt es 1:1 für
  `send()`/`sendEmail()`.

**Ist-Zustand Frontend:**

- `frontend/src/views/invoices/InvoicesView.vue:96-103` — Aktionsspalte
  der Tabelle, Zeile 101: `<button v-if="canSend(invoice)" disabled
  title="Versand-Dialog folgt in einem späteren Update" ...>Senden</button>`.
  Zeile 213-218: `SENDABLE_STATUSES = ['sent', 'reminded', 'overdue']`,
  Zeile 243-245: `canSend()` prüft `!authStore.isCustomer &&
  SENDABLE_STATUSES.includes(invoice.status)`.
- `frontend/src/views/invoices/InvoicesView.vue:159-161` — bestehende
  `showFormModal`/`showDetailModal`/`selectedInvoice`-Refs, einmalig
  gemountete Modals (`InvoiceFormModal`, `InvoiceDetailModal`), von
  Tabellenzeile **und** Detail-Modal gemeinsam genutzt — das Muster, das
  `InvoiceSendDialog` übernimmt.
- `frontend/src/views/invoices/InvoicesView.vue:283-302` —
  `downloadPDF(invoice)`: lädt `/api/v1/invoices/{id}/pdf` als Blob,
  erzeugt einen Objekt-URL, triggert den Browser-Download, zeigt
  `showSuccess(...)`. Wird von diesem Change **unverändert
  wiederverwendet** (nicht dupliziert) für den "Manuell versenden"-Pfad.
- `frontend/src/components/InvoiceDetailModal.vue:172-174` — identischer
  Stub-Button; Zeile 201-209 `defineEmits` (`close, download, edit,
  mark-paid, delete, finalize, cancel`) — erhält ein neues `send`-Event
  nach demselben Muster wie `finalize`/`cancel`.
- `frontend/src/components/InvoiceDetailModal.vue:85` — zeigt
  `invoice.customer?.user?.email` bereits im Detail-Modal an; bestätigt,
  dass dieses Feld im vom Frontend konsumierten Payload zuverlässig
  vorhanden ist, wenn die Relation geladen wurde.
- Bestehendes Dialog-Pattern: `InvoiceDetailModal.vue` nutzt
  `@headlessui/vue` (`TransitionRoot`, `Dialog`, `DialogPanel`,
  `DialogTitle`, Import Zeile 190). `InvoiceSendDialog.vue` übernimmt
  dasselbe Pattern für visuelle/strukturelle Konsistenz (nicht das
  alternative `Teleport`+`Transition`-Pattern aus `PaymentModal.vue`/
  `EmailPreviewModal.vue`, das an anderer Stelle im Projekt existiert,
  aber nicht Teil der Invoice-Komponentenfamilie ist).

## Goals / Non-Goals

**Goals:**

- Der Senden-Button in `InvoicesView.vue` und `InvoiceDetailModal.vue`
  ist für die Status `sent`, `reminded`, `overdue` voll funktionsfähig.
- Ein Admin/Trainer kann eine Rechnung wahlweise per App-internem
  E-Mail-Versand (inkl. PDF-Anhang) oder durch manuellen PDF-Download an
  den Kunden übermitteln.
- Der Dialog zeigt beide Optionen (App-Mail, manueller Download)
  unabhängig vom Vorhandensein einer Kunden-E-Mail-Adresse an; fehlt eine
  E-Mail-Adresse, meldet der Server das erst beim tatsächlichen Versuch,
  aus der App zu versenden (HTTP 422, siehe User-Gate-1-Entscheidung 4 in
  `proposal.md`, kein Frontend-UI-Zweig mehr).
- Ein fehlgeschlagener App-Mail-Versand wird dem Bedienenden unmittelbar
  und verständlich zurückgemeldet, mit klarem Hinweis auf den
  Fallback-Weg (manueller Download).
- Serverseitige Autorisierung/Zustandsprüfung, nicht nur Frontend-Logik
  (Defense in Depth, etabliertes Muster aus Change 1 Decision D8).

**Non-Goals (bewusst außerhalb dieses Change):**

- Keine neue Datenbank-Spalte/-Migration, insbesondere kein "zuletzt
  gesendet am"-Zeitstempel (siehe `proposal.md` offene Frage 1).
- Kein Statusübergang durch `sendEmail()` — Status bleibt exakt so, wie
  er vor dem Versand war.
- Kein Zahlungseingang, keine Eingabemaske (Change 3,
  `add-invoice-payment-entry`).
- Kein Mahnungs-Trigger, kein Dashboard-Widget (Change 4,
  `add-invoice-dunning-dashboard`).
- Keine Anti-Doppelversand-Sperre (Debounce/Lock) — konsistent mit dem
  bestehenden Verhalten von `finalize()`/`cancel()`/`markAsPaid()`, die
  ebenfalls keine serverseitige Idempotenz-Sperre über einen einzelnen
  `confirm()`-Dialog hinaus haben. Mehrfacher Versand ist fachlich
  unschädlich (der Kunde bekommt im schlimmsten Fall die Rechnung zweimal
  zugestellt).
- Kein signierter, unauthentifizierter Download-Link für die
  E-Mail-Variante — der PDF-Anhang macht das unnötig (siehe Decision D6).

## Decisions

**D1. Senden-Endpunkt heißt `send-email`, nicht generisch `send`.**
Ursprünglich als generischer `POST /invoices/{id}/send` mit einem
`method`-Body-Parameter (`email`/`manual`) angedacht. Verworfen: der
manuelle Pfad hat **keine** serverseitige Aktion (siehe D3) — ein
generischer Endpunkt hätte für `method=manual` nichts zu tun außer eine
leere 200-Antwort zurückzugeben, was ein bedeutungsloser Rundtrip wäre
(KISS/YAGNI). Der Endpunkt heißt daher exakt, was er tut:
`POST /invoices/{id}/send-email`, ohne Body-Parameter.

**D2. Kein "zuletzt gesendet am"-Zeitstempel.**
Der Anforderungstext (`Anforderung-Rechnungsworkflow.txt:22-23`) fordert
nur den Dialog und die Download-Option, keine Nachverfolgung des
Versandzeitpunkts. Eine neue Spalte würde eine weitere DB-kritische
Migration erfordern (MySQL/PostgreSQL/SQLite-Pfad, siehe CLAUDE.md 4.2)
für ein Feld, das aktuell **keinen** UI-Konsumenten hat (im Unterschied
zu `paidDate`/`remindedAt`, die in `InvoicesView.vue:88-93` bereits
sichtbar angezeigt werden, weil Change 1 das explizit vorsah). Alternative
geprüft: Zeitstempel "for free" mitspeichern, falls später gebraucht.
Verworfen nach YAGNI — eine ungenutzte Spalte ist toter Code auf
Datenbankebene, schwerer rückgängig zu machen als eine spätere additive
Migration. Explizit als offene Frage 1 in `proposal.md` markiert.

**D3. "Manuell versenden" ruft keinen neuen Endpunkt auf, sondern den
bestehenden PDF-Download.**
`downloadPdf()` (`InvoiceController.php:402-417`) ist bereits vollständig
implementiert, autorisiert und getestet
(`backend/tests/Feature/InvoicePdfTest.php`). "Manuell versenden" im
Sinne des Anforderungstexts bedeutet ausschließlich "PDF anbieten" — es
gibt keine zusätzliche fachliche Bedeutung, die einen eigenen Endpunkt
rechtfertigt (YAGNI). `InvoiceSendDialog.vue` löst für diesen Pfad
dieselbe Download-Logik aus wie der bestehende PDF-Button
(`InvoicesView.vue:283-302`), via `download`-Event an den bereits
vorhandenen Handler.

**D4. Synchroner statt asynchroner Mail-Versand für `sendEmail()`.**
Der bestehende `SendInvoiceCreatedEmail`-Listener implementiert
`ShouldQueue` (`Listener.php:13`) mit `Mail::to(...)->queue(...)`
(Zeile 37-38) — passend zum ursprünglichen automatischen Trigger bei
*jeder* Rechnungserstellung (potenziell hochfrequent, kein wartender
User, s. CLAUDE.md 4.3 "database-Driver mit `queue:work
--stop-when-empty`, getriggert per Hoster-Cron alle 5-15 Minuten"). Der
neue Senden-Button ist dagegen eine seltene, bewusste, blockierende
Einzelaktion: der Admin/Trainer klickt und erwartet eine unmittelbare
Rückmeldung, ob die Mail tatsächlich verschickt wurde — nicht "wurde für
die nächsten bis zu 15 Minuten vorgemerkt". Der umbenannte
`SendInvoiceEmail`-Listener (siehe D5) verliert daher `ShouldQueue`
und `InteractsWithQueue`, `Mail::to(...)->queue(...)` wird zu
`Mail::to(...)->send(...)`. `InvoiceController::sendEmail()` ruft
`InvoiceWasSent::dispatch($invoice)` innerhalb eines `try/catch
(\Throwable)` auf — bei synchronem Listener wird eine SMTP-Exception
(Verbindungsfehler, Auth-Fehler etc.) direkt im Request geworfen und kann
so in eine aussagekräftige HTTP-502-Antwort übersetzt werden ("Die
Rechnung konnte nicht per E-Mail versendet werden. Bitte laden Sie das
PDF herunter und versenden Sie es manuell."). Das beantwortet die vom
Auftrag geforderte Klärung "Was passiert bei fehlgeschlagenem
Mailversand?" konkret: **Fehlermeldung mit Fallback-Hinweis, kein
automatischer Retry.** Bleibt CLAUDE.md-konform (Abschnitt 4.3 erlaubt
`sync`-Verhalten explizit als zulässige Alternative zum
Cron-getriggerten `database`-Queue-Ansatz). Als offene Frage 2 in
`proposal.md` markiert, da dies vom bisherigen Verhalten dieser
konkreten Klasse abweicht.

**D5. Umbenennung der Mail-Infrastruktur von "Created" zu "Sent".**
`InvoiceWasCreated`/`SendInvoiceCreatedEmail`/`InvoiceCreated` (Event,
Listener, Mailable) sowie die View `emails/invoice-created.blade.php`
tragen Namen, die die ursprüngliche (in Change 1 entfernte) Semantik
"ausgelöst beim Erstellen" beschreiben. Nach diesem Change werden sie
ausschließlich vom expliziten Senden-Button ausgelöst — der Name wäre
irreführend für zukünftige Wartung ("warum heißt das `InvoiceCreated`,
wenn es nie beim Erstellen läuft?"). Alternative geprüft: Namen
beibehalten, um den Diff kleiner zu halten und den bereits grünen Test
`InvoiceCreatedMailBankDetailsTest.php` unangetastet zu lassen.
Entscheidung: **umbenennen** (`InvoiceWasSent`, `SendInvoiceEmail`,
`InvoiceSent`, `invoice-sent.blade.php`), weil Namensklarheit hier ein
echtes Wartungsrisiko adressiert (SOLID: eine Klasse soll durch ihren
Namen ihre Verantwortung kommunizieren) und die Umbenennung mechanisch
und risikoarm ist (keine Verhaltensänderung am Test-Inhalt, nur
Klassen-/Dateiname). Der betroffene Test wird 1:1 mitverschoben
(`InvoiceSentMailBankDetailsTest.php`), seine vier Assertions bleiben
unverändert. Als offene Frage 5 in `proposal.md` markiert, da dies eine
reine Stil-/Klarheits-Entscheidung ohne fachlichen Zwang ist.

**D6. PDF-Generierung in `App\Services\InvoicePdfRenderer` extrahiert;
PDF wird der Mail als Anhang beigefügt.**
Ohne Extraktion müsste die Kette `Pdf::loadView('pdf.invoice', [...])
->setPaper('a4', 'portrait')->setOption(...)->setOption(...)` künftig an
zwei Stellen stehen (`downloadPdf()` und `InvoiceSent::attachments()`) —
Verstoß gegen DRY. Der neue Service kapselt die Erzeugung:
```php
final class InvoicePdfRenderer
{
    public function render(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }
}
```
`downloadPdf()` ruft `$pdfRenderer->render($invoice)->download(...)`,
`InvoiceSent::attachments()` ruft
`Attachment::fromData(fn () => $pdfRenderer->render($invoice)->output(),
$invoice->invoice_number.'.pdf')->withMime('application/pdf')`.
Alternative geprüft (nur Link statt Anhang): ein Download-Link in der
Mail würde eine authentifizierte Session oder einen signierten,
zeitlich befristeten Public-Link erfordern (`URL::temporarySignedRoute`)
— zusätzliche Sicherheits-/Ablauf-Logik für ein Problem, das ein
einfacher Anhang ohne jede neue Angriffsfläche löst. Ein Anhang passt
zudem zum bereits vorhandenen Mail-Text ("anbei erhalten Sie ..."). Als
offene Frage 3 in `proposal.md` markiert (Mailgröße auf manchen
Shared-Hosting-SMTP-Relays potenziell limitiert — Rechnungs-PDFs sind
aber typischerweise einstellig-KB-groß, kein erwartetes Problem).

**D7. `InvoicePolicy::send()` — Rollen-only, Zustandsprüfung im
Controller.**
Identisches Split-Muster wie `finalize()`/`markAsPaid()`
(`InvoicePolicy.php:101-118` und angrenzender Abschnitt): die Policy
prüft nur `$user->isAdminOrTrainer()`, der Controller prüft in
`sendEmail()`, ob `$invoice->status` in `['sent', 'reminded',
'overdue']` liegt und ob `$invoice->customer->user->email` nicht leer
ist — beides als HTTP 422 mit sprechender Nachricht. Konsistent mit dem
etablierten Konventions-Kommentar in `InvoicePolicy::finalize()`
("policy = may this role act at all, controller = is this action valid
given the invoice's current state").

**D8. Frontend: ein einziger, wiederverwendbarer `InvoiceSendDialog`,
kein API-Zugriff im Dialog selbst, keine `hasEmail`-Verzweigung.**
Konsistent mit dem bestehenden Architekturprinzip dieses
Feature-Bereichs: `InvoiceDetailModal.vue` ist reines Presentation-Layer
und emittiert Events (`finalize`, `cancel`, `mark-paid`, …), während
`InvoicesView.vue` als einzige Stelle tatsächliche API-Aufrufe tätigt und
danach `loadInvoices()`/`showSuccess()`/`handleApiError()` aufruft (siehe
`design.md` von Change 1, dort für `InvoiceFormModal`/
`InvoiceDetailModal` bereits so etabliert). `InvoiceSendDialog.vue`
folgt demselben Muster: reine Auswahl-UI, die **immer** beide Optionen
zeigt (App-Mail, manueller Download), emittiert `download`/`send-email`/
`close`, **kein** `apiClient`-Import im Dialog selbst. Ursprünglich war
ein `hasEmail`-Computed vorgesehen, das bei fehlender Kunden-E-Mail-Adresse
nur den Download-Button anzeigt (siehe `proposal.md` ursprüngliche offene
Frage 4) — diese Verzweigung wurde in **User-Gate 1 gestrichen (YAGNI)**:
da jeder `Customer` nach aktuellem Datenmodell zwingend eine
E-Mail-Adresse hat (siehe Context oben), war der Zweig ohnehin nur über
präparierte Testdaten erreichbar. Der Dialog bleibt dadurch einfacher; die
serverseitige 422-Prüfung in `sendEmail()` (Decision D7) bleibt als
Defense-in-Depth bestehen, falls sich das Datenmodell künftig ändert.
`InvoicesView.vue`
mountet den Dialog einmalig (wie `showFormModal`/`showDetailModal`) und
bekommt zwei neue Handler-Funktionen: `openSendDialog(invoice)` (setzt
`selectedInvoice`, öffnet den Dialog) und `sendInvoiceEmail(invoice)`
(ruft `POST /invoices/{id}/send-email`, `try/catch` mit
`handleApiError()`/`showSuccess()`, schließt den Dialog bei Erfolg,
**bleibt bei Fehler offen**, damit sofort auf "Manuell versenden"
ausgewichen werden kann, siehe Goals). Der `download`-Pfad ruft die
bestehende `downloadPDF(invoice)`-Funktion
(`InvoicesView.vue:283-302`) unverändert wieder auf.
`InvoiceDetailModal.vue` emittiert für den Klick auf "Senden" ein neues
`send`-Event (analog `finalize`/`cancel`), das `InvoicesView.vue` auf
`openSendDialog()` mapped — der Dialog selbst öffnet sich dann
**über** dem Detail-Modal (beide auf `@headlessui/vue`-Basis, verschachtelte
Dialoge sind dort unterstützt, wie bereits implizit durch
`showFormModal`/`showDetailModal`-Koexistenz gezeigt).

## Migrationen

**Keine.** Dieser Change fügt keine Datenbank-Spalte, keine neue Tabelle
und keine Enum-Erweiterung hinzu (siehe Decision D2, Non-Goals). Damit
entfällt für Change 2 die in CLAUDE.md Abschnitt 7 verlangte
MySQL-/PostgreSQL-Kompatibilitätsprüfung durch den Architekten — es gibt
nichts zu prüfen.

## Ausblick auf Folge-Changes (nicht Teil dieses Change)

- `add-invoice-payment-entry`: unverändert wie in Change 1 beschrieben —
  nutzt das bestehende `Payment`-Modell, ergänzt die fehlende
  Frontend-Eingabemaske. Von diesem Change nicht berührt.
- `add-invoice-dunning-dashboard`: unverändert wie in Change 1
  beschrieben — nutzt das in Change 1 geschaffene
  `invoice_dunnings`-Datenmodell und den `reminded`-Status. Von diesem
  Change nicht berührt. Falls Change 4 einen Mahnungs-Mailversand
  einführt, kann geprüft werden, ob er `InvoicePdfRenderer` (Decision D6
  dieses Change) mitnutzt.

## Risks / Trade-offs

- **Umbenennung berührt eine bestehende, grüne Testdatei.** Geringes
  Risiko (reiner Compile-Fix, keine Assertion-Änderung), aber ein
  größerer Diff als bei einer reinen additiven Änderung. Siehe Decision
  D5 und offene Frage 5.
- **Synchroner Mail-Versand blockiert den HTTP-Request für die Dauer des
  SMTP-Handshakes.** Bei einem langsamen oder nicht erreichbaren
  SMTP-Server hängt der Klick auf "Aus der App versenden" entsprechend
  lange, bevor der Fehler zurückkommt. Für eine seltene, bewusste
  Einzelaktion mit einem wartenden Admin/Trainer als akzeptabel bewertet
  (siehe Decision D4); kein Hintergrund-Retry in diesem Change.
- **Kein Zeitstempel für den Versand.** Falls sich im Betrieb
  herausstellt, dass Nachverfolgbarkeit doch gebraucht wird (z. B. für
  Buchhaltung/Support-Anfragen "wurde die Rechnung X schon verschickt?"),
  ist das ein separater, kleiner Folge-Change mit eigener Migration
  (siehe Decision D2).
- **"Keine E-Mail-Adresse"-Frontend-Zweig entfernt (YAGNI, User-Gate-1-
  Entscheidung 4).** Der Dialog zeigt beide Optionen immer an, auch wenn
  (hypothetisch) keine E-Mail-Adresse hinterlegt wäre — mit dem aktuellen
  Datenmodell ist das ohnehin nicht über reguläre Kundenanlage erreichbar
  (siehe Context). Die serverseitige 422-Prüfung bleibt bestehen und
  verhindert einen fehlerhaften Versand; der Nutzer bekäme in diesem
  (aktuell unerreichbaren) Fall lediglich eine Fehlermeldung statt eines
  präventiven UI-Hinweises. Falls Kunden ohne E-Mail-Adresse künftig
  möglich werden, ist der UI-Zweig ein eigener, kleiner Folge-Change
  (siehe `proposal.md` offene Frage 4).
