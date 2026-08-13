# invoice-status-lifecycle

## Purpose

Definiert den Lebenszyklus einer Rechnung von der Erstellung als Entwurf
bis zum Ausgleich (Bezahlt) bzw. Mahnfall (Überfällig/Gemahnt), inklusive
Rechnungsnummern-Vergabe, Unveränderlichkeit ab Freigabe, status-abhängiger
UI-Aktionen und rollenbasierter Sichtbarkeit.

## Requirements

### Requirement: Rechnungsnummer wird erst bei Freigabe vergeben

Eine neu erstellte Rechnung SHALL im Status `draft` ohne
`invoice_number` angelegt werden. Die `invoice_number` SHALL
ausschließlich beim Übergang von `draft` zu `sent` (Freigabe) fortlaufend
und eindeutig vergeben werden.

#### Scenario: Neu erstellte Rechnung hat keine Rechnungsnummer
- **GIVEN** ein Admin oder Trainer legt eine neue Rechnung an
- **WHEN** die Rechnung gespeichert wird
- **THEN** die Rechnung hat den Status `draft` und keine `invoice_number`

#### Scenario: Freigabe einer Entwurfsrechnung vergibt eine fortlaufende Nummer
- **GIVEN** eine Rechnung im Status `draft` ohne `invoice_number`
- **WHEN** ein Admin oder Trainer die Rechnung freigibt
- **THEN** die Rechnung erhält eine eindeutige, fortlaufende
  `invoice_number` im Format `RE-{Jahr}-{4-stellig}` und wechselt in den
  Status `sent`

#### Scenario: Parallele Freigaben erzeugen keine doppelte Rechnungsnummer
- **GIVEN** zwei Entwurfsrechnungen ohne Rechnungsnummer
- **WHEN** beide nahezu gleichzeitig freigegeben werden
- **THEN** beide erhalten unterschiedliche, fortlaufende Rechnungsnummern
  ohne Konflikt

### Requirement: Rechnung ist ab Status "Offen" inhaltlich unveränderlich

Eine Rechnung, die nicht mehr im Status `draft` ist, SHALL nicht mehr über
die allgemeine Bearbeiten- oder Löschen-Funktion verändert werden können.
Änderungen sind nur noch über dedizierte Statusübergänge möglich.

#### Scenario: Bearbeiten einer offenen Rechnung wird verweigert
- **GIVEN** eine Rechnung im Status `sent`
- **WHEN** ein Admin oder Trainer versucht, Felder der Rechnung über die
  Bearbeiten-Funktion zu ändern
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt

#### Scenario: Löschen einer offenen Rechnung wird verweigert
- **GIVEN** eine Rechnung im Status `sent` oder `paid`
- **WHEN** ein Admin versucht, die Rechnung zu löschen
- **THEN** die Anfrage wird mit HTTP 403 abgelehnt

#### Scenario: Entwurf kann frei bearbeitet und gelöscht werden
- **GIVEN** eine Rechnung im Status `draft`
- **WHEN** ein Admin oder Trainer die Rechnung bearbeitet oder ein Admin
  sie löscht
- **THEN** die Änderung bzw. Löschung wird ausgeführt

### Requirement: Kein automatischer E-Mail-Versand beim Erstellen einer Rechnung

Das Erstellen einer Rechnung (im Status `draft`) SHALL keinen
automatischen E-Mail-Versand an den Kunden auslösen.

#### Scenario: Erstellen eines Entwurfs löst keine E-Mail aus
- **GIVEN** ein Admin oder Trainer legt eine neue Rechnung an
- **WHEN** die Rechnung gespeichert wird
- **THEN** es wird keine E-Mail an den Kunden verschickt oder eingereiht

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

### Requirement: Überfällig-Kennzeichnung wird zur Anzeigezeit berechnet

Der Überfällig-Zustand einer Rechnung SHALL nicht als eigener,
persistierter Status geführt werden, sondern beim Anzeigen anhand des
Fälligkeitsdatums berechnet werden.

#### Scenario: Rechnung mit abgelaufenem Fälligkeitsdatum wird als überfällig markiert
- **GIVEN** eine Rechnung im Status `sent` oder `reminded` mit einem
  Fälligkeitsdatum in der Vergangenheit
- **WHEN** die Rechnung angezeigt wird
- **THEN** sie wird visuell als überfällig markiert

#### Scenario: Bezahlte oder stornierte Rechnung wird nie als überfällig markiert
- **GIVEN** eine Rechnung im Status `paid` oder `cancelled` mit einem
  Fälligkeitsdatum in der Vergangenheit
- **WHEN** die Rechnung angezeigt wird
- **THEN** sie wird nicht als überfällig markiert

### Requirement: Mahnstufen-Datenmodell

Das System SHALL mehrere Mahnstufen pro Rechnung mit jeweils Datum und
Mahngebühr persistieren können, sowie den Status `reminded` für
Rechnungen mit mindestens einer Mahnung unterstützen. Die Erzeugung
solcher Mahn-Datensätze und der zugehörige Statuswechsel sind nicht Teil
dieses Change.

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

### Requirement: Rollen-Sichtbarkeit für Kunden

Ein Kunde SHALL ausschließlich eigene Rechnungen in den Status `sent`,
`paid`, `overdue` (berechnet) oder `reminded` sehen können, sowohl in der
Listenansicht als auch beim direkten Abruf einer einzelnen Rechnung.

#### Scenario: Kunde sieht keine Entwürfe
- **GIVEN** ein Kunde mit einer eigenen Rechnung im Status `draft`
- **WHEN** der Kunde seine Rechnungsliste abruft oder die Rechnung direkt
  abruft
- **THEN** die Entwurfsrechnung erscheint nicht in der Liste und der
  direkte Abruf wird mit HTTP 403 abgelehnt

#### Scenario: Kunde sieht keine stornierten Rechnungen
- **GIVEN** ein Kunde mit einer eigenen Rechnung im Status `cancelled`
- **WHEN** der Kunde seine Rechnungsliste abruft oder die Rechnung direkt
  abruft
- **THEN** die stornierte Rechnung erscheint nicht in der Liste und der
  direkte Abruf wird mit HTTP 403 abgelehnt

#### Scenario: Admin und Trainer sehen alle Status
- **GIVEN** ein Admin oder Trainer
- **WHEN** die Rechnungsliste abgerufen wird
- **THEN** Rechnungen aller Status, einschließlich `draft` und
  `cancelled`, werden angezeigt
