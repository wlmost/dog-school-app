# Review: T03 + T04 — pricing-overview

**Reviewer-Prüfung:** 2026-05-14  
**Gesamtbewertung:** CHANGES REQUESTED

---

## Zusammenfassung

Die Implementierung ist handwerklich sauber: TypeScript-Interfaces stimmen mit
der Spec überein, alle Vue 3 Composition-API-Konventionen werden eingehalten,
Dark-Mode-Klassen sind durchgängig vorhanden, und die Sicherheits-Checkliste
ist ohne Befunde. Zwei **Blocker** verhindern die Abnahme: die in beiden Tasks
als Akzeptanzkriterium gelisteten Vitest-Tests wurden nicht erstellt. Daneben
gibt es ein praxisrelevantes MINOR-Problem mit fehlender NaN-Validierung im
Preisfeld und ein fehlendes Loading-Feedback auf der öffentlichen Startseite.

---

## Befunde

### frontend/src/api/pricingItems.ts

Keine Befunde — alles korrekt.

Anmerkung: `Omit<PricingItem, 'id' | 'createdAt' | 'updatedAt'>` als Argument-Typ
für `create()` und `update()` ist strenger als die Spec (`Partial<PricingItem>`)
und verhindert versehentliches Übergeben von id/timestamp — eine sinnvolle
Verbesserung.

---

### frontend/src/composables/usePricingItems.ts

Keine Befunde — alles korrekt.

---

### frontend/src/composables/usePricingItems.test.ts

| # | Schwere | Befund | Zeile | Empfehlung |
|---|---------|--------|-------|------------|
| F01 | 🔴 BLOCKER | Datei existiert nicht. T03-Akzeptanzkriterium lautet: „Vitest-Tests für `usePricingItems.ts` (mock API-Calls, prüfe State-Übergänge)". Die Datei wurde nicht erstellt. | — | Datei `frontend/src/composables/usePricingItems.test.ts` mit Vitest anlegen. Mindest-Abdeckung: (1) `loadPublic()` befüllt `groups`, setzt `loading` zurück; (2) `loadPublic()` bei API-Fehler setzt `error`; (3) `createItem()` hängt neues Item an `items`; (4) `deleteItem()` filtert Item aus `items`. Mocks via `vi.mock('@/api/pricingItems')`. |

---

### frontend/src/components/PricingModal.vue

Keine Befunde — alles korrekt.

Positiv: `aria-label="Modal schließen"` am X-Button, `maximumFractionDigits: 2`
zusätzlich zu `minimumFractionDigits: 2` (verhindert vierstellige Dezimalstellen
bei Float-Ungenauigkeiten — besser als Spec-Vorgabe).

---

### frontend/src/views/HomeView.vue (neue Teile)

| # | Schwere | Befund | Zeile | Empfehlung |
|---|---------|--------|-------|------------|
| F02 | 🟡 MINOR | `loadPublic()` wird via `onMounted` aufgerufen, aber `loading` und `error` aus `usePricingItems()` werden nicht destructured und nicht im Template verwendet. Klickt ein Nutzer die Preise-Kachel, bevor die API geantwortet hat, öffnet sich das Modal und zeigt sofort „Noch keine Preise hinterlegt." — obwohl der Ladevorgang noch läuft. Review-Kriterium: „Loading-States vorhanden". | L208 | `loading` und `error` aus dem Composable destrukturieren. Im Modal entweder: (a) `loading`-Prop an `PricingModal` weitergeben und dort einen Spinner zeigen, oder (b) die Kachel-Klick-Aktion disablen während `loading === true`. Einfachste Lösung: `:disabled="loading"` + `cursor-wait` auf dem Feature-7-div. |
| F03 | 🟢 INFO | Zwei separate `onMounted()`-Aufrufe (L216: `loadPublic()`, L219: SEO-Meta). Beide werden korrekt ausgeführt (Vue 3 akkumuliert mehrere `onMounted`-Hooks), aber der Code liest sich klarer als einer. Der zweite `onMounted` war vorher schon vorhanden — das ist ein Pre-Existing-Issue. | L216, L219 | Optional: Beide Callbacks in einem `onMounted(() => { loadPublic(); document.title = …; … })` zusammenführen. |

---

### frontend/src/components/PricingItemForm.vue

