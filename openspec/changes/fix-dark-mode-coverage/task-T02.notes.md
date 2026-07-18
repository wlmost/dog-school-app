# Task T02 Notes: Hunde & Kunden

**Change-ID:** fix-dark-mode-coverage
**Agent:** dev-typescript
**Datum:** 2026-07-18

## Zusammenfassung

Vollständige Tailwind-`dark:`-Abdeckung für die fünf Hunde-/Kunden-Modals
(zuvor 0 `dark:`-Klassen) sowie Vervollständigung der bereits teilweise
`dark:`-fähigen Views `CustomersView.vue` und `DogsView.vue`, gemäß
Referenzmuster D1 (`design.md`: Dialog-Panel-Muster aus
`frontend/src/components/PricingModal.vue`, Formular-Label-Muster aus
`frontend/src/layouts/DefaultLayout.vue:36,53,83`). Ausschließlich
`class`-Attribute (inkl. der beiden Tailwind-Klassenstrings in der
`getBookingStatusClass()`-Funktion in `CustomerDetailModal.vue`, die
1:1 als `:class`-Bindung im Template landen) wurden geändert — keine
Props/Emits/Composables/Business-Logik angefasst. Alle Diffs sind
Line-für-Line-Ersetzungen (Insertions == Deletions je Datei, siehe
`git diff --stat`), keine Strukturänderung.

## Geänderte Dateien

- `frontend/src/components/DogFormModal.vue` (555 Zeilen)
- `frontend/src/components/CustomerFormModal.vue` (543 Zeilen)
- `frontend/src/components/CustomerDetailModal.vue` (152 Zeilen)
- `frontend/src/components/CustomerBookingModal.vue` (465 Zeilen)
- `frontend/src/components/CustomerDogRequestModal.vue` (331 Zeilen)
- `frontend/src/views/customers/CustomersView.vue` (200 Zeilen)
- `frontend/src/views/dogs/DogsView.vue` (225 Zeilen)

## Details pro Datei

### DogFormModal.vue
Vorher 0 `dark:`-Klassen (bestätigt in `verification.md` Zeile 49:
`DogFormModal.vue:27-28` `DialogPanel bg-white` / `text-gray-900` ohne
Pendant), jetzt 28 `dark:`-Vorkommen. Ergänzt: `DialogPanel`
(`bg-white dark:bg-gray-800`), `DialogTitle` (`text-gray-900
dark:text-white`), alle 14 Formular-Labels (`text-gray-700
dark:text-gray-300`), Bild-Platzhalter (dashed border, `bg-gray-100
dark:bg-gray-700`, `border-gray-300 dark:border-gray-600`),
"Bild auswählen"-Button, Hilfetext, "Auswahl aufheben"-Button
(rot, `dark:text-red-400`), Besitzer-Anzeige für Kunden-Rolle
(`bg-gray-50 dark:bg-gray-700`), Trennlinie vor "Additional Info"
(`border-t border-gray-200 dark:border-gray-700`), Checkbox-Rahmen
und Checkbox-Label, Fehlermeldungs-Box (`bg-red-50
dark:bg-red-900/20`, `text-red-800 dark:text-red-200` — Muster
übernommen aus `frontend/src/components/ToastContainer.vue:54` /
`frontend/src/views/auth/LoginView.vue:19-20`), "Abbrechen"-Button.

### CustomerFormModal.vue
Vorher 0 `dark:`-Klassen, jetzt 38 `dark:`-Vorkommen. Analog zu
`DogFormModal.vue`: `DialogPanel`/`DialogTitle`, alle 16
Formular-Labels, Passwort-Generator-Bereich (Passwort-Input,
"Kopieren"-/"Neu"-Buttons inkl. der `:class`-Ternary für
`passwordCopied`), Warnhinweis (`text-amber-600
dark:text-amber-400`), Adress-/Notizen-Trennlinien, eingebettete
Hunde-Liste (Karten-Hintergrund, Name/Rasse-Text, Lösch-Icon-Button),
eingebettetes Mini-Hundeformular (Labels, Abbrechen-Button),
Haupt-Buttons.

