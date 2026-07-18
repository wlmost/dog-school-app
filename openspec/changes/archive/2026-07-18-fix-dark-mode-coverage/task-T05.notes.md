# Task T05 Notes: Anamnese

**Change-ID:** fix-dark-mode-coverage
**Agent:** dev-typescript
**Datum:** 2026-07-18

## Gelesen vor Implementierung

- `openspec/changes/fix-dark-mode-coverage/tasks.md` Abschnitt T05
  (Z.189–223)
- `openspec/changes/fix-dark-mode-coverage/design.md` (Referenzmuster D1,
  D2 optional nicht relevant für T05; Teilbefund 1/2)
- `openspec/changes/fix-dark-mode-coverage/proposal.md`
- `openspec/changes/fix-dark-mode-coverage/verification.md` (keine
  Skeptiker-Befunde, die T05-Scope oder -Dateiliste ändern; die für T05
  relevante Aussage zu `AnamnesisView.vue` — "3× `dark:text-white`
  bereits vorhanden, Rest fehlt" — wurde im Abschnitt "Widerlegt" präzisiert,
  nicht negiert; genau dieser präzisierte Stand wurde als Ausgangspunkt
  verwendet, siehe unten)
- `openspec/changes/fix-dark-mode-coverage/specs/dark-mode-theming/spec.md`
- Referenzdateien: `frontend/src/components/PricingModal.vue`,
  `frontend/src/layouts/PublicLayout.vue` (D1-Referenzmuster, wie in
  `tasks.md` vorgegeben)
