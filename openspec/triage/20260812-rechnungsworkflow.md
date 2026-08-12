# Triage: Rechnungsworkflow (Statuslebenszyklus, Versand, Zahlung, Mahnwesen)

**Pfad:** gross
**Geschätzter Umfang:** ca. 20–30 Dateien betroffen (Schätzung, siehe unten), PHP (Laravel-Backend) + TypeScript (Vue-Frontend)
**Risiko:** hoch — Datenmodell (Migrationen, Status-Enum), Rechnungsnummern-Vergabe (steuerlich relevant), Rollen-/Sichtbarkeitsregeln, ggf. Cron/Scheduler für Überfälligkeits-/Mahnlogik.
**Klarheit:** mehrdeutig — mehrere Teilanforderungen sind nicht eindeutig spezifiziert (siehe Rückfragen).

## Anforderung (Zusammenfassung)

Der Rechnungs-Lebenszyklus soll vollständig als State Machine abgebildet
werden: Entwurf → Offen/Verschickt → Bezahlt (bzw. Überfällig → Gemahnt),
zusätzlich Storniert als Seitenzweig. Jeder Status hat definierte
Listen-Buttons und Sichtbarkeitsregeln. Neu hinzu kommen: ein Versand-Dialog
(App-Mail vs. manueller PDF-Download), eine Eingabemaske für den
Zahlungseingang, ein Dashboard-Widget für überfällige/gemahnte Rechnungen,
ein Mahnungs-Trigger mit Bestätigungsdialog für Trainer/Admin, sowie
verschärfte Sichtbarkeitsregeln für Kunden (nur Offen/Bezahlt/Überfällig/
Gemahnt sichtbar, nicht Entwurf).

## Bestandscode-Analyse (Ist-Zustand)

- `backend/app/Models/Invoice.php:1` — Model existiert bereits mit
  `status`-Feld (String, kein Enum-Cast), Helper `isPaid()`, `isOverdue()`
  (rein berechnet aus `due_date`, nicht persistiert), Scopes `unpaid`,
  `overdue`, `paid`.
- `backend/database/migrations/2025_12_22_185107_create_invoices_table.php:18`
  — DB-Enum bereits vorhanden: `['draft', 'sent', 'paid', 'overdue',
  'cancelled']`. **Fehlt:** ein "gemahnt/reminded"-Status sowie ein Feld für
  das Mahndatum.
- `backend/app/Http/Requests/StoreInvoiceRequest.php:85` — Die
  `invoice_number` wird **bereits beim Erstellen** (auch im Entwurf,
  Status `draft`) fortlaufend generiert (`generateInvoiceNumber()`,
  Format `RE-{Jahr}-{laufende Nummer}`). Das widerspricht der neuen
  Anforderung, wonach die Nummer erst bei "Offen" final vergeben werden
  soll — ein Entwurf soll laut Anforderungstext frei löschbar/änderbar
  sein, ohne eine Nummernlücke zu reißen. **Ungeprüfte Referenz/Konflikt:**
  hier ist eine echte Verhaltensänderung nötig, kein additiver Zusatz.
- `backend/app/Models/Payment.php` + `backend/database/migrations/
  2025_12_22_185135_create_payments_table.php:14` — Payment-Model und
  -Tabelle existieren bereits (`payment_date`, `amount`, `payment_method`,
  `status`), unterstützen grundsätzlich auch Teilzahlungen. Es gibt aber
  **keine Frontend-Eingabemaske**, die diese Tabelle befüllt — aktuell
  setzt `InvoiceController::markAsPaid()`
  (`backend/app/Http/Controllers/Api/InvoiceController.php:184`) nur
  `status = paid` und `paid_date = now()`, ohne Betrag/Zahlungsart zu
  erfassen und ohne einen `Payment`-Datensatz anzulegen.
