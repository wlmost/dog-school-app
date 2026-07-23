# Triage: Error-State-UI in List-Views

**Pfad:** klein
**Geschätzter Umfang:** 4 Dateien, TypeScript/Vue
**Risiko:** niedrig — rein additive Präsentationslogik; keine Schnittstellen, keine Backend-Änderungen
**Klarheit:** klar — Anforderung benennt konkret vier Dateien, Expected Behaviour, 403-Sonderfall und Akzeptanzkriterien

## Anforderung (Zusammenfassung)

In vier List-Views (`CustomersView`, `DogsView`, `BookingsView`, `InvoicesView`) wird im `catch`-Block der `loadXxx()`-Funktion derzeit nur `console.error` aufgerufen; ein `error`-Ref fehlt. Dadurch zeigt die View nach einem API-Fehler den Leer-Zustand statt einen Fehlerbanner. Gefordert ist ein sichtbarer Fehlerbanner mit „Erneut laden"-Button, ein dedizierter 403-Hinweis sowie die Absicherung des Leer-Zustands durch eine `\!error`-Guard.

## Befunde aus Codeanalyse

### Existierendes Error/Toast-System
- **`useToastStore` (Pinia)** + **`ToastContainer.vue`**: globales Toast-System bereits produktiv im Einsatz.
- **`utils/errorHandler.ts` → `handleApiError()`**: parsiert bereits 403 / 500 / Netzwerkfehler und feuert den passenden Toast. Alle vier betroffenen Views importieren `handleApiError` bereits — nutzen es aber **nur für Mutations (Löschen), nicht für den initialen Load**.

### Existierendes Error-State-Banner-Pattern
Zwei Views verwenden bereits ein Inline-Banner-Pattern mit dediziertem `loadError`-Ref:
- **`AnnouncementsView.vue`** (Z. 32–34): `v-else-if="loadError"` → rotes Banner, Leer-Zustand darunter nur wenn kein Fehler.
- **`SettingsView.vue`** (Z. 16–17, 565–608): identisches Pattern.
- **Lücke gegenüber Anforderung:** Kein Retry-Button, keine 403-Unterscheidung im Banner-Text — das muss neu gebaut werden.

### Kein separates 403-Banner-Component
Es gibt keine vorgefertigte `<ErrorBanner>`-Komponente. Das Banner wird bisher direkt als `<div>`-Block im Template der jeweiligen View inline definiert.

### Relevante Specs
- `openspec/specs/frontend-toast-notifications/spec.md`: beschreibt Toast-Rendering, nichts über Inline-Banners.
- `openspec/specs/dog-form-error-handling/spec.md`: betrifft Modal-Formulare, nicht List-Views.
- Keine bestehende Spec für „List-View Load Error State".

## Empfohlener Implementierungsansatz

Das AnnouncementsView-Pattern (inline `loadError: ref<string|null>(null)`) als Vorlage verwenden und erweitern:

1. **Pro View** einen `loadError`-Ref hinzufügen (Typ `string | null`).
2. Im `loadXxx()`-catch-Block: Status 403 → speziellen Text setzen; sonst generische Netzwerk-/Server-Meldung.
3. `loadXxx()` zu Beginn `loadError.value = null` setzen (Reset bei Retry).
4. Template: `v-else-if="loadError"` → Banner mit Meldung + „Erneut laden"-Button (ruft `loadXxx()` auf).
5. Leer-Zustand: `v-else-if="\!items.length"` bleibt, greift aber nur wenn kein Error vorliegt.

**Design-Entscheidung für den Entwickler:** Ein gemeinsames Helper-Composable (`useListError`) ist *nicht zwingend*, da das Pattern in vier Dateien identisch, aber sehr klein ist (3–4 Zeilen pro catch). Inline ist ausreichend — kein Over-Engineering.

## Rückfragen an den User

Keine — Anforderung ist eindeutig.

## Empfohlene nächste Aktion

**`dev-typescript`** direkt beauftragen — kein Architekt-Durchlauf nötig. Der Change ist klein, das Muster ist im Projekt etabliert (AnnouncementsView), und alle Akzeptanzkriterien sind klar spezifiziert.

Aufgabe für `dev-typescript`:
- Lies diese Triage-Datei und `CLAUDE.md`
- Implementiere `loadError`-Ref + Banner + Retry-Button in allen vier Views
- Beachte: 403 → „Sie haben keine Berechtigung, diese Daten zu sehen." / andere Fehler → generische Meldung + ggf. Netzwerkhinweis
- Leer-Zustand nur bei fehlerfreier, leerer Antwort
- Vorlage für Banner-Markup: `AnnouncementsView.vue` Z. 32–34 (erweitern um Retry-Button und 403-Zweig)
- Nach Implementierung: `npm run lint && npm run test && npm run build`
