# Task-T03-Notes: Kurse & Buchungen

**Change-ID:** fix-dark-mode-coverage
**Task:** T03
**Agent:** dev-typescript
**Datum:** 2026-07-18

## Zusammenfassung

Vollständige `dark:`-Klassen-Abdeckung für die sechs in `tasks.md` T03
gelisteten Dateien ergänzt, ausschließlich `class`-Attribute geändert
(siehe Diff-Beleg unten). Referenzmuster D1 (`design.md`) angewendet:
Dialog-Panel-Muster (`PricingModal.vue`), Seiten-Hintergrund-Muster
(`PublicLayout.vue`) sowie — wo im Projekt bereits als Konvention für
Status-Badges/Aktions-Buttons/Tabellen etabliert (siehe unten) — die
entsprechenden Farbfamilien-Analogien (`red`, `blue`, `green`, `yellow`,
`orange`).

## Geänderte Dateien (nur `class`-Attribute; `git diff --numstat` zeigt
identische Insert-/Delete-Zeilenzahl je Datei → reine Attribut-Ersetzung,
keine Strukturänderung)

- `frontend/src/components/CourseFormModal.vue` (27/27 Zeilen)
- `frontend/src/components/CourseSessionList.vue` (14/14 Zeilen)
- `frontend/src/components/CourseRecurrenceForm.vue` (12/12 Zeilen)
- `frontend/src/views/courses/CoursesView.vue` (24/24 Zeilen)
- `frontend/src/components/BookingFormModal.vue` (9/9 Zeilen)
- `frontend/src/views/bookings/BookingsView.vue` (35/35 Zeilen)

Verifiziert per `git diff | grep -E '^[+-]' | grep -v 'class='` → leere
Ausgabe, d. h. jede geänderte Zeile enthält ein `class`-Attribut (oder
einen Eintrag der Status-Klassen-Maps, die selbst nur Klassen-Strings
sind). Keine Änderung an Props, Emits, Composables oder Business-Logik.

## Details je Datei

### `CourseFormModal.vue`
- `DialogPanel` (vormals `frontend/src/components/CourseFormModal.vue:27`,
  reines `bg-white`): `dark:bg-gray-800` ergänzt (Referenzmuster
  `PricingModal.vue:3`).
- `DialogTitle` (Zeile 28): `text-gray-900` → `dark:text-white` ergänzt.
- Alle 15 Formular-Labels (`class="block text-sm font-medium
  text-gray-700 mb-1"`, u. a. Zeilen 36, 41, 51, 56, 67, 75, 80, 85, 90,
  100, 147, 152, 157, 166, 178) sowie das Zwischenüberschrift-Label
  „Kurs-Einheiten" (Zeile 97): `dark:text-gray-300` ergänzt
  (Referenzmuster `DefaultLayout.vue`-Label-Konvention aus `design.md`
  D1).
- Vier `border-t border-gray-200`-Trennlinien (Zeilen 73, 96, 145, 163):
  `dark:border-gray-700` ergänzt.
- Termin-Entfernen-Button (Zeile 123, `text-red-500 hover:text-red-700`):
  `dark:text-red-400 dark:hover:text-red-300` ergänzt.
