# Notes T06: Einstellungen, Mail-Vorschau, rechtliche Seiten

**Change-ID:** fix-dark-mode-coverage
**Agent:** dev-typescript
**Datum:** 2026-07-18

## Vorgehen

Vor der Implementierung wurden `tasks.md` (Abschnitt T06), `design.md`
(Decisions D1/D2, Bestandsaufnahme Teilbefund 1+2) und `proposal.md`
vollständig gelesen sowie die zwei Referenzmuster-Dateien
`frontend/src/components/PricingModal.vue` (Dialog/Modal-Panel-Muster)
und `frontend/src/layouts/PublicLayout.vue` (Seiten-/Container-
Hintergrund-Muster).

Statt mich blind auf die in `design.md` Teilbefund 2 genannten
Zählwerte (`SettingsView.vue`: 47/18, `EmailPreviewModal.vue`: 9/4,
`AgbView.vue`: 23/11, `DatenschutzView.vue`: 29/12) zu verlassen, habe
ich die tatsächlichen Lücken je Datei per gezieltem Grep verifiziert
(`grep -oP '(?<!dark:)(text|border|bg|divide)-(gray|slate|zinc|neutral)-[0-9]+'`
gegen jede Datei sowie zeilenweiser Abgleich, ob der jeweilige Treffer
ein `dark:`-Pendant auf derselben Klasse hat). Das war nötig, weil
`design.md` selbst als Risiko dokumentiert, dass die einfache
Zeilenzählung nur Kandidaten liefert, keine erschöpfende Liste (siehe
`design.md` Abschnitt "Risks/Trade-offs", "Heuristik-Risiko"). Beleg
für die Notwendigkeit: Ein simpler `grep -oE 'text-(gray|...)'` zählt
fälschlich auch das `text-gray-300` **innerhalb** von
`dark:text-gray-300` mit, wodurch `AgbView.vue`/`DatenschutzView.vue`
in `design.md` als "teilweise abgedeckt" (23/11, 29/12) erscheinen,
obwohl sie es bei genauerer Prüfung nicht sind (siehe Befund unten).

## Befunde je Datei

### `frontend/src/views/AgbView.vue` und `frontend/src/views/DatenschutzView.vue`

**Keine Code-Änderung nötig.** Verifiziert per
`grep -n 'text-gray-900' <datei> | grep -v 'dark:text-white'` (und
analog für `text-gray-700`/`dark:text-gray-300` sowie
`border-gray-200`/`dark:border-gray-700`) — alle Treffer liefern
**null Zeilen**, d. h. jede Vorkommen von `text-gray-900`,
`text-gray-700` und `border-gray-200` in beiden Dateien hat bereits ein
vollständiges `dark:`-Pendant (`dark:text-white`,
`dark:text-gray-300`, `dark:border-gray-700`). Beide Views verwenden
konsequent `bg-white dark:bg-gray-900` (Seiten-Hintergrund, D1),
`text-gray-900 dark:text-white` (Überschriften), `text-gray-700
dark:text-gray-300` (Fließtext in allen `<section>`-Absätzen, auch den
langen juristischen Textblöcken) und `text-primary-600
dark:text-primary-400` (Links). Die in `design.md` genannten Zahlen
23/11 bzw. 29/12 beruhen auf dem oben beschriebenen Zähl-Artefakt
(Doppelzählung des Substrings `text-gray-300` innerhalb von
`dark:text-gray-300`) und stellen keine tatsächliche Lücke dar. Da die
Akzeptanzkriterien explizit "auf durchgängige `dark:text-*`-Paarung in
allen Absätzen prüfen, nicht nur Überschriften" verlangen, wurde jede
`<section>` beider Dateien manuell durchgesehen (nicht nur der
aggregierte Zähler) — Ergebnis bestätigt vollständige Abdeckung.

### `frontend/src/components/EmailPreviewModal.vue`

