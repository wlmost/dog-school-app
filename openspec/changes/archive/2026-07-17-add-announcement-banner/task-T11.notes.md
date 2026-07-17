# Notes T11: Admin-Bereich `AnnouncementsView.vue`

**Change-ID:** add-announcement-banner
**Agent:** dev-typescript

**Dateien (neu):**
- `frontend/src/views/AnnouncementsView.vue`
- `frontend/src/views/AnnouncementsView.test.ts`

**Dateien (geändert):**
- `frontend/src/router/index.ts`
- `frontend/src/layouts/DefaultLayout.vue`

## Umsetzung

### `frontend/src/views/AnnouncementsView.vue`

Struktur folgt `design.md` Abschnitt 8.1, orientiert an `SettingsView.vue`
(Seitengerüst: Titel, Ladezustand/Fehlerzustand, Leerzustand) und
`PricingItemForm.vue` (Modal-Formular-Muster: `reactive`-Form-Objekt,
`reactive`-Errors-Objekt, `saving`-Ref, clientseitige Validierung vor dem
API-Aufruf, `errors.general` aus dem `error`-Ref des Composables nach dem
Aufruf). Anders als bei `PricingItemForm.vue` ist das Formular **nicht**
als eigene Komponente ausgelagert, sondern direkt in `AnnouncementsView.vue`
eingebettet (Task listet keine eigene Formular-Komponentendatei; die
Entscheidung "eingebettet oder Modal" lag laut `design.md` Abschnitt 8.1
explizit beim Entwickler-Agenten — Modal-Overlay-Optik wie
`PricingItemForm.vue`, aber ohne zusätzliche Datei, da nur eine
Aufrufstelle existiert und die Task keine weitere Datei vorsieht — YAGNI).

- **Liste:** `<ul>` mit `<li>` pro Ankündigung aus `announcements`
  (`useAnnouncements().loadAll()`, aufgerufen in `onMounted`), zeigt
  Miniaturbild (falls `imageUrl` gesetzt), Titel, Status-Badge (`Aktiv` /
  grün `bg-green-100 text-green-800` wenn `announcement.isActive`,
  sonst `Abgelaufen` / grau `bg-gray-100 text-gray-800` — Farbschema
  1:1 aus dem bestehenden Status-Badge-Muster in
  `frontend/src/views/bookings/BookingsView.vue:319-321`
  (`bookingStatusClass`) übernommen), gekürzte Textvorschau (HTML-Tags
  regex-gestrippt statt `v-html`, da für eine reine Listenvorschau kein
  Rich-Text-Rendering nötig ist — vermeidet zusätzliche
  DOMPurify-Abhängigkeit in dieser Datei), Anzeigedauer und formatiertes
  Ablaufdatum (`toLocaleDateString('de-DE')`, analog `BookingsView.vue`),
  Bearbeiten-/Löschen-Buttons.
- **Löschen:** `window.confirm(...)`-Bestätigung, danach
  `deleteAnnouncement(id)` — identisches Muster wie
  `SettingsView.vue:717-721` (`handleDeletePricingItem`) und
  `CoursesView.vue:271` (`confirm(...)`-Aufruf vor dem Löschen).
