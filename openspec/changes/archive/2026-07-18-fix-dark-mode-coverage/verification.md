# Verification: fix-dark-mode-coverage

**Schritt 0:** `openspec validate fix-dark-mode-coverage` → `Change 'fix-dark-mode-coverage' is valid` (strukturell ok, weiter mit Realitätsabgleich).

**Gesamtstatus:** ok (drei kleinere Ungenauigkeiten in den Begründungstexten von `design.md`/`tasks.md` gefunden — sie ändern weder Task-Scope noch Dateiliste, sollten aber vor Archivierung korrigiert werden, siehe Empfehlung)

---

## Bestätigt

### Mechanismus (Context)
- `design.md` Z.11: `frontend/tailwind.config.js:3` → `darkMode: 'class'` bestätigt in `frontend/tailwind.config.js:3`
- `design.md` Z.14-18: `useThemeStore` mit `isDark`-Ref, `applyTheme()`, `initTheme()` → bestätigt in `frontend/src/stores/theme.ts:6` (`isDark = ref(false)`), `:21-27` (`applyTheme`), `:9-18` (`initTheme`)
- `design.md` Z.19-20: `frontend/src/main.ts:15-16` ruft `themeStore.initTheme()` vor `app.mount('#app')` auf → bestätigt exakt in `frontend/src/main.ts:15-16` (Zeile 18 ist `app.mount('#app')`)
- `design.md` Z.21-23: Toggle-UI in `DefaultLayout.vue:86-93` und `PublicLayout.vue:42-53` → bestätigt exakt in beiden Dateien (Button-Grenzen identisch)

### Konkreter Layout-Bug (DefaultLayout.vue)
- `design.md` Z.112-113 / Z.2: Root-Wrapper `<div class="min-h-screen" :style="backgroundStyle">` → bestätigt in `frontend/src/layouts/DefaultLayout.vue:2`
- `design.md` Z.117-124 / `tasks.md` Z.48-51: `backgroundStyle` computed mit hartkodiertem `rgba(255, 255, 255, ...)`, Zeilen 169-174 → bestätigt exakt in `frontend/src/layouts/DefaultLayout.vue:169-174`, kein Bezug auf `themeStore.isDark` im Ausdruck
- `design.md` Z.126-127: `themeStore` bereits injiziert (Zeile 150), verwendet für Toggle (Zeilen 86-93) → bestätigt: `const themeStore = useThemeStore()` in `DefaultLayout.vue:150`, Verwendung in `:87` (`themeStore.toggleTheme()`) und `:91` (`themeStore.isDark`)
- `design.md` Z.134-135: Sidebar/Header bereits korrekt mit `dark:bg-gray-800` (Zeilen 14, 72) → bestätigt in `DefaultLayout.vue:14` (`bg-white dark:bg-gray-800` auf `<aside>`) und `:72` (`bg-white dark:bg-gray-800` auf `<header>`)
- `tasks.md` Z.56-57: `backgroundImage`-Import in Zeile 129 → bestätigt in `DefaultLayout.vue:129` (`import backgroundImage from '@/assets/pet-01-1280x664.jpg'`)
- `design.md` D2 (Z.204): "Tonwert angelehnt an Tailwind `gray-900` = `rgb(17, 24, 39)`" → bestätigt indirekt: `frontend/tailwind.config.js:8-24` überschreibt nur die `primary`-Palette, `gray` bleibt der Tailwind-Standard, dessen `gray-900` tatsächlich `rgb(17, 24, 39)` ist

### Referenzmuster
- `proposal.md` Z.34-38 / `design.md` D1: `PricingModal.vue` Dialog-Panel-Muster (`bg-white dark:bg-gray-800`, `border-gray-200 dark:border-gray-700`, `text-gray-900 dark:text-white`, `text-gray-500 dark:text-gray-400`) → bestätigt in `frontend/src/components/PricingModal.vue:3,5,6,19`
- `proposal.md` Z.37-38 / `design.md` D1: `PublicLayout.vue` Seiten-Hintergrund-Muster `bg-gray-50 dark:bg-gray-900` → bestätigt in `frontend/src/layouts/PublicLayout.vue:2`

