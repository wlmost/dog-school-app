# invoice-pdf-status-display

## Purpose

Definiert, dass das Rechnungs-PDF den internen Dokumentstatus einer
Rechnung nicht als sichtbaren Text darstellt, während die davon
unabhängige funktionale Unterscheidung zwischen offener und bezahlter
Rechnung im PDF erhalten bleibt.

## Requirements

### Requirement: Rechnungs-PDF zeigt keinen internen Dokumentstatus als Text

Das Rechnungs-PDF (`pdf/invoice.blade.php`) SHALL den internen Status
der Rechnung (`draft`, `sent`, `paid`, `overdue`, `cancelled`) an keiner
Stelle als sichtbaren Text (Label "Status:" oder Statuswert) darstellen.

#### Scenario: Entwurfs-Rechnung zeigt keinen "DRAFT"-Text im PDF
- **GIVEN** eine Rechnung mit Status `draft`
- **WHEN** das PDF der Rechnung generiert wird
- **THEN** das PDF enthält weder das Label "Status:" noch den Text
  "DRAFT"

#### Scenario: Rechnung mit beliebigem anderem Status zeigt ebenfalls keinen Statustext
- **GIVEN** eine Rechnung mit Status `sent`, `paid`, `overdue` oder
  `cancelled`
- **WHEN** das PDF der Rechnung generiert wird
- **THEN** das PDF enthält weder das Label "Status:" noch den
  jeweiligen Statuswert als sichtbaren Text

### Requirement: Funktionale Unterscheidung zwischen offener und bezahlter Rechnung bleibt erhalten

Das Rechnungs-PDF SHALL die bestehende, nicht als Text sichtbare
Verzweigung zwischen der Zahlungsinformationen-Box (Rechnung nicht
bezahlt) und der Zahlungsbestätigungs-Box (Rechnung bezahlt) unverändert
beibehalten; die Entfernung der Statusanzeige SHALL diese Verzweigung
nicht beeinflussen.

#### Scenario: Unbezahlte Rechnung zeigt weiterhin die Zahlungsinformationen-Box
- **GIVEN** eine Rechnung mit Status ungleich `paid`
- **WHEN** das PDF der Rechnung generiert wird
- **THEN** das PDF enthält die Zahlungsinformationen-Box mit
  Zahlungsziel und Bankdaten

#### Scenario: Bezahlte Rechnung zeigt weiterhin die Zahlungsbestätigungs-Box
- **GIVEN** eine Rechnung mit Status `paid`
- **WHEN** das PDF der Rechnung generiert wird
- **THEN** das PDF enthält die Zahlungsbestätigungs-Box mit dem Hinweis,
  dass die Rechnung vollständig bezahlt wurde
