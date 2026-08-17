# Triage: Mehrstufiges Mahnwesen mit Mahngebühren + Dashboard (Change 4)

**Pfad:** gross
**Geschätzter Umfang:** ca. 15–20 Dateien betroffen (Schätzung, siehe unten), PHP (Laravel-Backend) + TypeScript (Vue-Frontend)
**Risiko:** hoch — Mahngebühren berühren die (nach Versand steuerrechtlich fixierte) Rechnungssumme, ein bestehender automatisierter Cron-Mailer widerspricht dem geforderten Bestätigungs-Flow, öffentliche API (`InvoiceResource`) wird erweitert.
**Klarheit:** mehrdeutig — mehrere fachliche Kernfragen (Stufenanzahl, Gebührenhöhe, Verbuchung, Verhältnis zum bestehenden Cron-Job) sind offen.

## Anforderung (Zusammenfassung)

Change 4 (`add-invoice-dunning-dashboard`) soll das im ursprünglichen
Anforderungstext nur einstufig angedeutete Mahnwesen als **mehrstufigen**
Prozess mit **Mahngebühren** umsetzen (bindende User-Entscheidung vom
2026-08-12, vergrößert den Scope gegenüber der Ersteinschätzung in
`openspec/triage/20260812-rechnungsworkflow.md` deutlich). Trainer/Admin
sollen im Dashboard überfällige/gemahnte Rechnungen sehen und pro Rechnung
eine Mahnung mit Bestätigungsdialog auslösen können; die Überfällig-Erkennung
bleibt Anzeigezeit-basiert (keine neue Cronjob-Pflicht für die reine
Statusanzeige, ebenfalls bindende Entscheidung). Baut auf den bereits
gemergten/offenen Changes 1–3 auf (`finalize()`/`cancel()`/Send-Email-Flow/
Payment-Endpunkte existieren bereits).

## Bestandscode-Analyse (Ist-Zustand, verifiziert im Code auf `main`)

**Bereits vorhanden (reines Datenmodell, aus Change 1, OHNE Trigger-Logik):**

- `backend/database/migrations/2026_08_12_130002_create_invoice_dunnings_table.php:14-23`
  — Tabelle `invoice_dunnings` existiert bereits: `invoice_id`, `level`
  (`unsignedTinyInteger`), `dunning_date`, `fee_amount` (`decimal(10,2)`,
  Default 0). Struktur ist **bereits mehrstufig-tauglich** (Level-Spalte
  vorhanden).
- `backend/app/Models/InvoiceDunning.php:1-66` — Model mit `fillable`,
  Casts, `invoice()`-Relation. Keine Business-Logik.
- `backend/app/Models/Invoice.php:108-111,166-179` — `dunnings()`-Relation,
  `getDunningLevelAttribute()` (höchste Stufe), `getRemindedAtAttribute()`
  (jüngstes Datum). Rein lesend.
- `backend/database/migrations/2026_08_12_130001_add_reminded_status_to_invoices_table.php`
  — Status-Enum bereits um `reminded` erweitert (MySQL/Postgres/SQLite je
  eigener Zweig).
- `backend/app/Http/Resources/InvoiceResource.php:51-52` — exponiert
  `remindedAt`/`dunningLevel` (Skalare, keine volle Mahnhistorie/Gebühren
  pro Stufe).
- `backend/app/Policies/InvoicePolicy.php:48,126` — `reminded` bereits Teil
  der Sichtbarkeits-/Storno-Whitelists.
- `backend/app/Http/Controllers/Api/InvoiceController.php:56,80` —
  `SENDABLE_STATUSES` und Customer-Sichtbarkeitsfilter enthalten bereits
  `reminded`.
- `frontend/src/views/invoices/InvoicesView.vue:12,91-92,257,268,275,489,501`
  — Status-Filter-Option "Gemahnt", Anzeige "Gemahnt am {remindedAt}",
  `reminded` bereits Teil von `SENDABLE_STATUSES`/`CANCELLABLE_STATUSES`/
  `PAYABLE_STATUSES`, Label/Farbklasse vorhanden.
- Tests: `backend/tests/Unit/Models/InvoiceDunningTest.php`,
  `backend/tests/Feature/DatabaseStructureTest.php:147-151`,
  `backend/tests/Feature/InvoiceApiTest.php:758-784` — decken nur
  Modell-/Resource-Ebene ab (Factory, Relation, `dunning_level`/
  `reminded_at`-Ableitung). **Kein Test erzeugt eine Mahnung über einen
  Endpunkt.**

**Fehlt vollständig (Trigger-Logik, verifiziert per Grep — keine Treffer
außer den oben genannten Modell-/Test-Dateien):**

- Kein Controller-Endpunkt, keine Route (`backend/routes/api.php:182-187`
  kennt nur `finalize`, `cancel`, `send-email`, `overdue`, aber kein
  `remind`/`dunning`).
