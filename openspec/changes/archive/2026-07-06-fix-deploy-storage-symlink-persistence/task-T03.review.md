# Review: T03 — `.github/workflows/ci.yml` (neuer Job `deploy-workflow-lint`)

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

Keine.

## Sollte (vor Merge erledigen, kann diskutiert werden)

Keine.

## Könnte (optional, Verbesserung)

- **[Testbarkeit/Robustheit]** `.github/workflows/ci.yml:150-151`: Die
  beiden `grep -q`-Muster (`"exclude='backend/public/storage'"` und
  `"artisan storage:link"`) suchen ohne Kontext-Anker über die gesamte
  Datei. Aktuell verifiziert kein falsch-positives/-negatives Ergebnis
  (siehe `task-T03.notes.md`, Abschnitt „Durchgeführte lokale
  Verifikation"), da die exakten Strings in `deploy.yml` nur an der
  relevanten Stelle vorkommen (der erklärende Kommentar in
  `deploy.yml:186-199` verwendet die Formulierung „Laravel's storage:link
  command", nicht „artisan storage:link" — kollidiert also nicht mit dem
  zweiten Grep-Muster). Kein Handlungsbedarf; nur als Hinweis, dass der
  Schutz rein textbasiert ist und bei künftigen Umformulierungen des
  Kommentarblocks in `deploy.yml` erneut manuell gegengeprüft werden
  sollte (in `design.md` Abschnitt 6 bereits bewusst als „bewusst kein
  vollwertiger Linter" (YAGNI) begründet — akzeptabel für den hier
  verfolgten, engen Regressionsschutz).

## Lob (kurz, was gut gelöst wurde)

- Der neue Job `deploy-workflow-lint` (`.github/workflows/ci.yml:145-158`)
  ist minimal gehalten (kein Docker, kein zusätzlicher Tool-Download),
  konsistent mit dem `Checkout current branch`-Namensmuster der
  bestehenden Jobs `backend-tests`/`frontend-tests`.
- Beide Grep-Muster wurden gegen den tatsächlich in T01 implementierten
  Wortlaut verifiziert (`--exclude='backend/public/storage'` in
  `deploy.yml:176`, `php artisan storage:link` in `deploy.yml:205`) — beim
  eigenen Nachvollzug dieses Reviews ebenfalls bestätigt, keine
  Falsch-Positiv-/Negativ-Diskrepanz.
- YAML-Syntax von `ci.yml` wurde verifiziert (erneuter Lauf von `python3
  -c "import yaml; yaml.safe_load(open('.github/workflows/ci.yml'))"`
  während dieses Reviews, fehlerfrei).
- `git diff main -- .github/workflows/ci.yml` zeigt ausschließlich den
  neuen Job, keine Änderung an `backend-tests`/`frontend-tests`.
- Die Entscheidung gegen `actionlint`/`yamllint` als zusätzliche
  Abhängigkeit ist im Sinne von YAGNI/KISS nachvollziehbar begründet und im
  Job selbst konsequent umgesetzt (reines `grep`, kein neuer Fremd-Task).
