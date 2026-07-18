# Task T04 Notes: Trainer & Rechnungen

**Agent:** dev-typescript
**Change-ID:** fix-dark-mode-coverage
**Status:** implementiert

## Gelesene Grundlagen

- `openspec/changes/fix-dark-mode-coverage/tasks.md` Abschnitt T04
  (Zeilen 159-186)
- `openspec/changes/fix-dark-mode-coverage/design.md` (Referenzmuster D1,
  Teilbefund 1/2, Decisions D1-D4)
- `openspec/changes/fix-dark-mode-coverage/proposal.md`
- `openspec/changes/fix-dark-mode-coverage/verification.md` (keine
  T04-spezifischen Skeptiker-Befunde; die Teilbefund-2-Zahlen für
  `TrainersView.vue` 25/0 und `InvoicesView.vue` 16/0 wurden bestätigt)
- Referenzdateien: `frontend/src/components/PricingModal.vue` (Dialog-
  Panel-Muster), `frontend/src/layouts/PublicLayout.vue`
  (Seiten-Hintergrund-Muster), `frontend/src/layouts/DefaultLayout.vue`
  (Label-/Werte-Muster)
- `frontend/src/assets/main.css:28-30` (`.input`) und `:12-30` (`.btn`,
  `.card`): globale Utility-Klassen sind bereits dark-mode-fähig, daher
  keine Änderung an `class="input"`/`class="card"`-Verwendungen nötig.

## Geänderte Dateien

1. `frontend/src/components/TrainerFormModal.vue`
2. `frontend/src/components/InvoiceDetailModal.vue`
3. `frontend/src/components/InvoiceFormModal.vue`
4. `frontend/src/views/trainers/TrainersView.vue`
5. `frontend/src/views/invoices/InvoicesView.vue`

Ausschließlich `class`-/Klassen-String-Änderungen (siehe Diff-Beleg
unten). Keine Änderung an Props, Emits, Composables, Template-Logik
(`v-if`/`v-for`/Bindings) oder Script-Funktionen — Ausnahme: die
Farbwert-Strings in den Status-Mapping-Objekten `getStatusClass()`
(`InvoiceDetailModal.vue`) und `invoiceStatusClass()` (`InvoicesView.vue`)
wurden um `dark:`-Klassen ergänzt (reine String-Erweiterung, keine
Struktur-/Logikänderung der Funktion).

## Angewendete Konventionen (aus D1 abgeleitet + bestehender Code-Praxis)

Da D1 nur zwei Kernmuster (Dialog-Panel, Seiten-Hintergrund) sowie das
Label-/Werte-Muster aus `DefaultLayout.vue` vorgibt, aber Status-Badges,
Aktions-Links und Sekundär-Icons nicht explizit abdeckt, wurde vor der
Umsetzung per `grep` verifiziert, welche Konvention im übrigen (bereits
korrekten) Projektcode für diese Fälle verwendet wird, um keine neue
Konvention zu erfinden (DRY/KISS, siehe D1-Prinzip):

- Dialog-Panel: `bg-white dark:bg-gray-800`, Titel `text-gray-900
  dark:text-white`, Sekundärtext `text-gray-500/600 dark:text-gray-400`
  (exakt `PricingModal.vue`)
- Formular-Labels: `text-gray-700 dark:text-gray-300` (exakt
  `DefaultLayout.vue:36`)
- Formular-/Tabellen-Werte: `text-gray-900 dark:text-gray-100` bzw. für
  Tabellenzellen `dark:text-white` (Muster aus `DefaultLayout.vue:53,83`
  bzw. `AnamnesisView.vue:5,62,68`, der einzigen bereits teilweise
  korrekten Schwester-Datei aus Teilbefund 2)
- Trennlinien: `border-gray-200 dark:border-gray-700`,
  `border-gray-300 dark:border-gray-600` (exakt `PricingModal.vue`/
  `PricingItemForm.vue`)
