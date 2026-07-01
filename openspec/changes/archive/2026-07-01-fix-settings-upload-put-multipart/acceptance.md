# Abnahme: fix-settings-upload-put-multipart

**Status:** bereit-für-user-review

---

## 0. Strukturelle Validität

```
$ openspec validate fix-settings-upload-put-multipart --strict
Change 'fix-settings-upload-put-multipart' is valid
```

Strukturell einwandfrei (Frontmatter, Pflichtfelder, Spec-Delta-Form). Deckt
sich mit dem bereits vom Skeptiker in `verification.md` (Z.7-9) gemeldeten
Ergebnis des einfachen `validate`-Laufs.

---

## 1. Vollständigkeit (`tasks.md`)

T01 ist vollständig als erledigt markiert. Von 11 Akzeptanzkriterien sind
10 mit `[x]` abgehakt, eines mit `[~]` (dokumentierte, begründete
Abweichung: `npm run lint` existiert im Projekt nicht — kein `lint`-Script
in `frontend/package.json`, keine ESLint-Config; bestätigt per eigenem
`grep -i lint frontend/package.json` → kein Treffer). Das ist ein
bestehender Projektzustand außerhalb des Scopes von T01, korrekt dokumentiert
in `task-T01.notes.md` und `task-T01.test-report.md`, keine Nacharbeit
erforderlich.

---

## 2. Spec-Konformität (Diff gegen `design.md`/`spec.md`)

Aktueller Stand von `frontend/src/api/settings.ts` (per `Read`, Zeilen 35-66)
und `git diff HEAD -- frontend/src/api/settings.ts` geprüft:

- `apiClient.put(...)` → `apiClient.post(...)`: bestätigt (Zeile 60).
- Endpunkt `/api/v1/settings` unverändert: bestätigt.
- Header `Content-Type: multipart/form-data` unverändert: bestätigt.
- `FormData`-Feld `_method=PUT`: vorhanden, aber **anders platziert als im
  ursprünglichen `design.md`-Codeblock** (Z.140: `formData.append('_method',
  'PUT')` **vor** der `forEach`-Schleife). Der tatsächliche Code setzt
  stattdessen `formData.set('_method', 'PUT')` **nach** der Schleife
  (Zeilen 48-51), exakt wie vom Reviewer in `task-T01.review.md`
  ("Sollte"-Befund 2, Z.27-46) vorgeschlagen.

  Das ist mit `specs/settings-file-upload-transport/spec.md` konform: die
  Requirement-Formulierung (Z.25-34) und alle vier Szenarien (Z.36-68)
  verlangen lediglich, dass der `FormData`-Body ein Feld `_method` mit Wert
  `PUT` enthält und der Request per POST an `/api/v1/settings` geht — die
  interne Reihenfolge/Methode (`.append()` vs. `.set()`, vor vs. nach der
  Schleife) ist nicht Teil der Spec-Formulierung. Die Änderung ist also
  spec-konform und zusätzlich robuster (verhindert, dass ein hypothetischer
  `settings`-Schlüssel `_method` den Override per PHP-Multipart-"last-wins"
  überschreiben könnte).

  **Randnotiz (nicht blockierend):** `tasks.md` Akzeptanzkriterium 1 nennt
  wörtlich `formData.append('_method', 'PUT')` — das ist nach dieser
  nachträglichen Änderung textlich leicht veraltet (jetzt `.set()`, andere
  Position), der fachliche Kern des Kriteriums (Feld wird vor dem Absenden
  gesetzt) bleibt aber erfüllt. Empfehlung: `tasks.md` bei Gelegenheit
  redaktionell nachziehen, kein Merge-Blocker.

- Kein Backend-Code angefasst: bestätigt, `git status --short` zeigt nur
  `frontend/src/api/settings.ts` (modifiziert) und `frontend/src/api/settings.test.ts`
  (neu) als inhaltliche Änderungen dieses Changes.
- Route `backend/routes/api.php:195` unangetastet: vom Reviewer bereits
  bestätigt (`task-T01.review.md`, "Lob"-Abschnitt), stichprobenartig
  gegengeprüft — keine Backend-Datei im Diff.

**Prozess-Hinweis:** Diese konkrete Korrektur (`.set()` nach der Schleife)
wurde nicht über den regulären `dev-javascript`-Task-Zyklus (Schritt 10 der
`WORKFLOW.md`) eingespielt, sondern manuell vom Orchestrator nachgezogen,
nachdem der Reviewer sie als "Sollte"-Befund vorgeschlagen hatte. Dadurch
wurde der finale Diff nicht noch einmal formal vom Reviewer gegengelesen.
Ich habe das als Architekt eigenständig nachgeprüft (Diff gelesen, Tests
selbst ausgeführt, s. Abschnitt 4) und keine Abweichung von Spec oder
Reviewer-Empfehlung gefunden — inhaltlich unbedenklich, aber eine formale
Re-Review-Runde wäre nach Workflow-Buchstaben der sauberere Weg gewesen.
Kein Blocker für dieses kleine, nachträglich verifizierte Detail.