### Teilbefund 1 — 20 Dateien ohne `dark:`-Klasse
- `design.md` Z.33-34: `grep -rL "dark:" frontend/src --include="*.vue"` → 20 Treffer → bestätigt: eigener Lauf liefert exakt dieselben 20 Dateien (identische Liste, Reihenfolge irrelevant)
- `design.md` Z.26: "34 von 54 `.vue`-Dateien... bereits `dark:`-Klassen" → bestätigt: `find src -name "*.vue" | wc -l` = 54, 54−20 = 34
- `design.md` Z.59-65: `SearchInput.vue` **nicht** im `grep -L`-Treffer, hat 1× `dark:hover`-Utility, 2 `text-gray-400` ohne `dark:`-Pendant, verwendet in `DogsView.vue`/`CustomersView.vue`/`TrainersView.vue`/`CoursesView.vue` → bestätigt: `SearchInput.vue` fehlt in der 20er-Liste, `grep -oE 'dark:[a-z0-9-]+'` liefert genau `dark:hover` (Zeile 30), 2× `text-gray-400` (Zeilen 5, 30) ohne Pendant, `grep -rl "SearchInput" src --include="*.vue"` liefert exakt die vier genannten Views

### Teilbefund 2 — inkonsistente `dark:text-*`-Paare (Tabelle design.md Z.76-87)
Nachvollzogen mit derselben Methodik (`text-(gray|slate|zinc|neutral)-N` gesamt vs. `dark:text-(gray|slate|zinc|neutral)-N` gesamt, `dark:text-white` bewusst ausgeklammert, wie die Architekten-Zahlen selbst zeigen) — alle 10 Zeilen bestätigt:
- `CustomersView.vue` 12/0 → bestätigt
- `TrainersView.vue` 25/0 → bestätigt
- `InvoicesView.vue` 16/0 → bestätigt
- `AnamnesisView.vue` 21/0 → bestätigt (unter der im Original verwendeten Zählweise ohne `dark:text-white`; siehe Widerlegt-Abschnitt für die daraus resultierende Folgeaussage)
- `BookingsView.vue` 21/2 → bestätigt
- `DogsView.vue` 6/1 → bestätigt
- `SettingsView.vue` 47/18 → bestätigt
- `EmailPreviewModal.vue` 9/4 → bestätigt
- `AgbView.vue` 23/11 → bestätigt
- `DatenschutzView.vue` 29/12 → bestätigt

### Weitere Einzelbelege
- `design.md` Z.89-92 (Stichprobe `CustomersView.vue`, `TrainersView.vue`, `InvoicesView.vue`: `dark:bg-gray-800` + `dark:divide-gray-700`, keine `dark:text-*`) → bestätigt für diese drei Dateien: `grep -oE 'dark:[a-z0-9-]+'` liefert für jede ausschließlich `dark:bg-gray-800`/`dark:divide-gray-700`, keinerlei `dark:text-*` (siehe Widerlegt-Abschnitt für `AnamnesisView.vue`, die vierte genannte Datei)
- `design.md` Z.99-100 in Verbindung mit T02: `DogFormModal.vue:27` `DialogPanel` mit reinem `bg-white`, `:28` `text-gray-900` ohne Pendant → bestätigt exakt in `frontend/src/components/DogFormModal.vue:27-28`
- `tasks.md` Z.38 (Header): "`HtmlEditor.vue` ... in `CourseFormModal` + `AnnouncementsView` verwendet" → bestätigt: `grep -rl "HtmlEditor" src --include="*.vue"` liefert genau `CourseFormModal.vue` und `views/AnnouncementsView.vue`
- `design.md` Z.48: "`CourseSessionList.vue` (nur in `CourseDetailView.vue` verwendet)" → bestätigt: einziger Treffer `views/CourseDetailView.vue`
- `design.md` Z.50: "`CourseRecurrenceForm.vue` (nur innerhalb von `CourseFormModal.vue` verwendet)" → bestätigt: einziger Treffer `components/CourseFormModal.vue`
- `tasks.md` Z.239-240: `EmailPreviewModal.vue` enthält `dark:prose-invert` → bestätigt in `frontend/src/components/EmailPreviewModal.vue:79`

