# Triage: Dark-Mode — fehlende dark:-Varianten nach Anmeldung (Texte + Formulare)

**Pfad:** standard
**Geschätzter Umfang:** ca. 20+ Vue-Dateien (frontend/src, TypeScript/Vue only), kein Backend betroffen
**Risiko:** niedrig — reines Frontend-Styling (Tailwind `dark:`-Utility-Klassen), keine öffentlichen Schnittstellen, kein Datenmodell, keine Migration betroffen
**Klarheit:** klar — Ursache per Grep bereits eingegrenzt, kein Rückfragebedarf

## Anforderung (Zusammenfassung)
Ist der Dark-Modus aktiv, werden nach der Anmeldung Texte teilweise weiterhin
in dunkler statt heller Schrift dargestellt (schlecht/unlesbar auf dunklem
Hintergrund). Zusätzlich werden Formulare (Modals) teilweise gar nicht im
Dark-Mode gestylt. Erwartung: konsistente Dark-Mode-Darstellung in allen
nachgelagerten (eingeloggten) Views und Formular-Komponenten.

## Repo-Befund (Faktenlage vor Architekt-Übergabe)
- Dark-Mode-Mechanismus ist Tailwind class-basiert: `frontend/tailwind.config.js:3`
  → `darkMode: 'class'`.
- Umsetzung/Toggle: `frontend/src/stores/theme.ts` (Pinia-Store `useThemeStore`),
  setzt/entfernt `dark`-Klasse auf `document.documentElement`, persistiert in
  `localStorage`. Initialisierung in `frontend/src/main.ts:15-16`. Kein Bug in
  der Store-Logik erkennbar — reine Styling-Lücke in den Komponenten.
- Von 54 `.vue`-Dateien unter `frontend/src` enthalten **34 mindestens eine
  `dark:`-Klasse**, **20 enthalten überhaupt keine `dark:`-Klasse** (Grep
  `grep -L "dark:"`, Stand heute). Das deckt sich mit der Nutzerbeobachtung
  "Formulare teilweise nicht im Dark-Mode dargestellt" — praktisch alle
  Formular-Modals der Anwendung sind betroffen:
  - `frontend/src/components/DogFormModal.vue`
  - `frontend/src/components/CustomerFormModal.vue`
  - `frontend/src/components/CourseFormModal.vue`
  - `frontend/src/components/CustomerBookingModal.vue`
  - `frontend/src/components/TrainerFormModal.vue`
  - `frontend/src/components/InvoiceDetailModal.vue`
  - `frontend/src/components/CustomerDetailModal.vue`
  - `frontend/src/components/InvoiceFormModal.vue`
  - `frontend/src/components/BookingFormModal.vue`
  - `frontend/src/components/CourseSessionList.vue`
  - `frontend/src/components/CourseRecurrenceForm.vue`
  - `frontend/src/components/CustomerDogRequestModal.vue`
  - `frontend/src/components/HtmlEditor.vue`
  - `frontend/src/components/anamnesis/AnamnesisDetailModal.vue`
  - `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue`
  - `frontend/src/components/anamnesis/AnamnesisFormModal.vue`
  - `frontend/src/views/courses/CoursesView.vue`
  - `frontend/src/views/NotFoundView.vue`
  - `frontend/src/App.vue`, `frontend/src/components/HelloWorld.vue`
    (letztere zwei vermutlich unkritisch/Boilerplate — vom Architekten zu
    bewerten, ob HelloWorld.vue überhaupt noch produktiv genutzt wird;
    **ungeprüfte Referenz**, nicht weiter analysiert)
- Zusätzlich zum "komplett fehlend"-Befund erwähnt der User auch **falsch
  dunkle statt heller Schrift in bereits dark:-annotierten Views nach der
  Anmeldung** — das betrifft vermutlich Komponenten, die zwar `dark:`-Klassen
  haben, aber unvollständig/inkonsistent (z. B. `text-gray-900` ohne
  `dark:text-gray-100`-Gegenstück, oder verschachtelte Kind-Elemente ohne
  eigene `dark:`-Klasse trotz `dark:`-Klasse am Elternelement). Diese Fälle
  sind **nicht** durch den einfachen `grep -L "dark:"`-Scan erfassbar und
  erfordern eine visuelle/systematische Prüfung jeder nachgelagerten
  (Post-Login-)View durch den Architekten/Entwickler — reine Grep-Heuristik
  reicht hier nicht aus.
