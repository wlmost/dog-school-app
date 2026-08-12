# Tasks für fix-invoice-pdf-status-visible

## T01: Status-Absatz und ungenutztes CSS aus dem Rechnungs-PDF entfernen, Regressionstest ergänzen

- **Agent:** dev-php
- **Dateien:**
  - `backend/resources/views/pdf/invoice.blade.php`
  - `backend/tests/Feature/InvoicePdfTest.php`
- **Abhängigkeiten:** keine
- **Beschreibung:**
  1. In `invoice.blade.php` den Status-Absatz (Zeilen 175-182) komplett
     entfernen:
     ```blade
     <p><strong>Status:</strong>
         <span class="status-badge">
             @if($invoice->status === 'paid') BEZAHLT
             @elseif($invoice->status === 'overdue') ÜBERFÄLLIG
             @else {{ strtoupper($invoice->status) }}
             @endif
         </span>
     </p>
     ```
     Kein Ersatzinhalt, kein leeres Label. `<div class="invoice-details">`
     behält danach nur noch die drei `<p>`-Zeilen "Rechnungsnummer",
     "Rechnungsdatum", "Fälligkeitsdatum" (siehe `design.md` Decision 2).
  2. Im `<style>`-Block derselben Datei die CSS-Regel `.status-badge`
     (Zeilen 117-123) entfernen, da sie nach Schritt 1 in dieser Datei
     ungenutzt ist. **Nicht anfassen:** die gleichnamige, aber
     unabhängige `.status-badge`-Regel in `pdf/anamnesis.blade.php:101`
     (separate Datei, separater `<style>`-Block, nicht Teil dieses
     Changes).
  3. **Ausdrücklich unverändert lassen:** `invoice.blade.php:258`
     (`@if($invoice->status !== 'paid') ... @else ... @endif`) — diese
     Zeile gibt keinen Statustext aus, sondern steuert die Auswahl
     zwischen Zahlungsinformationen- und Zahlungsbestätigungs-Box (siehe
     `design.md` Kontext). Nicht mit dem entfernten Absatz verwechseln.
  4. In `backend/tests/Feature/InvoicePdfTest.php` einen neuen Test
     ergänzen (Vorbild für das View-Render-Pattern:
     `backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php:28-32`),
     der das gerenderte HTML direkt prüft — siehe `design.md` Decision 1
     zur Begründung, warum HTML- statt PDF-Binär-Prüfung:
     ```php
     it('zeigt keinen internen dokumentstatus im rechnungs-pdf', function () {
         $this->invoice->update(['status' => 'draft']);
         $this->invoice->load(['customer.user', 'items']);

         $html = view('pdf.invoice', ['invoice' => $this->invoice])->render();

         expect($html)->not->toContain('DRAFT');
         expect($html)->not->toContain('Status:');
         expect($html)->not->toContain('status-badge');
     });
     ```
     Der bestehende `beforeEach`-Block in `InvoicePdfTest.php` (Zeile
     13-26) erzeugt bereits `$this->invoice` mit Items — dieser Test
     nutzt dieselbe Fixture und muss keine eigene aufbauen.
- **Akzeptanzkriterien:**
  - [x] `view('pdf.invoice', ['invoice' => $invoice])->render()` enthält
    für keinen `status`-Wert (`draft`, `sent`, `paid`, `overdue`,
    `cancelled`) mehr die Strings "Status:" oder den in Großbuchstaben
    ausgegebenen Rohstatus (z. B. "DRAFT").
  - [x] Der neue Test aus Schritt 4 ist grün.
  - [x] Alle bestehenden Tests in `InvoicePdfTest.php` bleiben grün,
    insbesondere "PDF shows paid status correctly" und "PDF shows
    overdue status correctly" (Zeilen 116-144), ohne Anpassung ihrer
    bestehenden Assertions nötig zu sein.
  - [x] `invoice.blade.php:258` (`@if($invoice->status !== 'paid')`) ist
    im Diff nicht verändert; die Zahlungsinformationen-Box (Status
    ≠ `paid`) und die Zahlungsbestätigungs-Box (Status `paid`) rendern
    weiterhin wie vor der Änderung.
  - [x] Die CSS-Regel `.status-badge` kommt in `invoice.blade.php` nicht
    mehr vor; `pdf/anamnesis.blade.php` ist unverändert (Diff zeigt keine
    Änderung an dieser Datei).
  - [x] `composer qa` läuft grün (lint, stan, compat-check, pest).
