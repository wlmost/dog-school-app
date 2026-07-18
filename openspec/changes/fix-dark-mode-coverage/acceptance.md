# Abnahme: fix-dark-mode-coverage

**Status:** bereit-für-user-review

---

## Prüfschritte (Nachvollzug durch den Architekten)

- `openspec validate fix-dark-mode-coverage --strict` → `Change 'fix-dark-mode-coverage' is valid` (strukturell in Ordnung).
- `openspec/changes/fix-dark-mode-coverage/tasks.md`: alle sechs Tasks (T01–T06) mit allen task-spezifischen Akzeptanzkriterien als `[x]` markiert. Die vier `[ ]`-Zeilen in der Datei (Zeilen 21, 24, 26, 28) sind kein offener Rest, sondern die einmalige Definition der "Gemeinsamen Akzeptanzkriterien" im Kopf der Datei; jede Task hakt sie unter ihrer eigenen Kriterienliste separat als `[x] Gemeinsame Akzeptanzkriterien (siehe oben) erfüllt` ab — geprüft, kein inhaltlicher Rückstand.
- Alle sechs `task-T0X.review.md`: Gesamtempfehlung durchgängig `ok`, keine "Muss"-Befunde in keiner Task.
- Alle sechs `task-T0X.test-report.md`: Status durchgängig `alle-gruen`.
- `cd frontend && npx vitest run` selbst ausgeführt: **18 Testdateien, 194 Tests, alle grün** (bestätigt den in allen Test-Reports dokumentierten Endstand, inkl. `DefaultLayout.test.ts`, das T01 neu hinzugefügt hat).
- `cd frontend && npm run build` selbst ausgeführt: `vue-tsc -b && vite build` erfolgreich, keine TypeScript-Fehler, keine Build-Warnungen.
- Eigene, vom Entwickler-/Reviewer-Bericht unabhängige Stichprobe: `grep` gegen alle 28 laut `tasks.md`/`proposal.md` geänderten bzw. geprüften Dateien nach `(text|bg|border|divide)-(gray|slate|zinc|neutral)-<N>` bzw. `bg-white` ohne begleitendes `dark:`-Pendant auf derselben Zeile → **keine offenen Treffer**, mit einer harmlosen Ausnahme (`EmailPreviewModal.vue:216-222`, `.bg-white`-Selektoren in einem `<style scoped>`-Transition-Block, kein Template-Text). Ebenfalls für `AgbView.vue`/`DatenschutzView.vue` (laut T06 unverändert, da bereits vollständig) unabhängig nachgeprüft: keine offenen Treffer, bestätigt die Entwickler-Aussage in `task-T06.notes.md`.
- Stichprobe der zwei per Nachtrag behobenen Reviewer-Findings direkt im Code verifiziert: `frontend/src/views/bookings/BookingsView.vue:26` trägt jetzt `dark:divide-gray-700`; `frontend/src/components/InvoiceFormModal.vue:147` und `frontend/src/components/TrainerFormModal.vue` nutzen jetzt `dark:text-gray-300` statt `dark:text-gray-200` — beide Nachträge sind im Arbeitsverzeichnis vorhanden, nicht nur in den Notes behauptet.
- `specs/dark-mode-theming/spec.md` (3 ADDED Requirements: theme-reaktiver Layout-Hintergrund, dark-mode-fähige Formular-Modals, Text-Container-Konsistenz) deckt sich mit dem in `design.md`/`tasks.md` beschriebenen Scope; keine Diskrepanz zur Implementierung festgestellt.

## Erfüllt

- Alle 6 Tasks (T01–T06) vollständig implementiert, alle task-spezifischen Akzeptanzkriterien in `tasks.md` abgehakt.
- Kernbug behoben: `DefaultLayout.vue` `backgroundStyle` reagiert jetzt auf `themeStore.isDark` (dunkles statt immer helles Overlay im Dark-Mode), Light-Mode-Zweig byte-identisch zum Vorzustand — durch neuen, dedizierten Test (`DefaultLayout.test.ts`, 3 Fälle) automatisiert abgesichert.
- 20 Komponenten ohne jede `dark:`-Klasse sowie die in `design.md` Teilbefund 2 identifizierten inkonsistenten `dark:text-*`-Lücken (u. a. `CustomersView.vue`, `TrainersView.vue`, `InvoicesView.vue`, `AnamnesisView.vue`, `BookingsView.vue`, `DogsView.vue`) sind vollständig nachgezogen — durch eigene Stichprobe bestätigt, nicht nur aus den Notes übernommen.
- Alle 6 Reviewer-Durchgänge: keine "Muss"-Befunde. Die zwei "Sollte"-Befunde aus T03 (`BookingsView.vue` `dark:divide-gray-700`) und T04 (Werte-/Button-Farbinkonsistenzen in `TrainersView.vue`/`InvoicesView.vue`/`TrainerFormModal.vue`/`InvoiceFormModal.vue`) wurden per dokumentiertem Nachtrag behoben und im Code verifiziert.
- Kein Backend-Code betroffen, keine neue Abhängigkeit, ausschließlich `class`-Attribute plus die eine begründete `computed`-Erweiterung in `DefaultLayout.vue` — konsistent zu `proposal.md`/`design.md`.
- `npm run test` (194/194) und `npm run build` (warnungsfrei) laufen grün — unabhängig vom Architekten nachvollzogen, nicht nur aus den Test-Reports übernommen.
- `openspec validate --strict` erfolgreich.