- Betroffen ist ausschließlich `frontend/` (Vue 3 + TypeScript). Kein
  Backend-Code (`backend/`) identifiziert, der von diesem Bug berührt wäre.

## Einordnung Pfad
Kein "klein", da nicht auf 1–3 Dateien begrenzt (mindestens 20 Komponenten
ohne jede `dark:`-Unterstützung, plus unbekannte Zahl an Komponenten mit
unvollständigen `dark:`-Klassen quer durch mehrere fachliche Module: Hunde,
Kunden, Kurse, Buchungen, Trainer, Rechnungen, Anamnese). Kein "gross", da
nur ein Sprach-Stack (TypeScript/Vue) betroffen ist, keine Architektur-
Entscheidung nötig ist (Mechanismus/Konvention existiert bereits und
funktioniert an den 34 bereits korrekten Stellen) und die Anforderung klar
ist. Damit: **standard** — mehrere Module, kein Schnittstellenbruch, aber
messbar mehr als "klein"; voller Workflow inkl. Skeptiker sinnvoll, u. a.
damit der Skeptiker die "teilweise dunkle Schrift nach Login"-Fälle
gegen die tatsächlich gerenderten Views verifiziert (Realitätsprüfung über
reinen Grep-Befund hinaus).

## Verwandte offene Punkte (Cross-Check)
- `openspec/triage/20260707174511-anamnesis-template-questions-still-missing-after-edit.md`
  ist **thematisch unabhängig** (Anamnesebogen-Editor-Caching-Bug, bereits
  geschlossen, kein Anwendungscode-Bug). Keine inhaltliche Überschneidung mit
  diesem Dark-Mode-Befund, außer dass zwei der betroffenen Dark-Mode-Dateien
  (`AnamnesisTemplateFormModal.vue`, `AnamnesisDetailModal.vue`,
  `AnamnesisFormModal.vue`) im selben Anamnese-Modul liegen — rein
  zufällige Überschneidung der Verzeichnisstruktur, kein gemeinsamer
  Root-Cause.
- Kein weiterer offener Triage-/Change-Eintrag unter `openspec/triage/` oder
  `openspec/changes/` mit Bezug zu Dark-Mode/Theming gefunden (Verzeichnis-
  Listing geprüft).

## Rückfragen an den User
Keine — Anforderung ist klar genug, um direkt einen Change vorzuschlagen.
Optional (nicht blockierend): Screenshots/konkrete View-Namen der "teilweise
dunkle statt helle Schrift nach Anmeldung"-Fälle wären hilfreich, damit der
Architekt/Entwickler diese gezielt statt nur heuristisch prüft — falls der
User das nicht ohnehin schon parat hat, sammelt der Architekt dies notfalls
selbst per systematischer View-Durchsicht.

## Empfohlene nächste Aktion
`@architect` (Modus A) erstellt einen openspec-Change (Vorschlag Change-ID:
`fix-dark-mode-coverage`) mit Scope:
1. Alle 20 identifizierten Komponenten ohne jede `dark:`-Klasse auf
   konsistente Dark-Mode-Unterstützung bringen (Analogie zu bereits
   korrekten Geschwister-Komponenten, z. B. andere bestehende FormModals
   als Referenzmuster, falls vorhanden — sonst DefaultLayout/PublicLayout
   als Referenz für Farbpalette).
2. Systematische Prüfung der nachgelagerten (Post-Login-)Views auf
   unvollständige/inkonsistente `dark:`-Klassen (dunkler Text auf dunklem
   Hintergrund trotz vorhandener `dark:`-Basisklassen), da dies nicht per
   einfachem Grep vollständig erfassbar ist.
3. Task-Zuständigkeit: ausschließlich `dev-typescript` (reines Frontend/
   Vue/Tailwind, kein `dev-php` nötig).
Anschließend `@skeptic` zur Realitätsprüfung, insbesondere ob Punkt 2
(inkonsistente Textfarben nach Login) durch den Architekten treffsicher
eingegrenzt wurde oder ob dafür visuelle Verifikation (z. B. Playwright-
Screenshot-Vergleich oder manuelle Nutzer-Angabe konkreter Views) nötig ist.
