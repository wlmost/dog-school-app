## ADDED Requirements

### Requirement: Rechnungs-PDF zeigt Firmenkopf und -fuß aus den Einstellungen statt eines hartkodierten Platzhalters

Das Rechnungs-PDF (`pdf/invoice.blade.php`) SHALL Firmenname (`company_name`), Straße (`company_street`), PLZ/Ort (`company_zip`/`company_city`), Telefon (`company_phone`) und E-Mail (`company_email`) im Kopfbereich sowie Firmenname, Straße, PLZ/Ort und Steuernummer (`company_tax_id`) im Fußbereich aus den Systemeinstellungen anzeigen.

Nicht mehr angezeigt werden darf die frühere hartkodierte
Platzhalteradresse "Hundeschule Max Mustermann" / "Musterstraße 123 •
12345 Musterstadt" / "+49 123 456789" / "info@hundeschule-mustermann.de"
im Kopf bzw. die hartkodierte USt-IdNr "DE123456789" im Fuß.

#### Scenario: PDF einer Rechnung zeigt konfigurierten Firmenkopf und -fuß
- **GIVEN** die Einstellungen enthalten reale Firmendaten der Hundeschule
  (`company_name`, `company_street`, `company_zip`, `company_city`,
  `company_phone`, `company_email`, `company_tax_id`)
- **WHEN** das PDF einer Rechnung generiert wird
- **THEN** der Kopfbereich des PDF enthält Firmenname, Straße, PLZ/Ort,
  Telefon und E-Mail aus den Einstellungen
- **AND** der Fußbereich des PDF enthält Firmenname, Straße, PLZ/Ort und
  die Steuernummer aus den Einstellungen
- **AND** das PDF enthält weder "Hundeschule Max Mustermann" noch
  "Musterstraße 123" noch "DE123456789" als hartkodierten Text im
  Blade-Quellcode

#### Scenario: Fehlende Firmendaten führen nicht zu einem Rendering-Fehler
- **GIVEN** `company_street` (oder ein anderes Firmenfeld außer
  `company_name`) ist in den Einstellungen nicht gesetzt
- **WHEN** das PDF einer Rechnung generiert wird
- **THEN** die PDF-Generierung schlägt nicht mit einem PHP-Fehler fehl
- **AND** das entsprechende Feld erscheint leer im Dokument statt eines
  erfundenen Platzhalterwerts

#### Scenario: Fehlender Firmenname zeigt einen neutralen Fallback statt eines leeren Kopfes
- **GIVEN** `company_name` ist in den Einstellungen nicht gesetzt
- **WHEN** das PDF einer Rechnung generiert wird
- **THEN** der Kopf- und Fußbereich zeigen "Hundeschule" als neutralen
  Fallback statt eines leeren Firmennamens oder der alten
  Platzhalteradresse

---

### Requirement: Anamnese-PDF zeigt Firmenkopf und -fuß aus denselben Einstellungen wie das Rechnungs-PDF

Das Anamnese-PDF (`pdf/anamnesis.blade.php`) SHALL im Kopfbereich und im Fußbereich dieselben Firmendaten aus den Systemeinstellungen anzeigen wie das Rechnungs-PDF (`company_name`, `company_street`, `company_zip`, `company_city`, `company_phone`, `company_email` im Kopf; `company_name`, `company_street`, `company_zip`, `company_city`, `company_tax_id` im Fuß), nicht die frühere hartkodierte Platzhalteradresse.

Die bestehende, anamnese-spezifische Fußzeile mit dem Erstellungszeitpunkt
("Erstellt am: {Datum} {Uhrzeit} Uhr") SHALL unverändert zusätzlich zu den
Firmendaten angezeigt werden.

#### Scenario: PDF einer Anamnese-Antwort zeigt konfigurierten Firmenkopf und -fuß
- **GIVEN** die Einstellungen enthalten reale Firmendaten der Hundeschule
- **WHEN** das PDF einer Anamnese-Antwort generiert wird
- **THEN** der Kopfbereich des PDF enthält Firmenname, Straße, PLZ/Ort,
  Telefon und E-Mail aus den Einstellungen
- **AND** der Fußbereich des PDF enthält Firmenname, Straße, PLZ/Ort und
  die Steuernummer aus den Einstellungen
- **AND** das PDF enthält weder "Hundeschule Max Mustermann" noch
  "Musterstraße 123" noch "DE123456789" als hartkodierten Text im
  Blade-Quellcode

#### Scenario: Erstellungszeitpunkt-Zeile bleibt unverändert neben den Firmendaten bestehen
- **GIVEN** die Einstellungen enthalten reale Firmendaten der Hundeschule
- **WHEN** das PDF einer Anamnese-Antwort generiert wird
- **THEN** der Fußbereich enthält zusätzlich zu den Firmendaten weiterhin
  die Zeile "Erstellt am: {{ now()->format('d.m.Y H:i') }} Uhr"

#### Scenario: Fehlende Firmendaten führen nicht zu einem Rendering-Fehler
- **GIVEN** `company_street` (oder ein anderes Firmenfeld außer
  `company_name`) ist in den Einstellungen nicht gesetzt
- **WHEN** das PDF einer Anamnese-Antwort generiert wird
- **THEN** die PDF-Generierung schlägt nicht mit einem PHP-Fehler fehl
- **AND** das entsprechende Feld erscheint leer im Dokument statt eines
  erfundenen Platzhalterwerts

---

### Requirement: Firmenkopf- und -fußtext ist zwischen Rechnungs- und Anamnese-PDF an einer Stelle gepflegt

Der zwischen Rechnungs- und Anamnese-PDF identische Kopf-Textblock (Firmenname, Straße, PLZ/Ort, Telefon, E-Mail) sowie die gemeinsamen Fuß-Textzeilen (Firmenname, Straße, PLZ/Ort, Steuernummer) SHALL über gemeinsame Blade-Partials dargestellt werden, sodass eine künftige Anpassung des Textformats an einer Stelle erfolgen kann statt an beiden Templates einzeln.

#### Scenario: Änderung am gemeinsamen Kopf-Partial wirkt sich auf beide PDF-Typen aus
- **GIVEN** das gemeinsame Kopf-Partial rendert Firmenname, Straße,
  PLZ/Ort, Telefon und E-Mail
- **WHEN** sowohl das Rechnungs-PDF als auch das Anamnese-PDF generiert
  werden
- **THEN** beide PDF-Typen zeigen denselben Kopf-Textinhalt, erzeugt aus
  demselben Partial, nicht aus zwei unabhängig gepflegten
  Code-Duplikaten
