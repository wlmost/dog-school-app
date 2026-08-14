# invoice-dunning-trigger

## Purpose

Definiert das manuelle Auslösen einer Mahnung durch Admin oder Trainer:
eigenständiges Mahngebühren-Dokument statt Mutation der Original-Rechnung,
feste Gebühren je Stufe, Obergrenze bei Stufe 3, automatischer
E-Mail-Versand an den Kunden sowie die Ablösung des früheren
automatischen, unbeaufsichtigten Mahn-Mailversands.

## Requirements

### Requirement: Mahngebühr wird als eigenständiges Dokument gebucht

Eine ausgelöste Mahnung SHALL die Mahngebühr nicht als Mutation des
Gesamtbetrags der Original-Rechnung verbuchen, sondern ein
eigenständiges Gebührendokument mit eigener, fortlaufender
Rechnungsnummer erzeugen, das auf die Original-Rechnung verweist.

#### Scenario: Auslösen einer Mahnung erzeugt ein separates Gebührendokument
- **GIVEN** eine Rechnung im Status `sent` mit einem Gesamtbetrag
- **WHEN** ein Admin oder Trainer eine Mahnung auslöst
- **THEN** ein neues Dokument mit eigener, fortlaufender
  Rechnungsnummer wird erzeugt, das auf die Original-Rechnung verweist
  und genau die Mahngebühr der ausgelösten Stufe als Betrag ausweist

#### Scenario: Gesamtbetrag der Original-Rechnung bleibt unverändert
- **GIVEN** eine Rechnung mit einem Gesamtbetrag
- **WHEN** eine oder mehrere Mahnungen für diese Rechnung ausgelöst
  werden
- **THEN** der Gesamtbetrag der Original-Rechnung ist identisch mit dem
  Wert vor der ersten Mahnung

### Requirement: Feste Mahngebühren je Stufe, nicht frei eingebbar

Das System SHALL für jede der drei Mahnstufen einen festen,
vorkonfigurierten Gebührenbetrag verwenden. Der Betrag SHALL nicht als
freies Eingabefeld im Bestätigungsdialog änderbar sein.

#### Scenario: Erste Mahnung verwendet die für Stufe 1 konfigurierte Gebühr
- **GIVEN** eine Rechnung ohne bisherige Mahnung
- **WHEN** eine Mahnung ausgelöst wird
- **THEN** das erzeugte Gebührendokument weist genau den für Stufe 1
  konfigurierten Betrag aus

#### Scenario: Zweite und dritte Mahnung verwenden die jeweils höhere konfigurierte Gebühr
- **GIVEN** eine Rechnung mit einer vorhandenen Mahnstufe 1 bzw. 2
- **WHEN** die nächste Mahnung ausgelöst wird
- **THEN** das erzeugte Gebührendokument weist genau den für Stufe 2
  bzw. 3 konfigurierten Betrag aus

### Requirement: Obergrenze bei Mahnstufe 3

Nach Erreichen der dritten Mahnstufe SHALL keine weitere App-interne
Mahnung mehr ausgelöst werden können.

#### Scenario: Vierter Mahnungs-Versuch wird abgelehnt
- **GIVEN** eine Rechnung mit vorhandener Mahnstufe 3
- **WHEN** ein Admin oder Trainer erneut eine Mahnung auslöst
- **THEN** die Anfrage wird abgelehnt, es wird kein weiteres
  Gebührendokument und keine weitere Mahnstufe erzeugt

### Requirement: Automatischer E-Mail-Versand bei Mahnung

Das Auslösen einer Mahnung SHALL automatisch eine E-Mail an den Kunden
mit einem Hinweis auf die angefallene Mahngebühr verschicken.

#### Scenario: Erfolgreiche Mahnung verschickt eine Benachrichtigungs-E-Mail
- **GIVEN** eine Rechnung mit hinterlegter Kunden-E-Mail-Adresse
- **WHEN** ein Admin oder Trainer eine Mahnung erfolgreich auslöst
- **THEN** der Kunde erhält eine E-Mail mit dem Hinweis auf die
  Mahngebühr und dem Gebührendokument als Anhang

#### Scenario: Fehlgeschlagener E-Mail-Versand nimmt die erfasste Mahnung nicht zurück
- **GIVEN** eine Rechnung, für die eine Mahnung ausgelöst wird
- **WHEN** der E-Mail-Versand fehlschlägt
- **THEN** die Mahnstufe und das Gebührendokument bleiben erfasst, und
  der auslösenden Person wird der Fehlschlag mitgeteilt

### Requirement: Nur Admin und Trainer können eine Mahnung auslösen

Das Auslösen einer Mahnung SHALL ausschließlich Nutzern mit der Rolle
Admin oder Trainer möglich sein.

#### Scenario: Admin kann mahnen
- **GIVEN** ein Admin und eine mahnfähige Rechnung
- **WHEN** der Admin eine Mahnung auslöst
- **THEN** die Mahnung wird erfasst

#### Scenario: Trainer kann mahnen
- **GIVEN** ein Trainer und eine mahnfähige Rechnung
- **WHEN** der Trainer eine Mahnung auslöst
- **THEN** die Mahnung wird erfasst

#### Scenario: Kunde kann nicht mahnen
- **GIVEN** ein Kunde und eine mahnfähige eigene Rechnung
- **WHEN** der Kunde versucht, eine Mahnung auszulösen
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt

### Requirement: Automatischer, unbeaufsichtigter Mahn-Mailversand entfällt

Das System SHALL keinen automatischen, zeitgesteuerten Mailversand für
überfällige Rechnungen ohne Bestätigung durch einen Admin oder Trainer
mehr ausführen. Jede Mahnung SHALL ausschließlich über den manuellen
Bestätigungs-Trigger erzeugt werden.

#### Scenario: Kein zeitgesteuerter Mahn-Mailversand
- **GIVEN** eine überfällige Rechnung
- **WHEN** die geplanten Hintergrundaufgaben des Systems ausgeführt
  werden
- **THEN** es wird keine Mahn-E-Mail ohne vorherige Bestätigung durch
  einen Admin oder Trainer verschickt
