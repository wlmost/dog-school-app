## Why

Der Rechnungs-Lebenszyklus ist aktuell nur rudimentär abgebildet:
`backend/app/Models/Invoice.php:110-113` berechnet `isOverdue()` rein aus
`due_date`, `backend/database/migrations/2025_12_22_185107_create_invoices_table.php:18`
definiert zwar ein `status`-Enum
(`draft, sent, paid, overdue, cancelled`), aber es gibt **keinen** Status für
"gemahnt" und **keine** Datenfelder für Mahnstufen/-gebühren. Die
`invoice_number` wird schon beim Anlegen eines **Entwurfs** fest vergeben
(`backend/app/Http/Requests/StoreInvoiceRequest.php:85,103-118`), was der
fachlichen Anforderung widerspricht, dass ein Entwurf "beliebig geändert
oder gelöscht werden" kann, ohne eine Nummernlücke zu reißen (steuerlich
relevant). Beim Erstellen jeder Rechnung — auch im Entwurf — wird zudem
automatisch eine E-Mail an den Kunden verschickt
(`InvoiceWasCreated::dispatch($invoice)` in
`backend/app/Http/Controllers/Api/InvoiceController.php:140`, verarbeitet von
`backend/app/Listeners/SendInvoiceCreatedEmail.php:30-42`), obwohl ein
Entwurf laut Anforderung noch nicht final ist.

Es gibt weder einen Endpunkt noch eine UI, um eine Rechnung final
freizugeben (Entwurf → Offen) oder zu stornieren
(`backend/app/Http/Controllers/Api/InvoiceController.php:1-244` kennt nur
`index/store/show/update/destroy/markAsPaid/overdue/downloadPdf`). Die
generische `update()`-Route erlaubt aktuell, den `status` einer **beliebigen**
Rechnung — auch einer bereits "offenen" oder "bezahlten" — frei zu setzen
(`backend/app/Http/Requests/UpdateInvoiceRequest.php:28`), was der
fachlichen Vorgabe widerspricht, dass ein einmal offenes Dokument
"festgeschrieben" ist (Anforderungstext, Abschnitt "Offen/Verschickt",
Zeile 19-20 in `Anforderung-Rechnungsworkflow.txt`).

Auf Frontend-Seite zeigt `frontend/src/views/invoices/InvoicesView.vue:84-88`
nur die Buttons PDF (immer), Bearbeiten (nur `draft`) und "Bezahlt" (`draft`
oder `sent`) — es fehlen Löschen-, Senden- und Stornieren-Buttons sowie jede
Anzeige von Zahlungseingangs- oder Mahndatum.
`frontend/src/components/InvoiceFormModal.vue:57-65,303` erlaubt außerdem,
den Status beim Erstellen/Bearbeiten frei per Dropdown zu setzen — das
untergräbt die neue Nummernvergabe-Regel, wenn eine Rechnung direkt mit
Status "sent" angelegt würde.

Dieser Change (`add-invoice-status-lifecycle`) legt das **Datenmodell und
die Kern-Statuslogik** für den gesamten Rechnungsworkflow fest, auf dem drei
weitere, separat spezifizierte Changes aufbauen (siehe `design.md`,
Abschnitt "Ausblick auf Folge-Changes"):

- `add-invoice-send-flow` (Versand-Dialog App-Mail vs. manueller Download)
- `add-invoice-payment-entry` (Eingabemaske für Zahlungseingang)
- `add-invoice-dunning-dashboard` (Mahnungs-Trigger, Dashboard-Widget)

## What Changes

- **Breaking: Rechnungsnummern-Vergabe verschoben.** `invoice_number` wird
  nicht mehr beim Erstellen eines Entwurfs vergeben, sondern erst bei der
  Freigabe (Entwurf → Offen) über einen neuen Endpunkt
  `POST /api/v1/invoices/{invoice}/finalize`. Die Spaltendefinition
  `invoice_number` wird von `NOT NULL UNIQUE` auf `NULLABLE UNIQUE`
  umgestellt (Migration, DB-kritisch, siehe `design.md`).
  `StoreInvoiceRequest::generateInvoiceNumber()`
  (`backend/app/Http/Requests/StoreInvoiceRequest.php:103-118`) entfällt zugunsten
  eines neuen, wiederverwendbaren Service `App\Services\InvoiceNumberGenerator`.
- **Breaking: `status` ist beim Erstellen/Bearbeiten nicht mehr frei
  setzbar.** `StoreInvoiceRequest` erzwingt beim Anlegen immer `draft`.
  `UpdateInvoiceRequest` verliert das Feld `status` vollständig — Statuswechsel
  laufen ausschließlich über dedizierte Endpunkte (`finalize`, `cancel`,
  bestehendes `mark-paid`, künftig `remind` in Change 4). Eine offene oder
  bezahlte Rechnung ist damit inhaltlich "festgeschrieben": `update()`/`destroy()`
  sind laut `InvoicePolicy` nur noch für Rechnungen im Status `draft` erlaubt.