- Sekundär-Hintergründe (`thead`, `tfoot`, Zeilen-Hover):
  `bg-gray-50 dark:bg-gray-700`, `hover:bg-gray-50
  dark:hover:bg-gray-700` (exakt `SettingsView.vue:398,422`,
  `AnamnesisView.vue:60`)
- Status-Badges (`bg-X-100 text-X-800`): `dark:bg-X-900 dark:text-X-200`
  für Blau/Grün/Rot, `dark:bg-gray-700 dark:text-gray-300` für Grau
  (exakt `DashboardView.vue:90-91`, `CourseDetailView.vue:190-192`)
- Aktions-Links (`text-X-600 hover:text-X-900`):
  `dark:text-X-400 dark:hover:text-X-300` (exakt Muster aus
  `FileUpload.vue:46`, `AttachmentList.vue:215/230`)
- Fehlertext (`text-red-600`): `dark:text-red-400` (exakt
  `FileUpload.vue:159`, `SettingsView.vue:417`)
- Sekundär-Buttons (`bg-gray-100 hover:bg-gray-200 text-gray-700`):
  `dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700
  dark:text-gray-200` (analog `.btn-secondary` in `main.css:20-21`)

## Details pro Datei

### `TrainerFormModal.vue`
War laut `design.md` Teilbefund 1 ohne jede `dark:`-Klasse
(`DialogPanel` reines `bg-white`, `DialogTitle` `text-gray-900` ohne
Pendant, siehe `tasks.md:170-172`). Ergänzt: `DialogPanel`-Hintergrund,
`DialogTitle`, alle 11 Formular-Labels (Vorname, Nachname, E-Mail,
Telefon, Passwort ×4, Adresse ×4, Qualifikationen, Spezialisierungen),
alle Hilfetexte (`text-gray-500`), Passwort-Fehlertext (`text-red-600`),
alle `border-t border-gray-200`-Trennlinien, die beiden `h4`-Zwischen-
überschriften ("Passwort ändern", "Adresse") sowie den
"Abbrechen"-Button. Verifiziert per
`grep -n "text-gray-\|border-gray-\|bg-gray-\|bg-white\b" TrainerFormModal.vue | grep -v "dark:"`
→ 0 Treffer nach Änderung.

### `InvoiceDetailModal.vue`
War ebenfalls komplett ohne `dark:`-Klasse (`tasks.md:170-172`).
Ergänzt: `DialogPanel`, `DialogTitle`, Schließen-Icon, alle Label-/
Wert-Paare in "Rechnungsinformationen" und "Kunde", die
Rechnungspositionen-Tabelle (`thead`/`tbody`/`tfoot`, inkl. Trennlinien
und Gesamtbetrags-Zeile), den Zahlungen-Block, den Notizen-Block sowie
alle vier Action-Buttons (Schließen/PDF/Bearbeiten/Als bezahlt
markieren). Zusätzlich `getStatusClass()` (Zeilen 227-236 vor Änderung)
um `dark:`-Pendants für alle fünf Status (`draft`/`sent`/`paid`/
`overdue`/`cancelled`) ergänzt, konsistent zum in `InvoicesView.vue`
verwendeten Status-Mapping (beide Dateien zeigen denselben Rechnungs-
Status). Verifiziert: 0 verbleibende `text-gray-*`/`border-gray-*`/
`bg-gray-*`/`bg-white`-Treffer ohne `dark:`-Pendant.

### `InvoiceFormModal.vue`
War komplett ohne `dark:`-Klasse. Ergänzt: `DialogPanel`, `DialogTitle`,
alle Formular-Labels (Kunde, Rechnungsdatum, Fälligkeitsdatum, Status,
Spalten-Header der Positionstabelle), das readonly-Summenfeld
(`bg-gray-50` → zusätzlich `dark:bg-gray-700`), den
Positionen-Entfernen-Button, alle Trennlinien, den Summenblock
(Zwischensumme/MwSt/Kleinunternehmer-Hinweis/Gesamt) und den
"Abbrechen"-Button. Verifiziert: 0 verbleibende Treffer ohne
`dark:`-Pendant.

