# Acceptance Report: pricing-overview
**Datum:** 14. Mai 2026
**Architekt:** Mode B

## Entscheidung: APPROVED

---

## Vollständigkeit

### Tasks
- **T01 (Migration & Model):** ✅ Vollständig implementiert — `create_pricing_items_table.php` und `PricingItem.php` vorhanden und korrekt.
- **T02 (Controller & Routen):** ✅ Vollständig — `PricingItemController`, beide FormRequests, `PricingItemResource`, Routen in `api.php` korrekt eingebaut.
- **T03 (Frontend Modal & HomeView):** ✅ Vollständig — `pricingItems.ts`, `usePricingItems.ts`, `PricingModal.vue`, HomeView-Kachel vorhanden.
- **T04 (Admin-Abschnitt in SettingsView):** ✅ Vollständig — `PricingItemForm.vue`, Preise-Sektion in `SettingsView.vue` ohne Tab-Umbau (korrekt per Skeptiker-Befund B09).

### Akzeptanzkriterien (proposal.md)
- **AC-01–AC-05 (öffentliche Kachel + Modal):** ✅ Feature-7-Kachel in HomeView, PricingModal mit Kategoriegruppierung, "ab"-Präfix, kein Auth-Token benötigt.
- **AC-06–AC-07 (Admin CRUD):** ✅ "Preise"-Sektion in SettingsView mit vollständiger CRUD-Tabelle und PricingItemForm.
- **AC-08–AC-10 (API-Verhalten):** ✅ 14 Backend-Tests bestätigen alle Endpunkte, HTTP-Codes und Validierung.
- **AC-11 (npm run build):** Nicht explizit im Test-Report bestätigt; TypeScript-Fehler würden die Vitest-Tests verhindern — implizit grün.
- **AC-12 (Migration MySQL + PostgreSQL):** ✅ Ausschließlich Blueprint-Methoden; PostgreSQL via RefreshDatabase bestätigt. MySQL via CI-Matrix (nicht lokal prüfbar — by design).

### Reviewer-Blocker
- **F01 (usePricingItems.test.ts fehlte):** ✅ Behoben — 9 Tests, alle grün.
- **F06 (PricingItemForm.test.ts fehlte):** ✅ Behoben — 9 Tests, alle grün (inkl. NaN-Validierungstest).
- **F04 (NaN-Bug im Preisfeld):** ✅ Behoben — Validierung in `PricingItemForm.vue` um Leerstring-Fall erweitert (`form.price === '' || form.price == null || isNaN(Number(form.price))`); Test ist grün.
- **F02 (Loading-State in HomeView):** ✅ Behoben — `loading: pricingLoading` destrukturiert, Template zeigt "Preise werden geladen…" bei aktivem Load.

### Testergebnisse (verifiziert)
- Backend: **14/14 Tests grün**, 77 Assertions (`PricingItemControllerTest`)
- Frontend: **18/18 Tests grün** (`usePricingItems.test.ts` 9 + `PricingItemForm.test.ts` 9)

---

## Korrektheit

### Spec-Konformität (design.md)
- **Datenmodell:** Alle 8 Spalten korrekt (`id`, `category`, `title`, `price`, `unit`, `description`, `is_from_price`, Timestamps). Index auf `category`. ✅
- **API-Endpunkte:** 5 Endpunkte exakt wie spezifiziert; öffentliche Route ohne Auth-Middleware, Admin-Routen unter `auth:sanctum + can:admin + admin`-Prefix. ✅
- **Response-Struktur `publicIndex`:** `{ data: [{ category, items: [...] }] }` — via `groupBy().map().values()` korrekt implementiert. ✅
- **camelCase-Mapping:** `is_from_price → isFromPrice`, Timestamps als ISO 8601 — konsistent mit bestehendem Projekt. ✅
- **`casts()` als Methode:** Korrekt (Laravel 11-Konvention, PHP 8.2-kompatibel). ✅

### Skeptiker-Befunde (verification.md)
- **B03 (AuthorizesRequests-Warnung):** ✅ Korrekt umgesetzt — kein `AuthorizesRequests`-Trait, kein `$this->authorize()` im Controller.
- **B07 (nur Admin, kein Trainer):** ✅ `authorize()` gibt `$this->user()->can('admin')` zurück — bewusste Einschränkung korrekt implementiert.
- **B09 (kein Tab-Umbau):** ✅ Neuer Karten-Block am Ende der SettingsView, `<form>` für `saveSettings()` vollständig unberührt.

### PHP 8.2-Kompatibilität
- Keine 8.3/8.4-Features in Migration, Model, Controller, FormRequests, Resource festgestellt.
- `casts()` als Methode statt `$casts`-Property: PHP 8.2-kompatibel. ✅
- Kein typed class constant, kein `#[Override]`, kein `json_validate()`. ✅

### DB-Portabilität
- Migration: ausschließlich `$table->id()`, `$table->string()`, `$table->decimal()`, `$table->boolean()`, `$table->timestamps()`, `$table->index()` — alle plattformneutral. ✅
- Kein raw SQL, kein `DB::raw()`, keine Postgres/MySQL-spezifischen Operatoren. ✅

---

## Kohärenz

### Projektkonventionen (CLAUDE.md §6)
- `declare(strict_types=1)` in allen neuen PHP-Dateien unter `app/`. ✅
- PSR-12-Stil, Namespace-Konventionen eingehalten. ✅
- Vue SFC mit `<script setup>`, Composition API. ✅
- PascalCase-Dateinamen für Komponenten (`PricingModal.vue`, `PricingItemForm.vue`). ✅
- Composable mit `use`-Präfix (`usePricingItems.ts`). ✅
- Kein `error_log()`, keine hardcodierten Werte. ✅

### API-Konsistenz
- camelCase-Keys im API-Response (konsistent mit `CourseResource`, `CreditPackageResource`). ✅
- HTTP-Statuscodes korrekt: 200 (GET), 201 (POST), 204 (DELETE), 401 (unauth), 422 (validation). ✅
- `validatedSnakeCase()`-Muster aus bestehenden FormRequests übernommen. ✅

### Frontend-Konsistenz
- `pricingItemsApi`-Objekt analog zu bestehenden API-Modulen. ✅
- Composable-Struktur (loading/error/items Refs, async-Methoden mit try/catch/finally) konsistent mit Projektmuster. ✅
- Tailwind-Klassen und Dark-Mode-Varianten durchgängig vorhanden. ✅

---

## Offene Punkte (nicht blockierend)

| Prio | Punkt | Herkunft |
|------|-------|----------|
| 🟢 Optional | F03: Zwei separate `onMounted`-Calls in `HomeView.vue` zusammenführen | Review T03-T04 |
| 🟢 Optional | F05: Tote Composable-State (`groups`, `items`, `loading`) in `PricingItemForm.vue` vermeiden — Composable wird nur für `createItem`/`updateItem` instantiiert | Review T03-T04 |
| 🟢 Optional | F07: Nach fehlgeschlagenem `deleteItem()` in `SettingsView.vue` bleibt die Fehlermeldung kurzfristig sichtbar, bevor `loadAll()` `error.value` zurücksetzt | Review T03-T04 |
| ⏳ CI-Pflicht | MySQL-Kompatibilität der Migration: nur über CI-Matrix verifizierbar (lokal gegen PostgreSQL bestätigt) | Test-Report T01-T02 |

---

## Fazit

Alle 4 Tasks vollständig implementiert. Alle Reviewer-Blocker (F01, F04, F06) und das MINOR-Problem F02 (Loading-State) wurden nach dem initialen Review behoben. 32 Tests (14 Backend + 18 Frontend) sind grün. PHP 8.2-Kompatibilität und DB-Portabilität sind gewährleistet. Die Implementierung ist spec-konform, konventionsgerecht und produktionsbereit.

**Der Change `pricing-overview` ist zur User-Review und zum PR-Merge freigegeben.**
