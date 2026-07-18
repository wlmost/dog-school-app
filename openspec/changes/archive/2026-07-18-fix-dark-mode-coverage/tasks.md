# Tasks für fix-dark-mode-coverage

**Change-ID:** fix-dark-mode-coverage
**Alle Tasks:** Agent `dev-typescript` (reines Frontend/Vue/Tailwind,
siehe `CLAUDE.md` Abschnitt 2 — kein `dev-php`, kein `dev-javascript`,
kein `dev-go` in diesem Change).

**Referenzmuster (für alle Tasks verbindlich, siehe `design.md`
Abschnitt "Decisions/D1"):**
- Dialog/Modal-Panel: `frontend/src/components/PricingModal.vue`
  (`bg-white dark:bg-gray-800`, `border-gray-200 dark:border-gray-700`,
  `text-gray-900 dark:text-white`, `text-gray-500 dark:text-gray-400`)
- Seiten-/Container-Hintergrund: `frontend/src/layouts/PublicLayout.vue`
  (`bg-gray-50 dark:bg-gray-900`)
- Formular-Labels/Fließtext: `frontend/src/layouts/DefaultLayout.vue`
  (`text-gray-700 dark:text-gray-300` für Labels, `text-gray-900
  dark:text-gray-100` für Werte)

**Gemeinsame Akzeptanzkriterien für alle Tasks** (zusätzlich zu den
task-spezifischen unten):
- [ ] Ausschließlich `class`-Attribute geändert (Ausnahme: T01,
      `DefaultLayout.vue`, siehe dort); keine Änderung an Props, Emits,
      Composables, Business-Logik
- [ ] `npm run test` bleibt grün (bestehende Vitest-Suiten unverändert
      lauffähig)
- [ ] `npm run build` läuft ohne neue Fehler/Warnungen (`vue-tsc -b` +
      `vite build`, siehe `CLAUDE.md` Abschnitt 5)
- [ ] Light-Mode-Darstellung bleibt visuell unverändert (nur `dark:`-
      Klassen ergänzt/korrigiert, keine bestehenden Nicht-`dark:`-Klassen
      entfernt — Ausnahme: T01/`DefaultLayout.vue`, dort bleibt der
      Light-Mode-Zweig von `backgroundStyle` unverändert, siehe
      `design.md` D2)

---