- Zusätzlich (nicht in `tasks.md` explizit genannt, aber nötig, um keine
  neuen Konventionen zu erfinden, siehe D1-Prinzip "keine neuen
  Farb-/Klassen-Konventionen"): bereits im Rahmen anderer Tasks dieses
  Changes umgesetzte Dateien wurden als zusätzliche Präzedenzfälle
  herangezogen, da der Feature-Branch beim Start dieser Task bereits
  Teil-Fortschritt aus T01–T04 enthielt (`git status` zeigte u. a.
  `DogFormModal.vue`, `TrainerFormModal.vue`, `DefaultLayout.vue` bereits
  modifiziert):
  - `frontend/src/components/PricingItemForm.vue` (Formular-Muster mit
    rohen `border-gray-300`-Inputs, Indigo-Fokusring, `text-red-500`-
    Pflichtfeld-Sternchen, Cancel-/Submit-Button-Paar — strukturell nahezu
    identisch zu `AnamnesisTemplateFormModal.vue`)
  - `frontend/src/components/DogFormModal.vue`,
    `frontend/src/views/SettingsView.vue`,
    `frontend/src/views/AnnouncementsView.vue`,
    `frontend/src/views/bookings/BookingsView.vue` (bereits vorhandene
    `dark:`-Paare für Fehlerboxen, Status-Badges, Tabellen-Aktionslinks
    in Primary-/Blue-/Green-/Red-/Purple-Farbfamilien, Radio-/Checkbox-
    Ränder)
  - `frontend/src/assets/main.css` — `.input`, `.btn-primary`,
    `.btn-secondary`, `.btn-danger`, `.card` sind bereits vollständig
    dark-mode-fähig (`Z.12–34`); alle Stellen, die diese Utility-Klassen
    verwenden, waren daher bereits korrekt und mussten nicht angefasst
    werden.

## Geänderte Dateien

1. `frontend/src/components/anamnesis/AnamnesisDetailModal.vue`
2. `frontend/src/components/anamnesis/AnamnesisFormModal.vue`
3. `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue`
4. `frontend/src/views/anamnesis/AnamnesisView.vue`

Ausschließlich `class`-Attribute (Template-Ebene) sowie zwei reine
Klassen-String-Literale in `<script setup>` (`statusClass()` in
`AnamnesisView.vue:392-395` — liefert nur Tailwind-Klassenstrings für
`:class`-Bindings, keine Verhaltens-/Logikänderung). Keine Änderung an
Props, Emits, Composables oder API-Aufrufen.

## Was wurde geändert (mit Belegen Datei:Zeile, Stand nach Änderung)

### AnamnesisDetailModal.vue

Vollständige `dark:`-Abdeckung nach D1-Modal-Muster ergänzt:
- Panel-Hintergrund `bg-white` → `+ dark:bg-gray-800` (Zeile 8)
- Header `bg-white`/`border-gray-200` → `+ dark:bg-gray-800
  dark:border-gray-700` (Zeile 10)
- Überschrift `text-gray-900` → `+ dark:text-white` (Zeile 12, 71)
- Schließen-Icon `text-gray-400 hover:text-gray-600` → `+
  dark:text-gray-500 dark:hover:text-gray-300` (Zeile 15)
- Ladehinweis, "Noch keine Antworten" `text-gray-500` → `+
  dark:text-gray-400` (Zeile 30, 73)
- Info-Box `bg-gray-50` → `+ dark:bg-gray-700` (Zeile 35, konsistent mit
  bereits im Projekt etablierter Konvention für verschachtelte Boxen
  innerhalb eines `dark:bg-gray-800`-Panels, siehe z. B.
  `DogFormModal.vue:84`, `EmailTemplateEditor.vue:4`)
- 6× `dt`-Label `text-gray-500` → `+ dark:text-gray-400` (Zeile 38, 42,
  46, 50, 59, 63)
- 6× `dd`-Wert `text-gray-900` → `+ dark:text-gray-100` (Zeile 39, 43, 47,
  60, 64, 81; D1-Wertetext-Muster)
- Status-Badge (`:class`-Binding) `bg-green-100 text-green-800` /
  `bg-yellow-100 text-yellow-800` → `+ dark:bg-green-900
  dark:text-green-200` / `+ dark:bg-yellow-900 dark:text-yellow-200`
  (Zeile 52; Muster bereits etabliert in `DashboardView.vue:90,163`,
  `CourseDetailView.vue:190-192`)
- Antworten-Trenner `border-b` (ohne Farbe) → explizit `border-gray-200
  dark:border-gray-700` ergänzt (Zeile 71, 77 — Tailwinds implizite
  Default-`border-color` ist `gray-200` und hätte ohne `dark:`-Klasse im
  Dark-Mode einen kaum sichtbaren hellen Rahmen auf dunklem Hintergrund
  hinterlassen)
- Fragen-Label `text-gray-700` → `+ dark:text-gray-300` (Zeile 78,
  D1-Label-Muster)
- Footer `bg-gray-50`/`border-gray-200` → `+ dark:bg-gray-700
  dark:border-gray-700` (Zeile 90)
- "Schließen"-Button nutzt bereits `class="btn btn-primary"` — via
  `frontend/src/assets/main.css:16-18` bereits dark-mode-fähig, keine
  Änderung nötig

### AnamnesisFormModal.vue

Analoges Muster wie `AnamnesisDetailModal.vue`, zusätzlich Formularfelder:
- Panel/Header/Überschrift/Schließen-Icon: identisch zu oben (Zeile 8, 10,
  12, 15)
- Fehlerbox `bg-red-50 border-red-200` / Text `text-red-800` → `+
  dark:bg-red-900/20 dark:border-red-800` / `+ dark:text-red-200` (Zeile
  25-26; Muster übernommen von `DogFormModal.vue:200-201`,
  `PayPalButton.vue:10,15`)
- Labels "Hund"/"Anamnese-Vorlage"/Fragen-Label `text-gray-700` → `+
  dark:text-gray-300` (Zeile 32, 44/45, 64)
- Select-Felder nutzen bereits `class="input"` (dark-mode-fähig über
  `main.css:28-29`, keine Änderung nötig) — Zeile 35, 48
- Vorlagen-Beschreibungstext `text-gray-600` → `+ dark:text-gray-400`
  (Zeile 54)
- "Fragen"-Überschrift `text-gray-900` → `+ dark:text-white`, `border-b`
  → explizit `border-gray-200 dark:border-gray-700` (Zeile 61, gleiche
  Begründung wie oben)
- Hilfetext `text-gray-500` → `+ dark:text-gray-400` (Zeile 69)
- Radio-/Checkbox-Ränder `border-gray-300` → `+ dark:border-gray-600`
  (Zeile 101, 127; Muster: `DogFormModal.vue:154`)
- Options-Text `text-gray-700` → `+ dark:text-gray-300` (Zeile 103, 129)
- Ladehinweis "Lade Fragen..." `text-gray-500` → `+ dark:text-gray-400`
  (Zeile 135)
- Footer `bg-gray-50`/`border-gray-200` → `+ dark:bg-gray-700
  dark:border-gray-700` (Zeile 146)
- "Abbrechen"-Button (kein `.btn-secondary`, individuelles Klassenset)
  `bg-white hover:bg-gray-50 text-gray-700 border-gray-300` → `+
  dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300
  dark:border-gray-600` (Zeile 148; Muster identisch zu
  `PricingItemForm.vue:161`)
- "Speichern"-Button nutzt bereits `class="btn btn-primary"` — dark-mode-
  fähig, keine Änderung

### AnamnesisTemplateFormModal.vue

Diese Datei nutzt durchgängig rohe Utility-Klassen statt der
`.input`/`.btn-*`-Helper (Indigo-Fokusring statt Primary) — Struktur ist
nahezu identisch zu `frontend/src/components/PricingItemForm.vue`, dessen
bereits korrektes `dark:`-Muster 1:1 übernommen wurde:
- Panel `bg-white` → `+ dark:bg-gray-800` (Zeile 3)
- Header `border-gray-200` → `+ dark:border-gray-700` (Zeile 5),
  Überschrift `text-gray-900` → `+ dark:text-white` (Zeile 6, 53),
  Schließen-Icon `text-gray-400 hover:text-gray-600` → `+
  dark:text-gray-500 dark:hover:text-gray-300` (Zeile 9)
- Fehlerbox `bg-red-50 border-red-200 text-red-800` → `+
  dark:bg-red-900/20 dark:border-red-800 dark:text-red-200` (Zeile 19)
- Alle Labels (Vorlagenname, Beschreibung, Fragetext, Fragetyp,
  Pflichtfeld, Auswahlmöglichkeiten) `text-gray-700` → `+
  dark:text-gray-300` (Zeile 26, 38, 121, 135, 158, 165)
- Alle rohen Text-/Textarea-/Select-Inputs
  (`border-gray-300 ... focus:ring-indigo-500 focus:border-indigo-500`)
  → `+ dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100`
  (Zeile 32, 44, 127, 141, 177; Muster: `PricingItemForm.vue:50,72,94`)
- Leere-Fragenliste-Hinweis `text-gray-500 border-gray-300` → `+
  dark:text-gray-400 dark:border-gray-600` (Zeile 69)
- Fragen-Karte `border-gray-300 bg-gray-50` → `+ dark:border-gray-600
  dark:bg-gray-700` (Zeile 77)
- Fragen-Index-Badge `text-gray-600 bg-white` → `+ dark:text-gray-300
  dark:bg-gray-800` (Zeile 81 — Badge sitzt auf der jetzt dunkleren
  Fragen-Karte, daher eigener dunklerer Badge-Hintergrund analog zum
  Panel-Ton)
- Auf-/Ab-Sortierbuttons `text-gray-500 hover:text-gray-700` → `+
  dark:text-gray-400 dark:hover:text-gray-200` (Zeile 88, 99)
- Frage-entfernen-/Option-entfernen-Buttons `text-red-600
  hover:text-red-800` → `+ dark:text-red-400 dark:hover:text-red-300`
  (Zeile 110, 184; Muster: `DogFormModal.vue:71`)
- Pflichtfeld-Checkbox-Rand `border-gray-300` → `+ dark:border-gray-600`
  (Zeile 156)
- "Option hinzufügen"-Link `text-indigo-600 hover:text-indigo-800` → `+
  dark:text-indigo-400 dark:hover:text-indigo-300` (Zeile 194; Muster:
  `SettingsView.vue:484`, `AnnouncementsView.vue:79`)
- Footer `border-gray-200 bg-gray-50` → `+ dark:border-gray-700
  dark:bg-gray-700` (Zeile 209); "Abbrechen"-Button `border-gray-300
  text-gray-700 hover:bg-gray-50` → `+ dark:border-gray-600
  dark:text-gray-300 dark:hover:bg-gray-600` (Zeile 213)
- "Speichern"-Button (Indigo, solide Füllung) bewusst unverändert
  gelassen — etablierte Projektkonvention lässt gefüllte Indigo-/Primary-
  Buttons ohne `dark:`-Variante (ausreichender Kontrast in beiden Modi,
  siehe `PricingItemForm.vue:169`, `AnamnesisTemplateFormModal.vue:59`
  "Frage hinzufügen"-Button)

### AnamnesisView.vue

`dark:bg-gray-800`/`dark:divide-gray-700` sowie 3× `dark:text-white`
(Zeile 5, 62, 68 — Seitentitel, Hundename-Zelle, Vorlage-Zelle) waren
bereits vorhanden. Ergänzt wurden die laut `design.md` Teilbefund 2
verbleibenden Textfarben sowie weitere im Grep nicht erfasste Fälle
(dynamische `:class`-Bindings, Tab-Buttons):
- Tabellen-Rahmen `divide-gray-200` → `+ dark:divide-gray-700` (Zeile 34)
- Tabellenkopf `bg-gray-50` → `+ dark:bg-gray-700` (Zeile 35), 6×
  Spaltenkopf `text-gray-500` → `+ dark:text-gray-400` (Zeile 37-42)
- Lade-/Leerzustand in `<td>` `text-gray-500` → `+ dark:text-gray-400`
  (Zeile 47, 56)
- Zellenwerte "Besitzer"/"Erstellt am" `text-gray-600` → `+
  dark:text-gray-400` (Zeile 65, 71)
- 5 Aktions-Buttons ("Anzeigen"/"PDF"/"Bearbeiten"/"Abschließen"/
  "Löschen") — Primary-/Blue-/Green-/Purple-/Red-Farbfamilien jeweils `+
  dark:text-<farbe>-400 dark:hover:text-<farbe>-300` ergänzt (Zeile
  79-83; Muster 1:1 aus `BookingsView.vue:88-97`,
  `InvoicesView.vue:75,77`, `TrainersView.vue:191,194` übernommen — exakt
  dieselbe `<farbe>-600/900` → `dark:<farbe>-400/300`-Systematik, keine
  neue Konvention)
- "Anamnese-Vorlagen"-Überschrift `text-gray-900` → `+ dark:text-white`
  (Zeile 94)
- Tab-Leiste `border-gray-200` → `+ dark:border-gray-700` (Zeile 104);
  aktiver Tab `text-primary-600` → `+ dark:text-primary-400`, inaktiver
  Tab `text-gray-500 hover:text-gray-700 hover:border-gray-300` → `+
  dark:text-gray-400 dark:hover:text-gray-300
  dark:hover:border-gray-600` (Zeile 108-113, 117-124 — diese
  `:class`-Array-Bindings wurden vom einfachen `grep -L "dark:"`-Scan aus
  `design.md` nicht separat aufgeführt, gehören aber zum Task-Scope
  "vollständige `dark:`-Abdeckung" und zur Spec-Anforderung "jede
  sichtbare Textfarbe hat ein `dark:`-Pendant")
- Vorlagen-Grid: Lade-/Leerzustand `text-gray-500` → `+
  dark:text-gray-400` (Zeile 137, 140); Karten-Rahmen `border-gray-200`
  → `+ dark:border-gray-700`, Hover-Rahmen `hover:border-primary-300` →
  `+ dark:hover:border-primary-600` (Zeile 145); Vorlagenname
  `text-gray-900` → `+ dark:text-white` (Zeile 147); "Standard"-Badge
  `bg-blue-100 text-blue-800` → `+ dark:bg-blue-900 dark:text-blue-200`
  (Zeile 148); Beschreibung `text-gray-600` → `+ dark:text-gray-400`
  (Zeile 150); Fragenzahl `text-gray-500` → `+ dark:text-gray-400`
  (Zeile 151)
- Aktionsbuttons der Vorlagenkarte nutzen bereits `.btn-primary`/
  `.btn-secondary`/`.btn-danger` (Zeile 155, 156, 161, 166) — dark-mode-
  fähig über `main.css`, keine Änderung
- Script-Sektion: `statusClass()` (Zeile 392-395) liefert dieselben
  Badge-Klassenstrings wie in `AnamnesisDetailModal.vue` — `+
  dark:bg-green-900 dark:text-green-200` / `+ dark:bg-yellow-900
  dark:text-yellow-200` ergänzt, damit die Statusbadges in der Tabelle
  (Zeile 74, nutzt `statusClass()` über `:class`-Binding) im Dark-Mode
  konsistent zur Detail-Modal-Badge sind

## Abgrenzung zum Triage-Eintrag (Caching-Bug)

`openspec/triage/20260707174511-anamnesis-template-questions-still-missing-
after-edit.md` wurde nicht gelesen/verändert-relevant angefasst — laut
Task-Beschreibung bereits als geschlossen/kein-Anwendungscode-Bug
markiert und ohne funktionalen Bezug zu dieser rein CSS-bezogenen Task.
Es wurden ausschließlich `class`-Attribute geändert; keine Änderung an
`loadTemplate()`, `resetForm()`, `watch()`-Handlern oder anderer
Business-Logik in den vier betroffenen Dateien — verifiziert per
`git diff` (siehe unten), Diff enthält ausschließlich Zeilen mit
`class="..."` bzw. `:class="..."`-Änderungen und zwei
Klassenstring-Literalen in einer reinen Präsentations-Hilfsfunktion.

## Lokale Checks

```
cd frontend
CI=true npx vitest run
```
Ergebnis: **17 Testdateien, 191 Tests — alle bestanden** (keine
Regression durch die Klassen-Änderungen; keine Vitest-Suite prüft laut
`verification.md` CSS-Klassenstrings, betroffene Komponenten-Tests prüfen
nur `wrapper.text()`).

```
cd frontend
npm run build
```
Ergebnis: **`vue-tsc -b` und `vite build` erfolgreich, keine neuen
Fehler/Warnungen.** Build-Output in `frontend/dist/` (git-ignored, nicht
committet, siehe `CLAUDE.md` Abschnitt 4.4/7.1: "nur Lauffähigkeits-Check,
kein `dist/`-Commit").

`npm run lint` existiert laut `frontend/package.json` nicht (bestätigt in
`verification.md`) — daher nicht ausgeführt.

## Diff-Umfang

```
git diff --stat -- frontend/src/components/anamnesis/ frontend/src/views/anamnesis/
 .../components/anamnesis/AnamnesisDetailModal.vue  | 50 ++++++++---------
 .../components/anamnesis/AnamnesisFormModal.vue    | 42 +++++++-------
 .../anamnesis/AnamnesisTemplateFormModal.vue       | 56 +++++++++----------
 frontend/src/views/anamnesis/AnamnesisView.vue     | 64 +++++++++++-----------
 4 files changed, 106 insertions(+), 106 deletions(-)
```

Vollständig manuell gegen den Diff geprüft: jede geänderte Zeile betrifft
ausschließlich `class`/`:class`-Attribute oder die beiden
Klassenstring-Literale in `statusClass()`; keine Änderung an Templates
außerhalb von Klassenattributen, keine Änderung an `<script setup>`-Logik
(Props, Emits, API-Aufrufe, `watch`/`computed`-Definitionen unverändert).

## Offene Punkte / Restrisiko

- Manuelle Browser-Prüfung im Dark-Mode (Akzeptanzkriterium "manuell
  geprüft") wurde in dieser Session nicht durchgeführt (kein laufender
  Dev-Server im Agenten-Kontext) — verbleibt für Reviewer/Tester gemäß
  Workflow-Schritt 9.
- Die Farbwahl für den Fragen-Index-Badge (`bg-white dark:bg-gray-800`
  auf einer `bg-gray-50 dark:bg-gray-700`-Karte) sowie die
  Fehlerbox-Farbtöne (`dark:bg-red-900/20 dark:border-red-800
  dark:text-red-200` statt der alternativ im Projekt vorkommenden
  `dark:border-red-700 dark:text-red-400`-Variante aus
  `PricingItemForm.vue`) sind aus mehreren im Projekt bereits parallel
  existierenden, gleichwertigen Konventionen ausgewählt worden (beide
  Varianten sind im Codebase vorhanden, siehe Recherche oben) — keine
  neue Erfindung, aber eine bewusste Wahl zwischen zwei bestehenden
  Mustern für Konsistenz innerhalb des Anamnese-Feature-Bereichs
  (Detail-Modal und Formular-Modal nutzen jetzt denselben Fehlerbox-Ton).
