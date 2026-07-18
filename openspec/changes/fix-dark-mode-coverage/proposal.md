# Proposal: fix-dark-mode-coverage

**Change-ID:** fix-dark-mode-coverage
**Typ:** Bugfix (additiv, kein Breaking Change, kein Datenmodell-/API-Bezug)
**Priorität:** mittel
**Datum:** 2026-07-18
**Triage:** `openspec/triage/20260718114744-dark-mode-missing-variants.md`

---

## Why

Der Dark-Mode-Mechanismus selbst ist intakt (`frontend/tailwind.config.js:3`
`darkMode: 'class'`, Toggle/Persistenz in `frontend/src/stores/theme.ts`,
Initialisierung in `frontend/src/main.ts:15-16`), aber ein großer Teil der
Komponenten wendet die dafür nötigen Tailwind-`dark:`-Utility-Klassen gar
nicht oder nur unvollständig an. Nach der Anmeldung sind dadurch Texte
teilweise unlesbar (dunkle Schrift auf dunklem Hintergrund) und mehrere
Formular-Modals bleiben komplett im Hell-Modus-Layout hängen, obwohl der
Rest der Anwendung bereits umgeschaltet hat. Das untergräbt die
Nutzbarkeit des erst kürzlich eingeführten Dark-Mode-Features für exakt die
Bereiche, die nach dem Login am häufigsten verwendet werden (Stammdaten-
Formulare für Hunde, Kunden, Kurse, Buchungen, Trainer, Rechnungen,
Anamnese).

## What Changes

Reines Frontend-Styling (Tailwind `dark:`-Utility-Klassen), keine
Verhaltens-/Logikänderung, kein Backend-Bezug:

- **20 Vue-Komponenten ohne jede `dark:`-Klasse** erhalten konsistente
  Dark-Mode-Unterstützung, orientiert an bereits korrekt umgesetzten
  Referenzkomponenten im selben Projekt (siehe `design.md` Abschnitt
  "Referenzmuster"): `frontend/src/components/PricingModal.vue` (Dialog-
  Panel-Muster: `bg-white dark:bg-gray-800`, `border-gray-200
  dark:border-gray-700`, `text-gray-900 dark:text-white`) und
  `frontend/src/layouts/PublicLayout.vue` (Seiten-Hintergrund-Muster:
  `bg-gray-50 dark:bg-gray-900`).
- **Ein konkreter Layout-Bug in `frontend/src/layouts/DefaultLayout.vue`**
  wird behoben: Der Root-Wrapper aller eingeloggten Views setzt seinen
  Hintergrund ausschließlich über eine berechnete Inline-Style
  (`backgroundStyle`, Zeilen 169–174: hartkodiertes helles
  `rgba(255, 255, 255, ...)`-Overlay über einem Hintergrundbild), die
  **nicht** auf `themeStore.isDark` reagiert. Tailwind-`dark:`-Klassen
  können eine per `:style` gesetzte Inline-Regel nicht überschreiben —
  der Seitenhintergrund bleibt damit unabhängig vom Dark-Mode-Zustand
  immer hell getönt.
- **Systematisch identifizierte inkonsistente `dark:text-*`-Paare** in
  bereits teilweise dark-mode-fähigen Post-Login-Listen-Views werden
  vervollständigt (siehe `design.md` Abschnitt "Bestandsaufnahme
  Teilbefund 2" für die per Stichprobe belegte Auswahl, u. a.
  `CustomersView.vue`, `TrainersView.vue`, `InvoicesView.vue`,
  `AnamnesisView.vue`, `BookingsView.vue`, `DogsView.vue`,
  `SettingsView.vue`, `EmailPreviewModal.vue`, `AgbView.vue`,
  `DatenschutzView.vue`): Tabellen-/Card-Container haben dort bereits
  `dark:bg-gray-800`/`dark:divide-gray-700`, aber der Text darin bleibt
  auf dem hellen Standard-Grauton (`text-gray-900`/`-600`/`-500` ohne
  `dark:`-Pendant) — exakt das vom User gemeldete Symptom "dunkle Schrift
  auf dunklem Hintergrund".

## Capabilities

### New Capabilities

- `dark-mode-theming`: Definiert, dass alle post-Login-Views und
  Formular-Modals der Anwendung im Dark-Mode konsistent lesbar
  dargestellt werden (Hintergrund- und Textfarben immer als
  `<hell>`/`dark:<dunkel>`-Paar), inklusive des theme-reaktiven
  Seitenhintergrunds im Haupt-Layout. Es gab bisher keine dokumentierte
  Capability für das bestehende Dark-Mode-Feature selbst (`darkMode:
  'class'` + `useThemeStore` wurden ohne begleitende Spec eingeführt);
  dieser Change dokumentiert das Soll-Verhalten erstmals.

### Modified Capabilities