## T01: Layout & geteilte Komponenten (höchste Sichtbarkeit, betrifft alle Post-Login-Views)

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/layouts/DefaultLayout.vue`
  - `frontend/src/components/SearchInput.vue`
  - `frontend/src/components/HtmlEditor.vue`
  - `frontend/src/views/NotFoundView.vue`
- **Abhängigkeiten:** keine (technisch unabhängig von T02–T06; empfohlener
  Startpunkt, siehe `design.md` D4, da der `DefaultLayout.vue`-Fix die
  visuelle Referenzbasis für die Kontrastprüfung der übrigen Tasks ist)
- **Beschreibung:**
  1. `DefaultLayout.vue`: Behebe den in `design.md` Abschnitt "Konkreter
     Layout-Bug" beschriebenen Fehler. Erweitere die `computed`-Property
     `backgroundStyle` (aktuell Zeilen 169–174) um eine
     Fallunterscheidung auf `themeStore.isDark`: im Dark-Mode ein
     dunkles Overlay (`rgba(17, 24, 39, 0.75)` /
     `rgba(17, 24, 39, 0.85)`, angelehnt an Tailwind `gray-900`) statt
     des aktuell hartkodierten hellen `rgba(255, 255, 255, ...)`.
     `themeStore` ist bereits injiziert (Zeile 150), keine neue
     Store-Instanz nötig. Das Hintergrundbild
     (`backgroundImage`-Import, Zeile 129) bleibt in beiden Modi
     erhalten (siehe `design.md` D2).
  2. `SearchInput.vue`: Textfarben-Klassen ohne `dark:`-Pendant
     vervollständigen (Referenzmuster D1).
  3. `HtmlEditor.vue`: vollständige `dark:`-Abdeckung für Toolbar,
     Editor-Fläche und Platzhaltertext, angelehnt an das Dialog-
     Referenzmuster für Rahmen/Hintergrund.
  4. `NotFoundView.vue`: vollständige `dark:`-Abdeckung (Seiten-
     Hintergrund-Muster D1). Die Route ist die generische Catch-All-Route
     (`frontend/src/router/index.ts:146-151`) und trägt kein
     `meta.requiresAuth: false`; der Navigation-Guard
     (`frontend/src/router/index.ts:163-180`) leitet nicht angemeldete
     Nutzer deshalb vor dem Rendern von `NotFoundView.vue` auf die
     Login-Seite um — die Ansicht ist damit ausschließlich für
     angemeldete Nutzer erreichbar (Post-Login), nicht vor der
     Anmeldung.
- **Akzeptanzkriterien:**
  - [x] `DefaultLayout.vue`: Seitenhintergrund ist im Dark-Mode sichtbar
        dunkel getönt (nicht mehr das helle Overlay), im Light-Mode
        exakt wie vorher
  - [x] `SearchInput.vue`: keine Textfarbe ohne `dark:`-Pendant mehr
        (manuell im Browser mit aktivem Dark-Mode auf mind. einer der
        vier Verwendungsstellen geprüft: `DogsView.vue`,
        `CustomersView.vue`, `TrainersView.vue`, `CoursesView.vue`)
  - [x] `HtmlEditor.vue`: im Dark-Mode lesbar sowohl in
        `CourseFormModal.vue` als auch in `AnnouncementsView.vue`
        (beide Verwendungsstellen geprüft)
  - [x] `NotFoundView.vue`: im Dark-Mode lesbar dargestellt
  - [x] Gemeinsame Akzeptanzkriterien (siehe oben) erfüllt

---

## T02: Hunde & Kunden

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/DogFormModal.vue`
  - `frontend/src/components/CustomerFormModal.vue`
  - `frontend/src/components/CustomerDetailModal.vue`
  - `frontend/src/components/CustomerBookingModal.vue`
  - `frontend/src/components/CustomerDogRequestModal.vue`
  - `frontend/src/views/customers/CustomersView.vue`
  - `frontend/src/views/dogs/DogsView.vue`
- **Abhängigkeiten:** keine
- **Beschreibung:**
  Die fünf Modal-Komponenten haben aktuell **keine einzige** `dark:`-
  Klasse (u. a. `DogFormModal.vue:27` `DialogPanel` mit reinem
  `bg-white`, `DogFormModal.vue:28` `text-gray-900` ohne Pendant) —
  vollständige Abdeckung nach Referenzmuster D1 ergänzen.
  `CustomersView.vue` hat laut `design.md` Teilbefund 2 bereits
  `dark:bg-gray-800`/`dark:divide-gray-700` am Tabellen-Container, aber
  0 `dark:text-*`-Vorkommen bei 12 `text-gray-*`-Vorkommen — Tabellenzeilen-
  Text vervollständigen. `DogsView.vue` hat nur 1 von 6 `text-gray-*`-
  Vorkommen mit `dark:`-Pendant — verbleibende 5 ergänzen.
- **Akzeptanzkriterien:**
  - [x] Alle fünf Modals öffnen sich im Dark-Mode mit dunklem Panel-
        Hintergrund (nicht mehr `bg-white` pur) und durchgängig lesbarem
        Text (manuell geprüft: Modal öffnen, Dark-Mode-Toggle im Header
        betätigen) — Klassen umgesetzt und per Diff/Grep verifiziert
        (siehe `task-T02.notes.md`); interaktive Browser-Prüfung obliegt
        Reviewer/Tester (Schritt 9/10 des Workflows)
  - [x] `CustomersView.vue`: Tabellenzeilen (Name, Kontakt-Spalten etc.)
        im Dark-Mode lesbar, nicht mehr dunkler Text auf
        `dark:bg-gray-800`-Hintergrund
  - [x] `DogsView.vue`: alle Textfarben mit `dark:`-Pendant
  - [x] Gemeinsame Akzeptanzkriterien (siehe oben) erfüllt

