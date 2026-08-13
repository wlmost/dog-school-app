## MODIFIED Requirements

### Requirement: Zahlungsstatus bleibt kompatibel mit Teilzahlungen

Der Status einer Rechnung SHALL nicht rein binär zwischen "offen" und
"bezahlt" umschalten, sondern mit mehreren Teilzahlungen kompatibel
bleiben; die Rechnung gilt erst als vollständig beglichen, wenn die Summe
der abgeschlossenen Zahlungen dem Gesamtbetrag entspricht. Der Übergang
zu `paid` SHALL automatisch erfolgen, sobald dieser Zustand erreicht ist
— nicht durch eine manuelle, formularlose Bestätigung.

#### Scenario: Rechnung mit Teilzahlung bleibt offen
- **GIVEN** eine Rechnung im Status `sent` mit einer abgeschlossenen
  Teilzahlung, die kleiner als der Gesamtbetrag ist
- **WHEN** der Restbetrag der Rechnung abgefragt wird
- **THEN** der Restbetrag ist größer als null und die Rechnung bleibt im
  Status `sent`

#### Scenario: Rechnung wechselt automatisch zu bezahlt, sobald die Summe der Zahlungen den Gesamtbetrag erreicht
- **GIVEN** eine Rechnung im Status `sent`, `reminded` oder `overdue`
  mit einem Restbetrag größer als null
- **WHEN** eine weitere abgeschlossene Zahlung erfasst wird, deren
  Betrag zusammen mit bereits abgeschlossenen Zahlungen den Gesamtbetrag
  erreicht oder übersteigt
- **THEN** die Rechnung wechselt automatisch in den Status `paid` und das
  Datum des Zahlungseingangs entspricht dem Zahlungsdatum der
  abschließenden Zahlung

#### Scenario: Zwei nahezu gleichzeitige Teilzahlungen führen zu genau einem Übergang nach bezahlt
- **GIVEN** eine Rechnung im Status `sent` mit einem Restbetrag, der der
  Summe zweier ausstehender Teilzahlungen entspricht
- **WHEN** beide Teilzahlungen nahezu gleichzeitig erfasst werden
- **THEN** beide Zahlungen werden persistiert und die Rechnung wechselt
  genau einmal in den Status `paid`, ohne verlorene Aktualisierung