## Offen / Nacharbeit (kein Blocker)

- **Manuelle Browser-Sichtprüfung mit Dark-Mode-Toggle nicht durchführbar.** In `tasks.md` ist für praktisch jede Task ein Akzeptanzkriterium der Form "im Dark-Mode manuell im Browser geprüft" enthalten. Weder die sechs `dev-typescript`-Agenten noch der Tester hatten in dieser Umgebung Zugriff auf ein Browser-Tool (durchgängig dokumentiert in allen `task-T0X.notes.md`/`task-T0X.test-report.md` unter "Offene Punkte"/"Offener Punkt"). Ersatzweise wurde durchgängig statische Grep-/Diff-Verifikation durchgeführt (vollständige `dark:`-Klassenpaarung je Datei), die der Architekt in diesem Dokument zusätzlich unabhängig nachvollzogen hat. Das deckt "hat die Klasse ein `dark:`-Pendant" ab, **nicht** aber "ist der tatsächliche Farbkontrast im gerenderten Browser ausreichend lesbar" — das ist ein qualitativ anderer Check, den keine Automatisierung in dieser Session leisten konnte.
  → **Empfehlung an den User:** Vor dem finalen Merge/PR selbst kurz im Browser mit aktivem Dark-Mode-Toggle gegenchecken, mindestens: `DefaultLayout.vue`-Seitenhintergrund, je ein Modal aus T02/T04/T05 (z. B. `DogFormModal`, `InvoiceFormModal`, `AnamnesisFormModal`), sowie die Tabellen `CustomersView`/`TrainersView`/`InvoicesView`/`BookingsView`/`AnamnesisView`. Kein Blocker für die Abnahme, da alle 24 tatsächlich geänderten Dateien und die 2 als "bereits vollständig" verifizierten Dateien (`AgbView.vue`, `DatenschutzView.vue`) sowohl vom jeweiligen Entwickler als auch unabhängig vom Architekten per Grep als lückenlos `dark:`-gepaart bestätigt sind.
- **T01-Review "Sollte"-Befund ohne Nachtrag:** `HtmlEditor.vue` erhielt zusätzlich zu `dark:bg-gray-800` ein neues `bg-white` im Light-Mode (vorher implizit transparent). Reviewer stufte dies als unkritisch ein (an beiden bekannten Einsatzorten `CourseFormModal.vue`/`AnnouncementsView.vue` liegt ohnehin ein weißes Dialog-Panel dahinter). Anders als die T03/T04-"Sollte"-Befunde wurde hierzu kein Korrektur-Nachtrag dokumentiert. Kein Blocker (Reviewer selbst: "Sollte", kein "Muss"), aber falls `HtmlEditor.vue` künftig außerhalb eines weißen Containers eingesetzt wird, sollte das erneut geprüft werden.
- **Keine Git-Commits auf dem Feature-Branch.** `git log main..feature/fix-dark-mode-coverage` liefert keine Treffer — sämtliche Implementierungs- und Dokumentationsänderungen (T01–T06, Reviews, Test-Reports, Nachträge) liegen ausschließlich als unstaged Working-Tree-Änderungen vor, entgegen der in `WORKFLOW.md` Schritt 8/9 vorgesehenen Commit-je-Task-Konvention. Inhaltlich kein Abnahme-Blocker (der Diff selbst wurde geprüft und ist in Ordnung), aber vor `openspec archive`/PR sollte der Stand in sinnvolle Commits gebracht werden — sonst geht die in den Notes dokumentierte Task-für-Task-Historie im PR nicht sichtbar nach.

## Empfehlung an den User

Inhaltlich bereit für User-Gate 2: alle Tasks vollständig, keine offenen "Muss"-Befunde, Tests/Build grün (eigenständig nachvollzogen), Spec-Delta konsistent zur Implementierung. Vor der finalen Freigabe bitte kurz selbst im Browser mit Dark-Mode-Toggle gegenchecken (s. o.) und beachten, dass der Branch noch keine Commits enthält — ein sinnvoller Commit-Schnitt (z. B. je Task oder als ein zusammenfassender Commit) sollte vor `openspec archive`/PR nachgeholt werden.
