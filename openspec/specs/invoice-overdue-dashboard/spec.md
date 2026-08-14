# invoice-overdue-dashboard

## Purpose

Definiert das Dashboard-Widget, das Admin und Trainer eine informative
Übersicht überfälliger und gemahnter Rechnungen zeigt (ohne eigenen
Aktions-Button), rollenbasiert gescoped und ohne Rauschen durch
kurzlebige Storno-/Mahngebühren-Dokumente.

## Requirements

### Requirement: Dashboard zeigt überfällige und gemahnte Rechnungen

Das Dashboard SHALL Admin und Trainer eine Übersicht der überfälligen
und gemahnten Rechnungen anzeigen.

#### Scenario: Admin sieht überfällige und gemahnte Rechnungen aller Kunden
- **GIVEN** ein Admin und mehrere Rechnungen unterschiedlicher Kunden,
  darunter überfällige und gemahnte
- **WHEN** der Admin das Dashboard aufruft
- **THEN** die überfälligen und gemahnten Rechnungen aller Kunden werden
  in einer Übersicht angezeigt

#### Scenario: Trainer sieht nur überfällige und gemahnte Rechnungen eigener Kunden
- **GIVEN** ein Trainer mit zugewiesenen Kunden sowie überfällige und
  gemahnte Rechnungen sowohl eigener als auch fremder Kunden
- **WHEN** der Trainer das Dashboard aufruft
- **THEN** nur die überfälligen und gemahnten Rechnungen der ihm
  zugewiesenen Kunden werden angezeigt

#### Scenario: Bezahlte und stornierte Rechnungen erscheinen nicht in der Übersicht
- **GIVEN** eine bezahlte und eine stornierte Rechnung
- **WHEN** ein Admin oder Trainer das Dashboard aufruft
- **THEN** weder die bezahlte noch die stornierte Rechnung erscheint in
  der Übersicht

#### Scenario: Kunde sieht keine Übersicht überfälliger Rechnungen
- **GIVEN** ein Kunde
- **WHEN** der Kunde das Dashboard aufruft
- **THEN** die Übersicht überfälliger und gemahnter Rechnungen wird
  nicht angezeigt

### Requirement: Gebühren- und Korrekturdokumente erscheinen nicht als eigenständige Einträge

Ein Storno- oder Mahngebühren-Dokument SHALL nicht als eigener Eintrag
in der Übersicht überfälliger und gemahnter Rechnungen erscheinen, auch
wenn sein Fälligkeitsdatum in der Vergangenheit liegt.

#### Scenario: Mahngebühren-Dokument erscheint nicht in der Übersicht
- **GIVEN** eine Rechnung mit einem erzeugten Mahngebühren-Dokument,
  dessen Fälligkeitsdatum in der Vergangenheit liegt
- **WHEN** ein Admin oder Trainer das Dashboard aufruft
- **THEN** das Mahngebühren-Dokument erscheint nicht als eigener Eintrag
  in der Übersicht
