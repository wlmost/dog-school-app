## MODIFIED Requirements

### Requirement: Mahnstufen-Datenmodell

Das System SHALL mehrere Mahnstufen pro Rechnung mit jeweils Datum und
Mahngebühr persistieren können, sowie den Status `reminded` für
Rechnungen mit mindestens einer Mahnung unterstützen. Ein Admin oder
Trainer SHALL eine Mahnung für eine mahnfähige Rechnung auslösen können;
dabei SHALL genau eine weitere Mahnstufe (1, 2 oder 3) mit fester,
vorkonfigurierter Gebühr erzeugt werden. Ab Stufe 3 SHALL keine weitere
App-interne Mahnung mehr möglich sein.

#### Scenario: Mahnung mit Stufe, Datum und Gebühr kann persistiert werden
- **GIVEN** eine Rechnung im Status `sent` oder `overdue`
- **WHEN** ein Mahn-Datensatz mit Stufe, Datum und Gebühr für diese
  Rechnung angelegt wird
- **THEN** die Rechnung liefert dieses Datum als jüngstes Mahndatum und
  die zugehörige Stufe als aktuelle Mahnstufe

#### Scenario: Rechnung ohne Mahnung liefert kein Mahndatum
- **GIVEN** eine Rechnung ohne zugehörige Mahn-Datensätze
- **WHEN** die Rechnung abgefragt wird
- **THEN** Mahndatum und Mahnstufe sind leer

#### Scenario: Auslösen einer Mahnung erzeugt die nächsthöhere Stufe mit fester Gebühr
- **GIVEN** eine Rechnung im Status `sent`, `reminded` oder `overdue`
  ohne Mahnung oder mit einer bereits vorhandenen Mahnstufe kleiner als 3
- **WHEN** ein Admin oder Trainer eine Mahnung auslöst
- **THEN** ein neuer Mahn-Datensatz mit der nächsthöheren Stufe und der
  für diese Stufe vorkonfigurierten, festen Gebühr wird angelegt, die
  Rechnung wechselt in den Status `reminded`, und der Gesamtbetrag der
  Original-Rechnung bleibt unverändert

#### Scenario: Maximale Mahnstufe wurde bereits erreicht
- **GIVEN** eine Rechnung mit einer bereits vorhandenen Mahnstufe 3
- **WHEN** ein Admin oder Trainer erneut eine Mahnung auslöst
- **THEN** die Anfrage wird abgelehnt und es wird keine weitere Mahnstufe
  erzeugt

#### Scenario: Mahnung ist nur für mahnfähige Rechnungen möglich
- **GIVEN** eine Rechnung im Status `draft`, `paid` oder `cancelled`,
  oder eine Rechnung, die selbst ein Storno- oder Mahngebühren-Dokument
  einer anderen Rechnung ist
- **WHEN** ein Admin oder Trainer eine Mahnung auslöst
- **THEN** die Anfrage wird abgelehnt und es wird keine Mahnstufe erzeugt

#### Scenario: Nur Admin und Trainer können mahnen
- **GIVEN** ein Kunde und eine mahnfähige Rechnung
- **WHEN** der Kunde versucht, eine Mahnung auszulösen
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt

### Requirement: Listen- und Detail-Buttons pro Status

Die Rechnungs-Listenansicht und die Detailansicht SHALL je nach Status
der Rechnung nur die fachlich zulässigen Aktionen anzeigen.

#### Scenario: Entwurf zeigt PDF, Bearbeiten, Löschen und Freigeben
- **GIVEN** eine Rechnung im Status `draft`
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** die Aktionen PDF, Bearbeiten, Löschen und Freigeben werden
  angezeigt

#### Scenario: Offene Rechnung zeigt PDF, Senden, Zahlung erfassen, Mahnen und Stornieren
- **GIVEN** eine Rechnung im Status `sent`
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** die Aktionen PDF, Senden, Zahlung erfassen, Mahnen und
  Stornieren werden angezeigt

#### Scenario: Bezahlte Rechnung zeigt PDF, Stornieren und Zahlungseingangsdatum
- **GIVEN** eine Rechnung im Status `paid`
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** die Aktionen PDF und Stornieren sowie das Datum des
  Zahlungseingangs werden angezeigt

#### Scenario: Gemahnte Rechnung zeigt PDF, Senden, Zahlung erfassen, Mahndatum und ggf. weiteres Mahnen
- **GIVEN** eine Rechnung im Status `reminded` mit einer aktuellen
  Mahnstufe kleiner als 3
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** die Aktionen PDF, Senden, Zahlung erfassen, Mahnen (für die
  nächste Stufe) und Stornieren werden angezeigt, sowie das Datum der
  letzten Mahnung

#### Scenario: Gemahnte Rechnung auf maximaler Mahnstufe zeigt keinen Mahnen-Button mehr
- **GIVEN** eine Rechnung im Status `reminded` mit Mahnstufe 3
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** die Aktion Mahnen wird nicht mehr angezeigt

#### Scenario: Stornierte Rechnung zeigt nur PDF
- **GIVEN** eine Rechnung im Status `cancelled`
- **WHEN** ein Admin oder Trainer die Liste betrachtet
- **THEN** nur die Aktion PDF wird angezeigt
