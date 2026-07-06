# Review: T01 — Apache `LimitRequestBody` + `php_value`-Fallback in `backend-public.htaccess`

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

Keine.

## Sollte (vor Merge erledigen, kann diskutiert werden)

Keine.

## Könnte (optional, Verbesserung)

Keine.

## Lob (kurz, was gut gelöst wurde)

- `deployment-templates/htaccess/backend-public.htaccess:1-20`: Der neue Block wurde exakt wie in `tasks.md`/`design.md` spezifiziert vor dem bestehenden `<IfModule mod_negotiation.c>`-Block (jetzt Zeile 22) eingefügt. Ein Diff gegen `main` zeigt ausschließlich Additions am Dateianfang — kein bestehender Rewrite-/Header-Block wurde verändert (verifiziert per `git diff main -- deployment-templates/htaccess/backend-public.htaccess`).
- `LimitRequestBody 10485760` und beide `php_value`-`IfModule`-Blöcke (`mod_php.c` und `mod_php8.c`) sind syntaktisch korrekte Apache-Direktiven, additiv und mit nachvollziehbarem Kommentar zur Wahl der Werte (2x Laravel-Limit, `post_max_size` 2 MB über `upload_max_filesize`).
- `task-T01.notes.md` dokumentiert transparent, dass ein echter Shared-Hosting-Smoke-Test nach Deployment nötig bleibt (Docker-Lokalumgebung nutzt Nginx, kein Apache) — genau das vom Akzeptanzkriterium geforderte Vorgehen.