### CustomerDetailModal.vue
Vorher 0 `dark:`-Klassen, jetzt 27 `dark:`-Vorkommen. `DialogPanel`,
Titel-Zeile inkl. Schließen-Icon-Button, alle Info-Sektionen
(persönliche Daten, Adresse, Hunde, Buchungen, Notizen: Überschriften
`text-gray-500 dark:text-gray-400`, Werte `text-sm text-gray-600
dark:text-gray-400`), Karten-Hintergründe (`bg-gray-50
dark:bg-gray-700`), Trennlinien, Buchungs-Status-Badge: die Funktion
`getBookingStatusClass()` (Zeilen 133-141) liefert weiterhin nur
Tailwind-Klassenstrings, die per `:class`-Binding im Template
(Zeile 89) angewendet werden — jedes der vier Status-Badges plus der
Default-Fall wurde um das im Projekt bereits etablierte
`bg-X-100 dark:bg-X-900 text-X-800 dark:text-X-200`-Muster ergänzt
(Referenz: `frontend/src/views/DashboardView.vue:163`,
`frontend/src/views/CourseDetailView.vue:190-192`). Keine Änderung an
Funktionssignatur oder Logik, nur die zurückgegebenen Klassenstrings.

### CustomerBookingModal.vue
Vorher 0 `dark:`-Klassen, jetzt 25 `dark:`-Vorkommen. `DialogPanel`/
`DialogTitle`, Lade-/Fehler-/Leer-Zustände, beide "Abbrechen"-Buttons
(CourseRun-Pfad und "keine Termine"-Pfad — 3 identische Vorkommen des
Buttons via `replace_all` konsolidiert), CourseRun-Auswahl-Label,
Enthaltene-Termine-Liste (`<ul>` mit `bg-gray-50 dark:bg-gray-700`,
`border-gray-200 dark:border-gray-700`), Legacy-Session-Pfad
(Einzeltermin-Anzeige und Checkbox-Liste inkl. Checkbox-Rahmen),
Hund-Auswahl-Label und Leerzustand, Notizen-Label inkl.
"(optional)"-Hinweis, finale Buttons.

### CustomerDogRequestModal.vue
Vorher 0 `dark:`-Klassen (Ausnahme: die Datei enthält keinen
`dark:`-Treffer im initialen Grep, bestätigt), jetzt 18
`dark:`-Vorkommen. Erfolgs-Zustand (grüner Titel `text-green-700
dark:text-green-400`, Fließtext), Formular-Zustand (`DialogTitle`,
alle 9 Formular-Labels via `replace_all`, Checkbox-Label, Chip-/
Herkunfts-/Notizen-Felder, Fehlermeldungs-Box analog zu
`DogFormModal.vue`), "Abbrechen"-Button.

### CustomersView.vue
Laut `design.md` Teilbefund 2 bereits `dark:bg-gray-800`/
`dark:divide-gray-700` am `<tbody>`, aber 0 von 12 `text-gray-*`
mit `dark:`-Pendant (bestätigt in `verification.md` Zeile 36).
Ergänzt: `<table>`-Rahmen (`divide-gray-200 dark:divide-gray-700`),
`<thead>` (`bg-gray-50 dark:bg-gray-700` — exakt das bereits in
`frontend/src/views/bookings/BookingsView.vue:27`,
`frontend/src/views/trainers/TrainersView.vue:144` und
`frontend/src/views/invoices/InvoicesView.vue:27` verwendete Muster,
siehe Beleg unten), alle 6 Spaltenköpfe (`text-gray-500
dark:text-gray-400` — ebenfalls 1:1 aus `BookingsView.vue:29-36`
übernommen), Lade-/Leer-Zeilen, Zeilen-Hover (`hover:bg-gray-50
dark:hover:bg-gray-700`), alle Datenzellen (Name/E-Mail/Telefon/Hunde:
`text-gray-900 dark:text-gray-100` bzw. `text-gray-600
dark:text-gray-400` — exakt das Muster aus `BookingsView.vue:54-65`),
Status-Badge (`bg-green-100 text-green-800 dark:bg-green-900
dark:text-green-200`), Aktions-Links (Bearbeiten/Löschen, jeweils
`dark:hover:text-*-400` ergänzt).

