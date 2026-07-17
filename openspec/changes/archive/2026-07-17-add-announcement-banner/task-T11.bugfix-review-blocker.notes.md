# Notes: Reviewer-Blocker-Fix — geteilter `error`-Ref versteckt die Liste

**Change-ID:** add-announcement-banner
**Agent:** dev-typescript
**Bezug:** Reviewer-Befund zu T11 (`AnnouncementsView.vue`/`useAnnouncements.ts`)

## Befund (Zusammenfassung)

`useAnnouncements.ts` verwendete einen einzigen geteilten `error`-Ref für
Lade- **und** Mutations-Operationen. Das Template von `AnnouncementsView.vue`
rendert die Liste über eine `v-if`/`v-else-if`-Kette
(`loading` → `error` → `leer` → `<ul>`). Schlug eine Mutation (z. B.
`deleteAnnouncement`) fehl, blieb `error.value` gesetzt, und da
`v-else-if="error"` **vor** `<ul v-else>` geprüft wird, verschwand die
komplette (weiterhin gültige) Liste hinter der Fehlermeldung — obwohl
`announcements` unverändert befüllt war. Vom Tester unabhängig bestätigt,
inkl. neuer Testfälle in `AnnouncementsView.test.ts` für `update`/`delete`-
Fehlerfälle.

## Gewählte Option: (a) getrennte `loadError`/`mutationError`-Refs

Der Reviewer bot drei Optionen an und verwies zur Prüfung auf
`frontend/src/views/SettingsView.vue`. Vorab-Recherche vor der Entscheidung:

- **`SettingsView.vue:565-566` / `608` / `652`:** Die (inline im View selbst
  implementierte, nicht über ein Composable laufende) Einstellungs-Verwaltung
  verwendet tatsächlich getrennte `loadError`- und `saveError`-Refs — `loadError`
  gated den Formular-Bereich (`v-else-if="loadError"`, Zeile 16), `saveError`
  wird unabhängig davon am Fußende des Formulars angezeigt (Zeile 387-392) und
  blockiert nie die Sichtbarkeit des Formulars selbst. Das bestätigt Option (a)
  als etabliertes, bewusstes Projektmuster für **genau dieses Problem**
  (Lade- vs. Sende-Fehler nicht vermischen).
- **`usePricingItems.ts` (Composable, das laut `design.md` Abschnitt 6.2 als
  1:1-Vorbild für `useAnnouncements.ts` diente):** Hat denselben einzelnen
  geteilten `error`-Ref wie der ursprüngliche `useAnnouncements.ts` — also
  **kein** Vorbild für getrennte Fehler-Refs auf Composable-Ebene. In
  `SettingsView.vue` wird der latente Bug dort nur deshalb nicht sichtbar,
  weil `handleDeletePricingItem`/`handlePricingSaved`
  (`SettingsView.vue:712-721`) nach **jeder** Mutation unbedingt erneut
  `loadAll()` aufrufen, was `error.value` sofort wieder auf `null` zurücksetzt,
  bevor ein Render mit gesetztem Fehler und sichtbarer Tabelle zusammentreffen
  könnte. `AnnouncementsView.vue` ruft nach Mutationen bewusst **kein**
  erneutes `loadAll()` auf (State wird lokal aus der Response
  aktualisiert/gefiltert), wodurch der Bug dort tatsächlich auftritt.
  **`usePricingItems.ts` wurde nicht angefasst** (außerhalb des erlaubten
  Datei-Scopes dieser Korrektur) — das dortselbe latente Muster besteht
  fort, ist aber durch das beschriebene Aufrufmuster in `SettingsView.vue`
  aktuell nicht beobachtbar. Empfehlung für einen künftigen, separaten
  Change: dieselbe Aufteilung in `usePricingItems.ts` nachziehen, falls
  eine Aufrufstelle künftig ohne anschließendes `loadAll()` mutiert.

**Entscheidung:** `useAnnouncements.ts` bekommt zwei getrennte Refs,
`loadError` (gated `loadPublic`/`loadAll`) und `mutationError` (gated
`createAnnouncement`/`updateAnnouncement`/`deleteAnnouncement`) — konsistent
zur Namensgebung `loadError`/`saveError` aus `SettingsView.vue`. Das behebt
das Problem an der Wurzel (Composable-API), nicht nur kosmetisch im Template
(Option b wäre zudem unzureichend gewesen, siehe unten), und macht
`mutationError` unabhängig sichtbar, ohne die Ladezustand-Kette zu berühren.

**Warum nicht Option (b) (`v-else-if="error && announcements.length === 0"`)?**
Bei nicht-leerer Liste hätte diese Bedingung den Fehler zwar nicht mehr vor
der Liste versteckt, aber die Fehlermeldung dabei komplett unterdrückt statt
sie sichtbar an anderer Stelle anzuzeigen — insbesondere für
`deleteAnnouncement`-Fehler, die (anders als Create/Update) über keinen
Modal-Dialog laufen und sonst nirgends angezeigt würden. Der vom Tester
ergänzte Testfall "zeigt eine Fehlermeldung an wenn das Löschen fehlschlägt"
mit nicht-leerer Liste hätte mit Option (b) allein **nicht** grün werden
können.

