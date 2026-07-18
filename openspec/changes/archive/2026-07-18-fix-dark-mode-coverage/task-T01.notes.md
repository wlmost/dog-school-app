# Task T01 Notes: Layout & geteilte Komponenten

**Agent:** dev-typescript
**Change:** fix-dark-mode-coverage
**Status:** implementiert

---

## Geänderte Dateien

- `frontend/src/layouts/DefaultLayout.vue`
- `frontend/src/components/SearchInput.vue`
- `frontend/src/components/HtmlEditor.vue`
- `frontend/src/views/NotFoundView.vue`

Ausschließlich `class`-Attribute geändert, mit der in `tasks.md`
dokumentierten Ausnahme der `computed`-Erweiterung in
`DefaultLayout.vue`. Keine Änderung an Props, Emits, Composables oder
Business-Logik.

---

## 1. `frontend/src/layouts/DefaultLayout.vue:169-179`

Fehler laut `design.md` Abschnitt "Konkreter Layout-Bug" behoben: die
`computed`-Property `backgroundStyle` reagierte nicht auf
`themeStore.isDark` und rendert immer ein helles
`rgba(255, 255, 255, ...)`-Overlay.

Vorher (Zeilen 169-174, per `verification.md` bestätigt):
```ts
const backgroundStyle = computed(() => ({
  background: `linear-gradient(rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.8)), url(${backgroundImage})`,
  ...
}))
```

Nachher (Zeilen 169-179):
```ts
const backgroundStyle = computed(() => {
  const overlay = themeStore.isDark
    ? 'linear-gradient(rgba(17, 24, 39, 0.75), rgba(17, 24, 39, 0.85))'
    : 'linear-gradient(rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.8))'

  return {
    background: `${overlay}, url(${backgroundImage})`,
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    backgroundAttachment: 'fixed'
  }
})
```

- Der Light-Mode-Zweig ist byte-identisch zum bisherigen Ausdruck
  (`rgba(255, 255, 255, 0.7)` / `rgba(255, 255, 255, 0.8)`) — keine
  visuelle Regression im Light-Mode (Non-Regression, `design.md` D2).
- Der Dark-Mode-Zweig nutzt exakt die in `tasks.md` vorgegebenen Werte
  `rgba(17, 24, 39, 0.75)` / `rgba(17, 24, 39, 0.85)` (Tailwind
  `gray-900`).
- Keine neue Store-Instanz: `themeStore` war bereits injiziert
  (`DefaultLayout.vue:150`), hier nur zusätzlich gelesen.
- `backgroundImage`-Import (`DefaultLayout.vue:129`) unverändert, bleibt
  in beiden Modi Teil des `background`-Strings.

Alle übrigen `dark:`-Klassen in `DefaultLayout.vue` (Sidebar Zeile 14,
Header Zeile 72 usw.) waren laut `verification.md` bereits korrekt und
wurden nicht angefasst.

---

## 2. `frontend/src/components/SearchInput.vue`

Laut `design.md` Bestandsaufnahme (Teilbefund 1) hatte die Datei 2
`text-gray-400`-Vorkommen (Zeilen 5, 30) ohne `dark:`-Pendant.
Referenzmuster: `DefaultLayout.vue:59` (`text-gray-400
dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300`) —
identisches Muster für gedämpfte/interaktive Icon-Buttons.

- Zeile 5 (dekoratives Such-Icon, kein Hover-Zustand): `text-gray-400`
  → `text-gray-400 dark:text-gray-500`.
- Zeile 30 ("Clear"-Button, hatte bereits `dark:hover:text-gray-200` auf
  dem Hover-Zustand, aber keinen `dark:`-Pendant auf der Basisfarbe):
  `text-gray-400` → `text-gray-400 dark:text-gray-500` (Hover-Klassen
  unverändert).

Das `<input>`-Element selbst (Zeile 20) verwendet die globale `.input`-
Utility-Klasse (`frontend/src/assets/main.css:28-30`), die bereits
vollständige `dark:`-Abdeckung (Hintergrund, Rahmen, Text, Focus-Ring)
mitbringt — hier keine Änderung nötig.

Verwendungsstellen (laut `design.md`/`verification.md` bestätigt via
`grep -rl "SearchInput" src`): `DogsView.vue`, `CustomersView.vue`,
`TrainersView.vue`, `CoursesView.vue`.

---

## 3. `frontend/src/components/HtmlEditor.vue`