- **Formular:**
  - Titel (`<input maxlength="255">`), Pflichtfeld.
  - `<HtmlEditor v-model="form.body" />` — bestehende Komponente
    unverändert übernommen, kein neuer Rich-Text-Editor.
  - `<FileUpload :multiple="false" accepted-types="image/*"
    :auto-upload="true" @upload="handleImageUpload"
    @error="handleImageError" />`. `auto-upload="true"` gesetzt, damit
    die Dateiauswahl sofort per `upload`-Event an das Formular
    durchgereicht wird, statt dass in der `FileUpload`-Komponente selbst
    noch ein separater "Dateien hochladen"-Button geklickt werden müsste
    (der echte Netzwerk-Request passiert ohnehin erst beim Absenden des
    Ankündigungsformulars über `createAnnouncement`/`updateAnnouncement`
    — `FileUpload.vue` führt selbst keinen Upload durch, sondern emittiert
    nur die ausgewählte(n) Datei(en), siehe
    `frontend/src/components/FileUpload.vue:245-260`).
  - Bei Bearbeiten mit vorhandenem Bild und noch keiner neuen Auswahl wird
    das aktuelle Bild (`editingAnnouncement.imageUrl`) als Vorschau
    angezeigt; wird eine neue Datei gewählt, ersetzt sie
    `form.image` (bestehendes Bild bleibt serverseitig erhalten, wenn
    `form.image` beim Update `null` ist — `buildFormData()` in
    `frontend/src/api/announcements.ts:27-34` hängt `image` nur an, wenn
    ein Wert vorhanden ist; kein "Bild entfernen"-Schalter, wie in
    `design.md` Abschnitt 8.1 als YAGNI markiert).
  - Zahlenfeld `displayDays` (`<input type="number" min="1" max="365">`),
    clientseitige Validierung ergänzt den Bereich serverseitig
    (`StoreAnnouncementRequest`/`UpdateAnnouncementRequest`, T04) um
    sofortiges Feedback ohne Round-Trip.
  - Speichern ruft `createAnnouncement`/`updateAnnouncement` aus dem
    Composable auf; nach dem Aufruf wird `error.value` aus dem Composable
    geprüft — bei Fehler bleibt das Formular offen und zeigt
    `errors.general` (identisches Muster wie
    `PricingItemForm.vue:263-267`), sonst wird das Formular geschlossen.

### `frontend/src/router/index.ts`

Neuer Eintrag `announcements` (Route-Name `Announcements`,
`component: () => import('@/views/AnnouncementsView.vue')`,
`meta: { title: 'Ankündigungen', requiresAdmin: true }`) im
`/app`-Kind-Routen-Array, eingefügt nach dem `settings`-Eintrag und vor
`training-logs` — exakt an der in `design.md` Abschnitt 8.2 vorgegebenen
Stelle. Der bestehende Router-Guard
(`if (to.meta.requiresAdmin && !authStore.isAdmin) { ... }`,
`frontend/src/router/index.ts:182-185`, unverändert) greift automatisch,
da `requiresAdmin: true` gesetzt ist — kein Guard-Code geändert.

### `frontend/src/layouts/DefaultLayout.vue`

- `MegaphoneIcon`-Import in die bestehende
  `@heroicons/vue/24/outline`-Import-Liste ergänzt (nach `Cog6ToothIcon`).
- Neuer Navigations-Eintrag `{ name: 'Ankündigungen', to: { name:
  'Announcements' }, icon: MegaphoneIcon, roles: ['admin'] }` im
  `navigation`-Computed, eingefügt nach dem "Einstellungen"-Eintrag, vor
  "Kontakt" — exakt an der in `design.md` Abschnitt 8.3 vorgegebenen
  Stelle. Die bestehende Rollen-Filterung
  (`items.filter(item => !user.value || item.roles.includes(user.value.role))`,
  `DefaultLayout.vue:247`, unverändert) blendet den Eintrag für
  Nicht-Admins automatisch aus.

### `frontend/src/views/AnnouncementsView.test.ts`

15 Tests (Vitest + `@vue/test-utils`), Struktur/Stil an
`PricingItemForm.test.ts` und `SettingsView.test.ts` angelehnt:

- `useAnnouncements` wird gemockt (`vi.mock('@/composables/useAnnouncements', ...)`).
  **Wichtig:** Der Mock liefert **echte Vue-`ref()`s** für
  `announcements`/`loading`/`error` — nicht nur eine
  `{ value: ... }`-Attrappe wie in `PricingItemForm.test.ts`s
  `mockApiError`. Grund: `AnnouncementsView.vue` liest `error` sowohl im
  Template (`v-else-if="error"`, verlässt sich auf den
  Compiler-`unref()`-Mechanismus, der nur bei echten Refs korrekt
  entpackt) als auch im Script (`error.value` nach `createAnnouncement`/
  `updateAnnouncement`, analog `PricingItemForm.vue`). Eine reine
  `{ value: null }`-Attrappe ist im Template immer wahrheitswertig (da
  ein Objekt truthy ist, unabhängig von `.value`), was den
  Leerzustand-Test verfälscht hätte — mit echten `ref()`s verhalten sich
  beide Zugriffsarten identisch zur echten Composable-Implementierung.