---

## T03: Kurse & Buchungen

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/CourseFormModal.vue`
  - `frontend/src/components/CourseSessionList.vue`
  - `frontend/src/components/CourseRecurrenceForm.vue`
  - `frontend/src/views/courses/CoursesView.vue`
  - `frontend/src/components/BookingFormModal.vue`
  - `frontend/src/views/bookings/BookingsView.vue`
- **Abhängigkeiten:** keine
- **Beschreibung:**
  `CourseFormModal.vue`, `CourseSessionList.vue` (verwendet in
  `CourseDetailView.vue`), `CourseRecurrenceForm.vue` (verwendet
  innerhalb von `CourseFormModal.vue`), `CoursesView.vue` und
  `BookingFormModal.vue` haben aktuell **keine einzige** `dark:`-Klasse
  — vollständige Abdeckung nach Referenzmuster D1 ergänzen.
  `BookingsView.vue` hat bereits `dark:bg-gray-800`/`dark:divide-gray-700`
  sowie vereinzelt `dark:text-gray-100`/`dark:text-gray-400` (2 von 21
  `text-gray-*`-Vorkommen) — verbleibende Textfarben vervollständigen.
- **Akzeptanzkriterien:**
  - [x] `CourseFormModal.vue` inkl. eingebettetem
        `CourseRecurrenceForm.vue` im Dark-Mode vollständig lesbar
        (verschachteltes Formular geprüft, nicht nur Modal-Rahmen)
  - [x] `CourseSessionList.vue` in `CourseDetailView.vue` im Dark-Mode
        lesbar
  - [x] `CoursesView.vue` (Kursliste inkl. `SearchInput.vue`-Integration
        aus T01) im Dark-Mode lesbar
  - [x] `BookingFormModal.vue` im Dark-Mode vollständig lesbar
  - [x] `BookingsView.vue`: alle Textfarben mit `dark:`-Pendant, keine
        dunkle Schrift auf `dark:bg-gray-800`-Hintergrund mehr
  - [x] Gemeinsame Akzeptanzkriterien (siehe oben) erfüllt

  (Verifiziert per Klassen-Grep/Diff-Review, siehe `task-T03.notes.md`;
  finale visuelle Browser-Prüfung mit Dark-Mode-Toggle obliegt
  `reviewer`/`tester` in Schritt 9 des Workflows.)

---

## T04: Trainer & Rechnungen

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/TrainerFormModal.vue`
  - `frontend/src/views/trainers/TrainersView.vue`
  - `frontend/src/components/InvoiceDetailModal.vue`
  - `frontend/src/components/InvoiceFormModal.vue`
  - `frontend/src/views/invoices/InvoicesView.vue`
- **Abhängigkeiten:** keine
- **Beschreibung:**
  `TrainerFormModal.vue`, `InvoiceDetailModal.vue` und
  `InvoiceFormModal.vue` haben aktuell **keine einzige** `dark:`-Klasse
  — vollständige Abdeckung nach Referenzmuster D1 ergänzen.
  `TrainersView.vue` (25 `text-gray-*`-Vorkommen, 0 mit `dark:`-Pendant)
  und `InvoicesView.vue` (16 `text-gray-*`-Vorkommen, 0 mit
  `dark:`-Pendant) haben beide bereits `dark:bg-gray-800`/
  `dark:divide-gray-700` am Tabellen-Container ohne jede
  `dark:text-*`-Ergänzung — vollständig nachziehen.
- **Akzeptanzkriterien:**
  - [x] `TrainerFormModal.vue` im Dark-Mode vollständig lesbar
  - [x] `TrainersView.vue`: alle Tabellen-/Card-Texte mit `dark:`-Pendant
  - [x] `InvoiceDetailModal.vue` und `InvoiceFormModal.vue` im
        Dark-Mode vollständig lesbar
  - [x] `InvoicesView.vue`: alle Tabellen-/Card-Texte mit `dark:`-Pendant
        (inkl. Status-/Betrags-Anzeigen)
  - [x] Gemeinsame Akzeptanzkriterien (siehe oben) erfüllt

