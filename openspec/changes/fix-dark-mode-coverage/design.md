# Design: fix-dark-mode-coverage

**Change-ID:** fix-dark-mode-coverage

---

## Context

### Dark-Mode-Mechanismus (unverändert, funktioniert)

- `frontend/tailwind.config.js:3` — `darkMode: 'class'`: Tailwind generiert
  `dark:*`-Utility-Klassen, die nur greifen, wenn ein Vorfahre die Klasse
  `dark` trägt.
- `frontend/src/stores/theme.ts` — Pinia-Store `useThemeStore`: hält
  `isDark` (Ref), setzt/entfernt `dark` auf `document.documentElement`
  (`applyTheme()`, Zeilen 20–27), persistiert in `localStorage`
  (`watch(isDark, ...)`, Zeilen 39–43), initialisiert aus `localStorage`
  oder `prefers-color-scheme` (`initTheme()`, Zeilen 9–18).
- `frontend/src/main.ts:15-16` — `themeStore.initTheme()` wird beim
  App-Start aufgerufen, vor `app.mount('#app')`.
- Toggle-UI existiert in beiden Layouts: `frontend/src/layouts/
  DefaultLayout.vue:86-93` (Post-Login-Header) und `frontend/src/layouts/
  PublicLayout.vue:42-53` (Public-Nav).

Der Mechanismus selbst ist nicht Gegenstand dieses Changes — er
funktioniert nachweislich an den 34 von 54 `.vue`-Dateien, die bereits
`dark:`-Klassen verwenden (z. B. `PublicLayout.vue`, `PricingModal.vue`).
Der Change behebt ausschließlich fehlende/unvollständige `dark:`-Klassen
in den restlichen Komponenten sowie einen konkreten Layout-Bug.

### Bestandsaufnahme Teilbefund 1 — Komponenten ganz ohne `dark:`-Klasse

Reproduziert per `grep -rL "dark:" frontend/src --include="*.vue"` (Stand
2026-07-18), 20 Treffer:

```
src/App.vue                                          -> Out of Scope (kein eigener Style, s. proposal.md)
src/components/HtmlEditor.vue                        -> Gruppe 1 (Shared, in CourseFormModal + AnnouncementsView verwendet)
src/components/DogFormModal.vue                      -> Gruppe 2
src/components/CourseFormModal.vue                   -> Gruppe 3
src/components/TrainerFormModal.vue                  -> Gruppe 4
src/components/CustomerFormModal.vue                 -> Gruppe 2
src/components/InvoiceDetailModal.vue                -> Gruppe 4
src/components/InvoiceFormModal.vue                  -> Gruppe 4
src/components/CustomerBookingModal.vue               -> Gruppe 2
src/components/CustomerDetailModal.vue                -> Gruppe 2
src/components/HelloWorld.vue                         -> Out of Scope (totes Boilerplate, s. proposal.md)
src/components/CourseSessionList.vue                  -> Gruppe 3 (nur in CourseDetailView verwendet)
src/components/BookingFormModal.vue                   -> Gruppe 3
src/components/CourseRecurrenceForm.vue               -> Gruppe 3 (nur in CourseFormModal verwendet)
src/components/CustomerDogRequestModal.vue             -> Gruppe 2
src/components/anamnesis/AnamnesisDetailModal.vue      -> Gruppe 5
src/components/anamnesis/AnamnesisTemplateFormModal.vue -> Gruppe 5
src/components/anamnesis/AnamnesisFormModal.vue         -> Gruppe 5
src/views/NotFoundView.vue                              -> Gruppe 1 (Shared, generische 404-Route)
src/views/courses/CoursesView.vue                       -> Gruppe 3
```

`frontend/src/components/SearchInput.vue` erscheint **nicht** in diesem
Grep-Treffer (es hat ein einzelnes `dark:hover`-Utility auf einem
Icon-Button), enthält aber laut gezielter Prüfung (`grep -oE
'(^|[" ])text-(gray|slate|zinc|neutral)-[0-9]+'`) 2 Textfarben-Klassen
ganz ohne `dark:`-Pendant und wird als vier-fach wiederverwendete
Komponente (`DogsView.vue`, `CustomersView.vue`, `TrainersView.vue`,
`CoursesView.vue`) ebenfalls in Gruppe 1 (Shared) mitgeführt.

