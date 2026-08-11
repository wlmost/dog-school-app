# Spec: invoice-bank-details

**Status:** ADDED
**Capability:** Bankverbindung und Standard-Zahlungsziel der Hundeschule in
Einstellungen, dargestellt auf Rechnungs-PDF, Rechnungs-E-Mail und
Zahlungserinnerung-E-Mail

---

## Purpose

Ein Admin kann die Bankverbindung der Hundeschule (Kontoinhaber, Bankname,
IBAN, BIC) sowie ein Standard-Zahlungsziel in Wochen in den
Systemeinstellungen pflegen. Rechnungs-PDF, Rechnungs-E-Mail und
Zahlungserinnerung-E-Mail zeigen diese Werte anstelle einer hartkodierten
Platzhalter-IBAN/BIC. Rechnungs-PDF und Rechnungs-E-Mail enthalten
zusätzlich zur bestehenden Fälligkeitsdatum-Zeile einen Überweisungstext
mit diesen Angaben; die Zahlungserinnerung-E-Mail zeigt dieselben
Kontodaten ohne die Wochen-Frist-Formulierung, da die betroffene Rechnung
dort bereits fällig bzw. überfällig ist.

---

## ADDED Requirements

### Requirement: Bankdaten der Hundeschule sind in den Einstellungen konfigurierbar

Ein Admin SHALL Kontoinhaber, Bankname, IBAN und BIC der Hundeschule über
`PUT /api/v1/settings` setzen können. Alle vier Felder SHALL optional sein
(kein Pflichtfeld). IBAN und BIC SHALL gegen ein Format-Muster validiert
werden, bevor sie gespeichert werden.

#### Scenario: Admin speichert vollständige Bankdaten
- **GIVEN** ein Admin ist authentifiziert
- **WHEN** er `PUT /api/v1/settings` mit `company_bank_account_holder`,
  `company_bank_name`, `company_bank_iban` (Format `DE` + 20 Ziffern) und
  `company_bank_bic` (11-stelliger SWIFT-Code) sendet
- **THEN** die Anfrage wird mit HTTP 200 beantwortet
- **AND** `GET /api/v1/settings` liefert anschließend genau diese Werte
  in der Gruppe `company`

#### Scenario: Bankdaten-Felder bleiben optional
- **GIVEN** ein Admin sendet `PUT /api/v1/settings` ohne die vier
  Bankdaten-Felder
- **THEN** die Anfrage wird mit HTTP 200 beantwortet, kein
  Validierungsfehler für die Bankfelder

#### Scenario: Ungültige IBAN wird abgelehnt
- **GIVEN** ein Admin sendet `company_bank_iban` mit einem Wert, der nicht
  dem Muster `^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$` entspricht (z. B.
  Kleinbuchstaben oder Leerzeichen)
- **THEN** die API liefert HTTP 422 mit einem Validierungsfehler für
  `company_bank_iban`

#### Scenario: Ungültige BIC wird abgelehnt
- **GIVEN** ein Admin sendet `company_bank_bic` mit einem Wert, der nicht
  dem Muster `^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$` entspricht
- **THEN** die API liefert HTTP 422 mit einem Validierungsfehler für
  `company_bank_bic`

---

### Requirement: Standard-Zahlungsziel in Wochen ist konfigurierbar und unabhängig vom individuellen Fälligkeitsdatum

Ein Admin SHALL einen festen Wert `company_payment_term_weeks` (ganze
Zahl, 1–52) über die Einstellungen pflegen können. Dieser Wert SHALL
unabhängig vom `due_date`/`dueDate`-Feld einzelner Rechnungen sein und
diese nicht verändern oder berechnen.

#### Scenario: Gültiger Wochenwert wird gespeichert
- **GIVEN** ein Admin ist authentifiziert
- **WHEN** er `PUT /api/v1/settings` mit `company_payment_term_weeks: 3`
  sendet
- **THEN** die Anfrage wird mit HTTP 200 beantwortet
- **AND** der gespeicherte Wert ist als Integer abrufbar (kein String)

