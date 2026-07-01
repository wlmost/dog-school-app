# Spec: settings-file-upload-transport

**Status:** ADDED
**Capability:** Transportweg für Datei-Uploads beim Speichern der Systemeinstellungen

---

## Purpose

Wenn der Admin Systemeinstellungen speichert, die Dateien enthalten
(Firmenlogo, Favicon), muss der Request so an das Backend übertragen
werden, dass PHP den Request-Body inklusive Dateien zuverlässig auswertet
— unabhängig von der auf dem Server laufenden PHP-Version (8.2, 8.3 oder
8.4) und unabhängig davon, ob echte HTTP-PUT-Requests mit
`multipart/form-data`-Body auf dem jeweiligen Server korrekt geparst
werden. Das Backend muss die Anfrage weiterhin als logisches `PUT`
behandeln (Autorisierung, Validierung, Idempotenz-Semantik unverändert).

---

## ADDED Requirements

### Requirement: Settings-Update wird als POST mit Method-Override gesendet

Der Frontend-Client SHALL Settings-Update-Requests (`PUT /api/v1/settings`),
die potenziell Dateien enthalten, als HTTP-`POST` mit einem zusätzlichen
`FormData`-Feld `_method=PUT` senden, statt ein echtes HTTP-`PUT` mit
`multipart/form-data`-Body zu verwenden. Damit befüllt PHP `$_POST` und
`$_FILES` in jeder unterstützten PHP-Version (8.2, 8.3, 8.4) zuverlässig,
weil `POST` von der SAPI immer nativ geparst wird, unabhängig von
`request_parse_body()`-Verfügbarkeit oder sonstigen Server-Eigenheiten.
Das Backend MUSS den Request weiterhin fachlich als `PUT` behandeln
(Routing, Autorisierung, Validierung unverändert) — Laravels
Method-Override-Mechanismus übernimmt das vor dem Routing.

#### Scenario: Update ohne Datei
- **GIVEN** der Admin ändert nur Textfelder (z. B. `company_name`) in den
  Einstellungen und enthält keine Datei
- **WHEN** das Formular gespeichert wird
- **THEN** der Client sendet ein HTTP-`POST` an `/api/v1/settings`
- **AND** der `FormData`-Body enthält ein Feld `_method` mit dem Wert `PUT`
- **AND** das Backend behandelt die Anfrage als `PUT` (Autorisierung via
  `SettingsController::update()` greift wie bisher)

#### Scenario: Update mit Firmenlogo
- **GIVEN** der Admin wählt eine neue Logo-Datei aus
- **WHEN** das Formular gespeichert wird
- **THEN** der Client sendet ein HTTP-`POST` an `/api/v1/settings` mit
  `Content-Type: multipart/form-data`
- **AND** der `FormData`-Body enthält sowohl das Feld `_method=PUT` als
  auch die Logo-Datei unter ihrem Feldnamen (z. B. `company_logo`)
- **AND** die Datei kommt serverseitig zuverlässig in `$request->hasFile('company_logo')`
  an — unabhängig von der PHP-Version des Servers

#### Scenario: Update mit Favicon
- **GIVEN** der Admin wählt eine neue Favicon-Datei aus (PNG oder ICO)
- **WHEN** das Formular gespeichert wird
- **THEN** gilt dieselbe Übertragungslogik wie beim Firmenlogo (POST +
  `_method=PUT` + Datei im `FormData`)

#### Scenario: Server-seitiges Routing bleibt unverändert
- **GIVEN** ein POST-Request mit `_method=PUT` im `FormData`-Body erreicht
  das Backend
- **WHEN** Laravel den Request verarbeitet
- **THEN** die Route `PUT /api/v1/settings` matcht weiterhin (Method-Override
  wird vor dem Routing angewendet)
- **AND** `SettingsController::update()` erhält den Request unverändert wie
  bei einem echten `PUT`-Request

---

## Nicht Teil dieser Spec

- Validierungsregeln für einzelne Settings-Felder (siehe Capability
  `settings-favicon-upload` für die MIME-/Größenregeln von
  `company_favicon`).
- Verhalten des Frontend-Formulars bei Speicherfehlern (siehe ebenfalls
  `settings-favicon-upload`).
- Serverseitige PHP-Versionsanforderungen für Deployment/Installer — das
  ist Gegenstand der offenen Punkte in `proposal.md` dieses Changes und
  potenziell eines eigenen, künftigen Changes.
