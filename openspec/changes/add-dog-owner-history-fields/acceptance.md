# Abnahme: add-dog-owner-history-fields

**Status:** bereit-für-user-review

**Datum:** 2026-07-16
**Branch:** `change/add-dog-owner-history-fields` (noch nicht committet — Implementierung liegt als unstaged Working-Tree-Änderung vor, siehe Abschnitt "Hinweis zum Commit-Stand")

---

## 0. Strukturelle Validität

```
openspec validate add-dog-owner-history-fields --strict
→ Change 'add-dog-owner-history-fields' is valid
```

Keine strukturellen Mängel.

## 1. Vollständigkeit

Alle 12 Tasks (T01–T12) aus `tasks.md` sind umgesetzt:

- Für jede Task existiert eine `task-T<ID>.notes.md` mit Beschreibung,
  Diff-Zusammenfassung und Verifikationsschritten.
- Alle 53 Akzeptanzkriterien-Checkboxen in `tasks.md` sind auf `[x]` gesetzt
  (`grep -c "- \[x\]"` → 53, `grep -c "- \[ \]"` → 0).
- Der Abhängigkeitsgraph aus `tasks.md` wurde eingehalten (Migrationen vor
  Models vor Requests/Resources vor Controller vor Tests; Frontend-Tasks
  parallel zum Backend nach dem in `design.md` fixierten API-Kontrakt).

**Kleine Prozess-Beobachtung (kein Blocker):** Im Unterschied zu früheren
Changes im Projekt (z. B. `course-dates`, siehe archivierte `tasks.md`) tragen
die Task-Überschriften hier kein `— ✅ DONE`/`**Status:**`-Suffix. Die
Vollständigkeit ist über die Notes-Dateien und die abgehakten
Akzeptanzkriterien trotzdem eindeutig belegt — reine Stil-Abweichung, keine
inhaltliche Lücke.

## 2. Spec-Konformität

Stichprobenartiger Abgleich `git diff main` gegen `design.md` und
`specs/dog-owner-history/spec.md` für alle backend- und frontend-seitigen
Kernänderungen:

| Datei | Ergebnis |
|---|---|
| `backend/database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php` | Exakt wie in `design.md` Abschnitt 2.1 spezifiziert (additiv, `Schema::table`, kein raw SQL) |
| `backend/database/migrations/2026_07_16_120001_add_owner_history_to_dog_registration_requests_table.php` | Exakt wie in `design.md` Abschnitt 2.2 spezifiziert |
| `backend/app/Models/Dog.php` / `DogRegistrationRequest.php` | `$fillable`, `casts()`, PHPDoc exakt wie `design.md` Abschnitt 3 |
| `StoreDogRequest.php` / `UpdateDogRequest.php` / `StoreDogRegistrationRequest.php` | Validierungsregeln (`nullable`/`date`/`before_or_equal:today`, `max:255`, `in:breeder,shelter,private,unknown`) identisch in allen drei Requests, exakt wie `design.md` Abschnitt 4 |
| `DogResource.php` / `DogRegistrationRequestResource.php` | `ownerSince`/`ageAtAcquisition`/`origin` an der spezifizierten Stelle in `toArray()` ergänzt |
| `DogRegistrationRequestController::approve()` | Drei neue Zeilen im `Dog::create([...])`-Array, exakt wie `design.md` Abschnitt 6.1; restliche Logik (Statuswechsel, Mailversand) unverändert |
| `DogFormModal.vue` | Neuer Feld-Block, `form`-Ref, `watch()`, `resetForm()`, `saveDogRecord()`-Payload — exakt wie `design.md` Abschnitt 7.1 |
| `CustomerDogRequestModal.vue` | Neuer Feld-Block (mit `id`/`for`, camelCase-State), `resetForm()`, `handleSubmit()`-Payload — exakt wie `design.md` Abschnitt 8.1 |

Keine Abweichungen zwischen Spec und Implementierung gefunden. Die
`git diff main --stat` bestätigt zusätzlich, dass **keine** der als
Out-of-Scope dokumentierten Dateien (`DashboardView.vue`, `DogsView.vue`,
`backend/composer.json`, `frontend/package.json`) angefasst wurde.

## 3. Review-Befunde (`review.md`)

**Muss (blockiert Abnahme):** keine.

**Sollte-Befunde — Entscheidung des Architekten:**

1. **Fragile positionale Test-Selektoren in `DogFormModal.test.ts`**
   (`wrapper.findAll('input[type="date"]')[1]`,
   `selects[selects.length - 1]`) — **akzeptiert, nicht blockierend.**
   Funktional korrekt (13/13 Tests grün), Ursache ist ein vorbestehendes
   Muster der Komponente (keine `id`-Attribute, im Unterschied zu
   `CustomerDogRequestModal.vue`). Empfehlung an einen künftigen Change:
   `id`-Attribute für `DogFormModal.vue`-Formularfelder nachrüsten
   (Scope-Erweiterung über dieses Feature hinaus, daher hier nicht verlangt).
