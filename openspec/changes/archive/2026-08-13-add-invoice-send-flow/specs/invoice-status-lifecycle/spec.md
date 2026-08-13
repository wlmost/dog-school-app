## MODIFIED Requirements

### Requirement: Listen- und Detail-Buttons pro Status

Die Rechnungs-Listenansicht und die Detailansicht SHALL je nach Status
der Rechnung nur die fachlich zulässigen Aktionen anzeigen.

#### Scenario: Entwurf zeigt PDF, Bearbeiten, Löschen und Freigeben
- **GIVEN** eine Rechnung im Status `draft`
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** die Aktionen PDF, Bearbeiten, Löschen und Freigeben werden
  angezeigt

#### Scenario: Offene Rechnung zeigt PDF, Senden und Stornieren
- **GIVEN** eine Rechnung im Status `sent`
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** die Aktionen PDF, Senden (öffnet den Versand-Dialog, siehe
  Capability `invoice-send-flow`) und Stornieren werden angezeigt

#### Scenario: Bezahlte Rechnung zeigt PDF, Stornieren und Zahlungseingangsdatum
- **GIVEN** eine Rechnung im Status `paid`
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** die Aktionen PDF und Stornieren sowie das Datum des
  Zahlungseingangs werden angezeigt

#### Scenario: Stornierte Rechnung zeigt nur PDF
- **GIVEN** eine Rechnung im Status `cancelled`
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** nur die Aktion PDF wird angezeigt