---

## 3. Review-Befunde (`task-T01.review.md`)

**Muss:** keine gemeldet.

**Sollte (2 Befunde):**

1. Englische Test-Beschreibungen in `settings.test.ts` statt des im
   Frontend-Testbestand etablierten deutschen Musters — **bewusst nicht
   umgesetzt**. Nicht blockierend laut Reviewer selbst ("kann diskutiert
   werden"), betrifft nur Lesbarkeit/Konsistenz, keine Funktionalität.
   Akzeptabel dokumentiert zu lassen, siehe Auftrag Punkt 3.
2. `_method`-Feld per `.append()` vor der Schleife statt robusterem
   `.set()` nach der Schleife — **umgesetzt**, siehe Abschnitt 2 oben.
   Per eigenem `git diff` verifiziert: exakt der vom Reviewer vorgeschlagene
   Code (`formData.set('_method', 'PUT')` nach `forEach`).

**Könnte:** zwei rein informative Punkte (Kommentar-Duplikation zu
`design.md`, fehlender `boundary`-Parameter im Header, letzteres außerhalb
des Diffs) — beide ohne Handlungsbedarf laut Reviewer selbst, keine
Nacharbeit nötig.

---

## 4. Testergebnisse

Eigenständig im `frontend/`-Verzeichnis erneut ausgeführt (nicht nur auf
Aussage des Orchestrators verlassen):

```
$ npx vitest run src/api/settings.test.ts
 Test Files  1 passed (1)
      Tests  10 passed (10)

$ npm run test -- --run
 Test Files  11 passed (11)
      Tests  121 passed (121)

$ npm run build
> vue-tsc -b && vite build
✓ 636 modules transformed.
✓ built in 1.33s
(keine Fehler, keine Warnings im Output)
```

Ergebnis deckt sich mit den Angaben aus `task-T01.test-report.md`
(121 Tests, `alle-gruen`) und der Aussage des Orchestrators (10/10 in
`settings.test.ts`, Build fehlerfrei) — beides eigenständig bestätigt,
nicht nur übernommen.

**Ungetestete Akzeptanzkriterien:** keine. Alle funktionalen Kriterien aus
`tasks.md` sind durch Tests in `settings.test.ts` abgedeckt (Methode,
Endpunkt, Header, `_method`-Feld, Text-/Datei-Felder, Skip von
`null`/`undefined`, plus vier vom Tester ergänzte Grenzfälle: leeres
Argument, Logo+Favicon gleichzeitig, Nicht-String-Primitives, Rückgabewert).
Das einzige nicht ausführbare Kriterium (`npm run lint`) ist strukturell
bedingt (Script existiert nicht) und kein Test-, sondern ein
Tooling-Lücken-Thema außerhalb des Scopes.

---

## Erfüllt

- Alle T01-Akzeptanzkriterien erfüllt (bis auf das nicht-existente
  `lint`-Script, dokumentiert und nicht blockierend).
- `openspec validate --strict` erfolgreich.
- Spec-Deltas (`specs/settings-file-upload-transport/spec.md`) korrekt im
  Code umgesetzt, inklusive der nachträglichen, robusteren
  `.set()`-nach-der-Schleife-Variante.
- Beide "Sollte"-Reviewbefunde behandelt (einer umgesetzt, einer bewusst
  und nachvollziehbar zurückgestellt).
- Alle Tests grün (121/121 Frontend, davon 10/10 neu in
  `settings.test.ts`), Build fehlerfrei — von mir eigenständig
  nachvollzogen, nicht nur übernommen.
- Kein Backend-Code angefasst, wie im Scope vorgesehen.

## Offen / Nacharbeit (nicht blockierend)

- `tasks.md`, Akzeptanzkriterium 1: Wortlaut nennt noch `formData.append(...)`
  vor der Schleife, tatsächlicher Code nutzt jetzt `formData.set(...)` nach
  der Schleife (funktional gleichwertig, spec-konform). Redaktionelle
  Auffrischung empfohlen, kein Merge-Blocker.
- Die nachträgliche `.set()`-Korrektur wurde außerhalb des regulären
  `dev-javascript`-Review-Zyklus eingespielt und dadurch nicht noch einmal
  formal vom Reviewer gegengelesen. Ich habe das als Architekt eigenständig
  verifiziert (Diff, Tests, Build); für zukünftige manuelle
  Zwischenkorrekturen empfiehlt sich dennoch, den regulären
  Dev-Review-Zyklus einzuhalten, um diese Lücke zu vermeiden.

## Empfehlung an den User

Der Change ist inhaltlich, spec- und testseitig abnahmereif; beide offenen
Punkte sind rein redaktionell/prozessual und blockieren nicht. Empfehlung:
Freigabe erteilen und mit Schritt 13 (`openspec archive`) fortfahren.
