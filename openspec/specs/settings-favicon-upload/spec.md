# Spec: settings-favicon-upload

**Status:** ADDED
**Capability:** Favicon-Upload in den Systemeinstellungen

---

## Purpose

Der Admin kann in den Systemeinstellungen ein Favicon hochladen, das als
`image/x-icon` (`.ico`) oder als `image/png` (`.png`) vorliegen darf.
Das Backend akzeptiert beide Formate. Das Frontend bleibt nach einem
Validierungsfehler beim Speichern bedienbar.

---

## ADDED Requirements

### Requirement: Favicon-Dateivalidierung (Backend)

#### Scenario: ICO-Datei wird hochgeladen
- **GIVEN** ein Admin sendet eine `multipart/form-data`-Anfrage an `PUT /api/v1/settings`
- **AND** das Feld `company_favicon` enthält eine Datei mit MIME-Typ
  `image/x-icon` oder `image/vnd.microsoft.icon` und Dateigröße <= 512 KB
- **THEN** die Anfrage wird akzeptiert (kein Validierungsfehler für `company_favicon`)

#### Scenario: PNG-Datei wird hochgeladen
- **GIVEN** ein Admin sendet eine `multipart/form-data`-Anfrage an `PUT /api/v1/settings`
- **AND** das Feld `company_favicon` enthält eine Datei mit MIME-Typ `image/png`
  und Dateigröße <= 512 KB
- **THEN** die Anfrage wird akzeptiert

#### Scenario: Unerlaubter MIME-Typ wird abgelehnt
- **GIVEN** ein Admin sendet eine Datei mit einem anderen MIME-Typ
  (z. B. `application/octet-stream`, `image/jpeg`)
- **THEN** die API gibt HTTP 422 zurück mit einem Validierungsfehler
  für `company_favicon`

#### Scenario: Datei zu groß
- **GIVEN** ein Admin sendet eine ICO- oder PNG-Datei mit mehr als 512 KB
- **THEN** die API gibt HTTP 422 zurück mit einem Validierungsfehler
  für `company_favicon`

#### Scenario: Kein Favicon gesendet
- **GIVEN** die Anfrage enthält kein Feld `company_favicon`
- **THEN** die Anfrage wird ohne Fehler für `company_favicon` verarbeitet
  (`sometimes`-Regel)

---

### Requirement: Formular bleibt nach Speicherfehler sichtbar (Frontend)

#### Scenario: Speicherfehler durch Validierung
- **GIVEN** das Einstellungsformular ist geladen und sichtbar
- **WHEN** der Admin auf "Speichern" klickt und das Backend HTTP 422 antwortet
- **THEN** das `<form>`-Element bleibt im DOM (wird nicht ausgeblendet)
- **AND** eine Inline-Fehlermeldung erscheint unterhalb der Aktions-Buttons
- **AND** die zuvor eingegebenen Formulardaten bleiben erhalten

#### Scenario: Ladefehlerhält das Formular ausgeblendet
- **GIVEN** beim Laden der Einstellungen tritt ein Netzwerkfehler auf
- **THEN** das `<form>`-Element ist nicht im DOM
- **AND** ein Fehlerblock (rote Box) wird angezeigt

#### Scenario: Speicherfehler-Meldung verschwindet bei erneutem Versuch
- **GIVEN** ein Speicherfehler ist sichtbar
- **WHEN** der Admin erneut auf "Speichern" klickt
- **THEN** die alte `saveError`-Meldung wird zunächst gelöscht
  (`saveError = null` am Anfang von `saveSettings()`)
