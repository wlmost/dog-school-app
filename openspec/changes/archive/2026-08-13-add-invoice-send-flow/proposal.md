## Why

`add-invoice-status-lifecycle` (archiviert unter
`openspec/changes/archive/2026-08-12-add-invoice-status-lifecycle/`) hat
den Status-Lebenszyklus und die Rechnungsnummern-Vergabe fest verdrahtet,
aber den "Senden"-Button bewusst nur als **deaktivierten Stub** ausgeliefert:
`frontend/src/views/invoices/InvoicesView.vue:101` und
`frontend/src/components/InvoiceDetailModal.vue:172-174` zeigen für die
Status `sent`/`reminded`/`overdue` einen Button mit `disabled` und dem
Tooltip "Versand-Dialog folgt in einem späteren Update". Es gibt aktuell
**keinen** Weg, eine offene Rechnung tatsächlich an den Kunden zu
übermitteln — weder per E-Mail aus der App noch mit einer geführten
manuellen Download-Option (`Anforderung-Rechnungsworkflow.txt:22-23`).

Die dafür nötige Mail-Infrastruktur existiert bereits im Code, wurde aber
in Change 1 bewusst vom automatischen Trigger beim Erstellen entkoppelt
und **nirgends mehr ausgelöst**
(`backend/app/Events/InvoiceWasCreated.php`,
`backend/app/Listeners/SendInvoiceCreatedEmail.php`,
`backend/app/Mail/InvoiceCreated.php` — der `dispatch()`-Aufruf wurde aus
`InvoiceController::store()` entfernt, siehe Change-1-`proposal.md`
Abschnitt "What Changes", "Breaking: Kein automatischer Mail-Versand
mehr..."). Dieser Change verdrahtet diese Infrastruktur neu auf den
expliziten Senden-Button, passt Benennung/Inhalt an das neue
Auslöse-Ereignis an und ergänzt den fehlenden manuellen
PDF-Download-Pfad für Kunden ohne (oder mit) E-Mail-Adresse.

## What Changes

- **Neuer Endpunkt `POST /api/v1/invoices/{invoice}/send-email`**
  (`backend/app/Http/Controllers/Api/InvoiceController.php`, neue
  Policy-Methode `InvoicePolicy::send()`, neue Route in
  `backend/routes/api.php`): löst den App-internen E-Mail-Versand für
  eine Rechnung im Status `sent`, `reminded` oder `overdue` aus (Status
  bleibt dabei unverändert — reine Kommunikationsaktion, kein
  Statusübergang). Rollen-Prüfung analog `finalize()`/`markAsPaid()`,
  Zustandsprüfung (Status muss "sendbar" sein, Kunde muss eine
  E-Mail-Adresse haben) als HTTP 422 im Controller, analog zum
  bestehenden Muster.
- **Kein neuer Endpunkt für den manuellen Versand.** "Manuell versenden"
  nutzt die bereits existierende `GET /api/v1/invoices/{invoice}/pdf`
  (`backend/routes/api.php:181`, `InvoiceController::downloadPdf()`)
  unverändert wieder — es ist schlicht ein PDF-Download, kein separater
  fachlicher Vorgang (siehe `design.md` Decision D3, YAGNI).
- **Umbenennung + Anpassung der bestehenden, seit Change 1 ungenutzten
  Mail-Infrastruktur** (siehe `design.md` Decision D5 für die vollständige
  Begründung): `App\Events\InvoiceWasCreated` → `App\Events\InvoiceWasSent`,
  `App\Listeners\SendInvoiceCreatedEmail` → `App\Listeners\SendInvoiceEmail`
  (zusätzlich **synchron** statt `ShouldQueue`, siehe Decision D4/offene
  Frage 2), `App\Mail\InvoiceCreated` → `App\Mail\InvoiceSent`,
  `backend/resources/views/emails/invoice-created.blade.php` →
  `emails/invoice-sent.blade.php`. Registrierung in
  `backend/app/Providers/AppServiceProvider.php:79-82` entsprechend
  angepasst. Bestehender Test
  `backend/tests/Feature/InvoiceCreatedMailBankDetailsTest.php` wird auf
  die neuen Klassennamen umgestellt (Datei umbenannt zu
  `InvoiceSentMailBankDetailsTest.php`), Testinhalt/Assertions inhaltlich
  unverändert (reiner Compile-Fix wegen der Umbenennung).
- **`InvoiceSent`-Mailable erhält einen PDF-Anhang** (aktuell
  `attachments()` liefert eine leere Liste,
  `backend/app/Mail/InvoiceCreated.php:71-74`) — die Rechnung wird beim
  App-Mail-Versand als PDF angehängt, nicht nur als HTML-Text
  beschrieben (siehe Decision D6/offene Frage 3).
- **Neuer Service `App\Services\InvoicePdfRenderer`** extrahiert die
  bisher nur in `InvoiceController::downloadPdf()`
  (`backend/app/Http/Controllers/Api/InvoiceController.php:409-413`)
  vorhandene PDF-Erzeugungslogik (`Pdf::loadView(...)->setPaper(...)
  ->setOption(...)`), damit sie sowohl vom Download-Endpunkt als auch vom
  `InvoiceSent`-Mailable ohne Duplikation genutzt werden kann (DRY,
  Decision D6).
- **Neue Frontend-Komponente `InvoiceSendDialog.vue`**
  (`frontend/src/components/`) ersetzt die deaktivierten Stub-Buttons in
  `InvoicesView.vue:101` und `InvoiceDetailModal.vue:172-174`. Zeigt
  **immer** beide Optionen — Wahl zwischen "Aus der App versenden" (ruft
  den neuen Endpunkt) und "Manuell versenden" (löst den bestehenden
  PDF-Download aus) — unabhängig davon, ob für den Kunden eine
  E-Mail-Adresse hinterlegt ist. **Kein** `hasEmail`-Unterscheidungszweig
  im Frontend (siehe offene Frage 4, User-Gate-1-Entscheidung: YAGNI, da
  nach aktuellem Datenmodell jeder Kunde zwingend eine E-Mail-Adresse
  hat). Die serverseitige Validierung im `send-email`-Endpunkt bleibt als
  Defense-in-Depth bestehen (HTTP 422, falls doch keine E-Mail-Adresse
  vorhanden ist).

  Einmalig in `InvoicesView.vue` gemountet (analog zum bestehenden
  `showFormModal`/`showDetailModal`-Muster,
  `frontend/src/views/invoices/InvoicesView.vue:159-161`), sowohl für den
  Listenzeilen-Button als auch für den Button im Detail-Modal
  wiederverwendet (`InvoiceDetailModal.vue` emittiert ein neues
  `send`-Event analog zu `finalize`/`cancel`,
  `frontend/src/components/InvoiceDetailModal.vue:201-209`).
- **Keine neue Datenbank-Spalte/-Migration in diesem Change.** Es wird
  **kein** "zuletzt gesendet am"-Zeitstempel eingeführt (siehe
  `design.md` Decision D2 und offene Frage 1 — bewusste Entscheidung
  gegen Scope-Kriechen, zur Bestätigung durch Skeptiker/User markiert).
- **Keine Änderung an Status, Sichtbarkeit oder Buttons für andere
  Status** als die bereits in Change 1 als "sendbar" markierten
  (`sent`, `reminded`, `overdue`) — Zahlungseingang (Change 3) und
  Mahnungs-Trigger/Dashboard (Change 4) bleiben vollständig
  unangetastet.

## Capabilities

### New Capabilities

- `invoice-send-flow`: Der explizite Versand-Dialog für eine offene
  Rechnung — Auswahl zwischen App-internem E-Mail-Versand (mit
  PDF-Anhang) und manuellem PDF-Download, beide Optionen stets sichtbar;
  serverseitige Autorisierung und Zustandsprüfung (inkl.
  E-Mail-Adressen-Prüfung als Defense-in-Depth) für den E-Mail-Versand.

### Modified Capabilities

- `invoice-status-lifecycle`: Das Requirement "Listen- und
  Detail-Buttons pro Status"
  (`openspec/specs/invoice-status-lifecycle/spec.md:70-96`) beschreibt
  den Senden-Button aktuell explizit als "sichtbar, ohne aktive Funktion
  in diesem Change" (Szenario "Offene Rechnung zeigt PDF, Senden und
  Stornieren", Zeile 81-85). Dieser Change macht den Button funktional —
  das Szenario wird entsprechend aktualisiert (Delta in
  `specs/invoice-status-lifecycle/spec.md` dieses Change).

## Impact

**Betroffener Bestandscode (Backend):**
- `backend/app/Http/Controllers/Api/InvoiceController.php` — neue Methode
  `sendEmail()`, `downloadPdf()` refaktoriert auf `InvoicePdfRenderer`.
- `backend/app/Policies/InvoicePolicy.php` — neue Methode `send()`.
- `backend/routes/api.php` — eine neue Route.
- `backend/app/Events/InvoiceWasCreated.php` → `InvoiceWasSent.php`
  (Umbenennung).
- `backend/app/Listeners/SendInvoiceCreatedEmail.php` →
  `SendInvoiceEmail.php` (Umbenennung + synchron statt `ShouldQueue`).
- `backend/app/Mail/InvoiceCreated.php` → `InvoiceSent.php` (Umbenennung +
  PDF-Anhang).
- `backend/resources/views/emails/invoice-created.blade.php` →
  `invoice-sent.blade.php` (Umbenennung).
- `backend/app/Providers/AppServiceProvider.php` — Event/Listener-Import
  und -Registrierung angepasst.
- `backend/app/Services/InvoicePdfRenderer.php` — neu.
- `backend/tests/Feature/InvoiceCreatedMailBankDetailsTest.php` →
  `InvoiceSentMailBankDetailsTest.php` (Umbenennung, Klassenreferenzen
  angepasst).
- Neue Feature-Tests für `sendEmail()` (eigene Datei, analog
  `InvoicePdfTest.php`/`InvoiceCreatedMailBankDetailsTest.php`).

**Betroffener Bestandscode (Frontend):**
- `frontend/src/components/InvoiceSendDialog.vue` — neu.
- `frontend/src/views/invoices/InvoicesView.vue` — Stub-Button ersetzt,
  Dialog gemountet, neuer Handler `sendInvoiceEmail()`.
- `frontend/src/components/InvoiceDetailModal.vue` — Stub-Button ersetzt
  durch `send`-Emit.
- Neue/erweiterte Vitest-Tests: `InvoiceSendDialog.test.ts` (neu),
  `InvoicesView.test.ts`/`InvoiceDetailModal.test.ts` (erweitert).

**Nicht geändert (Non-Goals, siehe `design.md`):**
- Kein Zahlungseingang, keine Eingabemaske (Change 3).
- Kein Mahnungs-Trigger, kein Dashboard-Widget (Change 4).
- Keine neue Datenbank-Spalte/-Migration.
- Kein neuer Statusübergang — `sendEmail()` ändert `invoice.status`
  **nicht**.

## Offene Fragen für Skeptiker/User (User-Gate 1: entschieden am 2026-08-12)

1. **[ENTSCHIEDEN: kein Zeitstempel, wie empfohlen]** **["gesendet am"-Zeitstempel — bewusst weggelassen, bitte bestätigen.]**
   Der Anforderungstext (`Anforderung-Rechnungsworkflow.txt:22-23`)
   verlangt nur den Dialog und die Download-Option, keinen Zeitstempel.
   Dieser Change führt **keine** neue Spalte/Anzeige für "zuletzt
   versendet am" ein — der Status (`sent`/`reminded`/`overdue`) allein
   dokumentiert bereits, dass die Rechnung final ist; ein zusätzliches
   Versanddatum hätte aktuell keinen Konsumenten in der UI. Falls das
   für die Buchhaltung/Nachverfolgung doch benötigt wird (z. B. "wurde
   diese Rechnung überhaupt schon einmal verschickt?"), ist das ein
   eigener kleiner Folge-Change mit eigener Migration — bitte bestätigen,
   dass das für Change 2 nicht nötig ist.
2. **[ENTSCHIEDEN: synchroner Versand, wie empfohlen]** **[Synchroner statt asynchroner Mail-Versand — Abweichung vom
   Change-1-Muster, bitte bestätigen.]** Die bestehende Listener-Klasse
   implementiert aktuell `ShouldQueue`
   (`backend/app/Listeners/SendInvoiceCreatedEmail.php:13`) und nutzt
   `Mail::to(...)->queue(...)`
   (`backend/app/Listeners/SendInvoiceCreatedEmail.php:37-38`) — passend
   für das automatische Mailing bei *jeder* Rechnungserstellung
   (potenziell hohe Frequenz, kein wartender User). Der neue
   Senden-Button ist dagegen eine bewusste, seltene, blockierende
   Einzelaktion eines Admin/Trainers, der eine unmittelbare Rückmeldung
   erwartet ("wurde die Mail jetzt tatsächlich verschickt oder nicht?").
   Dieser Change stellt den Listener daher auf **synchronen** Versand um
   (`Mail::to(...)->send(...)`, kein `ShouldQueue` mehr) — das ist
   weiterhin CLAUDE.md-konform (Abschnitt 4.3 erlaubt explizit
   "sync-Driver für synchrone Ausführung"), ändert aber das bisherige
   Verhalten dieser konkreten Klasse. Bitte bestätigen, dass synchroner
   Versand mit direkter Fehlerrückmeldung (HTTP 502 + Hinweis auf
   manuellen Download bei Fehlschlag, siehe `design.md`) gegenüber
   "in die Warteschlange stellen und optimistisch Erfolg melden"
   bevorzugt wird.
3. **[ENTSCHIEDEN: PDF-Anhang, wie empfohlen]** **[PDF wird als Anhang verschickt, nicht nur beschrieben — bitte
   bestätigen.]** `InvoiceCreated::attachments()` liefert aktuell eine
   leere Liste (`backend/app/Mail/InvoiceCreated.php:71-74`), die Mail
   beschreibt die Rechnung nur als HTML-Text. Dieser Change hängt das
   generierte PDF als Anhang an (passend zum "anbei erhalten Sie Ihre
   Rechnung"-Text in
   `backend/resources/views/emails/invoice-created.blade.php:41`). Bitte
   bestätigen, dass ein PDF-Anhang gewünscht ist (Alternative: nur ein
   Link zum eingeloggten Download-Bereich — verworfen, siehe `design.md`
   Decision D6, da das eine Kunden-Anmeldung voraussetzen würde, was dem
   "einfach herunterladen"-Charakter des Anforderungstexts widerspricht).
4. **[ENTSCHIEDEN: Frontend-UI-Zweig für "keine E-Mail-Adresse" wird
   NICHT gebaut (YAGNI) — Abweichung von der Empfehlung des Architekten]**
   **["Keine E-Mail-Adresse vorhanden"-Zweig ist nach aktuellem
   Datenmodell nicht erreichbar — bitte bestätigen, dass er trotzdem
   gebaut werden soll.]** `users.email` ist in der DB als
   `string()->unique()` (implizit `NOT NULL`) definiert
   (`backend/database/migrations/0001_01_01_000000_create_users_table.php:16`),
   und jeder `Customer` ist zwingend über `user_id` mit genau einem
   `User` verknüpft
   (`backend/database/migrations/2025_12_22_184738_create_customers_table.php:18`,
   `backend/app/Models/Customer.php:82-85`). Nach aktuellem Schema hat
   **jeder** Kunde immer eine E-Mail-Adresse — der im Anforderungstext
   beschriebene "keine E-Mail-Adresse vorhanden"-Fall
   (`Anforderung-Rechnungsworkflow.txt:23`) ist damit mit echten Daten
   aktuell nicht herstellbar. Der Architekt hatte empfohlen, den
   defensiven UI-Zweig trotzdem zu bauen (geringer Aufwand,
   zukunftssicher, wörtliche Anforderungstext-Erfüllung). **Der User hat
   sich dagegen entschieden (YAGNI):** Der `InvoiceSendDialog` zeigt ab
   sofort **immer** beide Optionen ("Aus der App versenden" und "Manuell
   versenden") an, unabhängig davon, ob eine E-Mail-Adresse vorhanden
   ist — kein `hasEmail`-Zweig, kein Hinweistext im Frontend. Der Zweig
   soll erst gebaut werden, wenn Kunden ohne E-Mail-Adresse mit dem
   Datenmodell tatsächlich möglich werden (eigener Folge-Change).
   **Die serverseitige Validierung im `send-email`-Endpunkt (HTTP 422
   "Für diesen Kunden ist keine E-Mail-Adresse hinterlegt") bleibt
   bestehen** — das ist normale Input-Validierung/Defense-in-Depth am
   Backend (`InvoiceController::sendEmail()`, siehe `design.md` Decision
   D7), unabhängig von der Frontend-Entscheidung, und kostet keinen
   nennenswerten Mehraufwand.
5. **[ENTSCHIEDEN: Umbenennung wird durchgeführt, wie empfohlen]** **[Umbenennung von `InvoiceWasCreated`/`SendInvoiceCreatedEmail`/
   `InvoiceCreated` — bitte bestätigen.]** Die drei Klassen bzw. die
   Blade-View tragen weiterhin den Namen "Created", obwohl sie nach
   diesem Change ausschließlich durch den expliziten Senden-Button
   ausgelöst werden, nicht mehr durch das Erstellen einer Rechnung. Die
   Umbenennung (siehe `design.md` Decision D5) folgt reiner
   Namensklarheit (kein funktionaler Zwang) und berührt eine bestehende,
   bereits vollständig grüne Testdatei
   (`backend/tests/Feature/InvoiceCreatedMailBankDetailsTest.php`).
   Alternative: Namen unverändert lassen, um den Diff kleiner zu halten.
   Bitte entscheiden.
6. **[ENTSCHIEDEN: Senden-Dialog gilt für alle drei Status, wie
   empfohlen]** **[Senden-Dialog auch für `reminded`/`overdue`, nicht nur `sent` —
   Bestätigung der Change-1-Vorentscheidung.]** Change 1 hat den
   (damals deaktivierten) Senden-Button bereits für die Status `sent`,
   `reminded` und `overdue` sichtbar gemacht
   (`SENDABLE_STATUSES` in `InvoicesView.vue:218` und
   `InvoiceDetailModal.vue:216`). Dieser Change übernimmt das
   unverändert und macht den Button für **alle drei** Status
   funktional (wiederholter Versand z. B. bei einer überfälligen oder
   bereits gemahnten Rechnung ist damit weiterhin möglich — sinnvoll,
   falls der Kunde die ursprüngliche Mail verloren hat). Der
   Anforderungstext selbst beschreibt den Senden-Button nur im Abschnitt
   "Offen/Verschickt"
   (`Anforderung-Rechnungsworkflow.txt:14-23`). Bitte bestätigen, dass
   die Wiederverwendung für `reminded`/`overdue` gewünscht ist.
