## Context

- `backend/app/Models/Setting.php:26-163` — generisches Key/Value-Model
  mit `get()`/`set()`/`getByGroup()`, cached über `Cache::remember` bzw.
  `Cache::flush()` in `clearCache()`. Typumwandlung über `castValue()`
  (Zeilen 130-143): `boolean`, `integer`, `json`, `file`, sonst String.
- `backend/database/migrations/2026_01_05_144724_create_settings_table.php:14-25`
  — `settings.value` ist `text`, `type`/`group` sind Strings mit
  Default. Keine Längenbeschränkung, die IBAN (bis 34 Zeichen) oder BIC
  (bis 11 Zeichen) gefährden würde.
- `backend/app/Http/Requests/UpdateSettingsRequest.php:32-59` — alle
  bestehenden Company-Felder sind `sometimes`+`nullable`, keine
  Pflichtfelder. Gleiches Muster für die neuen Bankfelder sinnvoll (Admin
  kann die Rechnung vorerst ohne Bankdaten weiterlaufen lassen).
- `backend/database/seeders/SettingsSeeder.php:18-29` — Company-Settings
  werden als flaches Array mit `key`/`value`/`type`/`description`/`group`
  definiert und per `Setting::updateOrCreate()` geschrieben (Zeile 44-49).
- `backend/app/Http/Controllers/SettingsController.php:87-108`
  (`App\Http\Controllers\SettingsController`, **nicht** der
  `Api`-Namespace — der ist laut `routes/api.php:27,206-207` totes
  Duplikat) — `determineTypeAndGroup()` leitet `group` bereits generisch
  aus dem Key-Präfix ab (`str_starts_with($key, 'company_') => 'company'`,
  Zeile 101). Für `type` gibt es nur zwei Spezialfälle: `is_bool`/`is_int`
  (generisch) und ein explizites `$key === 'company_small_business' =>
  'boolean'` (Zeile 91), weil Checkbox-Werte über `FormData` immer als
  String `"true"`/`"false"`/`"1"`/`"0"` ankommen (siehe
  `frontend/src/api/settings.ts:43`: `formData.append(key,
  String(value))` — **jeder** Wert wird vor dem Versand zu String
  konvertiert, unabhängig vom ursprünglichen JS-Typ).
- `backend/resources/views/pdf/invoice.blade.php:127-133` — nutzt bereits
  **direkt** `\App\Models\Setting::get('company_small_business', false)`
  in einem `@php`-Block, ohne dass der Controller ein `$settings`-Array
  übergibt. `InvoiceController::downloadPdf()`
  (`backend/app/Http/Controllers/Api/InvoiceController.php:228-243`)
  übergibt nur `['invoice' => $invoice]` (Zeile 236).
- `backend/resources/views/layouts/email.blade.php:142-169` — nutzt ein
  `$settings`-Array (`$settings['company_name']` etc.), das laut
  `backend/app/Mail/InvoiceCreated.php:54-64` in `content()` über
  `Setting::pluck('value', 'key')->toArray()` (gecached als
  `all_settings`, Zeile 56-58) **vollständig** befüllt wird — jeder
  vorhandene Setting-Key landet automatisch im Template, ohne dass die
  Mailable-Klasse geändert werden muss.
- `backend/resources/views/emails/invoice-created.blade.php:97-105` zeigt
  dieselbe hartkodierte Platzhalter-IBAN/BIC wie das PDF.
