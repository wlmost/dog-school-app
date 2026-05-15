# Triage: pricing-overview

**Pfad:** standard
**Geschätzter Umfang:** ~9–11 Dateien, PHP + Vue.js
**Risiko:** mittel — neue DB-Tabelle und Migration, neue öffentliche API-Route, Admin-CRUD-Endpunkte mit Auth-Schutz; keine bestehenden Schnittstellen werden gebrochen.
**Klarheit:** geklärt — alle offenen Fragen beantwortet (siehe Abschnitt "Geklärte Anforderungen").

## Anforderung (Zusammenfassung)

Auf der öffentlichen Startseite (`HomeView.vue`) soll eine klickbare Kachel
(Tile) auf eine Preisübersicht hinweisen. Ein Klick öffnet eine Preisseite
oder ein Modal, das die aktuellen Preise der Hundeschule zeigt. Die Preise
sollen vom Admin über den geschützten Bereich der App gepflegt werden können
(anlegen, bearbeiten, löschen).

## Betroffene Bereiche (grobe Einschätzung)

### Backend (PHP / Laravel)
- Neue Migration: `create_pricing_items_table` (oder ähnlich)
- Neues Model: `PricingItem` (app/Models/)
- Neuer Controller: `PricingItemController` (öffentlich: GET-Liste; Admin: CRUD)
- Neues API Resource: `PricingItemResource`
- Neue Routen in `routes/api.php`: 1× public GET + 4× auth-geschützte CRUD-Routen

### Frontend (Vue.js 3)
- Neue öffentliche View: `PricingView.vue` (oder Modal-Variante)
- Erweiterung `HomeView.vue`: neue Kachel "Preise" mit RouterLink
- Neue Admin-Komponente: z. B. `PricingFormModal.vue` und/oder eigene Admin-View
- Router-Update: `frontend/src/router/index.ts` um `/preise`-Route erweitern

## Geklärte Anforderungen (User-Antworten vom 14.05.2026)

1. **Datenmodell:** Eigene Tabelle `pricing_items` mit manuell gepflegten Einträgen.

2. **UX – öffentliche Seite:** Kachel im bestehenden Kachel-Grid auf der
   Startseite (`HomeView.vue`). Klick öffnet ein **Modal** mit der Preisliste
   (keine eigene Seite/Route nötig).

3. **Admin-Pflege:** **Neuer Tab in `SettingsView`** (kein eigener Admin-Bereich).

4. **Struktur eines Preiseintrags** – aus Beispiel abgeleitet:
   - `category` (string) – Gruppe, z. B. "Verhaltensberatung", "Gruppenstunden"
   - `title` (string) – Name der Leistung, z. B. "Erstgespräch (Anamnese)"
   - `price` (decimal) – Preis in EUR
   - `unit` (string, nullable) – Einheit, z. B. "je Einheit", "pro Kurs", leer
   - `description` (string, nullable) – Zusatzinfo, z. B. "max 6 Teilnehmer", "ab 10km"
   - `is_from_price` (boolean) – "ab X EUR" Kennzeichnung (z. B. "Themenkurse: ab 200 EUR")

   Beispiel-Daten:
   ```
   Verhaltensberatung
   - Erstgespräch (Anamnese): 120 EUR
   - Folgetermine: je 65 EUR
   - Anfahrt ab 10km: 0,70 EUR

   Gruppenstunden (max 6 Teilnehmer): 25 EUR pro Einheit
   - 5er Karte: 100 EUR
   - 10er Karte: 225 EUR

   Einzelstunden: 65 EUR

   Themenkurse: ab 200 EUR
   ```

5. **Sortierung:** Keine manuelle Sortierung nötig — Anzeige nach `category`,
   dann nach Erstellungsreihenfolge (`id` ASC).

## Empfohlene nächste Aktion

→ Rückfragen 1–3 mit dem User klären. Danach:
**Architekt** aufrufen mit dem Auftrag, auf Basis dieser Triage-Datei einen
Change `pricing-overview` zu erstellen (`proposal.md` + `design.md` + `tasks.md`).
Dabei explizit darauf achten:
- Migration muss MySQL- **und** PostgreSQL-kompatibel sein (nur `$table->json()`,
  kein `JSONB`; Eloquent-Zugriffe statt raw SQL)
- Öffentliche API-Route darf keine Auth voraussetzen; Admin-Routen **müssen**
  durch Laravel Sanctum / bestehende Auth-Middleware geschützt sein
- PHP-Code muss PHP-8.2-kompatibel sein (keine 8.3/8.4-Features)