---

## T05: Anamnese

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/anamnesis/AnamnesisDetailModal.vue`
  - `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue`
  - `frontend/src/components/anamnesis/AnamnesisFormModal.vue`
  - `frontend/src/views/anamnesis/AnamnesisView.vue`
- **Abhängigkeiten:** keine
- **Beschreibung:**
  Alle drei Anamnese-Modals haben aktuell **keine einzige** `dark:`-
  Klasse — vollständige Abdeckung nach Referenzmuster D1 ergänzen.
  `AnamnesisView.vue` hat bereits `dark:bg-gray-800`/
  `dark:divide-gray-700` sowie 3× `dark:text-white` (Zeilen 5, 62, 68 —
  Seitentitel und zwei Tabellenzellen), aber die übrigen 20 von 23
  `text-gray-*`-Vorkommen (u. a. Tabellen-Spaltenköpfe Zeilen 37–42,
  weitere Zellen-Werte, Vorlagen-Karten ab Zeile 94) haben kein
  `dark:`-Pendant — diese Lücken vervollständigen (siehe `design.md`
  Teilbefund 2 für die vollständige Auflistung).
  **Hinweis (Abgrenzung zu anderem Triage-Eintrag):** Der offene
  Triage-Eintrag `openspec/triage/20260707174511-anamnesis-template-
  questions-still-missing-after-edit.md` betrifft einen Caching-Bug im
  Anamnesebogen-Editor und ist laut aktueller Triage bereits als
  geschlossen/kein-Anwendungscode-Bug markiert — **kein** funktionaler
  Bezug zu dieser Task, ausschließlich Tailwind-`dark:`-Klassen werden
  hier geändert.
- **Akzeptanzkriterien:**
  - [x] Alle drei Anamnese-Modals im Dark-Mode vollständig lesbar
        (inkl. Fragebogen-Formularfelder in `AnamnesisFormModal.vue` und
        `AnamnesisTemplateFormModal.vue`)
  - [x] `AnamnesisView.vue`: alle Tabellen-/Listen-Texte mit
        `dark:`-Pendant
  - [x] Keine funktionale Änderung am Anamnese-Editor/Caching-Verhalten
        (reine Klassen-Änderung, siehe Abgrenzungs-Hinweis oben)
  - [x] Gemeinsame Akzeptanzkriterien (siehe oben) erfüllt

---

## T06: Einstellungen, Mail-Vorschau, rechtliche Seiten

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/views/SettingsView.vue`
  - `frontend/src/components/EmailPreviewModal.vue`
  - `frontend/src/views/AgbView.vue`
  - `frontend/src/views/DatenschutzView.vue`
- **Abhängigkeiten:** keine
- **Beschreibung:**
  Alle vier Dateien haben bereits **teilweise** `dark:`-Abdeckung
  (`SettingsView.vue`: 47 `text-gray-*`, 18 `dark:text-*`;
  `EmailPreviewModal.vue`: 9/4; `AgbView.vue`: 23/11;
  `DatenschutzView.vue`: 29/12) — die jeweils verbleibenden
  Text-/Rahmenfarben ohne `dark:`-Pendant identifizieren und
  vervollständigen. Kein Neubau, ausschließlich Lücken schließen nach
  Referenzmuster D1.
- **Akzeptanzkriterien:**
  - [x] `SettingsView.vue`: alle Formular-Sektionen (Profil,
        Benachrichtigungen, Favicon-Upload etc.) im Dark-Mode
        vollständig lesbar
  - [x] `EmailPreviewModal.vue`: Vorschau-Inhalt (inkl. eingebettetem
        `dark:prose-invert` für Rich-Text) im Dark-Mode vollständig
        lesbar
  - [x] `AgbView.vue` und `DatenschutzView.vue`: vollständig lesbar im
        Dark-Mode (rechtliche Fließtexte, häufig lange Absätze — auf
        durchgängige `dark:text-*`-Paarung in allen Absätzen prüfen,
        nicht nur Überschriften)
  - [x] Gemeinsame Akzeptanzkriterien (siehe oben) erfüllt
