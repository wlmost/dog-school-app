# Proposal: ci-db-matrix

**Change-ID:** ci-db-matrix
**Datum:** 2026-05-13
**Status:** draft

---

## Problem

Die CI-Pipeline führt alle Backend-Tests ausschließlich gegen SQLite in-memory aus. SQLite unterscheidet sich in relevanten Punkten von MySQL und PostgreSQL (Typstrenge, JSON-Operatoren, Migrationsverhalten). Da Demo- und Produktiv-Umgebungen MySQL nutzen und die lokale Entwicklung PostgreSQL verwendet, können DB-Portabilitätsfehler in Migrations, raw SQL oder Query-Builder-Konstrukten unbemerkt in den Hauptbranch gelangen.

Zusätzlich fehlt in beiden PHP-Dockerfiles der `pdo_mysql`-Treiber. Ohne diesen schlägt jeder MySQL-Verbindungsversuch mit `PDO Exception: could not find driver` fehl — auch im Deployment-Image, das für MySQL-Hosts gebaut wird.

## Lösung

1. Den bestehenden CI-Job in zwei separate Jobs aufteilen:
   - **`backend-tests`** mit einer 2-Leg-Matrix (`mysql`, `pgsql`): Jeder Lauf startet einen echten Datenbank-Service-Container und führt die komplette Pest-Suite dagegen aus.
   - **`frontend-tests`**: Unverändert ohne Matrix, da Vitest keine DB-Abhängigkeit hat.
2. `pdo_mysql` in `docker/php/Dockerfile` (Entwicklungs-Image) und `docker/php/Dockerfile.build` (Deployment-Image) ergänzen.

## Non-Goals

- Keine Änderungen an Produktivcode, Migrations oder Anwendungslogik.
- Kein Umbau der lokalen Docker-Compose-Umgebung.
- Keine Einführung von SQLite als dritten Matrix-Leg (SQLite bleibt aus der Matrix ausgeschlossen; Ziel ist echte DB-Validierung).

## Begründung

Portabilitätsfehler werden frühestmöglich — im Pull Request — sichtbar, statt erst nach dem Deployment auf einem echten Datenbank-Host. Der Overhead ist gering: zwei parallele Backend-Jobs statt einem, kein Frontend-Mehraufwand. Das fehlende `pdo_mysql` ist ein Deployment-Blocker auf MySQL-Hosts und wird als Nebeneffekt dieses Changes behoben.
