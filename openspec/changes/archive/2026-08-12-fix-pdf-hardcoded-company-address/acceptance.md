# Abnahme: fix-pdf-hardcoded-company-address

**Status:** bereit-für-user-review

## Prüfmethodik

- `openspec validate fix-pdf-hardcoded-company-address --strict` → **valid** (kein struktureller Defekt).
- `git status` / `git diff` gegen den Working Tree geprüft (noch keine Commits auf `feature/fix-pdf-hardcoded-company-address`; alle Änderungen liegen unstaged bzw. untracked).
- Alle drei geänderten Dateien (`backend/resources/views/pdf/invoice.blade.php`, `backend/resources/views/pdf/anamnesis.blade.php`, `backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php`) per `git diff` gelesen und gegen `tasks.md` T01–T03 sowie `task-T0*.notes.md` abgeglichen — Diff entspricht wortgleich den in `tasks.md` vorgegebenen Code-Schnipseln.
- Beide neuen Partials (`backend/resources/views/pdf/partials/company-info.blade.php`, `backend/resources/views/pdf/partials/company-footer-lines.blade.php`) sowie die neue Testdatei `backend/tests/Feature/Pdf/AnamnesisCompanyDetailsPdfTest.php` vollständig gelesen — Inhalt deckt sich mit `task-T01.notes.md`, `test-report.md`.
- `docker compose exec php composer qa` **selbst ausgeführt** (nicht nur aus `test-report.md` übernommen): Exit-Code `0`, `765 passed (2422 assertions)`, PHPStan `[OK] No errors`, Pint PASS — deckt sich exakt mit den Zahlen aus `test-report.md` (765 passed / 0 failed) und `task-review.md` (Pint 297/298 Dateien je nach Lauf, PHPStan OK).
- `openspec/changes/fix-pdf-hardcoded-company-address/specs/pdf-company-branding/spec.md` gegen die tatsächlichen Blade-Diffs und Partial-Inhalte abgeglichen — Requirements und Szenarien entsprechen dem implementierten Verhalten (Settings-basierte Anzeige, Fallback `'Hundeschule'`, leere Felder statt Fake-Werten, gemeinsame Partials, unveränderte "Erstellt am"-Zeile im Anamnese-Fuß).

## Erfüllt

1. **Strukturelle Validität** — `openspec validate --strict` liefert `valid`.
2. **Vollständigkeit** — alle Akzeptanzkriterien in T01, T02, T03 (`tasks.md`) sind abgehakt; die Task-Notes (`task-T01.notes.md`, `task-T02.notes.md`, `task-T03.notes.md`) dokumentieren jeweils Umsetzung, manuelle Verifikation und `composer qa`-Ergebnis konsistent zum tatsächlichen Diff.
3. **Spec-Konformität** — `specs/pdf-company-branding/spec.md` (3 ADDED Requirements, 7 Szenarien) deckt sich mit dem Diff:
   - `backend/resources/views/pdf/invoice.blade.php` Kopf (Zeile 148-150 alt → `@include('pdf.partials.company-info')`) und Fuß (Zeile 288-289 alt → `@include('pdf.partials.company-footer-lines')`) bestätigt per Diff.
   - `backend/resources/views/pdf/anamnesis.blade.php` Kopf und Fuß analog umgestellt, "Erstellt am"-Zeile bleibt unverändert **nach** dem Footer-Include bestehen (Diff-Zeile mit `Erstellt am: {{ now()->format('d.m.Y H:i') }} Uhr` unverändert vorhanden).
   - Beide Partials laden Werte per `\App\Models\Setting::get(...)` mit den in der Spec/`design.md` Decision 3 festgelegten Fallbacks (`'Hundeschule'` für `company_name`, `''` für alle übrigen Felder) — inhaltlich per `Read` gegen die tatsächlichen Partial-Dateien verifiziert.
   - Kein hartkodierter Platzhaltertext (`Mustermann`, `hundeschule-mustermann`, `Musterstraße 123`, `DE123456789`) mehr im Blade-Quellcode unter `backend/resources/views/pdf/` (Grep-Treffer: keine).
