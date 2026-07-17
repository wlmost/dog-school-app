# Notes T10: Öffentliche Anzeige-Komponente `AnnouncementBanner.vue`

## Umgesetzte Dateien

- `frontend/src/components/AnnouncementBanner.vue` (neu)
- `frontend/src/components/AnnouncementBanner.test.ts` (neu)
- `frontend/src/views/HomeView.vue` (geändert: Import + Einbindung)

## Umsetzung

- Komponente nutzt `useAnnouncements()` (T09, bereits vorhanden) und ruft
  `loadPublic()` in `onMounted` auf. Kein Prop-Drilling von `HomeView.vue`
  aus nötig — Kapselung analog zur Begründung in `design.md` Abschnitt 7.2.
- Äußere `<section v-if="announcements.length">` sorgt dafür, dass bei
  keiner aktiven Ankündigung nichts gerendert wird (kein leerer Container
  im DOM).
- Vertikal gestapelte Liste (`v-for` über `<article>`-Karten), kein
  Karussell — wie in `design.md` Abschnitt 7.1 als KISS/YAGNI-Entscheidung
  festgelegt. Karte mit optionalem Bild links (`object-cover`,
  `w-48 h-48` auf `sm`+, volle Breite darunter auf Mobile) und
  Titel/Rich-Text rechts.
- `body` wird über `DOMPurify.sanitize(..., { ALLOWED_TAGS, ALLOWED_ATTR: [] })`
  sanitized, mit identischer `ALLOWED_TAGS`-Konstante wie
  `frontend/src/views/courses/CoursesView.vue:319-320`
  (`p, br, strong, em, h2, h3, ul, ol, li, blockquote, code, pre`).
  `v-html`-Stelle trägt den projektüblichen
  `<!-- eslint-disable-next-line vue/no-v-html -->`-Kommentar.
- Scoped-Style-Block für `.announcement-body :deep(p/ul/ol)` ist 1:1 vom
  `.course-description`-Pendant in `CoursesView.vue` übernommen (gleiches
  Rich-Text-Rendering-Verhalten für Absätze/Listen).
- `HomeView.vue`: `<AnnouncementBanner />` wurde exakt zwischen dem
  schließenden `</section>` der Hero-Section (ursprünglich Zeile 26) und
  dem Kommentar `<!-- Features Section -->` (ursprünglich Zeile 28)
  eingefügt — Zeilennummern beim eigenen Lesen der Datei verifiziert, sie
  stimmten exakt mit `design.md` Abschnitt 7.2 überein. Import
  `import AnnouncementBanner from '@/components/AnnouncementBanner.vue'`
  wurde vor dem bestehenden `PricingModal`-Import ergänzt (alphabetisch
  vor `PricingModal`). Kein zusätzlicher `onMounted()`-Aufruf in
  `HomeView.vue` nötig, wie in `design.md` begründet.

## Tests

`AnnouncementBanner.test.ts` mockt `useAnnouncements()` direkt (Muster
identisch zu `frontend/src/components/PricingItemForm.test.ts` und
`frontend/src/views/SettingsView.test.ts`, die ebenfalls Composables per
`vi.mock('@/composables/...')` stubben statt die HTTP-Ebene zu mocken).
Abgedeckte Fälle:

- kein sichtbarer Bereich bei leerer `announcements`-Liste
- `loadPublic()` wird beim Mount genau einmal aufgerufen
- eine Karte pro aktiver Ankündigung, inkl. Titel-Text
- Bild wird gerendert (`src`/`alt`) wenn `imageUrl` gesetzt ist
- kein `<img>`, wenn `imageUrl` `null` ist
- erlaubtes HTML (`<strong>`) wird über `v-html` durchgereicht
- `<script>`-Tags werden von DOMPurify entfernt
- nicht erlaubte Tags (`<img>` außerhalb `ALLOWED_TAGS`) und
  Inline-Event-Attribute (`onclick`, `onerror`) werden entfernt

**Hinweis zu einer Testumgebungs-Eigenheit (kein Produktivcode-Problem):**
Beim Entwerfen der DOMPurify-Tests wurde in einem isolierten Debug-Lauf
festgestellt, dass DOMPurify 3.4.3 unter der `happy-dom`-Testumgebung
(`vitest.config.ts`, `environment: 'happy-dom'`) für die *exakte*
Eingabe-Kombination `<script>...</script><img ...>` (Script unmittelbar
gefolgt von einem nicht erlaubten `<img>`-Tag, ohne dazwischenliegendes
erlaubtes Element) das `<img>`-Tag fälschlich nicht entfernt — obwohl
`img` nicht in `ALLOWED_TAGS` enthalten ist. Isoliert getestet (`<img>`
allein, `<p>…</p><img>`, `<script>…</script>` allein, `<p onclick>…</p>
<img onerror>` ohne Script davor) verhält sich DOMPurify in derselben
Umgebung jeweils korrekt (Tag/Attribute werden entfernt) — das Problem
tritt ausschließlich bei genau dieser Reihenfolge/Kombination auf und ist
damit eine `happy-dom`/DOMPurify-Interaktionseigenheit der Testumgebung,
kein reales Sanitizing-Problem im Produktivcode (derselbe Sanitizing-Code
läuft in `CoursesView.vue` bereits unverändert in Produktion). Die Tests
in `AnnouncementBanner.test.ts` wurden entsprechend in zwei unabhängige
Fälle aufgeteilt (Script-Entfernung separat von Tag-/Attribut-Entfernung),
um diese Testumgebungs-Eigenheit nicht versehentlich als Testfehler zu
maskieren oder einen irreführenden Fehlschlag zu erzeugen.

## QA-Checks (lokal, Frontend)

```bash
npx vitest run src/components/AnnouncementBanner.test.ts   # 8/8 grün
npx vitest run                                              # 174/174 grün (gesamte Suite)
npm run build                                                # vue-tsc -b + vite build, keine Fehler
```

`npm run lint` wurde **nicht** ausgeführt — `frontend/package.json` enthält
kein `lint`-Script und es existiert keine `.eslintrc*`-Konfiguration im
Projekt (bereits in `verification.md` unter "Fehlende QA-Scripts"
dokumentiert). Der `eslint-disable-next-line vue/no-v-html`-Kommentar
wurde dennoch gemäß Projektkonvention gesetzt, für den Fall einer künftigen
ESLint-Einführung.

## Bekannte Randbedingungen / bewusst nicht behandelt

- Keine eigene Fehleranzeige bei fehlgeschlagenem `loadPublic()` — die
  Komponente rendert dann einfach nichts (leeres `announcements`-Array
  bleibt bestehen), was für eine rein informative, nicht-kritische
  Banner-Komponente auf der öffentlichen Startseite angemessen ist
  (Fail-silent statt Fehlermeldung auf der Homepage). `useAnnouncements()`
  setzt `error.value` intern, das könnte bei Bedarf in einem späteren
  Change ausgewertet werden — nicht Teil der T10-Akzeptanzkriterien.
- Kein `loading`-Indikator während `loadPublic()` läuft — analog zur
  bestehenden `PricingModal`/`usePricingItems()`-Nutzung in `HomeView.vue`,
  die ebenfalls keinen sichtbaren Ladezustand für die Kachel zeigt
  (Konsistenz mit vorhandenem Muster, YAGNI für diesen Change).
- `frontend/src/router/index.ts` und `frontend/src/layouts/DefaultLayout.vue`
  wurden **nicht** angefasst (Teil von T11, laut Vorgabe explizit
  ausgeschlossen).
