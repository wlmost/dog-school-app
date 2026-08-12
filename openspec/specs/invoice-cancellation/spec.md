# invoice-cancellation

## Purpose

Definiert, wie eine Rechnung storniert wird: als eigenständiges
Korrekturdokument mit negierten Beträgen statt als reiner Statuswechsel,
inklusive Berechtigungen und Ausschluss von Mehrfach-Stornierungen.

## Requirements

### Requirement: Stornierung erzeugt ein eigenständiges Korrekturdokument

Eine Stornierung SHALL nicht als reiner Statuswechsel der
Original-Rechnung umgesetzt werden, sondern eine neue, eigenständige
Rechnung mit eigener Rechnungsnummer erzeugen, die die Original-Rechnung
betragsmäßig vollständig ausgleicht.

#### Scenario: Stornieren einer offenen Rechnung erzeugt eine Stornorechnung
- **GIVEN** eine Rechnung im Status `sent`
- **WHEN** ein Admin oder Trainer die Stornierung auslöst
- **THEN** eine neue Rechnung mit eigener, fortlaufender Rechnungsnummer
  wird erzeugt, die auf die Original-Rechnung verweist

#### Scenario: Stornieren einer bezahlten Rechnung erzeugt eine Stornorechnung
- **GIVEN** eine Rechnung im Status `paid`
- **WHEN** ein Admin oder Trainer die Stornierung auslöst
- **THEN** eine neue Rechnung mit eigener, fortlaufender Rechnungsnummer
  wird erzeugt, die auf die Original-Rechnung verweist

#### Scenario: Stornorechnung gleicht den Original-Betrag vollständig aus
- **GIVEN** eine Original-Rechnung mit einem Gesamtbetrag und den
  zugehörigen Rechnungspositionen
- **WHEN** die Stornorechnung erzeugt wird
- **THEN** die Summe aus dem Gesamtbetrag der Original-Rechnung und dem
  Gesamtbetrag der Stornorechnung ergibt null, und jede Position der
  Stornorechnung entspricht betragsmäßig der negierten Original-Position

### Requirement: Original-Rechnung wird als storniert markiert

Nach erfolgreicher Erzeugung einer Stornorechnung SHALL die
Original-Rechnung in den Status `cancelled` wechseln.

#### Scenario: Original-Rechnung zeigt nach Storno den Status "storniert"
- **GIVEN** eine Rechnung im Status `sent` oder `paid`
- **WHEN** sie erfolgreich storniert wird
- **THEN** ihr Status ist `cancelled`

### Requirement: Nur Admin und Trainer können stornieren

Die Stornierung einer Rechnung SHALL ausschließlich Nutzern mit der Rolle
Admin oder Trainer möglich sein.

#### Scenario: Admin kann stornieren
- **GIVEN** ein Admin und eine Rechnung im Status `sent`
- **WHEN** der Admin die Stornierung auslöst
- **THEN** die Stornierung wird ausgeführt

#### Scenario: Trainer kann stornieren
- **GIVEN** ein Trainer und eine Rechnung im Status `sent`
- **WHEN** der Trainer die Stornierung auslöst
- **THEN** die Stornierung wird ausgeführt

#### Scenario: Kunde kann nicht stornieren
- **GIVEN** ein Kunde und eine eigene Rechnung im Status `sent`
- **WHEN** der Kunde versucht, die Stornierung auszulösen
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt

### Requirement: Entwürfe können nicht storniert werden

Eine Rechnung im Status `draft` SHALL nicht storniert werden können, da
sie stattdessen gelöscht wird.

#### Scenario: Storno-Aktion für Entwurf wird verweigert
- **GIVEN** eine Rechnung im Status `draft`
- **WHEN** ein Admin oder Trainer versucht, die Rechnung zu stornieren
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt

### Requirement: Stornorechnungen können nicht erneut storniert werden

Eine Rechnung, die selbst als Stornorechnung erzeugt wurde, SHALL nicht
ein weiteres Mal storniert werden können.

#### Scenario: Storno-Aktion für eine Stornorechnung wird verweigert
- **GIVEN** eine Stornorechnung, die eine Original-Rechnung ausgleicht
- **WHEN** ein Admin oder Trainer versucht, diese Stornorechnung zu
  stornieren
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt

### Requirement: Bereits stornierte Rechnungen können nicht erneut storniert werden

Eine Rechnung, die bereits im Status `cancelled` ist, SHALL nicht ein
weiteres Mal storniert werden können.

#### Scenario: Storno-Aktion für eine bereits stornierte Rechnung wird verweigert
- **GIVEN** eine Rechnung im Status `cancelled`
- **WHEN** ein Admin oder Trainer versucht, die Rechnung erneut zu
  stornieren
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt
