# Test-Report: T03 + T04 — pricing-overview

**Status:** fehler-vorhanden (1 Fehler — Produktivcode-Bug, kein Test-Bug)

---

## Hinzugefügte Dateien

- [frontend/src/composables/usePricingItems.test.ts](../../../frontend/src/composables/usePricingItems.test.ts): 9 neue Tests
- [frontend/src/components/PricingItemForm.test.ts](../../../frontend/src/components/PricingItemForm.test.ts): 9 neue Tests

---

## Akzeptanzkriterien-Abdeckung

### T03 — `usePricingItems.ts`

- [x] `loadPublic()` bei Erfolg: `groups` befüllt, `loading` → `false`, `error` → `null` — `usePricingItems.test.ts::befüllt groups bei Erfolg`
- [x] `loadPublic()` bei API-Fehler: `error` gesetzt, `groups` leer, `loading` → `false` — `usePricingItems.test.ts::setzt error bei API-Fehler`
- [x] `loadAll()` bei Erfolg: `items` befüllt — `usePricingItems.test.ts::befüllt items bei Erfolg`
- [x] `createItem()` ruft `pricingItemsApi.create()` mit korrekten Daten auf und hängt Item an `items` — `usePricingItems.test.ts::ruft pricingItemsApi.create()`
- [x] `createItem()` aktualisiert `items` direkt (kein `getAll()`-Aufruf) — `usePricingItems.test.ts::aktualisiert items direkt`
- [x] `deleteItem(id)` ruft `pricingItemsApi.delete(id)` auf — `usePricingItems.test.ts::ruft pricingItemsApi.delete()`
- [x] `deleteItem(id)` entfernt das Item aus `items` — `usePricingItems.test.ts::entfernt das gelöschte Item`

### T04 — `PricingItemForm.vue`

- [x] Formular rendert nicht bei `visible = false` — `PricingItemForm.test.ts::rendert nicht wenn visible false ist`
- [x] Formular rendert bei `visible = true, item = null` — `PricingItemForm.test.ts::rendert das Formular wenn visible true ist`
- [x] Pflichtfeld `category` fehlt → Fehlermeldung, kein Emit — `PricingItemForm.test.ts::zeigt Fehlermeldung wenn Kategorie fehlt`
- [x] Pflichtfeld `title` fehlt → Fehlermeldung, kein Emit — `PricingItemForm.test.ts::zeigt Fehlermeldung wenn Leistungsbezeichnung fehlt`
- [ ] Leeres Preisfeld → Fehlermeldung (F04-Verifikation) — **FEHLGESCHLAGEN** (Bug im Produktivcode, siehe unten)
- [x] Negativer Preis → Fehlermeldung — `PricingItemForm.test.ts::zeigt Fehlermeldung bei negativem Preis`
- [x] `item`-Prop vorhanden: Felder bei `visible → true` vorausgefüllt — `PricingItemForm.test.ts::befüllt Formularfelder mit Item-Daten`
- [x] Erfolgreicher Neu-Submit → `createItem()` aufgerufen, `saved` emittiert — `PricingItemForm.test.ts::emittiert saved-Event`
- [x] Bestehender `item`-Prop → `updateItem()` statt `createItem()` aufgerufen — `PricingItemForm.test.ts::ruft updateItem statt createItem auf`

---

## Ausführungs-Ergebnis

```
 RUN  v4.1.6 /var/www/html/frontend

 ✓ src/composables/usePricingItems.test.ts (9 tests) 6ms
 ❯ src/components/PricingItemForm.test.ts (9 tests | 1 failed) 50ms
     ✓ rendert nicht wenn visible false ist 5ms
     ✓ rendert das Formular wenn visible true ist und item null ist 5ms
     ✓ zeigt Fehlermeldung wenn Kategorie beim Submit leer ist 7ms
     ✓ zeigt Fehlermeldung wenn Leistungsbezeichnung beim Submit leer ist 4ms
     × zeigt Fehlermeldung bei leerem Preisfeld (F04-Verifikation) 15ms
     ✓ zeigt Fehlermeldung bei negativem Preis 5ms
     ✓ befüllt Formularfelder mit Item-Daten wenn visible auf true wechselt 3ms
     ✓ emittiert saved-Event nach erfolgreichem Submit bei Neu-Anlage 3ms
     ✓ ruft updateItem statt createItem auf wenn ein bestehendes Item bearbeitet wird 3ms

 Test Files  1 failed | 1 passed (2)
      Tests  1 failed | 17 passed (18)
   Duration  892ms
```

---

## Fehler

### `PricingItemForm.test.ts::zeigt Fehlermeldung bei leerem Preisfeld (F04-Verifikation)` — PRODUKTIVCODE-BUG

- **Erwartet:** Fehlermeldung „Bitte einen gültigen Preis eingeben" erscheint nach Submit mit leerem Preisfeld
- **Erhalten:** Keine Fehlermeldung, Formular submittiert weiter
- **Ursache (NICHT von mir gefixt):**

  `v-model.number` ruft intern `looseToNumber('')` auf. Da `parseFloat('') === NaN`,
  gibt `looseToNumber` den Originalwert `''` (leerer String) zurück. `form.price`
  wird damit zu `''` (String), nicht zu `NaN`.

  Die Validierung in `handleSubmit` prüft:
  ```typescript
  if (isNaN(form.price) || !isFinite(form.price)) errors.price = '...'
  ```
  Für `form.price === ''`:
  - `isNaN('')` → `isNaN(Number(''))` → `isNaN(0)` → **`false`**
  - `!isFinite('')` → `!isFinite(0)` → **`false`**

  Kein Validierungsfehler wird gesetzt. Das Formular submittiert mit `price: ""`
  (via `String('')`) an das Backend → HTTP 422 ohne client-seitige Rückmeldung.

  **Empfohlene Korrektur** (für den Entwickler, nicht vom Tester angewandt):
  Validierung ergänzen:
  ```typescript
  if (String(form.price).trim() === '' || isNaN(Number(form.price)) || !isFinite(Number(form.price)))
    errors.price = 'Bitte einen gültigen Preis eingeben'
  ```
  oder kürzer: `if (form.price === '' || form.price == null || isNaN(+form.price))`

---

## Hinweis zu den vorhandenen e2e-Tests

Beim Lauf von `npm run test -- --run` ohne Datei-Filter werden Playwright-e2e-Specs
(`e2e/*.spec.ts`) durch Vitest aufgegriffen und schlagen mit ECONNREFUSED fehl
(kein Server läuft). Das ist ein **pre-existing Issue** und unabhängig von
diesem Change.

---

## Status der Blocker

| Blocker | Befund | Status |
|---------|--------|--------|
| **F01** — `usePricingItems.test.ts` fehlte | 9 Tests erstellt, alle grün | ✅ Behoben |
| **F06** — `PricingItemForm.test.ts` fehlte | 9 Tests erstellt, 8 grün | ⚠️ Teilweise behoben |

Der verbleibende Fehler (leeres Preisfeld) ist ein **Produktivcode-Bug** (unvollständige
F04-Validierung), kein Test-Bug. Blocker F06 gilt formal erst als behoben, wenn
der Entwickler die Validierung in `PricingItemForm.vue` um den Leerstring-Fall
erweitert hat.