**Beleg für Musterübereinstimmung** (Konsistenzprüfung während der
Umsetzung, nicht nur D1-Ableitung): `grep -n "thead" frontend/src
--include="*.vue" -A1 | grep -B1 dark` zeigt, dass `InvoicesView.vue`,
`SettingsView.vue`, `TrainersView.vue` und `BookingsView.vue` (von
anderen T0x-Tasks parallel bearbeitet, siehe `git status` zum
Zeitpunkt dieser Task — mehrere Dateien aus T01/T03/T04/T05 waren
bereits als modifiziert markiert) exakt dasselbe `bg-gray-50
dark:bg-gray-700`-Thead-Muster und `text-gray-500 dark:text-gray-400`-
Spaltenkopf-Muster verwenden; ursprünglich hatte ich versehentlich
`dark:text-gray-300` für die Spaltenköpfe gewählt und dies nach dem
Abgleich auf `dark:text-gray-400` korrigiert, um projektweit konsistent
zu bleiben.

### DogsView.vue
Laut `design.md`/`verification.md` 6 `text-gray-*`-Vorkommen, davon 1
bereits mit `dark:`-Pendant (Zeile 30, `card text-center py-12
text-gray-500 dark:text-gray-400` — Leerzustand). Die verbleibenden 5
ergänzt: Hunde-Karte Titel (`text-gray-900 dark:text-white`) und Rasse
(`text-gray-600 dark:text-gray-400`), die drei `flex items-center
text-sm text-gray-600`-Zeilen (Besitzer, Geburtsdatum, Chipnummer) via
`replace_all` auf `dark:text-gray-400` ergänzt. Zusätzlich (über die
im `design.md`-Heuristik-Zähler nicht erfasste, aber laut
Akzeptanzkriterium "alle Textfarben mit `dark:`-Pendant" verlangte)
Lücken geschlossen: Bild-Rahmen (`border-gray-200
dark:border-gray-700`), Trennlinie vor den Aktions-Buttons
(`border-t border-gray-200 dark:border-gray-700`), beide
"Löschen"-Buttons (`bg-red-100 dark:bg-red-900/30 hover:bg-red-200
dark:hover:bg-red-900/50 text-red-700 dark:text-red-300` — Muster
identisch zu `frontend/src/components/CourseSessionList.vue:85` und
`frontend/src/views/courses/CoursesView.vue:94`, beide zum
Bearbeitungszeitpunkt bereits von einer anderen Task modifiziert und
als Referenz herangezogen).

## Abweichungen von der wörtlichen Task-Beschreibung

Die Task-Beschreibung in `tasks.md` nennt für `CustomersView.vue` und
`DogsView.vue` explizit nur die `text-gray-*`-Lücken (Heuristik aus
`design.md` Teilbefund 2). Das jeweilige Akzeptanzkriterium ist jedoch
breiter formuliert ("alle Textfarben mit `dark:`-Pendant" /
"Tabellenzeilen... im Dark-Mode lesbar"). Ich habe deshalb zusätzlich
angrenzende, im selben Blick sichtbare Nicht-Grau-Farben (rote
Lösch-Buttons, Status-Badge, Rahmenfarben) mitgezogen, sofern dafür
bereits ein eindeutiges Projekt-Muster existierte (siehe Belege oben).
Kein Element wurde neu erfunden; jede Ergänzung ist 1:1 von einer
bereits im Repository vorhandenen `dark:`-Stelle übernommen. Dies
bleibt innerhalb der "Gemeinsamen Akzeptanzkriterien" (nur
`class`-Attribute geändert, keine Verhaltensänderung).