Eine echte Lücke gefunden: Zeile 22, Schließen-Button-Icon.

- Vorher: `class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"`
- Nachher: `class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"`

Der Ruhezustand (`text-gray-400`) hatte kein `dark:`-Pendant, nur der
`hover:`-Zustand war bereits abgedeckt (`dark:hover:text-gray-300`).
Ergänzt nach Referenzmuster D1 (Sekundär-/Icon-Farbe, angelehnt an
`PricingModal.vue:9` `text-gray-500 dark:text-gray-400`), hier
`dark:text-gray-500` gewählt, um eine konsistente Farbabstufung zum
bereits vorhandenen `dark:hover:text-gray-300` zu erhalten (Ruhezustand
dunkler als Hover-Zustand, wie auch im Light-Mode `text-gray-400` →
`hover:text-gray-600`).

Alle übrigen 8 `text-gray-*`-Vorkommen in dieser Datei (Zeilen 17, 45,
46, 53, 54, 81, 88, 89) hatten bereits vollständige `dark:`-Paarung
(`dark:text-white`/`dark:text-gray-400`/`dark:text-gray-300`), ebenso
alle 6 `border-gray-200`-Vorkommen (→ `dark:border-gray-700`). Das
eingebettete `dark:prose-invert` (Zeile 79,
`class="prose dark:prose-invert max-w-none"`) für die Rich-Text-
Vorschau wurde **nicht verändert** — es war bereits korrekt vorhanden
und blieb wie vom Auftrag gefordert unangetastet.

### `frontend/src/views/SettingsView.vue`

Größte Datei der Task, tatsächlich mehrere Dutzend Lücken (Formular
war ursprünglich mit klassischem Tailwind-Formular-Styling ohne jede
`dark:`-Klasse gebaut, während spätere Abschnitte — Preise-Tabelle ab
Zeile 396 — bereits vollständig dark-mode-fähig waren). Als lokale
Referenz für die Input-Feld-Konvention diente
`frontend/src/components/EmailTemplateEditor.vue:46` (bereits
korrekt, außerhalb des Task-Scopes, aber im selben Formular via
`<EmailTemplateEditor>` eingebunden, `SettingsView.vue:353`):
`border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white`
— dieselbe Konvention wurde für die reinen Tailwind-Inputs in
`SettingsView.vue` übernommen (`focus:border-indigo-500
focus:ring-indigo-500` blieb unverändert, da nicht Teil der
Grau-Familie und in `EmailTemplateEditor.vue` ebenfalls nicht
dark-spezifisch behandelt).

Geänderte Stellen (Datei:Zeile nach der Änderung):

1. **Load-Error-Box** (`SettingsView.vue:16-17`):
   `bg-red-50 border border-red-200` → `+ dark:bg-red-900/20
   dark:border-red-800`; `text-red-800` → `+ dark:text-red-400`.
   Konvention übernommen von `frontend/src/views/AnnouncementsView.vue:22-23`
   (identisches Error-Box-Muster, bereits im Projekt etabliert).
2. **Stammdaten-Sektionskopf** (`SettingsView.vue:24-25`):
   `bg-gray-50 ... border-gray-200` → `+ dark:bg-gray-700
   dark:border-gray-700`; `text-gray-900` (h2) → `+
   dark:text-gray-100`. Konvention 1:1 aus dem bereits korrekten
   Preise-Sektionskopf derselben Datei übernommen
   (`SettingsView.vue:398/400`, vor dieser Änderung schon vorhanden).
3. **E-Mail-Konfiguration-Sektionskopf** (`SettingsView.vue:222-223`):
   identisch zu Punkt 2 (per `replace_all`, da exakt derselbe
   Klassen-String zweimal im Dokument vorkam).
