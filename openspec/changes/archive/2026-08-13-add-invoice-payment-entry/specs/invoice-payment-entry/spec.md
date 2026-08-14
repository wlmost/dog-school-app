## ADDED Requirements

### Requirement: Zahlungseingang wird über eine Eingabemaske erfasst

Admins und Trainer SHALL einen Zahlungseingang für eine offene Rechnung
(`sent`, `reminded` oder `overdue`) über eine Eingabemaske mit Betrag,
Datum, Zahlungsart und optionaler Referenz erfassen können. Kunden SHALL
keine Zahlungen erfassen können.

#### Scenario: Admin oder Trainer erfasst eine Teilzahlung
- **GIVEN** eine Rechnung im Status `sent` mit einem offenen Restbetrag
- **WHEN** ein Admin oder Trainer über die Eingabemaske einen Betrag
  kleiner als der Restbetrag, ein Datum, eine Zahlungsart und optional
  eine Referenz erfasst
- **THEN** ein neuer, abgeschlossener Zahlungsdatensatz wird angelegt und
  die Rechnung bleibt im Status `sent`

#### Scenario: Kunde kann keine Zahlung erfassen
- **GIVEN** ein authentifizierter Kunde
- **WHEN** er versucht, eine Zahlung über die API zu erfassen
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt

#### Scenario: Trainer kann nur für eigene zugewiesene Kunden Zahlungen erfassen
- **GIVEN** ein Trainer und eine Rechnung eines Kunden, der einem anderen
  Trainer zugewiesen ist
- **WHEN** der Trainer versucht, eine Zahlung für diese Rechnung zu
  erfassen
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt

### Requirement: Zahlungserfassung ist auf offene Rechnungen beschränkt

Eine Zahlung SHALL nur für Rechnungen in einem der Status `sent`,
`reminded` oder `overdue` erfasst werden können. Für Entwürfe,
stornierte oder bereits vollständig bezahlte Rechnungen SHALL die
Erfassung abgelehnt werden.

#### Scenario: Zahlung für einen Entwurf wird abgelehnt
- **GIVEN** eine Rechnung im Status `draft`
- **WHEN** ein Admin versucht, eine Zahlung dafür zu erfassen
- **THEN** die Anfrage wird mit HTTP 422 abgelehnt

#### Scenario: Zahlung für eine stornierte Rechnung wird abgelehnt
- **GIVEN** eine Rechnung im Status `cancelled`
- **WHEN** ein Admin versucht, eine Zahlung dafür zu erfassen
- **THEN** die Anfrage wird mit HTTP 422 abgelehnt

### Requirement: Der Zahlungsbetrag darf den Restbetrag nicht übersteigen

Ein einzelner Zahlungsbetrag SHALL den aktuellen Restbetrag
(`remainingBalance`) der Rechnung nicht übersteigen.

#### Scenario: Zahlungsbetrag über dem Restbetrag wird abgelehnt
- **GIVEN** eine Rechnung mit einem Restbetrag von 50,00 €
- **WHEN** ein Admin oder Trainer eine Zahlung über 60,00 € erfasst
- **THEN** die Anfrage wird mit HTTP 422 und einer Nachricht abgelehnt,
  die den aktuellen Restbetrag nennt

#### Scenario: Zahlungsbetrag exakt in Höhe des Restbetrags wird akzeptiert
- **GIVEN** eine Rechnung mit einem Restbetrag von 50,00 €
- **WHEN** ein Admin oder Trainer eine Zahlung über genau 50,00 € erfasst
- **THEN** die Zahlung wird angelegt und die Rechnung wechselt in den
  Status `paid`

### Requirement: Zahlungseingang wird in Liste und Detailansicht angezeigt

Die Rechnungs-Listenansicht SHALL für Rechnungen mit mindestens einer
abgeschlossenen Teilzahlung den bereits bezahlten Betrag und den
Restbetrag anzeigen, solange die Rechnung noch nicht vollständig bezahlt
ist. Die Detailansicht SHALL zusätzlich jede einzelne Zahlung (Betrag,
Datum, Zahlungsart, Referenz) auflisten.

#### Scenario: Liste zeigt Teilzahlungsfortschritt
- **GIVEN** eine Rechnung im Status `sent` mit Gesamtbetrag 200,00 € und
  einer abgeschlossenen Zahlung über 150,00 €
- **WHEN** ein Admin oder Trainer die Rechnungsliste betrachtet
- **THEN** die Zeile zeigt an, dass 150,00 € von 200,00 € bezahlt wurden

#### Scenario: Detailansicht listet einzelne Zahlungen
- **GIVEN** eine Rechnung mit zwei abgeschlossenen Teilzahlungen
- **WHEN** ein Admin oder Trainer die Detailansicht öffnet
- **THEN** beide Zahlungen werden mit korrektem Betrag, Datum und
  Zahlungsart angezeigt, sowie die Summe der bezahlten Beträge und der
  verbleibende Restbetrag
