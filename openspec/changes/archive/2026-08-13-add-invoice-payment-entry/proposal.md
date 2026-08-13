## Why

Change 3 von 4 im Rechnungsworkflow-Umbau (`add-invoice-status-lifecycle` →
`add-invoice-send-flow` → **`add-invoice-payment-entry`** →
`add-invoice-dunning-dashboard`). Der Anforderungstext
(`Anforderung-Rechnungsworkflow.txt:25-27`, Abschnitt "Bezahlt") verlangt:
"Die Rechnung wurde bezahlt. In der Liste soll der Zahlungseingang
angezeigt werden. Hierzu muss eine Eingabemaske erstellt werden."

**Ist-Zustand — der aktuelle "Bezahlt"-Weg ist sowohl unvollständig als
auch fachlich fehlerhaft:**

- `InvoiceController::markAsPaid()`
  (`backend/app/Http/Controllers/Api/InvoiceController.php:218-240`) setzt
  bei einem Klick auf "Als bezahlt markieren"
  (`frontend/src/components/InvoiceDetailModal.vue:163-165`, nur ein
  `confirm()`-Dialog in `InvoicesView.vue:332-347`, **keine** Eingabemaske)
  direkt `status = 'paid'` und `paid_date = now()` auf der `Invoice` — **ohne
  jemals einen `Payment`-Datensatz anzulegen**.