**Warum nicht Option (c) (`error.value` nach Anzeige zurücksetzen)?**
Verschiebt das Problem nur zeitlich (Fehler blitzt kurz auf und verschwindet
unkontrolliert wieder) und vermischt weiterhin zwei fachlich unterschiedliche
Zustände (Laden vs. Mutieren) in einem Ref — widerspricht dem bereits im
Projekt etablierten `loadError`/`saveError`-Muster aus `SettingsView.vue`.

## Änderungen

### `frontend/src/composables/useAnnouncements.ts`

- `error` ersetzt durch zwei Refs: `loadError` (von `loadPublic`/`loadAll`
  gesetzt/zurückgesetzt) und `mutationError` (von `createAnnouncement`/
  `updateAnnouncement`/`deleteAnnouncement` gesetzt/zurückgesetzt). Jede
  Funktion setzt beim Start nur noch **ihren eigenen** Fehler-Ref auf `null`
  zurück — eine fehlgeschlagene Mutation überlebt daher einen nachfolgenden
  erfolgreichen `loadAll()`-Aufruf nicht implizit mit, sie wird erst beim
  nächsten Mutationsversuch zurückgesetzt (identisch zum Verhalten von
  `saveError` in `SettingsView.vue`, das ebenfalls nur von `saveSettings()`
  zurückgesetzt wird, nicht von `loadSettings()`).

### `frontend/src/views/AnnouncementsView.vue`

- Template: neuer, von der Lade-/Leer-/Listen-`v-if`-Kette **unabhängiger**
  Banner `<div v-if="mutationError">` direkt unterhalb der Kopfzeile —
  zeigt Mutations-Fehler (insbesondere Lösch-Fehler), ohne die
  darunterliegende Liste zu verstecken.
- Bestehende Kette (`loading` → `loadError` → leer → `<ul>`) unverändert in
  der Struktur, nur `error` → `loadError` umbenannt.
- `handleSubmit()`: prüft nach `createAnnouncement`/`updateAnnouncement`
  jetzt `mutationError.value` statt `error.value` für `errors.general` im
  Formular (unverändertes Verhalten, nur konsistente Ref-Referenz).

### Tests

- `frontend/src/composables/useAnnouncements.test.ts`: bestehende
  `error`-Assertions auf `loadError` (Lade-Tests) bzw. `mutationError`
  (Mutations-Tests) umgestellt; neuer Testfall, der explizit belegt, dass
  ein vorheriger `loadError` einen nachfolgenden `deleteAnnouncement`-Erfolg
  nicht mit einem stillen `mutationError` verunreinigt (Nachweis der
  Unabhängigkeit beider Refs).
- `frontend/src/views/AnnouncementsView.test.ts`: Mock liefert jetzt
  `mockLoadError`/`mockMutationError` statt `mockError` (mit
  aktualisiertem Erklär-Kommentar zum Grund für echte `ref()`-Mocks). Zwei
  neue/erweiterte Assertions:
  - "zeigt weiterhin die vorhandene Liste an, wenn eine Mutation
    fehlschlägt" — reproduziert exakt das vom Reviewer beschriebene
    Szenario (nicht-leere Liste + gesetzter `mutationError`) und prüft,
    dass sowohl die Fehlermeldung **als auch** alle `<li>`-Einträge sichtbar
    bleiben.
  - Bestehender Löschen-Fehler-Test um eine Prüfung ergänzt, dass die Liste
    (1 Eintrag) trotz Fehleranzeige weiterhin gerendert wird.

## Verifikation

```bash
cd frontend
npx vitest run
# 17 Testdateien, 191 Tests grün (189 bestehende + 2 neue: 1x
# useAnnouncements.test.ts, 1x AnnouncementsView.test.ts)

npm run build
# vue-tsc -b: keine Typfehler
# vite build: erfolgreich, keine Warnungen
```

`npm run lint` existiert weiterhin nicht in `frontend/package.json`
(bereits in `task-T11.notes.md` als bestehende Lücke dokumentiert, nicht
Teil dieses Bugfixes).

## Bewusst nicht angefasste Dateien

- `frontend/src/composables/usePricingItems.ts` — enthält dasselbe latente
  Muster (siehe Analyse oben), aber außerhalb des für diese Korrektur
  freigegebenen Datei-Scopes. Aktuell nicht beobachtbar, weil
  `SettingsView.vue` nach jeder Preis-Mutation ohnehin neu lädt.
- `frontend/src/views/SettingsView.vue` — nur als Referenz gelesen, nicht
  geändert.
- `frontend/src/components/AnnouncementBanner.vue` — konsumiert nur
  `announcements`/`loadPublic`, nicht `error`; von der Umbenennung nicht
  betroffen, verifiziert per `grep`.
