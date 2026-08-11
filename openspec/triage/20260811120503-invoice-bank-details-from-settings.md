# Triage: Kontodaten in Rechnungen aus Einstellungen lesen

**Pfad:** standard
**Geschätzter Umfang:** ca. 5–6 Dateien, PHP (Backend/Laravel) + TypeScript/Vue (Frontend)
**Risiko:** mittel — betrifft ein Kunden-/Zahlungsdokument (Rechnungs-PDF) und die Settings-API-Oberfläche (neue Felder), aber keine Migration, kein Auth, kein Datenmodell-Bruch.
**Klarheit:** mehrdeutig — der Fund ist größer als die gemeldete Anforderung; Rückfrage nötig, siehe unten.

## Anforderung (Zusammenfassung)

Der User meldet, dass in den Rechnungen die Kontodaten der Hundeschule (vermutlich IBAN/BIC) fehlen und stattdessen aus den Einstellungen gelesen werden sollen. Vermutung des Users: verwandtes Muster zu PR #78 (falscher/fehlender Settings-Key).

## Rechercheergebnis

Die Vermutung "falscher Settings-Key" (wie bei PR #78, `ccc30aa`) trifft **nicht** zu. Der tatsächliche Befund ist umfangreicher:

1. **Rechnungs-PDF-Template liest überhaupt keine Settings.**
   `backend/resources/views/pdf/invoice.blade.php` referenziert an keiner Stelle eine `$settings`-Variable (im Gegensatz zu `backend/resources/views/layouts/email.blade.php`, das `$settings['company_...']` nutzt). Firmenname, Adresse, Steuernummer **und** die Kontodaten (IBAN/BIC) sind komplett hartkodiert:
   - Zeile 144–146: `<h1>Hundeschule Max Mustermann</h1>`, `Musterstraße 123 • 12345 Musterstadt`, Tel/E-Mail hartkodiert
   - Zeile 259–260: `<p><strong>IBAN:</strong> DE89 3704 0044 0532 0130 00</p>` / `<p><strong>BIC:</strong> COBADEFFXXX</p>` — bekannte Platzhalter-/Test-IBAN (Musterbank), nicht die echten Kontodaten der Hundeschule
   - Zeile 280–281 (Footer): Firmenname/Adresse/USt-IdNr erneut hartkodiert

2. **Der Controller übergibt gar keine Settings an das PDF-Template.**
   `backend/app/Http/Controllers/Api/InvoiceController.php:236`: `Pdf::loadView('pdf.invoice', ['invoice' => $invoice])` — kein `$settings`-Array im Payload, anders als es für eine Settings-basierte Darstellung nötig wäre.

3. **Es existiert aktuell kein Settings-Key für Bankdaten der Hundeschule.**
   - `backend/database/seeders/SettingsSeeder.php:19–28` definiert nur `company_name`, `company_street`, `company_zip`, `company_city`, `company_country`, `company_phone`, `company_email`, `company_website`, `company_tax_id`, `company_registration_number` — keine Bank-/IBAN-/BIC-Felder.
   - `backend/app/Http/Requests/UpdateSettingsRequest.php:34–46` (Validierungsregeln) und `frontend/src/views/SettingsView.vue:31–171` (Formularfelder) kennen ebenfalls keine Bankdaten-Felder.
   - Es gibt zwar `bank_account_holder`, `bank_iban`, `bank_bic` — aber das sind Felder am **`Customer`**-Modell (`backend/app/Models/Customer.php:30–32`, Migration `2026_04_25_082743_add_mobile_phone_to_users_and_bank_fields_to_customers.php`) für SEPA-Lastschrift-Daten des *Kunden*, nicht die Bankverbindung der Hundeschule. Das ist eine andere Datenquelle mit anderem Zweck — **ungeprüfte Referenz vom User vermieden**, da hier keine Verwechslungsgefahr im Code besteht, aber es zeigt, dass "Kontodaten" im Code bereits doppeldeutig belegt ist.

4. **`backend/app/Http/Controllers/Api/SettingsController.php` ist vermutlich totes Backend-Duplikat.**
   Die tatsächlich geroutete Settings-Route (`backend/routes/api.php:206–207`) nutzt `App\Http\Controllers\SettingsController` (ohne `Api`-Namespace), nicht `App\Http\Controllers\Api\SettingsController`. Der `Api`-Controller mit den `company_vat_id`/`company_tax_number`-Validierungen scheint nicht verdrahtet zu sein — für den Architekten als Hinweis, damit nicht versehentlich der falsche Controller erweitert wird.

**Fazit:** Es fehlt nicht nur ein korrekter Settings-Key (wie bei PR #78), sondern die komplette Infrastruktur: neue Setting-Keys, Validierung, Seeder-Defaults, Frontend-Formularfelder, Controller-Übergabe an das PDF-Template und die eigentliche Template-Nutzung. Zusätzlich sind Firmenname/Adresse/Steuernummer im PDF ebenfalls hartkodiert — nicht nur die Bankdaten, die der User explizit nannte.

## Rückfragen an den User — beantwortet

1. **Scope Firmenstammdaten:** Nur Kontodaten beheben. Firmenname/Adresse/Steuernummer bleiben hartkodiert (separates Problem, nicht Teil dieses Change).
2. **Bankdaten-Felder:** IBAN + BIC + Bankname + Kontoinhaber (analog zu den `Customer`-Feldern `bank_account_holder`/`bank_iban`/`bank_bic`).
3. **E-Mail:** `emails/invoice-created.blade.php` wird mit geprüft und bei Bedarf ergänzt (nicht nur PDF).

## Zusätzliche Anforderung (Nachtrag des Users, nach Recherche)

Die Rechnung soll folgenden Überweisungstext enthalten:

```
Bitte überweisen Sie den Betrag innerhalb der <x> Wochen auf folgendes Konto:
<Kontoinhaber>
<Bankname>
<IBAN>
<BIC>
```

Recherche dazu: Es gibt aktuell **kein** "Zahlungsziel in Wochen"-Feld. `due_date` wird beim Anlegen der Rechnung frei vom User gesetzt (`StoreInvoiceRequest.php:91`, `backend/app/Http/Requests/StoreInvoiceRequest.php:91`) und ist bereits als konkretes Datum in PDF (`invoice.blade.php:258`, Label "Zahlungsziel") und E-Mail (`invoice-created.blade.php:93-94`) sichtbar.

**Klärung mit User:**
- `<x>` = **neues Settings-Feld** (z. B. `company_payment_term_weeks`), unabhängig vom individuellen `due_date` der jeweiligen Rechnung — kein dynamisch berechneter Wert.
- Der neue Überweisungstext wird **zusätzlich** zur bestehenden "Fälligkeitsdatum"/"Zahlungsziel"-Zeile eingefügt, ersetzt sie nicht.

## Empfohlene nächste Aktion

`@architect` (Modus A) mit Auftrag: openspec-Change erstellen, der:

(a) neue Settings ergänzt: `company_bank_account_holder`, `company_bank_name`, `company_bank_iban`, `company_bank_bic`, `company_payment_term_weeks` (Namen final vom Architekten festzulegen, Konvention beachten) — in `UpdateSettingsRequest`, `SettingsSeeder` und `SettingsView.vue`;
(b) `InvoiceController::downloadPdf` (und ggf. den Mail-Versand-Pfad für `InvoiceCreated`) die Settings an die jeweilige View übergibt — aktuell übergibt `InvoiceController.php:236` kein `$settings`-Array;
(c) `pdf/invoice.blade.php` und `emails/invoice-created.blade.php` den neuen Überweisungstext mit Kontodaten aus `$settings` zusätzlich zur bestehenden Fälligkeitsdatum-Zeile ergänzen. Nur Kontodaten — Firmenname/Adresse/Steuernummer bleiben out of scope für diesen Change.

Alle Rückfragen sind geklärt — Architekt kann direkt starten.
