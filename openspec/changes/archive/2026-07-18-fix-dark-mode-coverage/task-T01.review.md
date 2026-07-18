# Review: T01 — Layout & geteilte Komponenten

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)
(keine)

## Sollte (vor Merge erledigen, kann diskutiert werden)
- **[Konsistenz]** `frontend/src/components/HtmlEditor.vue:2`: Der Wrapper-`<div>` bekommt zusätzlich zu `dark:bg-gray-800` auch ein neues `bg-white`, das im Light-Mode vorher nicht explizit gesetzt war (Hintergrund war implizit transparent/vom Elternelement geerbt). Das ist laut `task-T01.notes.md` bewusst ergänzt ("damit die Editor-Fläche unabhängig vom umgebenden Kontext korrekt dunkel gefüllt ist") und in den bisher bekannten Einsatzorten (`CourseFormModal.vue`, `AnnouncementsView.vue`) visuell unauffällig, weil dort ohnehin ein weißes Dialog-Panel dahinterliegt. Das gemeinsame Akzeptanzkriterium "keine bestehenden Nicht-`dark:`-Klassen entfernt" ist damit zwar nicht verletzt, aber es wurde eine neue Light-Mode-Klasse hinzugefügt, die über den reinen `dark:`-Ergänzungs-Scope hinausgeht. Vorschlag: kurz gegenprüfen, ob `HtmlEditor.vue` auch außerhalb eines weißen Containers eingesetzt wird (aktuell laut Notes nur an den zwei bekannten Stellen) — falls ja, sicherstellen, dass `bg-white` dort keine unbeabsichtigte Kachel erzeugt.

## Könnte (optional, Verbesserung)
- **[Stil]** `frontend/src/views/NotFoundView.vue:4`: `dark:text-gray-700` für die dekorative "404"-Ziffer ist laut `task-T01.notes.md` eine neue, im Projekt noch nicht präzedente Farbwahl (kein bestehendes `text-gray-300 dark:...`-Muster gefunden). Farblich plausibel (erhält den "Wasserzeichen"-Effekt), aber da D1 explizit "keine neuen Konventionen" fordert, wäre eine kurze Rücksprache/Bestätigung durch den Architekten sinnvoll, falls diese Farbe in Zukunft an weiteren Stellen wiederverwendet werden soll.

## Lob
- Der eigentliche Bugfix (`frontend/src/layouts/DefaultLayout.vue:169-179`) ist sauber umgesetzt: Light-Mode-Zweig ist byte-identisch zum vorherigen Ausdruck (keine Regression), Dark-Mode-Zweig nutzt exakt die in `tasks.md` vorgegebenen `rgba`-Werte, keine neue Store-Instanz. Das ist die einzige dokumentierte Ausnahme vom "nur `class`-Attribute"-Kriterium und sauber als solche gekennzeichnet.
- Diff ist über alle vier Dateien ausschließlich auf `class`/`:class`-Änderungen plus die eine begründete `computed`-Erweiterung beschränkt (verifiziert per `git diff`).
- `npm run test` (191/191) und `npm run build` laufen grün und warnungsfrei (unabhängig nachvollzogen).