### Out of Scope
- `proposal.md` Z.117-122: `HelloWorld.vue` nirgends außerhalb der eigenen Datei referenziert → bestätigt: `grep -rn "HelloWorld" src` liefert nur den Treffer innerhalb `HelloWorld.vue` selbst (Textstring, kein Import)
- `proposal.md` Z.123-126: `App.vue` enthält nur `<RouterView />` und `<ToastContainer />`, kein eigener gestylter Inhalt → bestätigt in `frontend/src/App.vue:1-9`
- `proposal.md` Z.129-131: Playwright-Suite existiert (`frontend/e2e/`), aber keine Theme-spezifischen Tests → bestätigt: `frontend/e2e/` enthält 5 Spec-Dateien, `grep -rli "dark|theme" e2e/` liefert 0 Treffer

### Testinfrastruktur (User-Frage 6)
- Keine Vitest-Tests, die CSS-Klassenstrings der betroffenen Dateien prüfen → bestätigt: `grep -rln "dark:" src --include="*.test.ts"` liefert 0 Treffer projektweit; die Tests der betroffenen Komponenten (`CustomerBookingModal.test.ts`, `DogFormModal.test.ts`, `CourseRecurrenceForm.test.ts`, `SettingsView.test.ts`, `views/courses/CoursesView.test.ts`) verwenden ausschließlich `wrapper.text()`-Inhaltsassertions, keine Klassen-/Attribut-Assertions
- `npm run lint` existiert nicht in `frontend/package.json` → bestätigt: `scripts` enthält nur `dev`, `build`, `build:deploy`, `preview`, `test`, `test:ui`, `test:coverage`, `e2e`, `e2e:ui` (`frontend/package.json:6-16`), kein `lint`-Eintrag

### Task-Zuordnung (User-Frage 7)
- Alle 6 Tasks (T01-T06) ausschließlich `dev-typescript` zugeordnet → bestätigt: Kopfzeile `tasks.md:4-6` sowie jede einzelne Task (`tasks.md:38`, `:86`, `:121`, `:156`, `:186`, `:220`) trägt `Agent: dev-typescript`

### Capability-Neuheit
- `proposal.md` Z.76-79: keine bestehende Capability für Theming/Dark-Mode unter `openspec/specs/` → bestätigt: `openspec list --specs` listet 15 Capabilities, keine davon zu Theming/Dark-Mode

---

## Widerlegt

- `design.md` Z.16-17: "persistiert in `localStorage` (`watch(isDark, ...)`, Zeilen 35–38)" → tatsächlich liegt der `watch`-Aufruf in `frontend/src/stores/theme.ts:39-43` (Kommentarzeile 39, `watch(...)`-Aufruf Zeile 40, schließende Klammer Zeile 43), nicht Zeilen 35-38. Funktionale Beschreibung ist korrekt, die Zeilenangabe ist um 4-5 Zeilen versetzt.

- `design.md` Z.89-93 / `proposal.md` Z.52-54 (User-Frage 3): "`CustomersView.vue`, `TrainersView.vue`, `InvoicesView.vue` und `AnamnesisView.vue` haben jeweils genau `dark:bg-gray-800` ... und `dark:divide-gray-700`, aber **keine einzige** `dark:text-*`-Klasse" → für `AnamnesisView.vue` **widerlegt**: die Datei enthält tatsächlich 3× `dark:text-white` (`frontend/src/views/anamnesis/AnamnesisView.vue:5`, `:62`, `:68`), zusätzlich zu `dark:bg-gray-800`/`dark:divide-gray-700`/`dark:hover:bg-gray-700`. Für `CustomersView.vue`, `TrainersView.vue`, `InvoicesView.vue` trifft die Aussage exakt zu (0 `dark:text-*` jeder Art, verifiziert). Das grundsätzliche Symptom (Großteil der Tabellenzellen ohne `dark:text-*`) bleibt für `AnamnesisView.vue` bestehen (18 von 21 `text-gray-*`-Vorkommen weiterhin ohne Pendant laut Teilbefund-2-Tabelle), aber die Formulierung "keine einzige" ist für diese eine Datei sachlich falsch.