- `Invoice::getTotalPaidAttribute()` (`backend/app/Models/Invoice.php:148-153`)
  und `getRemainingBalanceAttribute()` (`Invoice.php:158-161`) summieren aber
  ausschließlich `payments()->where('status','completed')->sum('amount')`.
  **Konsequenz: nach einem `markAsPaid()`-Aufruf gilt `isPaid() === true`,
  während `totalPaid === 0` und `remainingBalance === total_amount` bleiben**
  — ein gebrochenes Invariant, das der bindenden Entscheidung aus Change 1
  widerspricht: Teilzahlungen sind unterstützt, das bestehende `Payment`-
  Datenmodell bleibt dafür unverändert nutzbar, das Statusmodell ist damit
  bereits kompatibel
  (`openspec/changes/archive/2026-08-12-add-invoice-status-lifecycle/acceptance.md:139-141`,
  Punkt 6: "Teilzahlungen unterstützt. … Statusmodell kompatibel, UI folgt
  in Change 3."). Die Triage-Datei
  (`openspec/triage/20260812-rechnungsworkflow.md:195-198`) hatte die
  Teilzahlungsfrage ursprünglich nur als offene Frage aufgeworfen — die
  bindende Entscheidung dazu fiel erst in Change 1s User-Gate, nicht in der
  Triage selbst.
- Das bereits vorhandene `Payment`-Model
  (`backend/app/Models/Payment.php`) + die vollständige
  `PaymentController`/`PaymentPolicy`/`PaymentResource`/
  `StorePaymentRequest`-Infrastruktur
  (`backend/app/Http/Controllers/Api/PaymentController.php`) unterstützen
  Teilzahlungen bereits auf Datenebene (`PaymentController::store()`,
  Zeile 87-105, aktualisiert den Rechnungsstatus korrekt über
  `$invoice->payments()->completed()->sum('amount')`) — **aber diese
  Endpunkte haben aktuell keinen Frontend-Konsumenten**. Der einzige
  bestehende Frontend-Zugriff auf `/api/v1/payments/*`
  (`frontend/src/api/paypal.ts`) betrifft ausschließlich die
  kundenseitige PayPal-Zahlung
  (`frontend/src/components/PaymentModal.vue`), nicht die manuelle
  Erfassung durch Admin/Trainer.
- `frontend/src/components/InvoiceDetailModal.vue:134-144` rendert zwar
  bereits einen "Zahlungen"-Block für `invoice.payments`, greift dabei
  aber auf `payment.payment_date`/`payment.payment_method` (snake_case)
  zu, während `PaymentResource::toArray()`
  (`backend/app/Http/Resources/PaymentResource.php:25-28`) camelCase
  liefert (`paymentDate`, `paymentMethod`) — dieser Block hat also
  vermutlich **noch nie korrekt gerendert**, da bislang ohnehin nie ein
  `Payment`-Datensatz über die UI erzeugt wurde.
- `PaymentResource::toArray()` referenziert zusätzlich bereits ein Feld
  `notes` (Zeile 29), das weder in der Migration
  (`backend/database/migrations/2025_12_22_185135_create_payments_table.php`)
  noch in `Payment::$fillable` (`Payment.php:37-44`) existiert — liefert
  aktuell immer `null` (kein Laufzeitfehler, da Eloquent unbekannte
  Attribute stillschweigend als `null` auflöst, aber toter Code).

Dieser Change baut die tatsächliche Eingabemaske, macht die bestehende
`Payment`-API zu ihrem ersten echten Konsumenten, behebt die gefundene
Dateninkonsistenz von `markAsPaid()` und ergänzt die fehlende
Zahlungseingangs-/Restbetrag-Anzeige in Liste und Detailansicht.

## What Changes

- **Breaking: `InvoiceController::markAsPaid()` entfällt vollständig**
  (Controller-Methode, Route `POST /invoices/{invoice}/mark-paid`,
  `InvoicePolicy::markAsPaid()`, Frontend-Button/-Emit/-Handler in
  `InvoicesView.vue`/`InvoiceDetailModal.vue`, die vier zugehörigen Tests
  in `backend/tests/Feature/InvoiceApiTest.php:410-474`) — siehe
  `design.md` Decision D1 für die Begründung (Datenintegrität, DRY).
  Ersetzt durch die neue Eingabemaske mit Komfort-Vorbelegung "volle
  Restsumme".
- **Neue Spalte `payments.notes`** (nullable, additiv) macht das bereits
  in `PaymentResource` vorgesehene, aber bislang immer-`null`-Feld nutzbar
  — dient als "Referenz"-Freitextfeld gemäß Anforderungstext ("ggf. …
  Referenz").
- **Neuer Service `App\Services\InvoicePaymentRecorder`** kapselt
  "Zahlung anlegen + Rechnung ggf. atomar auf `paid` setzen" in einem
  `DB::transaction()` mit `lockForUpdate()` auf der Rechnung — behebt eine
  Race Condition, die aktuell unabhängig und ungesichert an drei Stellen
  **derselben Datei** existiert: `PaymentController::store()` (Zeile
  93-102), `PaymentController::markAsCompleted()` (Zeile 166-175) und
  `PaymentController::handlePaymentCaptureCompleted()` (Zeile 328-350, der
  PayPal-Webhook-Pfad) — alle drei werden in T03 auf den neuen Service
  umgestellt (siehe `design.md` Decision D2, analog zu Change 1 Decision D2
  für die Rechnungsnummern-Vergabe). `paid_date` wird dabei auf das
  tatsächliche `payment_date` der abschließenden Zahlung gesetzt statt auf
  `now()` (behebt einen weiteren, bislang unbemerkten Fehler bei
  rückwirkend erfassten Zahlungen). Eine vierte, unabhängig implementierte
  Fundstelle in `PayPalService::captureOrder()` bleibt bewusst unangetastet
  (siehe Non-Goals unten).
- **`PaymentController::store()` erhält neue Geschäftsregeln:** die
  Ziel-Rechnung muss in einem "offenen" Status sein (`sent`, `reminded`,
  `overdue`), sonst HTTP 422; der Zahlungsbetrag darf den aktuellen
  `remainingBalance` nicht übersteigen, sonst HTTP 422 (siehe offene
  Frage 1). `PaymentPolicy::create()` erhält zusätzlich die
  Rechnung als Kontext-Parameter und beschränkt Trainer auf Rechnungen
  ihrer eigenen zugewiesenen Kunden (`Customer::trainer_id`) — eine
  Autorisierungslücke, die durch diesen Change erstmals über eine echte
  UI erreichbar wird (siehe `design.md` Decision D4).
- **Neue Frontend-Komponente `InvoicePaymentDialog.vue`**
  (`frontend/src/components/`), strukturell an `InvoiceSendDialog.vue`
  angelehnt: Formular mit Betrag (vorbelegt mit `remainingBalance`),
  Datum (vorbelegt mit heute), Zahlungsart (Auswahl aus dem bestehenden
  `Payment`-Enum), optionaler Referenz/Notiz. Reines Presentation-Layer,
  emittiert `record-payment`, kein direkter `apiClient`-Zugriff (Muster
  aus Change 2 Decision D8). Ersetzt den entfernten
  "Als bezahlt markieren"-Button; ist **nicht** dieselbe Komponente wie
  das bestehende, kundenseitige `PaymentModal.vue` (PayPal-Checkout,
  andere Zielgruppe, unverändert).
- **Listen- und Detailansicht zeigen den Zahlungseingang:**
  `InvoicesView.vue` ergänzt für offene Rechnungen mit mindestens einer
  abgeschlossenen Teilzahlung eine Fortschrittsanzeige ("150,00 € von
  200,00 € bezahlt"); die bestehende "Bezahlt am …"-Anzeige für
  vollständig bezahlte Rechnungen (`InvoicesView.vue:88-90`) bleibt
  unverändert korrekt (jetzt mit korrektem `paid_date`, siehe oben).
  `InvoiceDetailModal.vue` behebt den bestehenden `payment_date`/
  `payment_method`-Feldnamen-Bug und ergänzt eine Restbetrag-Zusammenfassung
  über der Zahlungsliste.
- **Automatischer Statuswechsel zu `paid`** erfolgt ausschließlich, wenn
  die Summe abgeschlossener Zahlungen den Gesamtbetrag erreicht — kein
  manueller Bestätigungsklick mehr (bindende User-Entscheidung).

## Capabilities

### New Capabilities

- `invoice-payment-entry`: Eingabemaske zur Erfassung eines
  Zahlungseingangs (Betrag/Datum/Zahlungsart/Referenz), Validierung
  (offener Status, keine Überzahlung), Rollen-Autorisierung
  (Admin/Trainer, Trainer nur eigene Kunden), Anzeige des
  Zahlungseingangs/Restbetrags in Liste und Detailansicht.

### Modified Capabilities

- `invoice-status-lifecycle`: Das Requirement "Zahlungsstatus bleibt
  kompatibel mit Teilzahlungen"
  (`openspec/specs/invoice-status-lifecycle/spec.md:136-148`) wird um den
  automatischen Übergang zu `paid` bei vollständiger Zahlungssumme
  erweitert (bislang nur als Datenmodell-Fähigkeit beschrieben, jetzt mit
  aktivem Trigger).

## Impact

**Betroffener Bestandscode (Backend):**
- `backend/database/migrations/` — eine neue, additive Migration
  (`payments.notes`, DB-unkritisch, siehe `design.md`).
- `backend/app/Models/Payment.php` — `notes` in `$fillable`.
- `backend/app/Http/Requests/StorePaymentRequest.php`,
  `UpdatePaymentRequest.php` — `notes`-Validierung.
- `backend/app/Services/InvoicePaymentRecorder.php` — neu.
- `backend/app/Http/Controllers/Api/PaymentController.php` — `store()`,
  `markAsCompleted()` **und** `handlePaymentCaptureCompleted()` nutzen den
  neuen Service; neue 422-Geschäftsregeln in `store()`.
- `backend/app/Policies/PaymentPolicy.php` — `create()` erhält
  Rechnungs-Kontext und Trainer-Scoping.
- `backend/app/Http/Controllers/Api/InvoiceController.php` — `markAsPaid()`
  entfernt.
- `backend/app/Policies/InvoicePolicy.php` — `markAsPaid()` entfernt.
- `backend/routes/api.php` — Route `mark-paid` entfernt.
- `backend/tests/Feature/InvoiceApiTest.php` — vier `mark-paid`-Tests
  entfernt (Funktionalität entfällt).
- Neue Tests: `backend/tests/Feature/Api/InvoicePaymentApiTest.php`
  (neue Geschäftsregeln, inkl. `handlePaymentCaptureCompleted()`-Pfad).
- `backend/tests/Feature/PaymentApiTest.php` — **eine gezielte Anpassung**
  am Bestandstest `'trainer can create payment'` (Zeile 116-138): die dort
  über `beforeEach` erzeugte `Customer`-Factory erhält `trainer_id =>
  $this->trainer->id`, sonst liefert `PaymentPolicy::create()` nach
  Decision D4 `false` statt `true` und der Test bricht (403 statt 201) —
  siehe `design.md` Context. Die übrigen 22 Tests bleiben unverändert
  grün (Bestand, TESTING.md Boy-Scout-Regel: neue Tests weiterhin in
  `it()`-Stil in neuen Dateien, keine stilistische Umschreibung des
  Bestands).

**Betroffener Bestandscode (Frontend):**
- `frontend/src/components/InvoicePaymentDialog.vue` — neu.
- `frontend/src/views/invoices/InvoicesView.vue` — `markAsPaid()`-Handler/
  Button entfernt, neuer Dialog gemountet, neue Handler
  `openPaymentDialog()`/`recordPayment()`, Teilzahlungs-Badge in der
  Tabelle.
- `frontend/src/components/InvoiceDetailModal.vue` — `mark-paid`-Emit
  entfernt, neues `record-payment`-Emit, Bugfix `payment.paymentDate`/
  `payment.paymentMethod`, Restbetrag-Zusammenfassung.
- Vitest: `InvoicePaymentDialog.test.ts` (neu),
  `InvoicesView.test.ts`/`InvoiceDetailModal.test.ts` (angepasst, u. a.
  Entfernen der `mark-paid`-Erwartungen).

**Nicht geändert (Non-Goals):**
- Kein Mahnungs-Trigger, kein Dashboard-Widget (Change 4).
- Keine Änderung an `PayPalService::captureOrder()` — dieselbe Race
  Condition existiert dort ebenfalls
  (`backend/app/Services/PayPalService.php:100-134`, Kernzeilen 122-125),
  wird aber bewusst **nicht** in diesem Change mitgefixt (siehe `design.md`
  Decision D2, Risiko-Abschnitt) — eigener, unabhängiger Folge-Change
  empfohlen, analog zur Empfehlung aus Change 2 für die doppelte
  Event-Registrierung. Die vierte, strukturell identische Fundstelle
  `PaymentController::handlePaymentCaptureCompleted()`
  (`backend/app/Http/Controllers/Api/PaymentController.php:328-350`) ist
  dagegen **kein** Non-Goal — sie liegt in derselben Datei, die T03 ohnehin
  auf den neuen Service umstellt, und wird dort mitbehoben (siehe T03).
- Keine Änderung an `PaymentController::update()`/`destroy()` — eine
  über `PUT /payments/{id}` nachträglich auf `completed` gesetzte Zahlung
  löst aktuell **keinen** Statuswechsel der Rechnung aus (bestehende
  Lücke, unabhängig von diesem Change, da die neue Eingabemaske
  ausschließlich `store()` nutzt). Dokumentiert als offene Frage 3.
- Keine Erweiterung von `PaymentPolicy::view()`/`update()`/`delete()` um
  Trainer-Scoping — nur `create()` wird angepasst, weil dieser Change
  genau diesen Pfad erstmals über eine echte UI erreichbar macht (YAGNI
  für die übrigen, weiterhin ungenutzten Endpunkte).

## Offene Fragen für Skeptiker/User

1. **[ENTSCHIEDEN: Überzahlung wird abgelehnt, HTTP 422]** Überzahlung wird standardmäßig abgelehnt (HTTP 422) — bitte
   bestätigen. Der Anforderungstext regelt Überzahlung nicht. Diese
   Spezifikation schlägt vor, einen Zahlungsbetrag > `remainingBalance`
   serverseitig abzulehnen (konservativ, hält `remainingBalance`
   nicht-negativ, vermeidet einen ungenutzten Kredit-Saldo — das
   bestehende `CustomerCredit`-Modell betrifft ausschließlich
   Kurs-Guthaben, nicht Geld, und ist kein passender Auffangmechanismus).
   Alternative: Überzahlung zulassen und als "Guthaben" o. Ä. anzeigen —
   das wäre deutlich mehr Scope (neues Anzeige-/Verrechnungskonzept) und
   wird hier bewusst nicht gebaut (YAGNI). Bitte bestätigen oder
   korrigieren.
2. **[ENTSCHIEDEN: markAsPaid() wird ersatzlos entfernt]** `markAsPaid()` wird ersatzlos entfernt, nicht erweitert — bitte
   bestätigen. Siehe `design.md` Decision D1. Alternative: `markAsPaid()`
   beibehalten und intern auf denselben `InvoicePaymentRecorder` umstellen
   (würde einen zusätzlichen, redundanten "Volltreffer ohne Formular"-Pfad
   neben der neuen Eingabemaske erhalten). Diese Spezifikation bevorzugt
   die ersatzlose Entfernung zugunsten eines einzigen, konsistenten Wegs
   (die Eingabemaske bietet "volle Restsumme" als Vorbelegung als
   gleichwertig schnellen Ersatz).
3. **[ENTSCHIEDEN: keine Korrektur-UI in Change 3]** Nachträgliches Korrigieren/Stornieren einzelner Zahlungen ist nicht
   Teil dieses Change. Der Anforderungstext erwähnt das nicht.
   `PaymentController::update()`/`destroy()` existieren bereits
   (unverändert von diesem Change), sind aber über keine Admin/Trainer-UI
   erreichbar — eine Korrektur-UI für bereits erfasste Zahlungen (z. B.
   "Zahlung stornieren" bei Fehleingabe) ist ein möglicher eigener
   Folge-Change. Bitte bestätigen, dass das für Change 3 nicht nötig ist.
4. **[ENTSCHIEDEN: Button sichtbar für sent, reminded und overdue]** Sichtbarkeit des neuen "Zahlungseingang erfassen"-Buttons. Diese
   Spezifikation zeigt ihn für `sent`, `reminded` **und** `overdue` (jede
   offene Rechnung mit `remainingBalance > 0`) — breiter als der alte
   `canMarkAsPaid()`, der nur `sent` erlaubte
   (`InvoiceDetailModal.vue:238-240`). Bitte bestätigen, dass Zahlungen
   auch für gemahnte/überfällige Rechnungen erfasst werden können sollen
   (fachlich naheliegend, aber eine Erweiterung gegenüber dem
   bisherigen Verhalten).
5. **[ENTSCHIEDEN: separat behoben]** Neu entdeckte, vorbestehende Sicherheitslücke in
   `PaymentController::index()` (außerhalb des Scopes dieses Change) —
   wurde als eigenständiger, dringender Fix umgesetzt (siehe PR #89,
   `docs/security-fixes/payment-authorization-scope.md`), bevor Change 3
   implementiert wird. `InvoicePaymentRecorder`/die neue Eingabemaske
   bauen damit bereits auf dem korrigierten Autorisierungs-Scoping auf. `index()`
   (`backend/app/Http/Controllers/Api/PaymentController.php:45-82`)
   filtert **überhaupt nicht** nach dem angemeldeten Nutzer — es gibt nur
   optionale Query-Parameter-Filter (`invoiceId`, `paymentMethod`,
   `status`, `completedOnly`, `startDate`/`endDate`), keinen einzigen
   Bezug zu `$request->user()`. Jeder authentifizierte Nutzer, auch ein
   Kunde, kann über `GET /api/v1/payments` (ohne oder mit fremder
   `invoiceId`) grundsätzlich Zahlungen **anderer** Kunden einsehen; der
   einzige naheliegende Test
   (`backend/tests/Feature/PaymentApiTest.php:46-55`, `'customer can list
   payments for their invoices'`) prüft nur den positiven Filterfall,
   nicht die Isolation gegen fremde Zahlungen. Diese Lücke ist
   **vorbestehend und unabhängig** von diesem Change — dieser Change
   ändert `index()` nicht und aktiviert auch keinen neuen UI-Pfad dorthin.
   Sie wird hier ausschließlich dokumentiert, damit sie nicht durch die
   (korrigierte) Ist-Zustand-Beschreibung in `design.md` verdeckt bleibt.
   **Kein impliziter Fix in diesem Change** — bitte entscheiden, ob dafür
   ein eigener, dedizierter openspec-Change angestoßen wird.