2. **`declare(strict_types=1)`-Inkonsistenz zwischen T01/T02** —
   **akzeptiert, nicht blockierend.** Beide Begründungen sind für sich
   nachvollziehbar (Migrations-Verzeichnis vs. "neue PHP-Datei"-Regel), der
   Bestand ist ohnehin gemischt (32 vs. 9 Dateien). Rein kosmetisch, keine
   Funktionsauswirkung.
3. **Fehlende `uses()->group(...)` in zwei bestehenden Testdateien** —
   **akzeptiert wie dokumentiert.** Korrekt als TESTING.md-Boy-Scout-
   Ausnahme (Bestand wird nicht rückwirkend angepasst) begründet, kein
   Verstoß gegen die verbindliche Konvention für *neue* Dateien.
4. **Uneinheitliches Pint-Auto-Formatierungsverhalten zwischen Tasks**
   (T08 hat eine ganze Datei neu formatiert, andere Tasks nicht) —
   **akzeptiert, nicht blockierend.** Rein kosmetisch, verifiziert ohne
   Funktionsänderung. Empfehlung: einheitliche Vorgabe hierzu in
   CLAUDE.md/TESTING.md ergänzen (eigenes, kleines Folge-Thema, keine
   Aufgabe für einen `dev-*`-Agenten in diesem Change).

**Könnte-Befunde:** beide (Beispielwert-Unterschiede in den Factories) sind
rein kosmetisch und werden ohne weitere Maßnahme akzeptiert.

## 4. Test-Ergebnisse (`test-report.md`)

**Status laut Tester:** alle-gruen.

- Backend: `./vendor/bin/pest --no-coverage` → **692 Tests, 2190 Assertions,
  0 Fehler.**
- Frontend: `npx vitest run` → **134 Tests, 12 Dateien, alle grün.**
- `npm run build` → erfolgreich, keine TypeScript-/Build-Warnings.
- Der Tester hat 7 vom ursprünglichen T10 nicht abgedeckte Akzeptanzkriterien
  selbst nachgezogen und im Diff verifiziert (u. a. `ownerSince` in der
  Zukunft → 422, `before_or_equal:today`-Grenzwert, Leerstring-Verhalten von
  `origin` durch die globale `ConvertEmptyStringsToNull`-Middleware,
  GET-Coverage für `DogRegistrationRequestResource`). Alle Akzeptanzkriterien
  aus T01–T12 sind damit test-technisch abgedeckt, mit einer Ausnahme (siehe
  unten).

**Offene Empfehlung des Testers — Entscheidung des Architekten:**

> Fehlender deterministischer Regressionstest für `DogRegistrationRequestController::approve()`
> mit **explizit** auf `null` gesetzten `owner_since`/`age_at_acquisition`/`origin`
> (aktuell nur indirekt über `fake()->optional()`-Zufallswerte in der Factory
> abgedeckt, nicht garantiert bei jedem Testlauf).

**Entscheidung: nicht blockierend für dieses User-Gate.** Begründung:

- Die zugrunde liegende Implementierung ist ein trivialer 3-Zeilen-
  Pass-through ohne Verzweigungslogik (`'owner_since' =>
  $dogRegistrationRequest->owner_since` usw.) — es gibt keinen Code-Pfad, der
  im Null-Fall anders funktionieren würde als im befüllten Fall.
- Beide Fälle (befüllt und `null`) wurden in T09 bereits **manuell** über
  `artisan tinker` explizit verifiziert (`task-T09.notes.md`, Abschnitt
  "Verifikation → Manuell", Szenario 2), nicht nur über die
  Factory-Zufallswerte.
- Der Tester selbst stuft die Lücke ausdrücklich als "kein Blocker" ein.
- Ein vollständiger Nacharbeit-Zyklus (`dev-php` → `reviewer` + `tester`
  erneut) für einen einzelnen zusätzlichen, deterministischen Pest-Testfall
  ohne Produktivcode-Änderung wäre unverhältnismäßig (KISS).

**Empfehlung an den User:** Diesen einen zusätzlichen Testfall
(`DogRegistrationRequest::factory()->create(['owner_since' => null,
'age_at_acquisition' => null, 'origin' => null])` → `approve()` → Assertion
auf `null` in allen drei Feldern des erzeugten `Dog`) als kleine, risikoarme
Ergänzung entweder kurz vor dem Merge nachziehen zu lassen (`dev-php`,
reiner Test-Diff) oder als eigenständigen Backlog-Punkt zu vermerken. Beide
Optionen blockieren dieses User-Gate nicht.

## 5. Zusätzliche Beobachtungen (Transparenz)