- Da die Mock-Refs modulweit geteilt und zwischen Tests wiederverwendet
  werden, wird nach jedem Test explizit `wrapper.unmount()` aufgerufen
  (`afterEach`) — ohne das führten spätere Ref-Mutationen zu
  Reaktivitäts-Updates auf bereits von jsdom/happy-dom entsorgten
  Wrapper-Instanzen früherer Tests (`Cannot read properties of null`
  in `runtime-core`).
- `HtmlEditor` wird als einfaches `<textarea>` gestubbt (Tiptap/ProseMirror
  benötigt eine vollwertige `contenteditable`-DOM-Umgebung, die für einen
  reinen `v-model`-Vertragstest nicht nötig ist); `FileUpload` läuft
  **ungestubbt** (keine externen Abhängigkeiten) — der Datei-Upload-Test
  emittiert das `upload`-Event direkt über
  `wrapper.findComponent(FileUpload).vm.$emit(...)`.
- `window.confirm` wird über `vi.stubGlobal('confirm', vi.fn()...)`
  ersetzt (kein bestehendes Präzedenzbeispiel im Projekt gefunden, da
  `happy-dom` `window.confirm` nicht implementiert — `vi.spyOn(window,
  'confirm')` schlägt deshalb mit "can only spy on a function, received
  undefined" fehl).
- Abgedeckte Szenarien: `loadAll` beim Mounten, Ladeindikator,
  Fehleranzeige beim Laden, Leerzustand, Status-Badges für aktive **und**
  abgelaufene Ankündigungen, leeres/vorausgefülltes Formular öffnen,
  Client-Validierung (Titel leer, `displayDays` außerhalb 1–365),
  `createAnnouncement`-Aufruf mit Formulardaten + Formular schließt sich,
  `updateAnnouncement`-Aufruf mit der bearbeiteten ID, Bild-Auswahl landet
  im Payload, Formular bleibt bei Server-Fehler offen und zeigt die
  Fehlermeldung, Löschen mit/ohne Bestätigung.

## Verifikation

```bash
cd frontend
npx vitest run src/views/AnnouncementsView.test.ts   # 15/15 grün
npx vitest run                                       # 172/173 grün — 1 Fehlschlag in
                                                       # AnnouncementBanner.test.ts (T10),
                                                       # außerhalb des T11-Scopes, siehe unten
npx vue-tsc -b --force                                # keine Fehler
npm run build                                         # erfolgreich, keine TS-/Build-Fehler
```

**Hinweis zum einen fehlschlagenden Test außerhalb dieser Task:**
`src/components/AnnouncementBanner.test.ts` (`entfernt nicht erlaubte
Tags und Attribute aus body (DOMPurify)`) schlägt zum Zeitpunkt dieser
Implementierung fehl. Diese Datei gehört zu T10
(`AnnouncementBanner.vue`), das laut Aufgabenstellung von einem anderen
Agenten parallel bearbeitet wird und explizit **nicht** Teil von T11 ist
— `AnnouncementBanner.vue`, `AnnouncementBanner.test.ts` und
`HomeView.vue` wurden in dieser Task nicht angefasst. Kein
Zusammenhang mit den in T11 geänderten/neuen Dateien
(`AnnouncementsView.vue`, `AnnouncementsView.test.ts`, `router/index.ts`,
`DefaultLayout.vue`).

## Bekannte Abweichungen / Annahmen

- `design.md` Abschnitt 8.1 überließ die Entscheidung "eingebettet oder
  Modal" explizit dem Entwickler-Agenten. Gewählt: Modal-Overlay direkt
  in `AnnouncementsView.vue` (kein separates
  `AnnouncementFormModal.vue`), da die Task nur eine Formular-Aufrufstelle
  vorsieht und keine eigene Formular-Komponentendatei in der Task-Dateiliste
  steht.
- Kein automatisiertes `npm run lint` verfügbar (siehe `verification.md`,
  Abschnitt "Fehlende QA-Scripts" — `frontend/package.json` enthält
  keinen `lint`-Script). Manuell auf Konsistenz mit bestehenden
  Vue-/TypeScript-Konventionen geprüft (Datei- und Komponentennamen,
  `<script setup lang="ts">`, Prop-/Event-Typisierung ohne `any`).