- Keine Policy-Methode `remind()`/`dun()`.
- Kein Service analog zu `InvoicePaymentRecorder`
  (`backend/app/Services/InvoicePaymentRecorder.php:57-127`), der eine
  `InvoiceDunning` anlegt, den Status auf `reminded` setzt und dabei die
  Invoice-Zeile sperrt (`lockForUpdate()`-Muster wie dort vorexerziert).
- Keine Gebührenverbuchung: `fee_amount` wird nirgends geschrieben/gelesen
  außer in Tests mit manuell gesetzten Factory-Werten. Keine Verknüpfung
  zur `total_amount`/`remaining_balance`-Berechnung
  (`backend/app/Models/Invoice.php:148-161`).
- Kein Dashboard-Widget: `frontend/src/views/DashboardView.vue` zeigt nur
  eine Gesamt-Rechnungszahl-Kachel (Zeile 68-78), keine Liste
  überfälliger/gemahnter Rechnungen. Backend-seitig liefert
  `backend/app/Http/Controllers/Api/DashboardController.php:57-68` bereits
  ein etabliertes Muster für weitere Kennzahlen (`pendingDogRequests` etc.),
  aber keine überfällig/gemahnt-Liste.
- Kein Bestätigungsdialog/Button "Mahnen" in
  `frontend/src/views/invoices/InvoicesView.vue` (nur Lösch-/Freigabe-/
  Storno-Bestätigungen via `window.confirm()`, Zeilen 411, 428, 445 — dieses
  Muster ist etabliert und für den Mahnungs-Trigger direkt wiederverwendbar).

**Konfliktpotenzial — ungeprüfte Referenz, jetzt verifiziert:**