#### Scenario: Wert außerhalb des gültigen Bereichs wird abgelehnt
- **GIVEN** ein Admin sendet `company_payment_term_weeks: 0` oder
  `company_payment_term_weeks: 53`
- **THEN** die API liefert HTTP 422 mit einem Validierungsfehler für
  `company_payment_term_weeks`

#### Scenario: Individuelles Fälligkeitsdatum bleibt unverändert
- **GIVEN** eine Rechnung wurde mit einem beliebigen `dueDate` angelegt
- **WHEN** `company_payment_term_weeks` in den Einstellungen geändert wird
- **THEN** das `due_date` der bestehenden Rechnung bleibt unverändert

---

### Requirement: Admin kann die neuen Felder im Settings-Formular pflegen

Das Settings-Formular (`SettingsView.vue`) SHALL Eingabefelder für
Kontoinhaber, Bankname, IBAN, BIC und Standard-Zahlungsziel (Wochen)
anzeigen und beim Speichern an die Settings-API übertragen.

#### Scenario: Admin füllt Bankdaten im Formular aus und speichert
- **GIVEN** das Settings-Formular ist geladen
- **WHEN** der Admin Kontoinhaber, Bankname, IBAN, BIC und
  Zahlungsziel-Wochen ausfüllt und auf "Speichern" klickt
- **THEN** alle fünf Werte werden im Request an
  `PUT /api/v1/settings` übertragen

#### Scenario: Bereits gespeicherte Bankdaten werden beim Laden angezeigt
- **GIVEN** die Settings enthalten bereits Werte für die fünf neuen Keys
- **WHEN** das Settings-Formular geladen wird
- **THEN** die Eingabefelder zeigen die geladenen Werte an

---

### Requirement: Rechnungs-PDF zeigt Kontodaten aus den Einstellungen statt eines Platzhalters

Das Rechnungs-PDF (`pdf/invoice.blade.php`) SHALL für nicht bezahlte
Rechnungen die aktuellen Werte von `company_bank_account_holder`,
`company_bank_name`, `company_bank_iban` und `company_bank_bic` aus den
Einstellungen anzeigen, nicht die frühere hartkodierte Platzhalter-IBAN
(`DE89 3704 0044 0532 0130 00`) bzw. -BIC (`COBADEFFXXX`). Zusätzlich zur
bestehenden `Zahlungsziel: {due_date}`-Zeile SHALL das PDF einen
Überweisungstext mit dem Wortlaut "Bitte überweisen Sie den Betrag
innerhalb von {company_payment_term_weeks} Wochen auf folgendes Konto:"
gefolgt von den vier Kontodaten enthalten.

#### Scenario: PDF einer offenen Rechnung zeigt konfigurierte Kontodaten
- **GIVEN** die Einstellungen enthalten reale Bankdaten der Hundeschule
- **AND** eine Rechnung hat den Status `open` (nicht `paid`)
- **WHEN** das PDF der Rechnung generiert wird
- **THEN** das PDF enthält Kontoinhaber, Bankname, IBAN und BIC aus den
  Einstellungen
- **AND** das PDF enthält weder `DE89 3704 0044 0532 0130 00` noch
  `COBADEFFXXX` als hartkodierten Text

#### Scenario: PDF enthält Zahlungsziel-Zeile und Überweisungstext nebeneinander
- **GIVEN** eine offene Rechnung mit einem individuellen `due_date`
- **WHEN** das PDF generiert wird
- **THEN** das PDF enthält weiterhin die Zeile
  "Zahlungsziel: {due_date}"
- **AND** das PDF enthält zusätzlich den Überweisungstext mit der Anzahl
  Wochen aus `company_payment_term_weeks`

#### Scenario: Fehlende Bankdaten führen nicht zu einem Rendering-Fehler
- **GIVEN** `company_bank_iban` (oder ein anderes Bankfeld) ist in den
  Einstellungen nicht gesetzt
- **WHEN** das PDF generiert wird
- **THEN** die PDF-Generierung schlägt nicht mit einem PHP-Fehler fehl
- **AND** das entsprechende Feld erscheint leer im Dokument

---