### Bestandsaufnahme Teilbefund 2 — inkonsistente `dark:text-*`-Paare

Der einfache `grep -L "dark:"`-Scan erfasst nur Dateien **ganz ohne**
`dark:`-Klasse. Für das vom User gemeldete "Text bleibt dunkel statt
hell nach Anmeldung" wurde zusätzlich pro bereits-`dark:`-fähiger Datei
gezählt, wie viele `text-(gray|slate|zinc|neutral)-<N>`-Vorkommen ganz
ohne begleitendes `dark:text-*`-Vorkommen in derselben Datei bleiben:

```
Datei                                    text-* gesamt   dark:text-* gesamt
views/customers/CustomersView.vue        12               0
views/trainers/TrainersView.vue          25               0
views/invoices/InvoicesView.vue          16               0
views/anamnesis/AnamnesisView.vue        23               3*
views/bookings/BookingsView.vue          21               2
views/dogs/DogsView.vue                   6               1
views/SettingsView.vue                   47              18
components/EmailPreviewModal.vue          9               4
views/AgbView.vue                        23              11
views/DatenschutzView.vue                29              12
```

*`AnamnesisView.vue`: die 3 Treffer sind `dark:text-white` (Zeilen 5,
62, 68) — die Zählmethode (`grep -oE 'dark:text-(gray|slate|zinc|
neutral)-[0-9]+'`) erfasst nur graufamilien-basierte `dark:text-*`-
Klassen und hätte hier fälschlich 0 gemeldet; manuelle Nachprüfung
(`grep -n "dark:text-white" ...`) zeigt die tatsächlich vorhandene
Teilabdeckung.

Stichprobe (`grep -oE 'dark:[a-z0-9-]+' <datei>`) bestätigt das
gemeldete Muster: `CustomersView.vue`, `TrainersView.vue` und
`InvoicesView.vue` haben jeweils genau `dark:bg-gray-800` (Tabellen-/
Card-Container) und `dark:divide-gray-700`, aber **keine einzige**
`dark:text-*`-Klasse — Tabellenzellen-Text bleibt beim hellen
`text-gray-900`/`-600`/`-500`, während der umgebende Container bereits
auf dunklen Hintergrund umschaltet. `AnamnesisView.vue` hat dasselbe
Container-Muster (`dark:bg-gray-800`/`dark:divide-gray-700`) und ist
**teilweise** abgedeckt: 3 von 23 `text-gray-*`-Vorkommen haben bereits
ein `dark:text-white`-Pendant (Zeilen 5, 62, 68 — Seitentitel und zwei
Tabellenzellen), die übrigen 20 (u. a. Tabellen-Spaltenköpfe Zeilen
37–42, weitere Zellen-Werte, Vorlagen-Karten ab Zeile 94) haben kein
`dark:`-Pendant. Das erzeugt in allen vier Views exakt das gemeldete
Symptom "Texte teilweise weiterhin in dunkler statt heller Schrift ...
unlesbar auf dunklem Hintergrund" für die Listen-Ansichten, die nach
der Anmeldung typischerweise zuerst aufgerufen werden.

Diese Tabelle ist eine Heuristik (Zeilenzählung, kein AST-Parser) und
identifiziert **Kandidaten**, keine erschöpfende Liste jeder betroffenen
Zeile — die tatsächliche Korrektur pro Task erfordert Durchsicht der
Template-Sektion, nicht nur der aggregierten Zahl (siehe `tasks.md`
Akzeptanzkriterien: "jede sichtbare Textfarbe hat ein `dark:`-Pendant",
nicht "Zähler stimmt überein").

### Konkreter Layout-Bug: `frontend/src/layouts/DefaultLayout.vue`

Der Root-Wrapper aller eingeloggten Views:

```vue
<!-- Zeile 2 -->
<div class="min-h-screen" :style="backgroundStyle">
```

```ts
// Zeilen 169-174
const backgroundStyle = computed(() => ({
  background: `linear-gradient(rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.8)), url(${backgroundImage})`,
  backgroundSize: 'cover',
  backgroundPosition: 'center',
  backgroundAttachment: 'fixed'
}))
```