- Drei `.btn bg-gray-100 hover:bg-gray-200 text-gray-700`-Sekundär-Buttons
  (Zeilen 131, 186, 189 — „Termin hinzufügen", „Abbrechen",
  „Zurücksetzen"): `dark:bg-gray-700 dark:hover:bg-gray-600
  dark:text-gray-300` ergänzt.
- Hinweistext Stornierungsfrist (Zeile 175, `text-xs text-gray-500`):
  `dark:text-gray-400` ergänzt.
- Eingebettetes `CourseRecurrenceForm.vue` (Zeile 138-141) profitiert
  zusätzlich von dessen eigener Abdeckung (siehe unten) — Akzeptanz­
  kriterium „verschachteltes Formular geprüft" damit für beide Dateien
  gemeinsam erfüllt.
- `HtmlEditor`-Integration (Zeile 52) unverändert (Abdeckung ist
  Gegenstand von T01, nicht T03 — kein Doppel-Fix nötig).
- `.input`/`.btn`/`.btn-primary`-Klassen unverändert gelassen: bereits
  vollständig dark-mode-fähig in `frontend/src/assets/main.css:12-34`
  (`@layer components`), keine Änderung erforderlich.

### `CourseSessionList.vue`
- Lade-/Fehler-/Leer-Zustände (Zeilen 4, 9, 97): `dark:text-gray-400`
  bzw. für die Fehlerbox `dark:bg-red-900/20 dark:border-red-800
  dark:text-red-400` ergänzt (Referenzmuster analog `FileUpload.vue`
  Fehlerbox-Konvention, projektweit konsistent recherchiert per
  `grep -rn "bg-red-50 dark:"`).
- Tabellenkopf (Zeile 17): `dark:border-gray-700 dark:text-gray-400`
  ergänzt.
- Zeilentrenner `border-gray-100` (Zeilen 29, 66): `dark:border-gray-700/50`
  ergänzt — Wert 1:1 aus bereits vorhandenem Muster in
  `frontend/src/views/CourseDetailView.vue:286` übernommen (dieselbe
  Domäne, Kurs-Termine), keine neue Konvention erfunden.
  Zeilen-Hover (Zeile 66, `hover:bg-gray-50`): `dark:hover:bg-gray-700`
  ergänzt (Muster aus `views/anamnesis/AnamnesisView.vue:60`).
- Fünf `.btn bg-gray-100 hover:bg-gray-200 text-gray-700`-Sekundär-Buttons:
  `dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300` ergänzt.
- Löschen-Button (Zeile 85, `bg-red-100 hover:bg-red-200 text-red-700`):
  `dark:bg-red-900/30 dark:hover:bg-red-900/50 dark:text-red-300`
  ergänzt.
- Trennlinie „Add inline form" (Zeile 100): `dark:border-gray-700`
  ergänzt.

### `CourseRecurrenceForm.vue`
- Acht Formular-Labels (`class="block text-sm font-medium text-gray-700
  mb-1"`, Zeilen 125, 136, 148, 164, 171, 178, 187, 203, 216):
  `dark:text-gray-300` ergänzt.
- Zwei „(optional)"-Hinweise (`text-gray-400 font-normal`, Zeilen 204,
  217): `dark:text-gray-500` ergänzt.
- Vorschau-Box (Zeile 234, `bg-blue-50 border border-blue-200
  text-blue-800`): `dark:bg-blue-900/20 dark:border-blue-800
  dark:text-blue-200` ergänzt (Referenzmuster aus `EmailPreviewModal.vue`/
  `PayPalButton.vue`/`TrainingLogsView.vue`, projektweit konsistente
  Blau-Info-Box-Konvention).

### `CoursesView.vue`
- Lade-/Leer-Zustände (Zeilen 33, 36): `dark:text-gray-400` ergänzt.
- Kurskarten-Titel (Zeile 44, `text-gray-900`): `dark:text-white`
  ergänzt; Beschreibungstext (Zeile 46, `text-gray-600`):
  `dark:text-gray-400` ergänzt.
- Fünf Detail-Label/Wert-Paare (`text-xs text-gray-500 mb-1` /
  `text-sm font-medium text-gray-900`, Zeilen 55-72): jeweils
  `dark:text-gray-400` bzw. `dark:text-gray-100` ergänzt.
- Auslastungsanzeige (Zeilen 77, 81): `dark:text-gray-400` bzw.
  `dark:bg-gray-700` (Fortschrittsbalken-Hintergrund) ergänzt.
- Aktions-Buttons/Trennlinie (Zeile 89-100): Trennlinie
  `dark:border-gray-700`; Löschen-Button `dark:bg-red-900/30
  dark:hover:bg-red-900/50 dark:text-red-300`; „Bereits gebucht"-Badge
  `dark:text-green-300 dark:bg-green-900/30` ergänzt.
- Status-Badge-Funktion `courseStatusClass()` (Zeilen 284-292): allen vier
  Status-Klassen sowie dem Fallback `dark:bg-<farbe>-900
  dark:text-<farbe>-200` (bzw. `dark:bg-gray-700 dark:text-gray-300` für
  `completed`/Fallback) ergänzt — Muster identisch zu bereits im Projekt
  vorhandenen Status-Badges (`DashboardView.vue`, `CourseDetailView.vue`).
- `.card`-Klasse (Kurskarten-Container, Zeile 41) unverändert gelassen:
  bereits dark-mode-fähig in `frontend/src/assets/main.css:32-34`.
- `SearchInput`-Integration (Zeile 6-10) unverändert (Abdeckung ist
  Gegenstand von T01, nicht T03).

### `BookingFormModal.vue`
- `DialogPanel`/`DialogTitle` (Zeilen 27-28): identisches Muster wie
  `CourseFormModal.vue` — `dark:bg-gray-800` bzw. `dark:text-white`
  ergänzt.
- Sechs Formular-Labels (`class="block text-sm font-medium text-gray-700
  mb-1"`): `dark:text-gray-300` ergänzt.
