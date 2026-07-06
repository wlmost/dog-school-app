# Review: T04 — `backend/public/.htaccess.production` aufräumen

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

Keine.

## Sollte (vor Merge erledigen, kann diskutiert werden)

Keine.

## Könnte (optional, Verbesserung)

Keine.

## Lob (kurz, was gut gelöst wurde)

- Entscheidung "Entfernen statt Umbenennen" ist in `task-T04.notes.md` mit fünf konkreten, nachvollziehbaren Gründen belegt (Redundanz zu T01/T02, kein eigenständiger Anwendungsfall für 50M-Werte, YAGNI, Fehlsuche-Risiko, KISS) — entspricht der in `tasks.md` als "nicht bindend vorgegeben" formulierten Entscheidungsfreiheit.
- Verifikation vor der Löschung sauber dokumentiert (`grep -rn "htaccess.production"` vor und nach der Änderung), `git rm` korrekt verwendet (`git status` zeigt die Datei als "deleted", nicht nur lokal entfernt).
- Nachvollziehbare Abgrenzung, warum verbleibende Treffer in `openspec/changes/fix-dog-image-upload-shared-hosting/*.md` unkritisch sind (Prozessdokumentation des Changes selbst, keine Betriebsdoku) — passt zum Akzeptanzkriterium, das sich explizit auf Build-Skripte und `DEPLOYMENT.md` bezieht.
- Kein Restrisiko: `grep -rn "htaccess.production" --include="*.sh" --include="*.md" --include="*.yml" --include="*.yaml" .` (eigene Nachprüfung außerhalb der Change-Dokumente) liefert keinen Treffer mehr.