`themeStore` ist in dieser Komponente bereits injiziert (Zeile 150,
verwendet für den Toggle-Button Zeilen 86-93), wird aber in
`backgroundStyle` nicht konsultiert. Das Ergebnis ist ein **immer helles**
Overlay (`rgba(255,255,255,...)`) über dem Hintergrundbild — unabhängig
vom `dark`-Klassen-Zustand auf `document.documentElement`, weil
Tailwind-`dark:`-Utility-Klassen ausschließlich über CSS-Klassen wirken
und eine per `:style`-Binding gesetzte Inline-Regel nicht überschreiben
können (Inline-Styles haben in der CSS-Kaskade ohnehin höhere Spezifität
als Klassen). Sidebar, Header und Karten in `DefaultLayout.vue` selbst
sind korrekt mit `dark:bg-gray-800` etc. versehen (Zeilen 14, 72 u. a.)
— nur der Root-Hintergrund nicht.

## Goals / Non-Goals

**Goals:**
- Alle 20 in Teilbefund 1 gelisteten Komponenten (außer den zwei
  begründeten Out-of-Scope-Fällen) erhalten vollständige
  `dark:`-Klassenpaare für Hintergrund, Rahmen und Text, konsistent zu
  den Referenzmustern (`PricingModal.vue`, `PublicLayout.vue`).
- `frontend/src/components/SearchInput.vue` (shared, in 4 Domänen
  verwendet) erhält vollständige `dark:`-Abdeckung.
- Der `backgroundStyle`-Bug in `DefaultLayout.vue` wird behoben: Overlay-
  Farbe reagiert auf `themeStore.isDark`.
- Die in Teilbefund 2 identifizierten Kandidaten-Dateien werden auf
  vollständige `dark:text-*`-Paarung geprüft und korrigiert.
- Keine Verhaltensänderung: ausschließlich `class`-Attribute (und die
  eine `computed`-Erweiterung in `DefaultLayout.vue`) werden geändert.

**Non-Goals:**
- Keine Einführung neuer Farb-Tokens/Design-Tokens — Wiederverwendung der
  bestehenden Tailwind-Grautöne (`gray-50` … `gray-900`) und der
  bestehenden `primary`-Palette aus `tailwind.config.js`.
- Keine Konsolidierung der Modal-Implementierungen (manche nutzen
  Headless-UI `Dialog`/`DialogPanel`, manche einen einfachen `<div>` mit
  `fixed inset-0` — siehe Referenzmuster-Abschnitt). Dieser Change ändert
  nur Klassen, nicht die Struktur/Bibliothek der Modals (YAGNI, größeres
  Refactoring nicht angefordert und nicht nötig, um den Bug zu beheben).
- Keine automatisierten visuellen Regressionstests (siehe
  `proposal.md` Out of Scope).
- `HelloWorld.vue`/`App.vue` bleiben unangetastet (siehe `proposal.md`
  Out of Scope).

## Decisions

### D1 — Referenzmuster statt neuer Konventionen

Es werden **keine neuen** Farb-/Klassen-Konventionen erfunden. Zwei
bereits im Projekt korrekt umgesetzte Muster dienen als verbindliche
Vorlage für alle Tasks:

1. **Dialog/Modal-Panel** (`frontend/src/components/PricingModal.vue:3`):
   ```
   bg-white dark:bg-gray-800 ... 
   border-gray-200 dark:border-gray-700   (Trennlinien/Rahmen)
   text-gray-900 dark:text-white          (Überschriften)
   text-gray-500 dark:text-gray-400       (Sekundärtext/Icons)
   ```
   Für Formulare zusätzlich das Label-/Input-Muster aus bereits
   korrekten Komponenten wie `frontend/src/layouts/DefaultLayout.vue`
   (`text-gray-700 dark:text-gray-300` für Labels,
   `text-gray-900 dark:text-gray-100` für Werte/Fließtext).

2. **Seiten-/Container-Hintergrund**
   (`frontend/src/layouts/PublicLayout.vue:2`):
   ```
   bg-gray-50 dark:bg-gray-900
   ```

Jede Task in `tasks.md` verweist auf diese zwei Muster statt eigene
Farbentscheidungen zu treffen (DRY/KISS — ein Muster, überall
angewendet).

### D2 — `DefaultLayout.vue`-Hintergrund wird theme-reaktiv, nicht entfernt