### Requirement: Rechnungs-E-Mail zeigt dieselben Kontodaten wie das PDF

Die Rechnungs-E-Mail SHALL (versendet über `App\Mail\InvoiceCreated`,
Template `emails/invoice-created.blade.php`) dieselben vier
Kontodaten-Werte und denselben Überweisungstext wie das PDF anzeigen,
nicht die frühere hartkodierte Platzhalter-IBAN/BIC.

#### Scenario: Rechnungs-E-Mail enthält konfigurierte Kontodaten
- **GIVEN** die Einstellungen enthalten reale Bankdaten der Hundeschule
- **WHEN** eine neue Rechnung angelegt wird und die
  `InvoiceCreated`-E-Mail versendet wird
- **THEN** die E-Mail enthält Kontoinhaber, Bankname, IBAN und BIC aus den
  Einstellungen
- **AND** die E-Mail enthält weder `DE89 3704 0044 0532 0130 00` noch
  `COBADEFFXXX` als hartkodierten Text

#### Scenario: E-Mail enthält Zahlungsziel-Zeile und Überweisungstext nebeneinander
- **GIVEN** eine neu angelegte Rechnung mit einem individuellen `due_date`
- **WHEN** die `InvoiceCreated`-E-Mail versendet wird
- **THEN** die E-Mail enthält weiterhin die bestehende
  "Zahlungsziel"-Zeile mit dem individuellen `due_date`
- **AND** die E-Mail enthält zusätzlich den Überweisungstext mit der
  Anzahl Wochen aus `company_payment_term_weeks`

---

### Requirement: Zahlungserinnerung-E-Mail zeigt dieselben Kontodaten ohne Wochen-Frist-Text

Die Zahlungserinnerung-E-Mail SHALL (versendet über
`App\Mail\PaymentReminder`, Template `emails/payment-reminder.blade.php`)
Kontoinhaber, Bankname, IBAN und BIC aus den Einstellungen anzeigen, nicht
die frühere hartkodierte Platzhalter-IBAN/BIC. Anders als PDF und
Rechnungs-E-Mail SHALL die Zahlungserinnerung-E-Mail **keinen**
Wochen-Frist-Text ("innerhalb von X Wochen") enthalten, da die betroffene
Rechnung zum Zeitpunkt der Zahlungserinnerung bereits fällig oder
überfällig ist. Der bestehende, fristneutrale Einleitungssatz und die
bestehende `Verwendungszweck`-Zeile mit der Rechnungsnummer SHALL
unverändert bleiben.

#### Scenario: Zahlungserinnerung-E-Mail enthält konfigurierte Kontodaten
- **GIVEN** die Einstellungen enthalten reale Bankdaten der Hundeschule
- **WHEN** eine Zahlungserinnerung für eine offene Rechnung versendet wird
- **THEN** die E-Mail enthält Kontoinhaber, Bankname, IBAN und BIC aus den
  Einstellungen
- **AND** die E-Mail enthält weder `DE89 3704 0044 0532 0130 00` noch
  `COBADEFFXXX` als hartkodierten Text

#### Scenario: Zahlungserinnerung-E-Mail enthält keine Wochen-Frist-Formulierung
- **GIVEN** eine Zahlungserinnerung wird für eine überfällige Rechnung
  versendet
- **WHEN** die E-Mail generiert wird
- **THEN** die E-Mail enthält weiterhin den bestehenden Einleitungssatz
  ("Bitte überweisen Sie den offenen Betrag unter Angabe der
  Rechnungsnummer auf folgendes Konto:")
- **AND** die E-Mail enthält **keinen** Satz der Form "innerhalb von X
  Wochen"

#### Scenario: Fehlende Bankdaten führen nicht zu einem Rendering-Fehler
- **GIVEN** `company_bank_iban` (oder ein anderes Bankfeld) ist in den
  Einstellungen nicht gesetzt
- **WHEN** die Zahlungserinnerung-E-Mail generiert wird
- **THEN** die E-Mail-Generierung schlägt nicht mit einem PHP-Fehler fehl
- **AND** das entsprechende Feld erscheint leer im Dokument