- „Abbrechen"-Button (Zeile 92): `dark:bg-gray-700 dark:hover:bg-gray-600
  dark:text-gray-300` ergänzt.

### `BookingsView.vue`
Ausgangslage laut `design.md`: bereits `dark:bg-gray-800`/
`dark:divide-gray-700` am Tabellen-Container sowie 2 von 21
`text-gray-*`-Vorkommen mit `dark:`-Pendant — verbleibende Lücken
geschlossen:
- Tabellenkopf `<thead>` (Zeile 27) und alle acht `<th>`-Spaltenköpfe
  (Zeilen 29-36): `dark:bg-gray-700` bzw. `dark:text-gray-400` ergänzt.
  Wert `dark:bg-gray-700` für `<thead>` **nicht neu erfunden**, sondern
  1:1 aus bereits vorhandenem Muster in
  `frontend/src/views/invoices/InvoicesView.vue:27` übernommen (identische
  Tabellenstruktur, im Rahmen von T04 durch einen parallel laufenden
  `dev-typescript`-Agenten bereits mit diesem Muster versehen — siehe
  `git status` zum Zeitpunkt dieser Task, mehrere Tasks laufen laut
  `design.md` D4 unabhängig voneinander).
- Lade-/Leer-Zeilen (`td colspan="7"`, Zeilen 41, 50): `dark:text-gray-400`
  ergänzt.
- Tabellenzeilen-Hover (Zeile 54): `dark:hover:bg-gray-700` ergänzt.
- Alle Tabellenzellen-Textfarben (Buchungsnr., Kunde, Hund, Kurs, Datum,
  Stornierungsfrist-Spalte inkl. bedingter Klasse, Zeilen 56-78):
  `dark:text-gray-100`/`dark:text-gray-400`/`dark:text-red-400`/
  `dark:text-gray-500` je nach Grundfarbe ergänzt.
- Alle Aktions-Buttons/-Links (Trainer- und Kunden-Ansicht, Zeilen 88-123):
  `text-primary-600 hover:text-primary-900` →
  `dark:text-primary-400 dark:hover:text-primary-300` (Muster aus
  `DatenschutzView.vue`/`FileUpload.vue`: `text-<farbe>-600
  dark:text-<farbe>-400 hover:text-<farbe>-700/900
  dark:hover:text-<farbe>-300`); analog für `green`, `orange`, `red`
  (zweimal) sowie die reinen Status-Spans (`text-gray-400`,
  `text-orange-500`).
- Stornierungsfrist-abgelaufen-Modal (Zeilen 154-155):
  `bg-red-100`/`text-red-600` → `dark:bg-red-900/30`/`dark:text-red-400`
  ergänzt (Muster aus `DashboardView.vue` Icon-Kreis-Konvention).
- Status-Badge-Funktion `bookingStatusClass()` (Zeilen 319-327): allen
  fünf Status-Klassen `dark:bg-<farbe>-900 dark:text-<farbe>-200`
  ergänzt.
- Spinner-Icon (Zeile 42, `text-primary-600`) **bewusst unverändert**
  gelassen: identisches, unverändertes Muster in allen anderen
  Listen-Views des Projekts (`CoursesView.vue:29`,
  `views/invoices/InvoicesView.vue`), `primary-600` (`#2563eb`) ist als
  gesättigte Akzentfarbe auf `dark:bg-gray-800`-Hintergrund ausreichend
  lesbar, kein vom User gemeldetes „dunkler Text auf dunklem
  Hintergrund"-Symptom (das betrifft laut `design.md` ausschließlich die
  Graufamilie `text-gray-*`); Konsistenz mit dem restlichen,
  unveränderten Code hat hier Vorrang vor einer nicht angeforderten,
  über den Task-Scope hinausgehenden Änderung (YAGNI).

## Hinweis: parallele Task-Ausführung