Statt den Hintergrund auf eine reine Tailwind-Klasse umzustellen (was das
Hintergrundbild `pet-01-1280x664.jpg` entfernen würde), wird
`backgroundStyle` um eine Fallunterscheidung auf `themeStore.isDark`
erweitert: im Dark-Mode ein dunkles Overlay (Tonwert angelehnt an
Tailwind `gray-900` = `rgb(17, 24, 39)`, z. B. `rgba(17, 24, 39, 0.75)` /
`rgba(17, 24, 39, 0.85)`) statt des hellen `rgba(255, 255, 255, ...)`.
Das erhält das bestehende visuelle Design (Hintergrundbild bleibt
sichtbar) und macht es zusätzlich dark-mode-korrekt — kein Bruch
bestehender Optik im Light-Mode (Non-Regression).

### D3 — Task-Schnitt nach fachlicher Domäne statt einer Mega-Task

Sechs Tasks (Layout/Shared, Hunde & Kunden, Kurse & Buchungen, Trainer &
Rechnungen, Anamnese, Einstellungen/Sonstiges) statt einer einzelnen
Aufgabe für alle 24 Dateien. Begründung: kleinere, unabhängig
review- und testbare Einheiten (jede Task hat eigene, in sich
abgeschlossene Akzeptanzkriterien), keine Task blockiert eine andere
(alle Dateien sind fachlich und technisch unabhängig voneinander —
keine gemeinsame Komponente außer den in Gruppe 1 vorab behandelten
Shared-Komponenten).

### D4 — Keine harte Abhängigkeit zwischen den Tasks, aber empfohlene Reihenfolge

Technisch ist keine Task von einer anderen abhängig (reine
CSS-Klassen-Änderungen an unterschiedlichen Dateien). `tasks.md` markiert
trotzdem T01 (Layout/Shared) als empfohlenen Startpunkt, weil dort der
`DefaultLayout.vue`-Bug behoben wird, der die visuelle Prüfung aller
nachfolgenden Tasks beeinflusst (ohne den Fix ist der Seitenhintergrund
in jeder Domänen-Task weiterhin hell getönt, was die manuelle
Kontrastprüfung der übrigen Tasks verfälschen würde).

## Risks / Trade-offs

- **Heuristik-Risiko (Teilbefund 2):** Die Zeilenzählung identifiziert
  Kandidaten, aber garantiert keine 100%ige Vollständigkeit (z. B.
  dynamisch per `:class`-Binding zusammengesetzte Klassen werden vom
  Grep nicht erfasst). Mitigation: Akzeptanzkriterien in `tasks.md`
  verlangen manuelle Durchsicht der jeweiligen Template-Sektion, nicht
  nur automatisierten Klassenabgleich; Reviewer prüft stichprobenartig
  gegen den tatsächlichen Diff.
- **Kein Backend-Risiko, kein DB-Bezug** — Abschnitt 4.2/4.3 der
  `CLAUDE.md` (SQL-Portabilität, Shared-Hosting-Queue/Scheduler) sind
  nicht betroffen; dieser Change berührt ausschließlich `frontend/`.
- **Geringes Regressions-Risiko im Light-Mode:** Da ausschließlich
  `dark:`-Präfix-Klassen ergänzt werden (keine bestehenden
  Nicht-`dark:`-Klassen entfernt oder verändert, außer der
  `backgroundStyle`-Fallunterscheidung in D2, die im Light-Mode-Zweig
  exakt das bisherige Verhalten beibehält), ist die Wahrscheinlichkeit
  einer Light-Mode-Regression gering. Tester prüft dies trotzdem
  explizit (siehe `tasks.md`-Akzeptanzkriterien "Light-Mode weiterhin
  unverändert").
- **Manuelle Kontrastprüfung statt automatisierter Visual-Regression:**
  Ohne Screenshot-Diffing bleibt ein Restrisiko, dass einzelne
  Kontrastfälle (z. B. Hover-/Focus-Zustände) übersehen werden. Bewusst
  in Kauf genommen (siehe `proposal.md` Out of Scope) — Aufwand für
  Visual-Regression-Infrastruktur steht in keinem Verhältnis zu diesem
  Bugfix-Scope (YAGNI); kann als eigenständiges Vorhaben nachgezogen
  werden, falls zukünftig weitere Theming-Bugs auftreten.