Datei hatte laut Teilbefund 1 **keine einzige** `dark:`-Klasse.
Vollständige Abdeckung ergänzt, angelehnt an das Dialog-Referenzmuster
(`PricingModal.vue`: `bg-white dark:bg-gray-800`, `border-gray-200
dark:border-gray-700`) sowie das Toolbar-/Formular-Muster aus
`DefaultLayout.vue` (aktive/inaktive Button-Zustände analog zu Zeile 37
`bg-primary-100 dark:bg-gray-700 text-primary-700 dark:text-primary-400`):

- Wrapper (Zeile 2): `border-gray-300` → `border-gray-300
  dark:border-gray-600` (Rahmenfarbe, konsistent zu `.input`-Utility);
  zusätzlich explizit `bg-white dark:bg-gray-800` ergänzt, damit die
  Editor-Fläche unabhängig vom umgebenden Kontext (Modal-Panel vs.
  Seiten-Container) korrekt dunkel gefüllt ist statt sich implizit auf
  einen transparenten Hintergrund zu verlassen.
- Toolbar (Zeile 4): `bg-gray-50 border-gray-300` →
  `bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600`.
- Alle fünf Formatierungs-Buttons (Bold/Italic/H2/Bullet/Ordered,
  Zeilen 9, 21, 33, 45, 57): Hover-Zustand `hover:bg-gray-200` →
  `hover:bg-gray-200 dark:hover:bg-gray-600`; aktiver Zustand
  `bg-gray-200 text-primary-700` → `bg-gray-200 dark:bg-gray-600
  text-primary-700 dark:text-primary-400`; inaktiver Zustand
  `text-gray-700` → `text-gray-700 dark:text-gray-300`.
- `EditorContent` (Zeile 69, vormals Zeile 67-70): explizite Textfarbe
  `text-gray-900 dark:text-gray-100` ergänzt (Formular-Fließtext-Muster
  aus `design.md` D1), statt sich auf die implizite Vererbung der
  globalen `body`-Farbe (`main.css:7`) zu verlassen.

**Hinweis zu "Platzhaltertext" (`tasks.md` Zeile 62):** Die Datei
enthält keine Tiptap-`Placeholder`-Extension und keine
`::before`/`data-placeholder`-CSS-Regel im `<style scoped>`-Block
(geprüft: kein Treffer für `[Pp]laceholder` in der Datei vor dieser
Änderung). Es gibt daher keinen separaten Platzhaltertext-Zustand zum
Stylen — nur den regulären Editor-Inhalt (siehe `EditorContent`-Fix
oben). Eine neue Placeholder-Extension einzuführen wäre eine
Feature-Erweiterung, nicht Teil des reinen `dark:`-Klassen-Scopes dieser
Task und daher nicht umgesetzt (Akzeptanzkriterium bezieht sich auf
Lesbarkeit in `CourseFormModal.vue`/`AnnouncementsView.vue`, was durch
den `EditorContent`-Fix erfüllt ist).

Verwendungsstellen (laut `design.md`/`verification.md` bestätigt via
`grep -rl "HtmlEditor" src`): `CourseFormModal.vue`,
`views/AnnouncementsView.vue`.

---

## 4. `frontend/src/views/NotFoundView.vue`

Datei hatte laut Teilbefund 1 **keine einzige** `dark:`-Klasse.
Seiten-Hintergrund-Muster (`design.md` D1, `PublicLayout.vue:2`)
angewendet, bestehende Light-Mode-Klassen unverändert gelassen:

- Wrapper (Zeile 2): `bg-gray-100` → `bg-gray-100 dark:bg-gray-900`.
- "404"-Überschrift (Zeile 4): `text-gray-300` →
  `text-gray-300 dark:text-gray-700`. Kein bestehendes Codebase-Muster
  für dieses dekorative, sehr helle Grau gefunden (`grep -rn
  "text-gray-300 dark:"` liefert 0 Treffer vor dieser Änderung); Wahl
  von `dark:text-gray-700` erhält den im Light-Mode beabsichtigten
  "Wasserzeichen"-Effekt (Text nur leicht vom Hintergrund abgesetzt)
  auch im Dark-Mode.
- Zwischenüberschrift (Zeile 5): `text-gray-900` →
  `text-gray-900 dark:text-white` (Referenzmuster D1, Überschrift).
- Fließtext (Zeile 8): `text-gray-600` → `text-gray-600
  dark:text-gray-400` — etabliertes Muster, projektweit mehrfach belegt
  (`grep -rn "text-gray-600 dark:text-gray-400"` liefert u. a.
  `ProfileView.vue:6`, `DashboardView.vue:8`, `SettingsView.vue:5`).