4. **18 Formular-Labels** (`class="block text-sm font-medium
   text-gray-700"`, u. a. Zeilen 31, 44, 57, 70, 83, 96, 109, 122,
   135, 148, 229, 242, 255, 279, 292, 307, 320, 334) → `+
   dark:text-gray-300` (per `replace_all`, identischer Klassen-String
   an allen Stellen, Referenzmuster D1 Formular-Labels).
5. **18 Text-/Select-Inputs** (identischer Klassen-String `class="mt-1
   block w-full rounded-md border-gray-300 shadow-sm
   focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"`, u. a.
   Zeilen 38, 51, 64, 77, 90, 103, 116, 129, 142, 155, 236, 249, 261,
   286, 301, 314, 327, 340) → `+ dark:border-gray-600 dark:bg-gray-700
   dark:text-white` (per `replace_all`).
6. **Kleinunternehmer-Checkbox** (`SettingsView.vue:167`):
   `border-gray-300` → `+ dark:border-gray-600`.
7. **Kleinunternehmer-Label** (`SettingsView.vue:171`):
   `text-gray-700` → `+ dark:text-gray-300`.
8. **Kleinunternehmer-Beschreibungstext** (`SettingsView.vue:174`):
   `text-gray-500` → `+ dark:text-gray-400`.
9. **Firmenlogo-/Favicon-Labels** (`SettingsView.vue:182, 201`,
   identischer Klassen-String `class="block text-sm font-medium
   text-gray-700 mb-2"`) → `+ dark:text-gray-300` (je einzeln editiert,
   da der `replace_all`-Aufruf für Punkt 4 diesen abweichenden
   `mb-2`-Suffix-String nicht erfasst hatte).
10. **Datei-Input-Styling Logo/Favicon** (`SettingsView.vue:193, 212`):
    `text-gray-500` → `+ dark:text-gray-400` (Basis-Textfarbe der
    nativen File-Input-Beschriftung; die dekorativen `file:bg-indigo-50
    file:text-indigo-700`-Klassen sind keine Grau-Familie und wurden
    unverändert gelassen, analog zu `EmailTemplateEditor.vue`, das
    ebenfalls keine dark-Variante für Indigo-Akzentfarben führt —
    kein neues Farbschema eingeführt, D1/Non-Goals).
11. **Hinweistexte "PNG, JPG..." / "PNG oder ICO..."**
    (`SettingsView.vue:196, 215`): `text-gray-500` → `+
    dark:text-gray-400`.
12. **SMTP-Abschnitt** (`SettingsView.vue:274-275`): `border-gray-200`
    → `+ dark:border-gray-700`; `text-gray-900` (h3) → `+
    dark:text-gray-100`.
13. **Success-Message-Box** (`SettingsView.vue:381, 383`):
    `bg-green-50 border border-green-200` → `+ dark:bg-green-900/20
    dark:border-green-800`; `text-green-800` → `+
    dark:text-green-200`. Konvention übernommen von
    `frontend/src/views/ContactView.vue:196-197` (identisches
    Success-Box-Muster).
14. **Save-Error-Message-Box** (`SettingsView.vue:389, 391`): wie
    Punkt 1 (rotes Error-Box-Muster).

Nicht verändert (bereits vollständig `dark:`-abgedeckt, verifiziert):
`SettingsView.vue:4-5` (Seitenkopf), `SettingsView.vue:23/221/396`
(Card-Hintergrund `bg-white dark:bg-gray-800`), gesamte
Preise-Tabelle (`SettingsView.vue:396-513`, war bereits korrekt), der
"Zurücksetzen"-Button (`SettingsView.vue:364`, bereits vollständig
dark-mode-fähig), `pricingError`-Anzeige (`SettingsView.vue:417`,
bereits `text-red-600 dark:text-red-400`).