4. **Review-Befunde** — `task-review.md`, Gesamtempfehlung "ok", keine "Muss"- und keine "Sollte"-Befunde. Ein "Könnte"-Befund (DRY zwischen den beiden Partials bezüglich der vier gemeinsam geladenen Settings-Variablen) ist laut Review-Klassifizierung ausdrücklich nicht blockierend und rein stilistisch (kein Performance-/Korrektheitsproblem, da `Setting::get()` gecacht ist). Dokumentiert, keine Nacharbeit nötig.
5. **Testergebnisse** — `test-report.md` Status "alle-gruen", 765 passed / 0 failed, selbst nachvollzogen (identisches Ergebnis bei eigenem `composer qa`-Lauf). Die vom Tester zusätzlich geschlossene Testlücke für T02 (`AnamnesisCompanyDetailsPdfTest.php`, 5 neue Tests) deckt alle Akzeptanzkriterien aus T02 ab, inkl. Fallback-Verhalten ohne gesetzte Settings und Reihenfolge der "Erstellt am"-Zeile — Testinhalt per `Read` geprüft, entspricht den Behauptungen in `test-report.md`.
6. **Non-Goals eingehalten** — keine neuen Settings-Keys angezeigt, keine Änderung an Controllern, `layouts/email.blade.php` oder dem bestehenden Bankdaten-Block; per Diff bestätigt (`git status` zeigt ausschließlich die drei erwarteten geänderten Dateien plus die zwei neuen Partials und die eine neue Testdatei).
7. **PHP-Kompatibilität (CLAUDE.md Abschnitt 4.1)** — Diff enthält ausschließlich Blade-`@php`/`@include`-Syntax und einfache `Setting::get()`-Aufrufe, keine PHP-8.3/8.4-Features. `compat-check` (Teil von `composer qa`) lief ohne Ausgabe = ohne Verstoß.
8. **DB-Portabilität (CLAUDE.md Abschnitt 4.2)** — keine Migration, kein raw SQL in diesem Change; `design.md` dokumentiert das korrekt und der Diff bestätigt es (keine Datei unter `backend/database/` verändert).

## Offen / Nacharbeit

Keine blockierenden Punkte. Zur Transparenz dokumentiert (nicht abnahmekritisch):

- **Nicht-blockierender Stil-Hinweis aus `task-review.md`** ("Könnte"): Die beiden Partials laden `company_name`/`company_street`/`company_zip`/`company_city` mit identischem `Setting::get(...)`-Code. Ein optionales drittes, reines Daten-Partial könnte das weiter vereinheitlichen. Kann bei Gelegenheit in einem künftigen Change aufgegriffen werden, ist aber keine Voraussetzung für diese Abnahme.
- **Branch-Zustand:** Aktuell liegen alle Änderungen unstaged/untracked auf `feature/fix-pdf-hardcoded-company-address`, es existiert noch kein Commit. Vor dem PR (Workflow-Schritt 13/14) sind die Commits gemäß CLAUDE.md Abschnitt 6/7 sowie `~/.claude/WORKFLOW.md` Schritt 7–10 nachzuholen (openspec-Artefakte, T01–T03, Review/Test-Report je als eigene Commits) — das ist regulärer nächster Schritt nach User-Gate 2, kein inhaltlicher Mangel.
- **`.claude/settings.json` (untracked)** — nicht Teil dieses Changes, gehört nicht in den Commit-Scope von `fix-pdf-hardcoded-company-address`.

## Empfehlung an den User

Der Change ist inhaltlich, spec-konform und testseitig vollständig; alle Artefakte sind kohärent zum tatsächlichen Diff, `composer qa` läuft grün (765/765, eigenständig nachgeprüft). Empfehlung: Full-Diff-Sichtung (`git diff` auf dem Feature-Branch, da noch kein `main`-Referenzcommit existiert) durchführen, dann Commits gemäß Workflow nachholen, anschließend `openspec archive fix-pdf-hardcoded-company-address` und PR gemäß CLAUDE.md Abschnitt 7.