### `TrainersView.vue`
Laut `design.md` Teilbefund 2: 25 `text-gray-*`-Vorkommen, 0 mit
`dark:`-Pendant, obwohl Tabellen-Container bereits `dark:bg-gray-800`/
`dark:divide-gray-700` hat (`tasks.md:173-177`). Betroffen waren
**beide** Ansichts-Modi der View (Cards **und** Table, per
`viewMode`-Toggle umschaltbar) — die Task-Beschreibung nennt zwar primär
"Tabellen-/Card-Texte" im Akzeptanzkriterium, daher wurden konsequent
beide Zweige vervollständigt, nicht nur die im Teilbefund-2-Grep
gezählte Tabellenansicht:
- Seitentitel `h1`
- View-Toggle (aktiver/inaktiver Zustand, Icon-Hintergrund)
- Lade-Zustand-Text
- Cards-Ansicht: Avatar-Kreis, Name, "Trainer"-Label, alle Kontakt-
  Icons-Zeilen (E-Mail/Telefon/Stadt/Spezialisierungen), Trennlinie und
  "Aktive Kurse"-Zeile, beide Aktions-Buttons (Bearbeiten/Löschen)
- Table-Ansicht: `thead`, Zeilen-Hover, Avatar-Kreis, alle sechs
  Datenspalten, Kurse-Badge (`bg-green-100 text-green-800` →
  `dark:bg-green-900 dark:text-green-200`), beide Aktions-Links
- Empty-State (Icon, Überschrift, Hilfetext)

Verifiziert per
`grep -n "text-gray-\|border-gray-\|bg-gray-\|bg-white\b" TrainersView.vue | grep -v "dark:"`
→ 0 Treffer nach Änderung (zwei Iterationen nötig, da der
`bg-primary-100`-Avatar-Kreis in der Cards-Ansicht beim ersten Scan
übersehen wurde und in einem zweiten Korrektur-Edit nachgezogen wurde).

### `InvoicesView.vue`
Laut `design.md` Teilbefund 2: 16 `text-gray-*`-Vorkommen, 0 mit
`dark:`-Pendant, gleiches Muster wie `TrainersView.vue`. Ergänzt:
`thead`, Zeilen-Hover, Lade-/Leer-Zustand, alle sechs Tabellenspalten
(Rechnungsnr./Kunde/Datum/Fällig/Betrag/Status), die drei Aktions-Links
(PDF/Bearbeiten/Bezahlt) sowie `invoiceStatusClass()` (Zeilen 252-261 vor
Änderung) um `dark:`-Pendants für alle fünf Status. Verifiziert: 0
verbleibende Treffer ohne `dark:`-Pendant.

## Nicht geänderte, bewusst ausgelassene Stellen

- `class="input"` und `class="card"` in allen fünf Dateien: bereits
  dark-mode-fähig über `@layer components` in
  `frontend/src/assets/main.css:28-34` — keine Änderung nötig
  (DRY, keine doppelte Farblogik einführen).
- Text ohne explizite Grau-Farbklasse (z. B. `<p class="text-base
  font-mono font-medium">` in `InvoiceDetailModal.vue` vor der Änderung)
  erbt die globale Body-Textfarbe `text-gray-900 dark:text-gray-100`
  (`main.css:6-8`) — dort wurde die Farbe dennoch explizit gesetzt, um
  konsistent mit den Nachbar-`<span>`-Elementen zu bleiben und die
  Lesbarkeit unabhängig von künftigen Body-Style-Änderungen
  sicherzustellen.

## Parallel-Beobachtung (keine Auswirkung auf T04)

Während der Bearbeitung zeigte `git status`, dass zahlreiche weitere
Dateien aus T01/T02/T03/T05/T06 (z. B. `BookingsView.vue`,
`DogFormModal.vue`, `CoursesView.vue`, `AnamnesisFormModal.vue` etc.)
ebenfalls im Arbeitsverzeichnis verändert sind — das ist erwartungsgemäß
Parallelarbeit anderer `dev-typescript`-Aufrufe für die übrigen Tasks
desselben Change und wurde nicht angefasst. `git diff --stat` für die
fünf T04-Dateien zeigt exakt 176 Insertions/176 Deletions (reine
Zeilen-Ersetzungen durch längere `class`-Strings, keine neuen/entfernten
Zeilen), und ein gezielter Diff-Grep bestätigt, dass jede geänderte
Zeile entweder ein `class=`-Attribut oder einen Farbwert-String
innerhalb der Status-Mapping-Objekte betrifft.

