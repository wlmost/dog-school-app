# Review: T02 — `DEPLOY-WORKFLOW.md`

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

Keine.

## Sollte (vor Merge erledigen, kann diskutiert werden)

Keine.

## Könnte (optional, Verbesserung)

Keine.

## Lob (kurz, was gut gelöst wurde)

- Der neue Zeilenname in der Schritt-Tabelle,
  „**Ensure public storage symlink exists**" (`DEPLOY-WORKFLOW.md:183`),
  stimmt exakt mit dem `name:`-Feld des in T01 implementierten Schritts
  überein (`.github/workflows/deploy.yml:200`) — keine Doku-Drift zwischen
  Workflow-Code und Beschreibung.
- Die neue Zeile ist an der laut Task korrekten Position eingefügt (zwischen
  `rsync` und `Migrationen`), passend zur tatsächlichen Reihenfolge der
  Schritte in `deploy.yml`.
- Der Eintrag in „Geschützte Verzeichnisse"
  (`DEPLOY-WORKFLOW.md:198-202`) macht den Unterschied zu den übrigen
  Einträgen (Nutzerdaten vs. automatisch sichergestellter Symlink) klar
  verständlich, statt den Eintrag unkommentiert in die Liste zu hängen.
- `git diff main -- DEPLOY-WORKFLOW.md` zeigt ausschließlich die zwei
  angekündigten Hunks, keine sonstigen inhaltlichen Änderungen.
  `DEPLOYMENT.md` bleibt unverändert (`git diff main --stat -- DEPLOYMENT.md`
  liefert keine Ausgabe) — die im `design.md` (Abschnitt 5) begründete
  Abgrenzung zwischen den beiden Dokumenten wurde eingehalten.