## Nicht angefasst / bewusst ausgelassen

- `.input`, `.btn`, `.card` (definiert in
  `frontend/src/assets/main.css:12-34`) sind bereits vollständig
  `dark:`-fähig (`@apply ... dark:bg-gray-800 dark:text-gray-100`
  etc.) — alle `<input>`/`<select>`/`<textarea class="input">` und
  alle unveränderten `class="btn btn-primary"`/`class="card"`-
  Elemente in den sieben Dateien benötigten daher keine Änderung.
- Reine `text-base`/`font-medium`-Elemente ohne explizite Graufarbe in
  `CustomerDetailModal.vue` (z. B. `<p class="text-base
  font-medium">`) erben die Textfarbe von `body` (`frontend/src/assets/
  main.css:7`: `text-gray-900 dark:text-gray-100`) und sind bereits
  dark-mode-korrekt, ohne dass eine explizite Klasse nötig wäre.
- Native `<input type="checkbox">`-Steuerelemente: Rahmenfarbe wurde
  aus Konsistenzgründen auf `dark:border-gray-*` ergänzt, das native
  Checkbox-Rendering selbst (Häkchen-Farbe) ist browserabhängig und
  nicht Gegenstand dieses Changes (kein bekanntes Lesbarkeitsproblem,
  kein Referenzmuster im Projekt für Checkbox-Theming vorhanden).

## Lokale Checks

```
cd frontend
npm run test -- --run
```
Ergebnis: **17 Testdateien, 191 Tests — alle grün** (inkl.
`src/components/DogFormModal.test.ts` und
`src/components/CustomerBookingModal.test.ts`, die beiden einzigen
existierenden Testdateien für die sieben geänderten Komponenten;
`CustomerFormModal.vue`, `CustomerDetailModal.vue`,
`CustomerDogRequestModal.vue`, `CustomersView.vue`, `DogsView.vue`
haben keine eigenen Vitest-Dateien).

```
npm run build
```
Ergebnis: `vue-tsc -b && vite build` — **0 TypeScript-Fehler, 0
Build-Warnungen**, Build erfolgreich in 1.32s (Ausgabe u. a.
`dist/assets/CustomersView-*.js`, `dist/assets/DogsView-*.js`,
`dist/assets/CustomerBookingModal.vue_vue_type_script_setup_true_lang-*.js`
vorhanden — die geänderten Komponenten werden korrekt kompiliert).
`dist/` ist über `frontend/.gitignore` ausgeschlossen, kein Commit
nötig (reiner Lauffähigkeits-Check gemäß `CLAUDE.md` Abschnitt 7.1).

## Verifikations-Bezug

`verification.md` bestätigt für T02 relevante Behauptungen: Zeile 49
(`DogFormModal.vue:27-28` reines `bg-white`/`text-gray-900`), Zeile 36
(`CustomersView.vue` 12/0 `text-gray-*`/`dark:text-*`), Zeile 41
(`DogsView.vue` 6/1). Keine der drei in `verification.md` unter
"Widerlegt" gelisteten Ungenauigkeiten betrifft T02-Dateien.

## Diff-Umfang

`git diff --stat` für die sieben Dateien: 164 Insertions / 164
Deletions über 7 Dateien — durchgehend Line-für-Line-Ersetzungen
innerhalb bestehender `class`-Attribute, keine hinzugefügten/
entfernten Zeilen (Ausnahme keine, da `+`/`-`-Zahlen exakt
übereinstimmen), bestätigt die Einhaltung der Akzeptanzkriterien
"nur `class`-Attribute geändert" und "Light-Mode-Darstellung bleibt
visuell unverändert" (keine bestehende Nicht-`dark:`-Klasse entfernt).