## Lokale Checks

```bash
cd frontend
npx vitest run
# Test Files  17 passed (17)
# Tests  191 passed (191)

npm run build
# vue-tsc -b && vite build → erfolgreich, keine TS-Fehler, keine
# Build-Warnungen zu den geänderten Dateien
```

Beide Checks liefen nach Abschluss aller T04-Änderungen grün/erfolgreich
(vollständige Ausgabe siehe Tool-Log dieser Session).

## Einschränkung / Hinweis für Reviewer/Tester

Als `dev-typescript`-Agent steht kein Browser-Tool zur Verfügung. Die
Akzeptanzkriterien "im Dark-Mode vollständig lesbar" wurden daher
ausschließlich per **Code-Review** verifiziert: für jede der fünf
Dateien wurde nach der Änderung per `grep` sichergestellt, dass **keine**
`text-gray-*`/`border-gray-*`/`bg-gray-*`/`bg-white`-Klasse mehr ohne
begleitendes `dark:`-Pendant vorkommt, und jede ergänzte `dark:`-Klasse
wurde gegen eine bereits im Projekt korrekt umgesetzte Referenzstelle
abgeglichen (siehe Abschnitt "Angewendete Konventionen" oben). Eine
tatsächliche visuelle Prüfung im Browser (Dark-Mode-Toggle, wie in den
Akzeptanzkriterien beschrieben) sollte laut Workflow im `tester`-Schritt
(Schritt 9) erfolgen.

## Nachtrag: Reviewer-Findings behoben (2026-07-18)

`task-T04.review.md`, Abschnitt "Sollte", meldete zwei Konsistenz-Findings
(keine Blocker):

1. `frontend/src/views/trainers/TrainersView.vue:172,175,178,181`
   (E-Mail/Telefon/Stadt/Spezialisierungen) und
   `frontend/src/views/invoices/InvoicesView.vue:55,67`
   (Rechnungsnummer, Gesamtbetrag) nutzten `dark:text-white` statt des im
   Change durchgängig etablierten D1-Werte-Musters `dark:text-gray-100`
   (vgl. `CustomersView.vue`, `BookingsView.vue`, `CoursesView.vue`).
   **Fix:** alle sechs Stellen von `text-gray-900 dark:text-white` auf
   `text-gray-900 dark:text-gray-100` vereinheitlicht. Nicht angetastet:
   `TrainersView.vue:4` (Seitentitel `<h1>`), `:83` (Modal-Überschrift
   `<h3>`) und `:165` (Trainer-Namensfeld) — diese waren nicht Teil des
   Reviewer-Findings (nur die vier genannten Zeilennummern betreffen das
   Werte-Muster) und bleiben unverändert `dark:text-white`.

2. `frontend/src/components/TrainerFormModal.vue:133` und
   `frontend/src/components/InvoiceFormModal.vue:147` (Sekundär-Button
   "Abbrechen") nutzten `dark:text-gray-200` statt des in fünf anderen
   Dateien desselben Changes etablierten `dark:text-gray-300`
   (vgl. `DogFormModal.vue`, `CustomerFormModal.vue`,
   `AnamnesisFormModal.vue`). **Fix:** beide Stellen von
   `text-gray-700 dark:text-gray-200` auf `text-gray-700 dark:text-gray-300`
   vereinheitlicht.

Ausschließlich `class`-Attribute geändert, keine Logikänderung.

Verifikation: `npm run test` (194/194 grün) und `npm run build`
(warnungsfrei) nach den Änderungen erneut ausgeführt, beide weiterhin
erfolgreich.