- `backend/app/Events/InvoiceWasCreated.php` +
  `backend/app/Listeners/SendInvoiceCreatedEmail.php:1` +
  `backend/app/Mail/InvoiceCreated.php` — Es existiert bereits eine
  Mail-Infrastruktur, aber sie feuert **beim Erstellen** der Rechnung
  (auch im Entwurf!), nicht beim expliziten "Senden"-Button aus der neuen
  Anforderung. Für den neuen Versand-Flow (Dialog "App-Mail vs. manueller
  Download") ist eine neue, vom Erstellen entkoppelte Aktion nötig; das
  bestehende Auto-Mailing beim Erstellen widerspricht vermutlich der neuen
  Anforderung (Versand soll ein bewusster Schritt im Status "Offen" sein).
- `backend/app/Http/Controllers/Api/InvoiceController.php:1` — vorhandene
  Endpunkte: `index`, `store`, `show`, `update`, `destroy`, `markAsPaid`,
  `overdue`, `downloadPdf`. **Fehlen:** `send` (Versand-Aktion mit
  Statuswechsel zu "sent" + fixer Nummer), `cancel`/Storno-Endpunkt,
  `remind`/Mahnung-Endpunkt.
- `frontend/src/views/invoices/InvoicesView.vue:84-88` — Listen-Buttons
  aktuell: PDF (immer), Bearbeiten (nur `draft`), Bezahlt-Button (`draft`
  oder `sent`). **Es gibt aktuell keine Buttons für Löschen, Senden oder
  Stornieren** in der Liste — diese müssen neu gebaut werden. Auch keine
  Anzeige von Zahlungseingangsdatum oder Mahndatum in der Tabelle.
- `frontend/src/components/InvoiceDetailModal.vue`,
  `InvoiceFormModal.vue` — vorhanden, decken aktuell nur Anzeige/Bearbeiten
  ab, kein Versand-Dialog, keine Zahlungseingabe, kein Storno/Mahnung-UI.
- `frontend/src/views/DashboardView.vue` — existiert bereits als
  Dashboard-Basis, aber ohne Widget für überfällige/gemahnte Rechnungen
  (ungeprüft im Detail, nur Existenz verifiziert).
- Rollenfilterung existiert bereits grundsätzlich in
  `InvoiceController::index()` (Trainer sieht nur eigene Kunden, Kunde nur
  eigene Rechnungen) — die zusätzliche Einschränkung "Kunde sieht nur
  Offen/Bezahlt/Überfällig/Gemahnt (nicht Entwurf)" ist aber **nicht**
  implementiert und muss ergänzt werden.
- Storno-Logik (`cancelled` im Enum vorhanden) ist im Model als Status-Wert
  vorgesehen, aber es gibt **keine** Storno-Aktion/Endpoint/UI. Unklar, ob
  reine Statusänderung reicht oder ob (steuerlich korrekt) eine
  Stornorechnung/Korrekturrechnung als eigenes Dokument nötig ist (im
  Anforderungstext angedeutet: "Fehler erfordern eine Stornierung oder
  Korrekturrechnung", aber im Abschnitt "Storniert" nur simple
  Statusanzeige beschrieben).
- Überfällig-Erkennung ist aktuell **rein lesend** berechnet
  (`isOverdue()`/`scopeOverdue()` vergleichen live gegen `due_date`), nicht
  persistiert. Für ein Dashboard-Widget reicht das potenziell aus (Query
  zur Anzeigezeit), aber die Anforderung "App soll im Dashboard [...]
  darstellen" plus Mahnungs-Trigger braucht vermutlich einen periodischen
  Check — unter den Shared-Hosting-Regeln (CLAUDE.md 4.3) nur per
  `schedule:run`-Cron möglich, kein Daemon.

## Komplexitätsbewertung

Die Anforderung enthält mindestens sechs fachlich unterscheidbare, aber am
gemeinsamen Status-Kern hängende Teilfunktionen:

1. **Status-State-Machine** (Kern): neuer Status "gemahnt", Buttons je
   Status, Rechnungsnummern-Vergabe erst bei "Offen" (Verhaltensänderung
   an bestehender, produktiv genutzter Logik), Rollen-Sichtbarkeit für
   Kunden.
2. **Versand-Flow**: Dialog App-Mail vs. manueller Download, Entkopplung
   vom bisherigen Auto-Mailing bei Erstellung.
3. **Zahlungseingangs-Erfassung**: neue Eingabemaske, Anbindung an
   bestehendes `Payment`-Model, Anzeige in der Liste.
4. **Überfällig-Erkennung + Dashboard-Widget**.
5. **Mahnungs-Trigger** mit Bestätigungsdialog für Trainer/Admin,
   Mahndatum-Speicherung und -Anzeige.
6. **Storno-Flow**: Button, Bestätigung, Anzeige — offen ob nur
   Statusänderung oder vollständige Stornorechnung.

Jede dieser sechs Teilfunktionen berührt Backend (Model/Migration/
Controller/Request/Policy/ggf. Mail) **und** Frontend (View/Modal/
Composable). Die Kernanforderung (1) ist Voraussetzung für alle anderen
(State Machine bestimmt, welche Aktionen in welchem Status überhaupt
zulässig sind) — ein reiner "klein"- oder "standard"-Zuschnitt würde das
Risiko bergen, dass Folge-Changes auf einer instabilen Statuslogik
aufbauen. Zusätzlich wird eine bestehende, bereits produktiv laufende
Verhaltensweise geändert (Rechnungsnummern-Vergabe, Auto-Mail bei
Erstellung) — das ist ein Eingriff in bestehende fachliche/rechtliche
Logik, kein rein additives Feature.

→ Bewertung: **gross**. Architektur-Eingriff (Statusmodell, Nummernvergabe),
mehrere Sprachen (PHP + TypeScript), mehrere Module, mittlere bis niedrige
Klarheit in Teilen der Anforderung.

## Empfohlene Change-Aufteilung

Empfehlung: **ein Kern-Change plus mehrere darauf aufbauende Changes**,
nicht ein einziger Monolith-Change (zu groß für einen Task-Batch, schlecht
review- und testbar) und nicht sechs unabhängige Changes (Status-Logik ist
gemeinsamer Nenner, parallele Changes würden sich gegenseitig blockieren).

1. **`add-invoice-status-lifecycle`** (Kern, zuerst)
   - Status-Enum erweitern (neuer Wert für "gemahnt"/reminded),
     Migration für Mahndatum-Feld.
   - Rechnungsnummern-Vergabe von "bei Erstellung" auf "bei Übergang zu
     Offen/Versendet" umstellen (betrifft `StoreInvoiceRequest`,
     `Invoice`-Model, ggf. neuen `sent`-Endpunkt-Vorgriff).
   - Listen-Buttons je Status (PDF/Bearbeiten/Löschen bei Entwurf;
     PDF/Senden/Stornieren bei Offen; PDF/Stornieren + Zahlungsdatum bei
     Bezahlt; Storno-Anzeige; Mahn-Anzeige inkl. Datum).
   - Rollen-Sichtbarkeit: Kunde sieht nur Offen/Bezahlt/Überfällig/Gemahnt.
   - Löschen-Button/Endpunkt für Entwürfe (falls nicht schon über
     `destroy()` abgedeckt — prüfen, ob Policy das für alle Rollen erlaubt).