- **Breaking: Kein automatischer Mail-Versand mehr beim Erstellen.** Der
  `InvoiceWasCreated::dispatch()`-Aufruf in
  `InvoiceController::store()` (Zeile 140) entfällt ersatzlos für Change 1.
  Event-Klasse, Listener und Mailable bleiben unverändert im Code
  (`App\Events\InvoiceWasCreated`, `App\Listeners\SendInvoiceCreatedEmail`,
  `App\Mail\InvoiceCreated`), werden aber **in Change 1 an keiner Stelle mehr
  ausgelöst** — Change 2 (`add-invoice-send-flow`) verdrahtet den expliziten
  Versand-Dialog neu.
- **Neuer Status `reminded`** ("gemahnt") im `invoices.status`-Enum,
  additive Migration nach dem Muster von
  `backend/database/migrations/2026_05_04_110001_add_cancellation_requested_status_to_bookings_table.php`
  (treiberspezifisch für MySQL/PostgreSQL/SQLite, siehe `design.md`).
- **Neues Mahnstufen-Datenmodell** (`invoice_dunnings`-Tabelle +
  `InvoiceDunning`-Model): pro Mahnung ein Datensatz mit Stufe, Datum und
  Gebühr — Grundlage für Change 4, keine Mahn-Logik/-Trigger in diesem
  Change.
- **Storno als vollwertiges Korrekturdokument** (bindende Entscheidung 2):
  Ein neuer Endpunkt `POST /api/v1/invoices/{invoice}/cancel` erzeugt eine
  eigenständige Stornorechnung (eigene `invoice_number`, negative
  Rechnungspositionen, die die Original-Rechnung ausgleichen) und markiert
  die Original-Rechnung als `cancelled`. Neue Spalte
  `invoices.original_invoice_id` (selbstreferenzierender Fremdschlüssel)
  verknüpft Storno- und Original-Dokument.