| # | Schwere | Befund | Zeile | Empfehlung |
|---|---------|--------|-------|------------|
| F04 | 🟡 MINOR | `price`-Feld wird via `v-model.number` als `number` gebunden. Leert der Nutzer das Eingabefeld vollständig, liefert Vue `NaN`. Die Validierungsbedingung `form.price < 0` ist für `NaN` **false** (`NaN < 0 === false`), sodass kein Fehler angezeigt wird. `String(NaN)` = `"NaN"` wird dann an das Backend gesendet → HTTP 422 ohne client-seitige Rückmeldung. | L83 (`v-model.number`), L245 (`if (form.price < 0)`) | Validierungsbedingung erweitern: `if (form.price < 0 \|\| \!isFinite(form.price) \|\| isNaN(form.price)) errors.price = 'Bitte einen gültigen Preis eingeben'`. |
| F05 | 🟢 INFO | `usePricingItems()` wird innerhalb der Form-Komponente instanziiert, um nur `createItem` und `updateItem` zu beziehen (L236). Dabei werden `groups`, `items` und `loading` als tote reaktive Refs mit alloziert, die nie genutzt werden. Kein funktionaler Fehler, aber leicht irreführend. | L236 | Optional: Nur die benötigten Composable-Methoden destrukturieren oder prüfen, ob `createItem`/`updateItem` als Props übergeben werden können. Da `SettingsView` die gleiche Composable-Instanz verwendet, wäre eine Prop-Lösung sauberer. |

---

### frontend/src/components/PricingItemForm.test.ts

| # | Schwere | Befund | Zeile | Empfehlung |
|---|---------|--------|-------|------------|
| F06 | 🔴 BLOCKER | Datei existiert nicht. T04-Akzeptanzkriterium lautet: „Vitest-Tests für `PricingItemForm.vue` (Formularvalidierung, Submit-Verhalten)". | — | Datei `frontend/src/components/PricingItemForm.test.ts` mit Vitest + `@vue/test-utils` anlegen. Mindest-Abdeckung: (1) leere Pflichtfelder → Fehlermeldung sichtbar, kein Emit; (2) negativer Preis → Fehlermeldung; (3) gültige Daten bei `item = null` → `createItem()` aufgerufen, `'saved'` emittiert; (4) gültige Daten bei `item \!= null` → `updateItem()` aufgerufen; (5) Watch auf `props.visible`: Formular wird bei `visible = true` mit `item`-Daten befüllt, bei `null` zurückgesetzt. Mocks via `vi.mock('@/composables/usePricingItems')`. |

---

### frontend/src/views/SettingsView.vue (neue Teile)

| # | Schwere | Befund | Zeile | Empfehlung |
|---|---------|--------|-------|------------|
| F07 | 🟢 INFO | Nach einem fehlgeschlagenen `deleteItem()`-Aufruf (z. B. Netzwerkfehler) setzt `loadAll()` im selben Handler `error.value = null` sofort zurück, bevor der Nutzer die Fehlermeldung lesen kann. `pricingError` wird damit stillschweigend gelöscht. | L710–717 (`handleDeletePricingItem`) | Optional: Entweder `loadAll()` nur aufrufen wenn kein Fehler vorliegt (`if (\!pricingError.value) await loadAll()`), oder vor `loadAll()` eine kurze Nutzernotifikation (Toast) ausgeben. Da keine Toast-Infrastruktur in der Spec vorgesehen ist, reicht der bedingte `loadAll()`-Aufruf. |

---

## Lob

- **pricingItems.ts:** `Omit<>`-Typisierung für Create/Update-Parameter ist strenger als die Spec und verhindert Missbrauch.
- **usePricingItems.ts:** Jede async-Methode setzt `error.value = null` am Anfang — konsequentes Reset-Pattern. Klare Trennung von `groups` (public) und `items` (admin).
- **PricingModal.vue:** `aria-label="Modal schließen"` am X-Button vorhanden; kein `v-html` mit User-Daten; `maximumFractionDigits: 2` als sinnvolle Erweiterung über Spec hinaus.
- **PricingItemForm.vue:** Watch-Pattern für Formular-Reset/Befüllung ist korrekt und vollständig (alle Felder, inkl. `null`-Handling für optionale Felder via `?? ''`). `saving`-Flag sauber von Composable-`loading` getrennt. `errors.general` für API-Fehler vorhanden.
- **SettingsView.vue:** Vollständige Tristate-Behandlung (loading/error/empty), alle 7 Tabellenspalten laut Spec, `window.confirm()` korrekt implementiert, existierende Funktionalität (saveSettings etc.) vollständig unberührt.

---

## Offene Punkte für Implementierer

| Priorität | Punkt |
|-----------|-------|
| 🔴 Muss | **F01** — Vitest-Tests für `usePricingItems.ts` erstellen (mind. 4 Testfälle, s. Befund) |
| 🔴 Muss | **F06** — Vitest-Tests für `PricingItemForm.vue` erstellen (mind. 5 Testfälle, s. Befund) |
| 🟡 Sollte | **F04** — NaN-Validierung für `price`-Feld in `PricingItemForm.vue` ergänzen |
| 🟡 Sollte | **F02** — Loading-State in `HomeView.vue` während `loadPublic()` einbauen (Kachel disablen oder Spinner im Modal) |
| 🟢 Optional | **F03** — Zwei `onMounted`-Calls in `HomeView.vue` zu einem zusammenführen |
| 🟢 Optional | **F05** — Tote Composable-State in `PricingItemForm.vue` vermeiden |
| 🟢 Optional | **F07** — Fehler-Persistenz nach fehlgeschlagenem Delete in `SettingsView.vue` verbessern |