- `backend/app/Console/Commands/SendPaymentReminders.php:1-94` +
  `backend/app/Mail/PaymentReminder.php` + `backend/routes/console.php:21-39`
  — Es existiert bereits ein **automatischer, unbeaufsichtigter** Cron-Job
  (`Schedule::command('invoices:send-reminders --days=7')` täglich 09:00,
  zusätzlich `--days=14` an Wochentagen), der bei Überfälligkeit ohne
  Trainer-/Admin-Bestätigung eine E-Mail verschickt. Er setzt **keinen**
  Status um, legt **keine** `InvoiceDunning` an und kennt keine Gebühr —
  läuft komplett am neuen Datenmodell vorbei. Das widerspricht dem
  Anforderungstext ("Wenn eine Mahnung ausgelöst werden soll, soll der
  Trainer/Admin entsprechend informiert und gefragt werden, ob das
  ausgeführt werden soll") — dort ist ein manueller Bestätigungsschritt
  gefordert, kein vollautomatischer Massen-Mailversand. Muss im
  Architektur-Entwurf explizit aufgelöst werden (ablösen, umwidmen als
  "unverbindliche Vorab-Erinnerung" vor der ersten offiziellen Mahnstufe,
  oder abschalten).
- Mahngebühr vs. eingefrorene Rechnung: `InvoicePolicy::update()`
  (`backend/app/Policies/InvoicePolicy.php:68-72`) erlaubt Änderungen nur
  im Status `draft` — eine bereits versendete/finalisierte Rechnung gilt
  laut Change 1 als inhaltlich fix (`design.md` von
  `add-invoice-status-lifecycle`, hier nicht erneut gelesen, aber die
  Policy-Kommentare referenzieren diese Regel konsistent). Eine Mahngebühr,
  die `total_amount` der bestehenden Rechnung erhöht, würde dieser Regel
  widersprechen — ähnlich der bereits gelösten Problematik bei Storno
  (dort: neues Dokument statt Mutation). Das ist eine echte
  Architektur-Entscheidung, keine Nebensächlichkeit.

## Komplexitätsbewertung

Fachlich neu hinzukommende Teile (Backend + Frontend je Punkt):

1. **Mahnungs-Trigger-Endpunkt** (Service analog `InvoicePaymentRecorder`,
   Controller-Aktion, Policy-Methode, Route) — inkl. Sperrlogik gegen
   Nebenläufigkeit (Muster bereits etabliert, aber neu zu bauen).
2. **Mehrstufigkeit**: wie viele Stufen, welche Statuswechsel-Regeln
   zwischen den Stufen (aktuell nur ein einziger `reminded`-Status im
   Enum — die Stufenzahl steckt ausschließlich in der `level`-Spalte von
   `invoice_dunnings`, das Invoice-`status`-Feld unterscheidet nicht
   zwischen 1./2./3. Mahnung).
3. **Mahngebühren-Verbuchung**: fachliche/rechtliche Kernfrage, siehe
   Konfliktpotenzial oben — Betrifft ggf. `total_amount`/
   `remaining_balance`, evtl. Wiederverwendung/Erweiterung von
   `InvoicePaymentRecorder` oder ein neues Dokumentmuster analog Storno.
4. **Dashboard-Widget**: neue Backend-Aggregation
   (`DashboardController`) + neue Vue-Komponente/Composable +
   Einbindung in `DashboardView.vue`.
5. **Bestätigungsdialog für Trainer/Admin**: UI-seitig klein (bestehendes
   `window.confirm()`-Muster), aber fachlich an Punkt 1–3 gekoppelt.
6. **Auflösung des bestehenden Cron-Mailers**: Entscheidung nötig, ob
   `SendPaymentReminders`/`invoices:send-reminders` abgeschaltet, ersetzt
   oder als separate Vorstufe erhalten bleibt — berührt
   `backend/routes/console.php` (Scheduler-Konfiguration, Shared-Hosting-
   relevant gemäß CLAUDE.md 4.3) und ggf. `PaymentReminder`-Mail.

Anders als Change 1–3 (jeweils eine klar abgegrenzte, additive Fähigkeit)
enthält Change 4 mit der Mehrstufigkeit+Gebühren-Entscheidung nun eine
echte Architektur-Frage (Gebühr vs. eingefrorene Rechnung) und einen
Bestandskonflikt (automatischer Cron-Mailer vs. gefordertem manuellem
Bestätigungsschritt), zusätzlich zu mehreren Sprachen (PHP + TypeScript)
und mehreren Modulen (Model/Service/Controller/Policy/Route/Resource/Mail/
Console/Settings möglich + Dashboard-Backend + zwei Frontend-Views).

→ Bewertung: **gross**. Der Architekt sollte prüfen, ob eine weitere
Vorab-Zerlegung innerhalb von Change 4 sinnvoll ist (z. B. Kern-Mahnlogik
zuerst, Dashboard-Widget als eigener Task-Block), das ist aber keine
zwingende Aufteilung in mehrere openspec-Changes wie bei der
Grobzerlegung vom 2026-08-12 — die Entscheidung liegt beim Architekten
gemäß CLAUDE.md-Vorgabe für "gross".

## Rückfragen an den User

- **Anzahl Mahnstufen:** Wie viele Stufen sind vorgesehen (z. B. 1./2./3.
  Mahnung)? Fix im Code oder konfigurierbar (z. B. über `Setting`)?
- **Gebührenhöhe je Stufe:** Fester Betrag pro Stufe (z. B. 5 €/10 €/15 €),
  prozentual vom Rechnungsbetrag, oder frei einzugebender Betrag beim
  Auslösen der Mahnung im Bestätigungsdialog?
- **Verbuchung der Mahngebühr:** Soll die Gebühr `total_amount` der
  bestehenden (bereits finalisierten) Rechnung erhöhen — was der Regel
  "Rechnung nach Versand inhaltlich fix" widerspricht — oder als eigene
  Zusatzposition/eigenes Dokument (analog zur Stornorechnung aus Change 1)
  erfasst werden? Diese Entscheidung bestimmt maßgeblich den Zuschnitt von
  Change 4.
- **Bestehender Cron-Mailer:** `backend/app/Console/Commands/
  SendPaymentReminders.php` verschickt bereits automatisch (ohne
  Bestätigung) Erinnerungs-E-Mails bei 7/14 Tagen Überfälligkeit
  (`backend/routes/console.php:21-39`), ohne Status-/Datenmodell-Bezug.
  Soll dieser Job abgeschaltet, durch den neuen interaktiven
  Mahnungs-Trigger ersetzt, oder als unverbindliche "Vor-Erinnerung" (ohne
  offizielle Mahnstufe/Gebühr) parallel weiterlaufen?
- **E-Mail-Versand bei Mahnung:** Soll das Auslösen einer Mahnung
  automatisch eine E-Mail an den Kunden verschicken (mit Hinweis auf
  Mahngebühr), oder ist der Trigger rein intern (Statuswechsel +
  Datensatz + Anzeige)?
- **Obergrenze:** Gibt es eine letzte Mahnstufe, nach der keine weitere
  Mahnung mehr über die App ausgelöst werden kann (z. B. Übergabe an
  Inkasso außerhalb der App)?

## Empfohlene nächste Aktion

`@architect` (Modus A) mit dem Auftrag, `add-invoice-dunning-dashboard`
als openspec-Change auszuarbeiten (`proposal.md`, `design.md`,
`tasks.md`), basierend auf dieser Triage-Datei. Vor Beginn der
Architektenarbeit sollten die obigen Rückfragen — insbesondere zur
Verbuchung der Mahngebühr und zum bestehenden Cron-Mailer — mit dem User
geklärt werden, da beide den Zuschnitt und die Task-Reihenfolge direkt
bestimmen. Der Architekt sollte zusätzlich `design.md` von
`add-invoice-status-lifecycle` (Change 1, bereits archiviert) auf die dort
getroffene "Rechnung nach Versand inhaltlich fix"-Entscheidung hin prüfen,
bevor die Mahngebühren-Verbuchung festgelegt wird.