- "Zurück zur Startseite"-Link nutzt bereits die globalen `btn
  btn-primary`-Utility-Klassen (`main.css:16-18`), die bereits
  vollständige `dark:`-Abdeckung mitbringen — keine Änderung nötig.

Route ist die generische Catch-All-Route
(`frontend/src/router/index.ts:147-151`). Laut `verification.md`
(Widerlegt-Abschnitt) ist die Ansicht wegen des Navigation-Guards
(`router/index.ts:163-180`, `requiresAuth !== false` greift, da
`meta.requiresAuth` bei dieser Route `undefined` ist) nur für
angemeldete Nutzer erreichbar — bestätigt Post-Login-Charakter der
Task, ändert aber nichts am reinen Klassen-Scope dieser Änderung.

---

## Verifikation

Für jede der vier Dateien wurde per gezieltem Grep geprüft, dass jede
sichtbare `bg-*`/`text-*`/`border-*`-Utility aus der Familie
`gray|slate|zinc|neutral|white` ein `dark:`-Pendant besitzt (keine
"nackten" Light-Mode-Farben mehr):

```
frontend/src/components/SearchInput.vue   -> alle Treffer gepaart
frontend/src/components/HtmlEditor.vue    -> alle Treffer gepaart
frontend/src/views/NotFoundView.vue       -> alle Treffer gepaart
```

Ein echter manueller Browser-Test mit aktivem Dark-Mode-Toggle (wie in
den Akzeptanzkriterien von `tasks.md` beschrieben) stand mir als
dev-Agent nicht zur Verfügung (kein Browser-Tool); die Code-Verifikation
oben (Klassen-Vollständigkeit gegen die Referenzmuster D1/D2) ist die
Grundlage für das Abhaken der Akzeptanzkriterien in `tasks.md`. Eine
zusätzliche visuelle/manuelle Bestätigung im Browser wird im
Workflow-Schritt 9 (Reviewer/Tester) empfohlen.

---

## Lokale Checks

```bash
cd frontend
npm run test    # 17 Testdateien, 191 Tests — alle grün, keine Regression
npm run build   # vue-tsc -b + vite build — keine neuen Fehler/Warnungen
```

`npm run lint` existiert laut `frontend/package.json` (Zeilen 6-16)
nicht als Skript (bestätigt bereits in `verification.md`, "Testinfra-
struktur"-Abschnitt) — daher nicht ausgeführt.

Ergebnis `npm run test -- --run`:
```
 Test Files  17 passed (17)
      Tests  191 passed (191)
```

Ergebnis `npm run build`: erfolgreich, `vue-tsc -b` meldet keine
Typfehler, `vite build` erzeugt alle Chunks ohne Fehler oder Warnungen
(u. a. `dist/assets/HtmlEditor-*.js`, `dist/assets/DefaultLayout-*.js`,
`dist/assets/NotFoundView-*.js` erfolgreich gebaut).

`dist/` ist laut `frontend/.gitignore:11-12` ignoriert, kein Commit
nötig (Build war nur Lauffähigkeits-Check, siehe `CLAUDE.md` Abschnitt
7.1).

---

## Anmerkung zum Arbeitsverzeichnis

Beim Start dieser Task waren im Arbeitsverzeichnis bereits weitere,
nicht zu T01 gehörende Dateien modifiziert (u. a. `DogFormModal.vue`,
`CourseFormModal.vue`, `SettingsView.vue`, diverse Anamnese-/
Rechnungs-/Trainer-Komponenten — vermutlich paralleler Fortschritt an
T02-T06). Diese Dateien wurden von dieser Task **nicht** angefasst;
der Diff dieser Task ist auf die vier in `tasks.md` T01 gelisteten
Dateien beschränkt (per `git diff` verifiziert).

---

## Offene Punkte / Empfehlungen für Reviewer/Tester

- Manuelle Browser-Prüfung mit Dark-Mode-Toggle auf mindestens einer
  `SearchInput`-Verwendungsstelle sowie beiden `HtmlEditor`-
  Verwendungsstellen steht noch aus (siehe Akzeptanzkriterien).
- Die Farbwahl `dark:text-gray-700` für die dekorative "404"-Zahl
  (`NotFoundView.vue:4`) ist eine begründete, aber neue Entscheidung
  ohne bestehendes Codebase-Präzedens — Reviewer kann hier abweichende
  Präferenz (z. B. `dark:text-gray-600`) einbringen, ohne dass es die
  Akzeptanzkriterien ("dark:-Pendant vorhanden, lesbar") verletzt.
