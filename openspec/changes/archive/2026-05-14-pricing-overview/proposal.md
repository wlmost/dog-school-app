# Proposal: pricing-overview

**Change-ID:** pricing-overview
**Erstellt:** 2026-05-14
**Status:** bereit zur Implementierung

---

## User Stories

### Öffentlicher Besucher
> Als Besucher der Hundeschul-Webseite möchte ich auf der Startseite auf einen Blick sehen,
> dass es Preisinformationen gibt, und diese mit einem Klick einsehen können –
> ohne mich einloggen zu müssen.

### Admin / Trainerin
> Als Admin möchte ich Preiseinträge (Leistung, Preis, Kategorie, Einheit) in der Verwaltungsoberfläche
> anlegen, bearbeiten und löschen können, damit die öffentliche Preisübersicht stets aktuell ist.

---

## Was wird gebaut (IN SCOPE)

- **Kachel "Preise"** im Feature-Grid der `HomeView.vue`
  - Klick öffnet ein Modal mit der Preisliste (keine eigene Route)
- **`PricingModal.vue`** — öffentlich zugängliche Preisliste, nach Kategorie gruppiert
- **`usePricingItems.ts`** — Composable für API-Calls (öffentlich + Admin)
- **`PricingItemForm.vue`** — Formular zum Anlegen/Bearbeiten von Preiseinträgen
- **Neuer Tab "Preise"** in `SettingsView.vue` (Admin-Bereich) mit CRUD-Tabelle
  - Hierzu wird `SettingsView.vue` auf eine Tab-Navigations-Struktur umgestellt
- **Backend**: Migration, Model `PricingItem`, `PricingItemResource`
- **Backend**: `PricingItemController` mit 5 Endpunkten (1 öffentlich, 4 auth-geschützt/admin)
- **Backend**: `StorePricingItemRequest` + `UpdatePricingItemRequest`
- **Routen** in `routes/api.php`

## Was NICHT gebaut wird (OUT OF SCOPE)

- Keine eigene öffentliche Seite `/preise` — nur Modal
- Kein Drag-and-Drop für manuelle Sortierung
- Keine Verknüpfung mit bestehenden `courses`- oder `credit_packages`-Daten
- Keine Währungsauswahl — EUR ist implizit hardcoded in der Anzeige
- Kein öffentliches Formular für Preisanfragen
- Keine Versionierung / Historie von Preisänderungen

---

## Akzeptanzkriterien

1. **AC-01:** Auf der Startseite (`/`) existiert im Feature-Grid eine Kachel mit dem Titel "Preise".
2. **AC-02:** Ein Klick auf die Kachel öffnet ein Modal mit der aktuellen Preisliste.
3. **AC-03:** Das Modal zeigt Preiseinträge nach Kategorie gruppiert, mit Titel, Preis (EUR), Einheit und optionaler Beschreibung.
4. **AC-04:** Einträge mit `is_from_price = true` werden mit dem Präfix "ab" vor dem Betrag dargestellt (z. B. "ab 200 €").
5. **AC-05:** Das Modal ist ohne Login zugänglich (keine Auth erforderlich).
6. **AC-06:** In `SettingsView.vue` (nur für eingeloggte Admins) existiert ein Tab "Preise".
7. **AC-07:** Im Preise-Tab können Admins alle Einträge sehen, neue anlegen, bestehende bearbeiten und löschen.
8. **AC-08:** `GET /api/v1/pricing-items` antwortet ohne Auth-Token mit HTTP 200 und gibt alle Einträge zurück.
9. **AC-09:** `POST /api/v1/admin/pricing-items` ohne Auth-Token antwortet mit HTTP 401.
10. **AC-10:** Alle Backend-Endpunkte validieren Eingaben (Pflichtfelder, Typen, Längen).
11. **AC-11:** `npm run build` läuft ohne Fehler oder Warnings durch.
12. **AC-12:** Die neue Migration läuft auf MySQL und PostgreSQL fehlerfrei durch.