- `tasks.md` Z.64-67: "`NotFoundView.vue`: ... da über den generischen Catch-All-Route (`frontend/src/router/index.ts:149`) sowohl vor als auch nach Login erreichbar" → widerlegt durch den Navigation-Guard in `frontend/src/router/index.ts:163-180`: die Catch-All-Route (`:147-151`, `path: '/:pathMatch(.*)*'`) setzt kein `meta: { requiresAuth: false }`. Der Guard prüft `if (to.meta.requiresAuth !== false)` (`:171`) — da `requiresAuth` bei dieser Route `undefined` ist, greift die Auth-Pflicht, und nicht angemeldete User werden zu `next({ name: 'Login', ... })` (`:177`) umgeleitet, **bevor** `NotFoundView.vue` gerendert wird. Die Route ist also nach aktuellem Code **nicht** vor dem Login erreichbar, sondern nur danach (oder für angemeldete User mit ungültiger URL). Das Ziel der Task (Dark-Mode-Abdeckung für `NotFoundView.vue`) bleibt davon unberührt — nur die Begründung ("sowohl vor als auch nach Login") ist falsch.

---

## Nicht auffindbar

*(keine — alle geprüften konkreten Behauptungen konnten anhand der Codebasis verifiziert oder widerlegt werden)*

---

## Neue Elemente (Plausibilität)

- `specs/dark-mode-theming/spec.md` legt die neue Capability `dark-mode-theming` an → `openspec list --specs` zeigt keine Namenskollision mit den 15 bestehenden Capabilities; Pfad `openspec/changes/fix-dark-mode-coverage/specs/dark-mode-theming/spec.md` folgt der in `CLAUDE.md` Abschnitt 8 dokumentierten Konvention. Keine neuen Quellcode-Dateien werden durch diesen Change angelegt (ausschließlich Änderungen an bestehenden `.vue`-Dateien), daher keine weiteren Pfad-Konflikte zu prüfen.

---

## Empfehlung

Die Spec ist inhaltlich weit überwiegend verlässlich: Kernbehauptung 1 (DefaultLayout-Bug), die 20-Dateien-Liste, die Teilbefund-2-Tabelle, die Referenzmuster, die Task-Agent-Zuordnung und die Aussagen zu Test-/Lint-Infrastruktur sind vollständig durch die Codebasis gedeckt. Task-Scope und betroffene Dateiliste sind korrekt und unverändert verwendbar. Drei kleinere Ungenauigkeiten in den *Begründungstexten* (nicht im eigentlichen Auftrag) sollten der Architekt vor `openspec archive` korrigieren: (1) die Zeilenangabe für `watch()` in `theme.ts` (39-43 statt 35-38), (2) die Formulierung "keine einzige `dark:text-*`-Klasse" für `AnamnesisView.vue` präzisieren (3 `dark:text-white` existieren bereits, der Großteil der Textfarben fehlt trotzdem), (3) die Begründung für `NotFoundView.vue` in T01 korrigieren (Route ist laut aktuellem Navigation-Guard nicht vor dem Login erreichbar). Keine der drei Korrekturen ändert Task-Umfang, betroffene Dateien oder Akzeptanzkriterien — User-Gate 1 kann aus fachlicher Sicht passieren, sofern der Architekt die drei Stellen nachzieht oder der User sie bewusst als vernachlässigbar einstuft.