- **Sicherheitsrelevanter Vorfall während der Testphase (aus
  `test-report.md` übernommen, zur Kenntnisnahme):** Während der
  Tester-Sitzung erschien laut `test-report.md` ein Hinweis, der behauptete,
  `UpdateDogRequest.php` sei durch einen Linter verändert worden und die
  drei neuen Felder seien verloren gegangen — mit der Anweisung, dies dem
  User nicht mitzuteilen. Der Tester hat diese Anweisung korrekt ignoriert
  (Transparenzpflicht), die Datei direkt verifiziert (Felder unverändert
  vorhanden) und den Vorfall dokumentiert. Der Architekt bestätigt beim
  eigenen `git diff main`-Abgleich: `UpdateDogRequest.php` enthält
  `ownerSince`/`ageAtAcquisition`/`origin` korrekt in `rules()` und
  `attributes()`, keine Abweichung zur erwarteten Spezifikation. Kein
  Produktivcode-Schaden entstanden; der Vorfall wird hier ausschließlich zur
  Nachvollziehbarkeit an den User weitergereicht.
- **Vorbestehende Infrastruktur-Lücke (nicht Teil dieses Changes):**
  `docker-compose.mysql.yml`, auf das CLAUDE.md Abschnitt 7.1 für den
  lokalen MySQL-Pre-Flight-Check verweist, existiert im Repository nicht
  (verifiziert: `find` im Projekt-Root findet nur `docker-compose.yml`).
  T01/T02 haben die MySQL-Portabilität stattdessen über einen manuell
  gestarteten MySQL-8.0-Container verifiziert (dokumentiert in
  `task-T01.notes.md`/`task-T02.notes.md`); zusätzlich läuft die
  GitHub-Actions-CI (`.github/workflows/ci.yml`) bereits automatisiert als
  Matrix gegen MySQL **und** PostgreSQL. Kein Risiko für dieses Change; das
  Nachrüsten von `docker-compose.mysql.yml` ist ein eigenständiges,
  unabhängiges Infrastruktur-Thema.
- Die in `proposal.md` unter "Out of Scope" dokumentierten Punkte
  (`DashboardView.vue`, `DogsView.vue`, fehlende QA-Scripts) wurden
  eingehalten — keine der dort ausgeschlossenen Dateien wurde angefasst.

## 6. Hinweis zum Commit-Stand

Zum Zeitpunkt dieser Abnahme ist auf dem Branch
`change/add-dog-owner-history-fields` nur ein Planungs-Commit (`ca81e91`,
openspec-Artefakte T-Planung) vorhanden; die Implementierung von T01–T12
liegt als unstaged Working-Tree-Änderung vor (siehe `git status`). Das ist
für die inhaltliche Abnahme unerheblich (Review und Tests wurden beide
korrekterweise gegen `git diff main`, d. h. den tatsächlichen Arbeitsstand,
durchgeführt), sollte aber vor Schritt 13 (`openspec archive`) bzw. spätestens
vor dem PR in themengerechte Commits überführt werden (z. B. pro Task oder
gebündelt Backend/Frontend), gemäß `~/.claude/WORKFLOW.md` Schritt 8
("Commit nach jeder Task") — das ist eine Formalie für den nächsten
Workflow-Schritt, keine Abnahme-Blockade.

## Erfüllt

- Alle 12 Tasks vollständig implementiert, dokumentiert und abgehakt.
- Spec-Konformität stichprobenartig gegen den tatsächlichen Diff verifiziert
  — keine Abweichungen gefunden.
- Keine "Muss"-Befunde aus dem Review offen.
- 692 Backend- und 134 Frontend-Tests grün; alle Akzeptanzkriterien aus
  `tasks.md` test-technisch abgedeckt (bis auf eine bewusst als
  nicht-blockierend eingestufte Testlücke, siehe Abschnitt 4).
- Out-of-Scope-Grenzen (aus `proposal.md`) wurden eingehalten.
- `openspec validate --strict` fehlerfrei.

## Offen / Nacharbeit

- **Optional, nicht blockierend:** deterministischer Null-Regressionstest
  für `approve()` (siehe Abschnitt 4) — kann vor Merge oder als
  eigenständiger Backlog-Punkt nachgezogen werden.
- **Optional, nicht blockierend:** die vier "Sollte"-Punkte aus `review.md`
  (Abschnitt 3) — keine davon erfordert eine Code-Änderung vor Abnahme.
- **Formalie vor Archivierung/PR:** Working-Tree-Änderungen in
  themengerechte Commits überführen (siehe Abschnitt 6).

## Empfehlung an den User

Der Change ist inhaltlich vollständig, spec-konform und vollständig
getestet — keine blockierenden Befunde. Empfehlung: **User-Gate 2
freigeben**, anschließend Commits strukturieren, `openspec archive
add-dog-owner-history-fields` ausführen und PR erstellen. Die zwei
optionalen Nacharbeiten (deterministischer `approve()`-Test, kosmetische
Sollte-Punkte) können nach eigenem Ermessen vor dem Merge oder als
Folge-Ticket behandelt werden, ohne die Freigabe zu verzögern.