- **Listen-Buttons pro Status** in `InvoicesView.vue` und
  `InvoiceDetailModal.vue`: Entwurf (PDF/Bearbeiten/Löschen/**Freigeben**,
  siehe Decision D1 in `design.md` zur Lücke im Anforderungstext),
  Offen (PDF/Senden [sichtbar, aber deaktiviert — Logik ist Change 2]/
  Stornieren), Bezahlt (PDF/Stornieren + Anzeige Zahlungseingangsdatum,
  Eingabemaske selbst ist Change 3), Storniert (nur PDF), zusätzlich
  visuelle Markierung für Überfällig und Gemahnt (inkl. Mahndatum).
- **Rollen-Sichtbarkeit verschärft:** Kunden sehen nur eigene Rechnungen in
  Status `sent`, `paid`, `overdue` (berechnet), `reminded` — nicht `draft`,
  nicht `cancelled`. Durchgesetzt sowohl in `InvoiceController::index()`
  (Query-Filter) als auch in `InvoicePolicy::view()` (Einzelabruf), nicht
  nur im Frontend.
- **`InvoiceFormModal.vue`** verliert das Status-Dropdown
  (Zeile 57-65) und sendet `status` nicht mehr im Payload (Zeile 303) — die
  Rechnung wird immer als Entwurf angelegt bzw. bleibt beim Bearbeiten
  Entwurf.

## Capabilities

### New Capabilities

- `invoice-status-lifecycle`: Zustandsautomat einer Rechnung (Entwurf →
  Offen → Bezahlt/Überfällig/Gemahnt), Zeitpunkt der Rechnungsnummern-Vergabe,
  Unveränderlichkeit ab "Offen", Rollen-Sichtbarkeit, Mahnstufen-Datenmodell,
  Listen-Buttons pro Status.
- `invoice-cancellation`: Stornierung einer Rechnung als eigenständiges
  Korrekturdokument mit eigener Nummer, das die Original-Rechnung
  ausgleicht und diese als storniert markiert.

### Modified Capabilities

<!-- Keine bestehende Spec-fähige Capability ändert ihr Verhalten. Die
     bestehenden Capabilities `invoice-pdf-status-display` (PDF zeigt
     keinen Statustext) und `invoice-bank-details` (Bankdaten im PDF/Mail)
     sind von diesem Change funktional nicht betroffen. -->

## Impact

**Betroffener Bestandscode (Backend):**
- `backend/app/Models/Invoice.php` — neue Relationen (`dunnings()`,
  `originalInvoice()`, `cancellationInvoice()`), erweiterte `$fillable`,
  Statuskonstanten.
- `backend/app/Http/Requests/StoreInvoiceRequest.php` — `status`-Feld und
  `generateInvoiceNumber()` entfernt.
- `backend/app/Http/Requests/UpdateInvoiceRequest.php` — `status`-Feld
  entfernt.
- `backend/app/Http/Controllers/Api/InvoiceController.php` — neue Methoden
  `finalize()`, `cancel()`; `store()` ohne Event-Dispatch; `index()`/`show()`
  mit verschärfter Sichtbarkeit.
- `backend/app/Policies/InvoicePolicy.php` — `update()`/`delete()` nur für
  `draft`; neue Methoden `finalize()`, `cancel()`; `view()` mit
  Status-Filter für Kunden.
- `backend/app/Http/Resources/InvoiceResource.php` — neue Felder
  (`remindedAt`, `dunningLevel`, `originalInvoiceId`,
  `originalInvoiceNumber`, `cancellationInvoiceId`,
  `cancellationInvoiceNumber`).
- `backend/routes/api.php` — zwei neue Routen (`finalize`, `cancel`).
- Neue Migrationen (siehe `design.md`), neuer Service
  `App\Services\InvoiceNumberGenerator`, neues Model `App\Models\InvoiceDunning`.
- **Nicht geändert, aber betroffen (Erwartung für Reviewer/Tester):**
  `backend/tests/Feature/InvoiceApiTest.php:243-260` (`trainer can update
  invoice` setzt `status => 'sent'` per PUT — muss nach diesem Change
  fehlschlagen bzw. angepasst werden) und
  `backend/database/factories/InvoiceFactory.php:27-32` (`overdue()`-State
  setzt `status => 'overdue'` — bleibt als Test-Fixture gültig, da der
  Enum-Wert nicht entfernt wird, siehe `design.md` Decision D3).

**Betroffener Bestandscode (Frontend):**
- `frontend/src/views/invoices/InvoicesView.vue` — Buttons/Badges pro
  Status.
- `frontend/src/components/InvoiceDetailModal.vue` — gleiche Button-/
  Status-Logik im Detail-Modal.
- `frontend/src/components/InvoiceFormModal.vue` — Status-Dropdown entfernt.

## Offene Fragen für Skeptiker/User (User-Gate 1: entschieden am 2026-08-12)

1. **[ENTSCHIEDEN: sichtbar]** **Sichtbarkeit der Stornorechnung für den Kunden.** Der
   Anforderungstext (`Anforderung-Rechnungsworkflow.txt:40`) listet die für
   Kunden sichtbaren **Statuswerte** ("Offen, Bezahlt, Überfällig und
   gemahnt"), wurde aber formuliert, bevor die bindende Entscheidung fiel,
   dass eine Stornierung ein **eigenständiges neues Rechnungsdokument**
   erzeugt (bindende Entscheidung 2). Der Text regelt nicht, ob dieses neue
   Dokument (das im Status `sent` angelegt wird, siehe `design.md`
   Decision D5) dem Kunden angezeigt werden soll. Dieser Change geht davon
   aus, dass die Stornorechnung **sichtbar** ist (sie fällt unter den
   sichtbaren Status `sent`, damit der Kunde die Korrektur seiner
   ursprünglichen — für ihn dann unsichtbaren, weil `cancelled` —
   Rechnung nachvollziehen kann). **Bitte im Skeptiker-/User-Gate
   bestätigen oder korrigieren.**
2. **[ENTSCHIEDEN: "Freigeben"-Button wie vorgeschlagen]** **Lücke im Anforderungstext: kein Button für Entwurf → Offen.** Der
   Abschnitt "Entwurf" (`Anforderung-Rechnungsworkflow.txt:12`) listet nur
   PDF/Bearbeiten/Löschen; der Abschnitt "Offen/Verschickt" beschreibt den
   Senden-Button bereits für eine **bereits offene** Rechnung mit fester
   Nummer (Zeile 21-23: "wird erfragt, ob die Rechnung ... versendet werden
   soll" — setzt voraus, dass die Rechnung schon final ist). Es fehlt eine
   explizite Aktion, die den Übergang Entwurf → Offen auslöst. Dieser
   Change führt dafür einen zusätzlichen Button **"Freigeben"** im
   Entwurf-Status ein (siehe `design.md` Decision D1), der ausschließlich
   die Nummernvergabe + den Statuswechsel durchführt, **ohne** Mailversand
   oder Sende-Dialog (das bleibt Change 2). **Bitte im Skeptiker-/
   User-Gate bestätigen, ob dieser zusätzliche Button gewünscht ist oder ob
   der Übergang stattdessen direkt in den (noch nicht spezifizierten)
   Senden-Dialog aus Change 2 integriert werden soll** — in letzterem Fall
   müsste der Entwurf-Status übergangsweise (bis Change 2 umgesetzt ist)
   ohne UI-Weg nach "Offen" bleiben, was für Change 1 allein nicht sinnvoll
   testbar wäre.
3. **PDF-Layout für Stornorechnungen.** Dieser Change generiert
   Stornorechnungen als normale `Invoice`-Datensätze mit negativen
   Beträgen; das bestehende PDF-Template (`pdf/invoice.blade.php`) wird
   **nicht** um eine explizite "Stornorechnung"-Kennzeichnung erweitert
   (Non-Goal, siehe `design.md`). Falls das für die Buchhaltung zwingend
   nötig ist, sollte das als Nachtrag zu diesem Change oder als eigener
   kleiner Change geklärt werden.
