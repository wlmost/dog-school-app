# Verification: add-invoice-bank-details

**openspec validate:** `Change 'add-invoice-bank-details' is valid` (strukturell ok, siehe Ausgabe unten)

```
$ openspec validate add-invoice-bank-details
Change 'add-invoice-bank-details' is valid
```

**Gesamtstatus:** nacharbeit-am-design-nötig

Begründung: Alle inhaltlichen Codebasis-Behauptungen in proposal.md/design.md/
tasks.md/spec.md sind zutreffend, bis auf eine kleine Zeilennummer-Ungenauigkeit.
Es gibt jedoch eine echte **Scope-Lücke**: ein drittes Template mit derselben
hartkodierten Platzhalter-IBAN/BIC (`emails/payment-reminder.blade.php`) wird
von diesem Change weder abgedeckt noch als Non-Goal benannt (siehe unten,
Abschnitt "Zusätzliche Befunde"). Das widerspricht der in `proposal.md` Z.1-10
formulierten Motivation ("Kunden erhalten dadurch auf jeder Rechnung falsche
Überweisungsdaten") — Zahlungserinnerungen sind ebenfalls Kunden-Kommunikation
mit derselben falschen IBAN/BIC.

---

## Bestätigt

### Architekten-Behauptungen (aus dem Auftrag)

1. **Gerouteter Controller ist `App\Http\Controllers\SettingsController` (ohne `Api`-Namespace)**
   → bestätigt in `backend/routes/api.php:27` (`use App\Http\Controllers\SettingsController;`)
   und `backend/routes/api.php:206-207` (`Route::get('/settings', [SettingsController::class, 'index']);`
   / `Route::put('/settings', [SettingsController::class, 'update']);`).
   `backend/app/Http/Controllers/Api/SettingsController.php` existiert zwar
   (`namespace App\Http\Controllers\Api;`, Zeile 5), wird aber laut
   projektweitem Grep (`grep -rn "Api\\SettingsController" backend/`) an
   keiner Stelle referenziert — totes Duplikat bestätigt.

2. **Keine Migration nötig, `settings.value` ist bereits `text`**
   → bestätigt in `backend/database/migrations/2026_01_05_144724_create_settings_table.php:17`
   (`$table->text('value')->nullable();`). Kein Längenlimit, IBAN (34 Zeichen)
   und BIC (11 Zeichen) unkritisch.

3. **PDF ruft bereits `\App\Models\Setting::get()` direkt im `@php`-Block auf,
   `InvoiceController::downloadPdf()` muss nicht geändert werden**
   → bestätigt: `backend/resources/views/pdf/invoice.blade.php:128`
   (`$isSmallBusiness = \App\Models\Setting::get('company_small_business', false);`)
   innerhalb des `@php`-Blocks Zeile 127-133. `InvoiceController::downloadPdf()`
   (`backend/app/Http/Controllers/Api/InvoiceController.php:228-243`) übergibt
   an `Pdf::loadView('pdf.invoice', ['invoice' => $invoice])` (Zeile 236)
   tatsächlich nur `invoice`, kein `$settings`-Array.

4. **`InvoiceCreated.php:56-58` sammelt bereits ALLE Settings in `$settings`
   fürs E-Mail-Template, kein Mailable-Change nötig**
   → bestätigt in `backend/app/Mail/InvoiceCreated.php:54-64`
   (`content()`-Methode). Zeilen 56-58 exakt:
   ```php
   $settings = Cache::remember('all_settings', 3600, function () {
       return Setting::pluck('value', 'key')->toArray();
   });
   ```
   Wird ungecastet (Rohwerte aus der DB, kein `castValue()`) als
   `with: ['settings' => $settings]` (Zeile 60-63) ans Template übergeben.
   Für reine Textausgabe im Blade-Template unkritisch.

5. **IBAN-/BIC-Regex-Wiederverwendung aus `UpdateCustomerRequest.php:61-62`**
   → bestätigt in `backend/app/Http/Requests/UpdateCustomerRequest.php:60-62`:
   Zeile 61 `'bankIban' => [..., 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/']`,
   Zeile 62 `'bankBic' => [..., 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/']`.
   Die in `tasks.md` T01 vorgeschlagenen Regeln für `company_bank_iban`/
   `company_bank_bic` sind zeichengenau identisch mit diesen Regex-Mustern.

6. **`SettingsController::determineTypeAndGroup()` braucht expliziten Case
   für `company_payment_term_weeks => 'integer'`**
   → bestätigt als notwendig: `backend/app/Http/Controllers/SettingsController.php:87-108`,
   `match(true)`-Block für `$type` (Zeile 90-97) hat aktuell nur den
   Sonderfall `$key === 'company_small_business' => 'boolean'` (Zeile 91)
   sowie generische `is_bool($value)` (Zeile 92) und `is_int($value)`
   (Zeile 93). `frontend/src/api/settings.ts:43`
   (`formData.append(key, String(value))`) bestätigt, dass jeder Wert als
   String beim Backend ankommt — `is_int($value)` (Zeile 93) würde für
   `company_payment_term_weeks` nie greifen, ohne expliziten Fall würde der
   Typ dauerhaft `'string'` bleiben. Die Gruppierungslogik
   `str_starts_with($key, 'company_') => 'company'` (Zeile 101) deckt alle
   fünf neuen Keys bereits automatisch ab — bestätigt.

### Weitere Datei:Zeile-Referenzen aus proposal.md / design.md / tasks.md / spec.md

- `proposal.md` Z.3-4: Platzhalter-IBAN/BIC in `pdf/invoice.blade.php:259-260`
  → bestätigt, exakter Wortlaut `<p><strong>IBAN:</strong> DE89 3704 0044 0532 0130 00</p>`
  (Zeile 259) und `<p><strong>BIC:</strong> COBADEFFXXX</p>` (Zeile 260).
- `proposal.md` Z.4-5: Platzhalter-IBAN/BIC in `emails/invoice-created.blade.php:98-104`
  → bestätigt, `info-row`-Block IBAN Zeile 97-100, BIC Zeile 102-105
  (Werte auf Zeile 99 bzw. 104).
- `proposal.md` Z.18-19: `company_name`/Adresse/Steuernummer hartkodiert in
  `pdf/invoice.blade.php:144-146,280-281`
  → bestätigt: Zeile 144 `<h1>Hundeschule Max Mustermann</h1>`, Zeile 145-146
  Adresse/Kontakt, Zeile 280-281 Footer mit Firmenname und USt-IdNr.
- `design.md` Context: `Setting.php:26-163` generisches Key/Value-Model
  → bestätigt, Klasse `Setting` beginnt Zeile 26, endet Zeile 163.
- `design.md` Context: `castValue()` Zeilen 130-143 mit `boolean`/`integer`/
  `json`/`file`/Default-String → bestätigt exakt (Setting.php:130-143).
- `design.md` Decision 6: `Setting::clearCache()` → `Cache::flush()`
  (`Setting.php:120-123`) → bestätigt exakt (Zeile 120-123, `Cache::flush();`
  auf Zeile 122). Da `Cache::flush()` global flusht, sind `email_settings`-
  und `all_settings`-Keys aus `InvoiceCreated.php`/`PaymentReminder.php`
  automatisch mit betroffen — Behauptung korrekt.
- `design.md` Context: `UpdateSettingsRequest.php:32-59` und `:34-46`
  (Company-Felder alle `sometimes`+`nullable`)
  → bestätigt: `rules()`-Array Zeile 32-59, Company-Block Zeile 34-46, alle
  13 Einträge nutzen `'sometimes', 'nullable'`.
- `design.md` Context: `SettingsSeeder.php:18-29` (`$companySettings`-Array)
  und `:44-49` (`Setting::updateOrCreate()`-Loop)
  → bestätigt exakt (Array Zeile 18-29, Loop Zeile 44-49).
- `design.md` Context: `layouts/email.blade.php:142-169` nutzt `$settings`-Array
  → bestätigt exakt, u. a. `$settings['company_name']` Zeile 143/145/156,
  `$settings['company_street']` Zeile 158 etc.
- `design.md` Context: `StoreInvoiceRequest.php:91` — `due_date` bleibt
  unverändertes Freitext-Datum → bestätigt: Zeile 91
  `'due_date' => $validated['dueDate'],`.
- `tasks.md` T01: Einfügeposition "nach `company_registration_number`, vor
  `company_small_business`" in `UpdateSettingsRequest::rules()`
  → bestätigt: `company_registration_number` Zeile 43, `company_small_business`
  Zeile 44 — direkt angrenzend, Einfügeposition eindeutig.
- `tasks.md` T02: `formData` Zeile 526-561, `SettingsView.vue`
  → bestätigt exakt (Objekt beginnt Zeile 526, endet Zeile 561).
- `tasks.md` T02: Stammdaten-Card Zeile 23-218, `company_registration_number`-Feld
  Zeile 146-157, `smtp_port`-Feld Zeile 290-303
  → alle drei bestätigt exakt.
- `tasks.md` T02: `loadSettings()`-Populate-Block Zeile 589-606,
  `saveSettings()`-Sammel-Loop Zeile 627-639
  → beide bestätigt exakt; beide iterieren generisch über `formData`/
  `Object.entries`, keine Änderung an der Methode selbst nötig — Behauptung
  korrekt.
- `tasks.md` T03: PDF `.payment-box`-Block Zeile 255-262 (Zahlungsziel 258,
  IBAN/BIC 259-260, Verwendungszweck 261)
  → bestätigt exakt.
- `tasks.md` T03: E-Mail `.payment-info`-Block, Zahlungsziel Zeile 92-95,
  IBAN-Row Zeile 97-100, BIC-Row Zeile 102-105, Verwendungszweck-Row
  Zeile 107-110 → bestätigt exakt (`emails/invoice-created.blade.php`).
- `proposal.md` "Nicht betroffen": `SettingsResource.php` generisches
  Key/Value-Mapping deckt neue Keys ab → bestätigt,
  `backend/app/Http/Resources/SettingsResource.php:26-50` castet generisch
  nach `$this->type` (`boolean`/`integer`/`json`/Default), keine
  Key-spezifische Logik.
- `proposal.md`/`design.md`: `frontend/src/api/settings.ts:43` —
  `formData.append(key, String(value))` → bestätigt exakt.

---

## Widerlegt

- `design.md` Decision 3, Z.104-106: "daher greift `is_int($value)`
  (Zeile 92) nie" → **Zeilennummer falsch.** In
  `backend/app/Http/Controllers/SettingsController.php` steht
  `is_bool($value) => 'boolean',` auf Zeile 92 und
  `is_int($value) => 'integer',` tatsächlich auf **Zeile 93** (per
  `grep -n`). Inhaltlich ist die Behauptung (dass `is_int()` wegen
  FormData-String-Konvertierung nie greift) korrekt — nur die referenzierte
  Zeilennummer ist um eins verschoben. Geringe Auswirkung (Dev-Agent
  könnte an falscher Stelle nach dem Code suchen), aber ein konkreter,
  falsifizierbarer Fehler.

---

## Nicht auffindbar

Keine — alle konkreten Datei:Zeile-Behauptungen in proposal.md, design.md,
tasks.md und spec.md konnten im Code lokalisiert werden (bis auf die oben
genannte Zeilennummer-Abweichung, die aber als "widerlegt" und nicht als
"nicht auffindbar" einzustufen ist, da der referenzierte Code an einer
Nachbarzeile existiert).

---

## Neue Elemente (Plausibilität)

- `tasks.md` T01: neue Regeln in `UpdateSettingsRequest::rules()`, neue
  Seed-Einträge in `SettingsSeeder::$companySettings`, neuer `match`-Case in
  `SettingsController::determineTypeAndGroup()` → alle drei Zielorte
  existieren, Einfügepositionen sind eindeutig lokalisierbar, keine
  Namenskollision (`grep -rn "company_bank_\|company_payment_term_weeks"
  backend/ frontend/` liefert **keine** Treffer im Bestandscode — die fünf
  Keys sind tatsächlich neu).
- `tasks.md` T02: neue Properties in `formData` (Zeile 526-561) und neue
  Template-Felder im Stammdaten-Card (nach Zeile 157, vor Zeile 160) →
  konsistent mit bestehendem Markup-Muster (Label+Input-Paare), keine
  Kollision mit vorhandenen Feldnamen.
- `tasks.md` T03: neue `@php`-Variablen in `pdf/invoice.blade.php` (neben
  `$isSmallBusiness`, Zeile 127-133) und Ersatz der IBAN/BIC-Zeilen in
  beiden Templates → Zielstellen existieren exakt wie beschrieben,
  Einfügepositionen (nach `Zahlungsziel`, vor `Verwendungszweck`) sind in
  beiden Dateien eindeutig identifizierbar.

---

## Zusätzliche Befunde (Scope-Lücke, nicht Teil der ursprünglichen Behauptungsliste)

- **Ein drittes Template mit identischer hartkodierter Platzhalter-IBAN/BIC
  existiert und wird von diesem Change nicht erwähnt:**
  `backend/resources/views/emails/payment-reminder.blade.php:71-81` enthält
  denselben Text (`Bitte überweisen Sie den offenen Betrag ... auf folgendes
  Konto:`, gefolgt von `DE89 3704 0044 0532 0130 00` / `COBADEFFXXX`).
  Versendet wird es über `backend/app/Mail/PaymentReminder.php`, das
  **exakt dasselbe Muster** wie `InvoiceCreated.php` nutzt (`content()`,
  Zeile 54-64, `Cache::remember('all_settings', ...)`, `with: ['settings' =>
  $settings]`) — d. h. dieselbe Änderung (Ersetzen der IBAN/BIC-Zeilen durch
  `$settings['company_bank_...']` + Überweisungstext) wäre mit identischem
  Aufwand wie T03 möglich.
  `grep -rln "DE89 3704 0044 0532 0130 00\|COBADEFFXXX" backend/resources/`
  liefert genau drei Treffer: `pdf/invoice.blade.php`,
  `emails/invoice-created.blade.php` **und**
  `emails/payment-reminder.blade.php`. Nur die ersten beiden sind in
  `proposal.md`/`tasks.md`/`spec.md` als Impact/Scope genannt.
  `proposal.md` benennt einen expliziten Non-Goal-Absatz nur für
  Firmenname/Adresse/Steuernummer (Z.17-19) — für
  `payment-reminder.blade.php` fehlt eine vergleichbare bewusste
  Abgrenzung komplett. Das widerspricht der eigenen Problem-Beschreibung in
  `proposal.md` Z.9-10 ("Kunden erhalten dadurch auf jeder Rechnung falsche
  Überweisungsdaten") — Zahlungserinnerungen sind ebenfalls
  Kunden-Kommunikation mit Rechnungsbezug und derselben falschen IBAN/BIC.

- **Leichte Inkonsistenz in `tasks.md` T02:** Feld "Abhängigkeiten: keine"
  wird direkt im selben Satz relativiert ("T02 kann parallel zu T01
  implementiert werden, benötigt aber vor Review/Test einen laufenden
  Backend-Stand aus T01 zur Integrationsprüfung"). Inhaltlich nachvollziehbar,
  aber das Feld selbst sollte dann nicht "keine" lauten, sondern die
  Soft-Dependency benennen — rein redaktionell, kein Sachverhalt der
  Codebasis.

---

## Empfehlung

Die Spec ist in allen überprüfbaren Codebasis-Behauptungen fast vollständig
akkurat (13+ Datei:Zeile-Referenzen bestätigt, nur eine Zeilennummer um eins
verschoben). Der Architekt sollte vor Freigabe zwei Punkte klären: (1) ob
`emails/payment-reminder.blade.php` bewusst als Non-Goal in `proposal.md`
ergänzt wird oder als zusätzliche Task (T04, `dev-php`) mit ins Impact
aufgenommen wird — sonst bleibt nach diesem Change eine der drei betroffenen
Kunden-Kommunikationen mit falscher Bankverbindung übrig; und (2) die
Zeilennummer-Korrektur in `design.md` Decision 3 (`is_int($value)` steht auf
Zeile 93, nicht 92). Beides ist geringer Aufwand und rechtfertigt keinen
kompletten Neuentwurf, aber sollte vor User-Gate 1 nachgezogen werden.

---

# Nachprüfung (Runde 2) — Fokus auf überarbeitete Teile

**openspec validate --strict:** `Change 'add-invoice-bank-details' is valid`

```
$ openspec validate add-invoice-bank-details --strict
Change 'add-invoice-bank-details' is valid
```

**Gesamtstatus (Runde 2):** bereit-für-user-gate

Begründung: Beide in Runde 1 offenen Punkte (Scope-Lücke `payment-reminder.
blade.php`, Zeilennummer-Fehler in Decision 3) wurden korrekt nachgezogen.
Alle neuen/geänderten Datei:Zeile-Behauptungen zu T04 und Decision 8 sind
zeichengenau gegen den Code verifiziert. Keine neuen Diskrepanzen gefunden.

## Bestätigt (neu)

- `design.md` Decision 3, Z.126: "`is_int($value)` (Zeile 93)" → bestätigt
  exakt in `backend/app/Http/Controllers/SettingsController.php:93`
  (`is_int($value) => 'integer',`); Zeile 92 ist `is_bool($value) =>
  'boolean',`. Der in Runde 1 gemeldete Off-by-one-Fehler ist behoben.

- `tasks.md` T04 / `design.md` Decision 8: Einleitungssatz in
  `backend/resources/views/emails/payment-reminder.blade.php:71`
  → bestätigt exakt: `<p>Bitte überweisen Sie den offenen Betrag unter
  Angabe der Rechnungsnummer auf folgendes Konto:</p>`.
- Hartkodierte IBAN `backend/resources/views/emails/payment-reminder.
  blade.php:75` → bestätigt exakt (`DE89 3704 0044 0532 0130 00`,
  eingebettet in `info-row`-Block Zeile 73-76).
- Hartkodierte BIC `backend/resources/views/emails/payment-reminder.
  blade.php:80` → bestätigt exakt (`COBADEFFXXX`, `info-row`-Block
  Zeile 78-81).
- Verwendungszweck-Zeile `backend/resources/views/emails/payment-reminder.
  blade.php:83-86` (`{{ $invoice->invoice_number }}`, bereits dynamisch)
  → bestätigt exakt.
- Restlicher unangetasteter Aufbau: Überfälligkeits-Hinweis Zeile 28-37
  (`@if($invoice->isOverdue())`-Block Zeile 31-35, davor `warning-box`
  Zeile 28-30, danach schließendes `</div>` Zeile 37) → bestätigt exakt.
  Betrag-Box Zeile 63-66 → bestätigt exakt (`amount-box`-Div). Signatur-
  Bereich Zeile 89-94 → Inhalt vorhanden und unverändert zu erwarten
  (Zeile 93-94 ist die eigentliche Grußformel, Zeile 89-91 sind zwei
  vorangehende Absätze — die Sammelbezeichnung "Signatur" für den ganzen
  Block ist eine lose Beschreibung, aber keine falsifizierbare
  Zeilenangabe für eine konkrete Code-Änderung).
- `backend/app/Mail/PaymentReminder.php:54-64` (`content()`-Methode)
  → bestätigt exakt:
  ```php
  public function content(): Content
  {
      $settings = Cache::remember('all_settings', 3600, function () {
          return Setting::pluck('value', 'key')->toArray();
      });

      return new Content(
          view: 'emails.payment-reminder',
          with: ['settings' => $settings]
      );
  }
  ```
  Zeichengenau identisch (bis auf den View-Namen in Zeile 61, `emails.
  payment-reminder` statt `emails.invoice-created`) mit
  `backend/app/Mail/InvoiceCreated.php:54-64` — Behauptung "identisches
  Muster wie `InvoiceCreated::content()`" bestätigt per direktem
  Zeilenvergleich beider Dateien. Keine Mailable-Änderung für T04 nötig,
  da `$settings` bereits das komplette Key/Value-Array enthält.
- `backend/app/Models/Invoice.php`: `isOverdue()` ist dort **nicht** auf
  Zeile 31-36 definiert, sondern auf Zeile 110 (`public function
  isOverdue(): bool`, per `grep -n`). Die "Zeile 31-36"-Angabe in
  `tasks.md:288` und `design.md:197` bezieht sich nachweislich auf den
  **Aufruf** `$invoice->isOverdue()` in
  `backend/resources/views/emails/payment-reminder.blade.php:31-36`
  (dem `@if`/`@else`-Block mit "ist ... überfällig" / "ist ... fällig"),
  nicht auf die Modell-Methode selbst — weder `design.md` noch `tasks.md`
  enthalten irgendeine Referenz "Invoice.php" (Grep nach `Invoice.php`
  in `design.md`/`tasks.md`/`proposal.md`/`specs/*/spec.md` liefert
  **keinen Treffer**). Die Spec macht also korrekterweise keine
  Behauptung über die Modelldatei; die Blade-Zeilenangabe 31-36 ist wie
  oben bestätigt exakt.

## Neue Elemente (Plausibilität) — T04

- `specs/invoice-bank-details/spec.md`, Requirement "Zahlungserinnerung-
  E-Mail zeigt dieselben Kontodaten ohne Wochen-Frist-Text" mit drei
  Szenarien → konsistent mit `tasks.md` T04 und `design.md` Decision 8:
  gleicher Einleitungssatz-Wortlaut, gleiche vier Kontodatenfelder,
  explizites "kein Wochen-Frist-Text"-Kriterium, explizites
  Fehlerfreiheits-Szenario bei fehlenden Settings-Werten. Kein Widerspruch
  zwischen Spec-Szenarien und T04-Akzeptanzkriterien gefunden.
- Neue `info-row`-Blöcke für Kontoinhaber/Bankname in
  `payment-reminder.blade.php` (einzufügen vor Zeile 73) folgen demselben
  Markup-Muster wie die bereits bestehenden IBAN-/BIC-/Verwendungszweck-
  `info-row`-Blöcke in derselben Datei — keine Namens- oder CSS-Klassen-
  Kollision (`.info-row`/`.info-label` bereits im Template etabliert,
  siehe Zeile 73-86).

## Empfehlung (Runde 2)

Beide in Runde 1 offenen Punkte sind sauber nachgezogen: T04 deckt jetzt
alle drei betroffenen Kunden-Kommunikationen ab, die Zeilennummer in
Decision 3 ist korrigiert, und die neue Decision 8 begründet die bewusste
Abweichung (kein Wochen-Frist-Satz bei Zahlungserinnerung) fachlich
nachvollziehbar und deckt sich mit dem tatsächlichen `isOverdue()`-Verhalten
im Template. Keine der in dieser Runde geprüften Datei:Zeile-Behauptungen
wurde widerlegt. Der Change kann zu User-Gate 1 weitergereicht werden.