2. **`add-invoice-send-flow`** (baut auf 1 auf)
   - Senden-Button mit Dialog (App-Mail wenn E-Mail vorhanden, sonst/
     zusätzlich manueller PDF-Download), Statuswechsel Entwurf → Offen,
     Entkopplung des bestehenden Auto-Mailings bei Erstellung.
3. **`add-invoice-payment-entry`** (baut auf 1 auf)
   - Eingabemaske für Zahlungseingang (Betrag, Datum, Zahlungsart) unter
     Nutzung des bestehenden `Payment`-Models, Anzeige des
     Zahlungseingangsdatums in der Liste, Ablösung des aktuellen
     "Bezahlt"-Buttons ohne Eingabemaske.
4. **`add-invoice-dunning-dashboard`** (baut auf 1 auf)
   - Überfällig-Erkennung/-Anzeige, Dashboard-Widget für
     überfällige/gemahnte Rechnungen, Mahnungs-Trigger mit
     Bestätigungsdialog für Trainer/Admin, Mahndatum-Speicherung.
5. **Storno-Flow**: Empfehlung, dies **in Change 1** zu integrieren (da nur
   Statuswechsel + Button/Bestätigung nötig ist, sofern keine
   Stornorechnung/Korrekturrechnung als eigenes Dokument gefordert ist —
   siehe Rückfrage unten). Falls doch ein vollwertiges
   Stornorechnungs-Dokument verlangt wird, eigener Change
   `add-invoice-credit-note`.