*(keine — es existiert keine bestehende dokumentierte Capability für
Theming/Dark-Mode unter `openspec/specs/`; `openspec list --specs`
geprüft, Stand 2026-07-18. Die Änderung ist rein additiv/korrigierend an
bestehendem, bisher unspezifiziertem UI-Verhalten.)*

## Impact

**Betroffene Dateien (siehe `design.md`/`tasks.md` für vollständige
Zuordnung, ausschließlich `frontend/`):**

- Layout/Shared (Gruppe 1): `frontend/src/layouts/DefaultLayout.vue`,
  `frontend/src/components/SearchInput.vue`,
  `frontend/src/components/HtmlEditor.vue`,
  `frontend/src/views/NotFoundView.vue`
- Hunde & Kunden (Gruppe 2): `DogFormModal.vue`, `CustomerFormModal.vue`,
  `CustomerDetailModal.vue`, `CustomerBookingModal.vue`,
  `CustomerDogRequestModal.vue`, `views/customers/CustomersView.vue`,
  `views/dogs/DogsView.vue`
- Kurse & Buchungen (Gruppe 3): `CourseFormModal.vue`,
  `CourseSessionList.vue`, `CourseRecurrenceForm.vue`,
  `views/courses/CoursesView.vue`, `BookingFormModal.vue`,
  `views/bookings/BookingsView.vue`
- Trainer & Rechnungen (Gruppe 4): `TrainerFormModal.vue`,
  `views/trainers/TrainersView.vue`, `InvoiceDetailModal.vue`,
  `InvoiceFormModal.vue`, `views/invoices/InvoicesView.vue`
- Anamnese (Gruppe 5): `components/anamnesis/AnamnesisDetailModal.vue`,
  `components/anamnesis/AnamnesisTemplateFormModal.vue`,
  `components/anamnesis/AnamnesisFormModal.vue`,
  `views/anamnesis/AnamnesisView.vue`
- Einstellungen, Mail-Vorschau, rechtliche Seiten (Gruppe 6):
  `views/SettingsView.vue`, `components/EmailPreviewModal.vue`,
  `views/AgbView.vue`, `views/DatenschutzView.vue`

**Kein Backend-Code betroffen.** Keine neue npm-Abhängigkeit (reine
Tailwind-Utility-Klassen, `darkMode: 'class'` bereits konfiguriert). Keine
Änderung an Komponenten-Props/Emits/Logik — ausschließlich `class`-
Attribute (und in `DefaultLayout.vue` eine `computed`-Erweiterung um
`themeStore.isDark`, kein neuer State).

## Out of Scope

- `frontend/src/components/HelloWorld.vue`: laut Grep (`grep -rln
  "HelloWorld" src`) nirgends außerhalb der eigenen Datei referenziert
  (nur ein Textstring innerhalb der Datei selbst, kein Import) — totes
  Vite-Boilerplate. Dark-Mode-Fix hier wäre verschwendeter Aufwand
  (YAGNI); Entfernung des toten Codes ist ein separates, nicht
  angefordertes Aufräum-Vorhaben und nicht Teil dieses Changes.
- `frontend/src/App.vue`: enthält laut Lesung nur `<RouterView />` und
  `<ToastContainer />`, keine eigenen sichtbaren/gestylten Elemente —
  kein `dark:`-Bedarf, daher trotz Nennung im ursprünglichen Grep-Treffer
  (`grep -L "dark:"`) keine Aufgabe hierfür.
- Automatisierte visuelle Regressionstests (z. B. Playwright-Screenshot-
  Vergleich Light/Dark) werden **nicht** neu aufgebaut — das Projekt hat
  zwar eine Playwright-Suite (`frontend/e2e/`), aber keine
  Theme-spezifischen Tests; ob und in welchem Umfang der Tester
  automatisierte Prüfungen ergänzt, entscheidet der Tester-Agent in
  Schritt 9 des Workflows, nicht der Architekt (KISS/YAGNI — kein
  Vorgriff auf Test-Strategie).
- Keine Einführung eines dritten Farbschemas (z. B. "System"/"Auto" als
  UI-Option über die bereits vorhandene `prefers-color-scheme`-Erkennung
  beim Erststart hinaus) — nicht angefordert.
- Keine Änderung an der Farbpalette/den Tailwind-Grundfarben selbst
  (`frontend/tailwind.config.js`) — bestehende Grautöne/`primary`-Palette
  werden weiterverwendet, nur um fehlende `dark:`-Pendants ergänzt.

## Referenzen

- Triage: `openspec/triage/20260718114744-dark-mode-missing-variants.md`
- Neue Capability `dark-mode-theming` (siehe
  `specs/dark-mode-theming/spec.md`)
- Referenzmuster Dialog/Modal: `frontend/src/components/PricingModal.vue`
- Referenzmuster Seiten-Hintergrund: `frontend/src/layouts/PublicLayout.vue`
- Dark-Mode-Mechanismus: `frontend/tailwind.config.js:3`,
  `frontend/src/stores/theme.ts`, `frontend/src/main.ts:15-16`