- `backend/resources/views/emails/payment-reminder.blade.php:71-86` zeigt
  dieselbe hartkodierte Platzhalter-IBAN (Zeile 75) und BIC (Zeile 80)
  innerhalb eines `.payment-info`-Blocks (Zeile 68-87). Der bestehende
  Einleitungssatz (Zeile 71: "Bitte überweisen Sie den offenen Betrag
  unter Angabe der Rechnungsnummer auf folgendes Konto:") enthält **keine**
  Wochen-/Fristangabe — anders als der für T03 geforderte Wortlaut
  ("...innerhalb von X Wochen..."). Versendet wird das Template über
  `backend/app/Mail/PaymentReminder.php:54-64` (`content()`), das
  **zeichengenau** dasselbe Muster wie `InvoiceCreated::content()`
  (Zeile 54-64 dort) nutzt: `Cache::remember('all_settings', 3600, fn () =>
  Setting::pluck('value', 'key')->toArray())`, übergeben als
  `with: ['settings' => $settings]`. Die vorhandene `Verwendungszweck`-Zeile
  (Zeile 83-86, `{{ $invoice->invoice_number }}`) ist bereits dynamisch und
  bleibt unverändert.
- `backend/app/Http/Requests/UpdateCustomerRequest.php:60-62` — bereits
  etablierte IBAN-/BIC-Regex für die (fachlich andere) SEPA-Bankverbindung
  des `Customer`-Modells:
  `bankIban`: `regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/`, `max:34`
  `bankBic`: `regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/`, `max:11`
  Dieselben Regeln werden für die neuen Settings-Felder übernommen
  (Konsistenz, DRY im Sinne von "gleiches Validierungsverhalten für
  gleichartige Daten").
- `backend/app/Http/Requests/StoreInvoiceRequest.php:91` — `due_date`
  bleibt unverändert ein pro Rechnung frei wählbares Datum. Das neue
  `company_payment_term_weeks` ist ein **separater, fixer** Settings-Wert
  und beeinflusst `due_date` nicht (siehe geklärte Anforderung Punkt 2).

## Goals / Non-Goals

**Goals:**
- Admin kann Kontoinhaber, Bankname, IBAN, BIC und ein
  Standard-Zahlungsziel (Wochen) in den Systemeinstellungen pflegen.
- Rechnungs-PDF, Rechnungs-E-Mail und Zahlungserinnerung-E-Mail zeigen
  diese Werte statt der hartkodierten Musterbank-Platzhalter.
- PDF und Rechnungs-E-Mail enthalten zusätzlich zur bestehenden
  "Zahlungsziel: {Datum}"-Zeile den geforderten Überweisungstext. Die
  Zahlungserinnerung-E-Mail erhält dieselben Kontodaten, aber **ohne** die
  "innerhalb von X Wochen"-Frist (siehe Decision 8) — dort ist die
  Rechnung bereits fällig/überfällig.
- Minimale Diff-Fläche: bestehende, bereits etablierte Muster
  (`Setting::get()` im PDF, `$settings`-Array in beiden E-Mails) werden
  fortgesetzt statt ein weiteres Muster einzuführen.

**Non-Goals:**
- Firmenname/Adresse/Steuernummer im PDF bleiben hartkodiert (separates,
  bewusst ausgeklammertes Problem, siehe `proposal.md`).
- Keine Änderung an `due_date`/`dueDate`-Logik einzelner Rechnungen.
- Keine Änderung am Einleitungssatz oder der Struktur der
  Zahlungserinnerung außerhalb der Kontodaten-Zeilen (z. B. Fälligkeits-
  /Überfälligkeitslogik in `payment-reminder.blade.php:31-36` bleibt
  unverändert).
- Keine Änderung an den SEPA-Bankfeldern des `Customer`-Modells
  (`bank_account_holder`/`bank_iban`/`bank_bic`) — andere Datenquelle,
  anderer Zweck (Lastschrift vom Kunden statt Überweisung an die
  Hundeschule).
- Kein Aufräumen des toten `Api\SettingsController`-Duplikats.
- Keine Migration — `settings` ist bereits ein generisches
  Key/Value-Schema.

## Decisions

**1. Naming der neuen Keys mit `company_`-Präfix.**
`company_bank_account_holder`, `company_bank_name`, `company_bank_iban`,
`company_bank_bic`, `company_payment_term_weeks`. Grund: die
Gruppierungslogik in `SettingsController::determineTypeAndGroup()`
(Zeile 100-105) ordnet jeden `company_*`-Key automatisch der Gruppe
`company` zu — keine Codeänderung an der Gruppierung nötig, die neuen
Felder erscheinen im Frontend zusammen mit den bestehenden Stammdaten.

**2. Validierung: `sometimes`+`nullable`, keine Pflichtfelder.**
Konsistent mit allen bestehenden Company-Settings (`UpdateSettingsRequest.php:34-46`).
Ein Admin muss die Bankdaten nicht sofort ausfüllen; das Formular bleibt
speicherbar. Für IBAN/BIC werden die bei `Customer` bereits etablierten
Regex-Muster übernommen (siehe Context). `company_payment_term_weeks`:
`integer`, `min:1`, `max:52` (Obergrenze verhindert Fehleingaben wie
Tage-statt-Wochen-Verwechslung; ein Jahr Zahlungsziel ist eine plausible
Obergrenze für ein KMU-Rechnungsformular).

**3. `company_payment_term_weeks` braucht eine explizite
Typ-Sonderbehandlung in `determineTypeAndGroup()`.**
Wie im Context hergeleitet, kommt jeder Wert über `FormData` als String
an (`frontend/src/api/settings.ts:43`), daher greift `is_int($value)`
(Zeile 93) nie. Ohne expliziten Fall würde der Wert dauerhaft als
`type = 'string'` gespeichert und `Setting::get()`s `castValue()` würde
ihn nie zu `int` casten. Der bestehende Code hat exakt dieses Muster
schon für `company_small_business` gelöst (Zeile 91) — derselbe Ansatz
wird für `company_payment_term_weeks => 'integer'` ergänzt. Für die
reine Textausgabe im Template wäre das zwar unkritisch (String wird
korrekt interpoliert), aber die konsistente Typisierung verhindert
Folgefehler, falls der Wert später einmal rechnerisch verwendet wird.

**4. PDF-Template nutzt weiterhin direkte `Setting::get()`-Aufrufe, kein
`$settings`-Array vom Controller.**
Alternative geprüft: `InvoiceController::downloadPdf()` könnte analog zu
`layouts/email.blade.php` ein `$settings`-Array an die View übergeben.
Verworfen, weil `pdf/invoice.blade.php` bereits an einer Stelle
(`company_small_business`, Zeile 128) das Muster "direkter
`Setting::get()`-Aufruf im `@php`-Block" etabliert hat. Ein zweites,
konkurrierendes Muster (Controller-Array) in derselben Datei würde gegen
Konsistenz/DRY verstoßen und wäre zusätzlicher, nicht notwendiger
Umbauaufwand (YAGNI) — `InvoiceController.php` muss dafür nicht
angefasst werden.

**5. E-Mail-Template braucht keine Mailable-Änderung.**
`InvoiceCreated::content()` lädt bereits **alle** Settings
(`Setting::pluck('value', 'key')->toArray()`, Zeile 56-58) in
`$settings`. Die neuen Keys erscheinen dort automatisch, sobald sie in
der `settings`-Tabelle existieren (T01). Nur das Blade-Template
(`emails/invoice-created.blade.php`) muss geändert werden.

**6. Cache-Invalidierung bereits vorhanden.**
`SettingsController::update()` ruft nach jedem Speichern
`Setting::clearCache()` auf, was intern `Cache::flush()` ausführt
(`Setting.php:120-123`) — das betrifft laut CLAUDE.md Abschnitt 4.3 sowohl
den `file`- als auch den `database`-Cache-Driver (Shared-Hosting-tauglich,
kein `redis`-spezifisches Verhalten nötig). Weder `email_settings`- noch
`all_settings`-Cache-Keys in `InvoiceCreated.php` müssen separat behandelt
werden.

**7. Überweisungstext-Formatierung.**
Exakter Wortlaut aus der Anforderung:
```
Bitte überweisen Sie den Betrag innerhalb der <x> Wochen auf folgendes Konto:
<Kontoinhaber>
<Bankname>
<IBAN>
<BIC>
```
Umsetzung im PDF (`.payment-box`, ersetzt die bisherigen `<p><strong>IBAN:</strong>...`/`<p><strong>BIC:</strong>...`-Zeilen 259-260) und in der
E-Mail (`.payment-info`, ersetzt die `info-row`-Paare für IBAN/BIC,
Zeilen 97-105): Einleitungssatz wortwörtlich wie oben (mit
`{{ $paymentTermWeeks }}` bzw. dem entsprechenden Settings-Wert für
`<x>`), gefolgt von den vier Werten als eigene Zeilen — mit Labels
(„Kontoinhaber:", „Bank:", „IBAN:", „BIC:") analog zum bestehenden
Label-Stil des jeweiligen Dokuments (`<strong>Label:</strong> Wert` im
PDF, `info-row`/`info-label` in der E-Mail), damit die Darstellung
konsistent zu den übrigen Feldern (`Fälligkeitsdatum`, `Verwendungszweck`)
bleibt. Die bestehende `Zahlungsziel:`/`Fälligkeitsdatum:`-Zeile mit dem
individuellen `due_date` bleibt unverändert bestehen; der neue Text wird
**zusätzlich** darunter eingefügt. Leere Bankfelder werden mit
`Setting::get($key, '')` bzw. `$settings['company_bank_...'] ?? ''`
abgefangen, damit keine PHP-Warnungen/-Fehler bei fehlenden Werten
entstehen (Feld erscheint dann leer, kein Blocker für diesen Change).

**8. Zahlungserinnerung erhält Kontodaten ohne Wochen-Frist-Text.**
Scope-Erweiterung nach Skeptiker-Befund (`verification.md`, "Zusätzliche
Befunde") und User-Entscheidung: `payment-reminder.blade.php` bekommt
denselben Kontodaten-Fix wie T03, aber mit angepasstem Wortlaut. Grund:
Der für Decision 7 festgelegte Satz "Bitte überweisen Sie den Betrag
innerhalb von {X} Wochen auf folgendes Konto:" passt inhaltlich nicht zu
einer Zahlungserinnerung — dort ist das Zahlungsziel der Rechnung bereits
erreicht oder überschritten (`payment-reminder.blade.php:31-36` prüft
`$invoice->isOverdue()` und zeigt explizit "ist seit dem {due_date}
überfällig" bzw. "ist am {due_date} fällig"). Eine erneute Wochenfrist ab
Versand der Erinnerung würde der bereits kommunizierten Fälligkeit
widersprechen. Der bestehende Einleitungssatz (Zeile 71: "Bitte überweisen
Sie den offenen Betrag unter Angabe der Rechnungsnummer auf folgendes
Konto:") ist bereits fristneutral formuliert und bleibt **unverändert**.
Geändert werden nur die beiden Werte-Zeilen: die hartkodierte IBAN
(Zeile 75) wird durch `{{ $settings['company_bank_iban'] ?? '' }}`
ersetzt, die hartkodierte BIC (Zeile 80) durch
`{{ $settings['company_bank_bic'] ?? '' }}`. Zusätzlich werden — analog zu
T03, für vollständige Kontodaten in allen drei Dokumenten — zwei neue
`info-row`-Zeilen für Kontoinhaber und Bankname **vor** der IBAN-Zeile
eingefügt (`{{ $settings['company_bank_account_holder'] ?? '' }}` /
`{{ $settings['company_bank_name'] ?? '' }}`), im selben `info-row`-Markup-
Stil wie die bestehenden IBAN-/BIC-/Verwendungszweck-Zeilen. Die
bestehende `Verwendungszweck`-Zeile (Zeile 83-86) bleibt unverändert, da
sie bereits dynamisch ist (`{{ $invoice->invoice_number }}`).

## Risks / Trade-offs

- **Leere Bankdaten nach Deploy:** Bestandsinstallationen ohne
  ausgeführten Seeder-Rerun haben die neuen Keys zunächst nicht in der
  DB. `Setting::get($key, '')` liefert dann `''`, das Dokument zeigt
  leere Zeilen statt eines Fehlers — akzeptiert, da der Seeder ohnehin
  nur für Neuinstallationen/Demo-Daten gedacht ist; Produktivinstanzen
  pflegen Settings über das Formular. Akzeptanzkriterium: kein Rendering-
  Fehler bei fehlenden Keys.
- **IBAN/BIC-Regex ist streng (nur Großbuchstaben, keine Leerzeichen).**
  Entspricht der DIN-Norm-Kompaktschreibweise und dem bereits etablierten
  Verhalten bei `Customer`-Bankfeldern — Admin muss ggf. Leerzeichen aus
  einer kopierten IBAN entfernen. Kein neues Risiko, sondern bestehendes,
  akzeptiertes UX-Verhalten im Projekt.
- **Zwei leicht unterschiedliche Template-Engines (DomPDF vs. Blade-Mail)
  für denselben Text:** Erfordert, dass T03 den Wortlaut in beiden
  Dateien konsistent hält. Gegenmaßnahme: exakter Wortlaut ist hier in
  `design.md` Decision 7 fixiert, beide Teiländerungen sind in einer
  einzigen Task (T03) gebündelt, um Abweichungen zu vermeiden.
- **Bewusst unterschiedlicher Wortlaut zwischen T03 (Rechnung/Erst-E-Mail)
  und T04 (Zahlungserinnerung):** Die Zahlungserinnerung nutzt keinen
  Wochen-Frist-Satz (Decision 8), während PDF und Rechnungs-E-Mail ihn
  enthalten. Das ist eine bewusste, fachlich begründete Abweichung
  (bereits fällige/überfällige Rechnung), kein Konsistenzfehler — trotzdem
  im Review explizit zu prüfen, damit T04 nicht versehentlich denselben
  Wochen-Satz wie T03 übernimmt.