Die konkrete Zerlegung und Reihenfolge legt der Architekt verbindlich in
`design.md`/`tasks.md` fest; die obige Gliederung ist ein Vorschlag zur
Vorab-Zerlegung, wie es CLAUDE.md für "gross" vorsieht.

## Rückfragen an den User

- **Rechnungsnummer-Vergabe:** Aktuell wird die `invoice_number` bereits
  beim Erstellen eines Entwurfs vergeben (`StoreInvoiceRequest::
  generateInvoiceNumber()`). Soll das umgestellt werden, sodass Entwürfe
  keine Nummer haben und diese erst beim Versand fix vergeben wird? Das
  ist ein Verhaltensbruch für bestehende Daten/Workflows — bitte
  bestätigen.
- **Storno:** Reicht eine einfache Statusänderung auf "storniert" (wie im
  Abschnitt "Storniert" beschrieben), oder wird — wie im einleitenden Satz
  angedeutet ("Fehler erfordern eine Stornierung oder Korrekturrechnung")
  — ein vollwertiges Stornorechnungs-/Korrekturdokument mit eigener
  Nummer benötigt?
- **Mahnstufen:** Ist eine einzelne Mahnstufe mit einem Mahndatum
  ausreichend, oder werden mehrstufige Mahnungen (1./2./3. Mahnung, ggf.
  mit Mahngebühren) benötigt? Der Anforderungstext spricht nur von einer
  Mahnung/einem Datum.
- **Automatisierung "Überfällig"/Mahnung:** Soll der Überfällig-Status rein
  zur Anzeigezeit berechnet werden (wie aktuell bereits über
  `Invoice::scopeOverdue()`), oder soll es einen periodischen
  Hintergrundjob geben, der den Status persistiert und z. B. Trainer/Admin
  proaktiv benachrichtigt? Bei einem Hintergrundjob gelten die
  Cron-Regeln aus CLAUDE.md 4.3 (`schedule:run` statt Daemon).
- **Benachrichtigung beim Mahnungs-Trigger:** Reicht ein Dialog im UI beim
  Öffnen der App/des Dashboards durch Trainer/Admin, oder wird zusätzlich
  eine aktive Benachrichtigung (E-Mail, Push) erwartet?
- **Zahlungseingang — Teilzahlungen:** Das bestehende `Payment`-Model
  unterstützt mehrere Zahlungen pro Rechnung. Verlangt die Anforderung
  ("Eingabemaske" Singular) nur eine einzelne Vollzahlung, oder sollen
  Teilzahlungen mit fortlaufender Reststand-Anzeige unterstützt werden?
- **Bestehendes Auto-Mailing:** Aktuell wird beim Erstellen jeder Rechnung
  (auch Entwurf) automatisch eine E-Mail an den Kunden verschickt
  (`InvoiceWasCreated`-Event). Soll dieses Verhalten entfernt/verschoben
  werden auf den expliziten "Senden"-Schritt, oder bleibt es zusätzlich
  bestehen?
- **Status-Bezeichnung "Offen (Gesendet)":** Der DB-Wert heißt aktuell
  `sent`. Reicht eine reine UI-Label-Anpassung ("Offen/Verschickt"), oder
  soll der DB-Wert selbst umbenannt werden (Migration + Datenanpassung
  nötig)?

## Empfohlene nächste Aktion

`@architect` (Modus A) mit dem Auftrag, basierend auf dieser Triage-Datei
**zunächst nur Change 1 (`add-invoice-status-lifecycle`)** als
openspec-Change auszuarbeiten (`proposal.md`, `design.md`, `tasks.md`),
inklusive einer Vorab-Zerlegungsübersicht für die Folge-Changes 2–4 im
`design.md`. Vor Beginn der Architektenarbeit sollten die oben genannten
Rückfragen — insbesondere zur Rechnungsnummern-Vergabe, zum Storno-Umfang
und zur Automatisierung von Überfällig/Mahnung — mit dem User geklärt
werden, da sie den Zuschnitt von Change 1 direkt beeinflussen.