**Anmerkung zur Aufgabenabgrenzung:** Die roten/grünen Status-Boxen
(Punkte 1, 13, 14) sind keine `text-gray-*`-Klassen und wurden daher
von der `grep`-Heuristik in `design.md` Teilbefund 2 nicht erfasst
(die dortige Zählung filtert explizit nur
`text-(gray|slate|zinc|neutral)`). Sie wurden trotzdem behoben, weil
(a) der Task-Beschreibungstext in `tasks.md` allgemein von
"Text-/Rahmenfarben ohne `dark:`-Pendant" spricht (nicht nur
Grau-Familie), (b) das Akzeptanzkriterium "alle Formular-Sektionen ...
im Dark-Mode vollständig lesbar" explizit auch Fehler-/Erfolgsmeldungen
einschließt, und (c) dafür ausschließlich bereits im Projekt etablierte
Farbkonventionen wiederverwendet wurden (`AnnouncementsView.vue`,
`ContactView.vue`) — keine neue Konvention erfunden (D1-Prinzip).

## Nicht angefasste Dateien / Komponenten

- `frontend/src/components/PricingItemForm.vue` (in `SettingsView.vue`
  über `<PricingItemForm>` eingebunden) war bereits vollständig
  dark-mode-fähig und ist nicht Teil des T06-Datei-Scopes — nicht
  verändert.
- `frontend/src/components/EmailTemplateEditor.vue` (in
  `SettingsView.vue` über `<EmailTemplateEditor>` eingebunden) war
  bereits vollständig dark-mode-fähig und diente lediglich als lokale
  Referenz für die Input-Konvention — nicht verändert.

## Akzeptanzkriterien (Task-Kopf, gemeinsam für alle Tasks)

- [x] Ausschließlich `class`-Attribute geändert — verifiziert per
  `git diff -- frontend/src/views/SettingsView.vue | grep -E '^[+-]' |
  grep -v '^[+-][+-][+-]' | grep -vE 'class="'` → keine Treffer, d. h.
  jede geänderte Zeile enthält ein `class="..."`-Attribut. Keine
  Änderung an Props, Emits, Composables oder Business-Logik.
- [x] `npm run test` bleibt grün: `npx vitest run` → `Test Files 17
  passed (17)`, `Tests 191 passed (191)`.
- [x] `npm run build` läuft ohne neue Fehler/Warnungen: `vue-tsc -b &&
  vite build` erfolgreich (`✓ built in 1.36s`, keine TS-Fehler, keine
  Build-Warnungen).
- [x] Light-Mode-Darstellung bleibt visuell unverändert: ausschließlich
  `dark:`-Klassen ergänzt, keine bestehende Nicht-`dark:`-Klasse
  entfernt oder verändert (siehe obige Diff-Verifikation).

## Task-spezifische Akzeptanzkriterien

- [x] `SettingsView.vue`: alle Formular-Sektionen (Profil,
  Benachrichtigungen/E-Mail-Konfiguration, Favicon-Upload etc.) im
  Dark-Mode vollständig lesbar (14 Änderungsstellen, siehe oben).
- [x] `EmailPreviewModal.vue`: Vorschau-Inhalt inkl.
  `dark:prose-invert` (unverändert erhalten) im Dark-Mode vollständig
  lesbar (1 Lücke am Schließen-Button-Icon behoben).
- [x] `AgbView.vue` und `DatenschutzView.vue`: vollständig lesbar im
  Dark-Mode — bei genauer Prüfung bereits vollständig abgedeckt, keine
  Code-Änderung nötig.

## Ausgeführte Checks

```
cd frontend
npx vitest run
# Test Files  17 passed (17)
# Tests  191 passed (191)

npm run build
# vue-tsc -b && vite build
# ✓ 643 modules transformed, ✓ built in 1.36s, keine Fehler/Warnungen
```

## Geänderte Dateien

- `frontend/src/views/SettingsView.vue`
- `frontend/src/components/EmailPreviewModal.vue`
- `frontend/src/views/AgbView.vue` (geprüft, keine Änderung nötig)
- `frontend/src/views/DatenschutzView.vue` (geprüft, keine Änderung
  nötig)