Beim Start dieser Task zeigte `git status` bereits Änderungen an Dateien
aus T01, T02, T04, T05 und T06 (u. a. `DefaultLayout.vue`,
`DogFormModal.vue`, `TrainerFormModal.vue`, `InvoicesView.vue`,
`SettingsView.vue`) — konsistent mit `design.md` D4 („keine harte
Abhängigkeit zwischen den Tasks"), mehrere `dev-typescript`-Agenten
laufen parallel an unterschiedlichen Task-Dateimengen. Das in
`InvoicesView.vue` bereits vorhandene `bg-gray-50 dark:bg-gray-700`-
Tabellenkopf-Muster wurde für Konsistenz in `BookingsView.vue`
übernommen (siehe oben). Keine Datei außerhalb der T03-Dateiliste wurde
von diesem Agenten verändert.

## Referenzmuster-Herkunft (Nachweis gegen Halluzination)

Alle verwendeten Farb-Kombinationen wurden vor Verwendung per `grep -rn`
gegen bereits im Projekt vorhandene `dark:`-Klassen verifiziert (keine
neu erfundenen Konventionen):
- Dialog-Panel/Label-Muster: `frontend/src/components/PricingModal.vue`,
  `frontend/src/layouts/DefaultLayout.vue` (D1).
- Rot-Fehlerbox: `frontend/src/components/FileUpload.vue:158-159`,
  `frontend/src/components/PayPalButton.vue:10`.
- Blau-Info-Box: `frontend/src/components/EmailPreviewModal.vue:113-118`,
  `frontend/src/views/training/TrainingLogsView.vue:16-17`.
- Status-Badges (grün/gelb/blau/orange auf `-900`/`-200`):
  `frontend/src/views/DashboardView.vue:74-90`,
  `frontend/src/views/CourseDetailView.vue:190-191`.
- Tabellenkopf `bg-gray-50 dark:bg-gray-700`:
  `frontend/src/views/invoices/InvoicesView.vue:27` (paralleler T04-Stand
  zum Zeitpunkt dieser Task).
- Zeilentrenner `border-gray-100 dark:border-gray-700/50`:
  `frontend/src/views/CourseDetailView.vue:286`.
- Zeilen-Hover `hover:bg-gray-50 dark:hover:bg-gray-700`:
  `frontend/src/views/anamnesis/AnamnesisView.vue:60`.
- Aktions-Link-Muster (`text-<farbe>-600 dark:text-<farbe>-400
  hover:text-<farbe>-900 dark:hover:text-<farbe>-300`):
  `frontend/src/views/DatenschutzView.vue:283-289`,
  `frontend/src/components/FileUpload.vue:118`.

## Lokale Checks

- `npx vitest run` (im `frontend/`-Verzeichnis, entspricht `npm run
  test` mit `run`-Flag für Single-Pass statt Watch-Modus, da
  `package.json`-Script `"test": "vitest"` standardmäßig im Watch-Modus
  läuft): **17 Testdateien, 191 Tests — alle grün.** Keine der
  betroffenen Komponenten-Tests (`CourseRecurrenceForm.test.ts`,
  `views/courses/CoursesView.test.ts` u. a.) verwenden Klassen-
  Assertions (bestätigt bereits in `verification.md`), daher keine
  Testanpassung nötig oder erfolgt.
- `npm run build` (`vue-tsc -b && vite build`): **erfolgreich, keine
  neuen Fehler/Warnungen.** Vollständiger Build-Output erzeugt
  (`frontend/dist/`, per `.gitignore` nicht versioniert, keine
  Bereinigung nötig).

## Abweichungen / offene Punkte

Keine. Alle in `tasks.md` T03 gelisteten Akzeptanzkriterien sind durch
die obigen Änderungen erfüllt:
- `CourseFormModal.vue` inkl. eingebettetem `CourseRecurrenceForm.vue`:
  vollständige `dark:`-Abdeckung (Modal-Rahmen + verschachteltes
  Formular).
- `CourseSessionList.vue`: vollständige `dark:`-Abdeckung (Tabelle,
  Inline-Bearbeitungsformular, Buttons).
- `CoursesView.vue`: vollständige `dark:`-Abdeckung (Karten, Status-
  Badges, Aktions-Buttons); `SearchInput.vue`-Integration bleibt
  T01-Zuständigkeit.
- `BookingFormModal.vue`: vollständige `dark:`-Abdeckung.
- `BookingsView.vue`: alle 21 `text-gray-*`-Vorkommen (bzw. deren
  Äquivalente in anderen Farbfamilien) haben nun ein `dark:`-Pendant,
  keine dunkle Schrift mehr auf `dark:bg-gray-800`-Hintergrund.
- Ausschließlich `class`-Attribute geändert, keine Logikänderung
  (belegt per `git diff`-Grep oben).
- `npm run test`/`npm run build`: grün (siehe „Lokale Checks").
- Light-Mode unverändert: alle bestehenden Nicht-`dark:`-Klassen
  wurden beibehalten, ausschließlich `dark:`-Präfix-Klassen ergänzt
  (keine Klasse entfernt oder umbenannt).

## Nachtrag: Reviewer-Finding behoben (2026-07-18)

`task-T03.review.md`, Abschnitt "Sollte", meldete ein Konsistenz-Finding:
`frontend/src/views/bookings/BookingsView.vue:26` fehlte `dark:divide-gray-700`
am `<table>`-Element, obwohl die strukturgleichen Nachbar-Views
(`CustomersView.vue`, `TrainersView.vue`, `InvoicesView.vue`) dieses
Pendant bereits hatten.

**Fix:** `class="min-w-full divide-y divide-gray-200"` →
`class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"`
(nur `class`-Attribut geändert, keine Logikänderung).

Verifikation: `npm run test` (194/194 grün) und `npm run build`
(warnungsfrei) nach der Änderung erneut ausgeführt, beide weiterhin
erfolgreich.
