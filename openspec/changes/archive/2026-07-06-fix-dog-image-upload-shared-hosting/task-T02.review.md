# Review: T02 — `.user.ini`-/`php.ini`-Templates + Build-Skripte + Doku

**Gesamtempfehlung:** nacharbeit-nötig

## Muss (blockiert Abnahme)

- **[Sicherheit]** `deployment-templates/htaccess/backend-public.user.ini` und `deployment-templates/htaccess/backend-public.php.ini` (neu, kopiert nach `backend/public/.user.ini` bzw. `backend/public/php.ini` laut `build-deployment.sh:245-253` / `build-deployment-docker.sh:341-349`) sind im ausgelieferten Build **öffentlich per HTTP direkt abrufbar und werden als Klartext ausgeliefert** — das ist ein neues Informationsleck, das durch diesen Task eingeführt wird.

  Konkreter Nachweis der Kausalkette (alle Dateien bereits im Repo, unverändert von diesem Change, aber in Kombination mit den neuen T02-Dateien relevant):
  - `deployment-templates/htaccess/root-post-install.htaccess:31`: `RewriteRule ^backend/(?!public/) - [F,L]` blockt **nur** Pfade außerhalb von `backend/public/` — `backend/public/*` ist also für Direktzugriffe explizit erlaubt (negative Lookahead-Regel).
  - `deployment-templates/htaccess/root-post-install.htaccess:36-38`: `RewriteCond %{REQUEST_FILENAME} -f` / `RewriteRule ^ - [L]` liefert **jede real existierende Datei direkt aus**, ohne Rewrite auf `index.php`.
  - `deployment-templates/htaccess/backend-public.htaccess` (T01, siehe Zeilen 1-24 und 29-48) enthält für die interne Rewrite-Kette exakt dieselbe Logik (`RewriteCond %{REQUEST_FILENAME} !-f` vor dem Fallback auf `index.php`, Zeile 45-46) — auch hier greift der Fallback auf `index.php` nur, wenn die Datei **nicht** existiert.
  - Es existiert in `backend-public.htaccess` **keine** `<Files>`/`<FilesMatch>`-Deny-Regel für `.user.ini` oder `php.ini` (verifiziert per Volltext-Read der Datei nach der T01-Änderung).
  - Ergebnis: Ein Request wie `GET /backend/public/.user.ini` bzw. `GET /backend/public/php.ini` wird von Apache als statische Datei mit Klartextinhalt (`upload_max_filesize = 10M`, `post_max_size = 12M`) ausgeliefert — anders als `.htaccess`-Dateien selbst, die durch Apaches eigene, verbreitete Default-Konfiguration (`<Files ".ht*"> Require all denied`, seit Apache 2.3.9 Teil der Referenz-`httpd-default.conf`) i. d. R. bereits serverseitig geschützt sind. `.user.ini` fällt **nicht** unter dieses `.ht*`-Muster und ist daher ungeschützt.

  Die Werte selbst sind unkritisch (keine Secrets), aber das Muster ("neue Konfigurationsdatei ungeschützt ins öffentliche Web-Root legen") ist ein Präzedenzfall mit Verwechslungs-/Erweiterungsrisiko (z. B. falls künftig weitere `.ini`-Overrides mit sensibleren Werten ergänzt werden) und verstößt gegen das in dieser Aufgabe explizit zu prüfende Kriterium "keine Informationslecks".

  **Vorschlag:** In `deployment-templates/htaccess/backend-public.htaccess` (T01) einen Deny-Block für beide neuen Dateien ergänzen, im Stil der bereits im Projekt etablierten dualen 2.2/2.4-Syntax (siehe `deployment-templates/htaccess/frontend-dist.htaccess:1-8` als Vorlage für `<IfModule mod_authz_core.c>`/`<IfModule !mod_authz_core.c>`), z. B.:
  ```apache
  <FilesMatch "^(\.user\.ini|php\.ini)$">
      <IfModule mod_authz_core.c>
          Require all denied
      </IfModule>
      <IfModule !mod_authz_core.c>
          Order allow,deny
          Deny from all
      </IfModule>
  </FilesMatch>
  ```
  Alternativ (einfacher, aber weniger explizit) ein genereller `<FilesMatch "\.ini$">`-Deny-Block. Wichtig: Muss vor bzw. unabhängig vom bestehenden `mod_rewrite`-Block wirken, da die Auslieferung über den statischen Datei-Handler erfolgt, nicht über `index.php`.

## Sollte (vor Merge erledigen, kann diskutiert werden)

Keine weiteren.

## Könnte (optional, Verbesserung)

Keine.

## Lob (kurz, was gut gelöst wurde)

- `build-deployment.sh:245-253`/`:281-283` und `build-deployment-docker.sh:341-349`/`:373-375`: Die Copy- und Verify-Erweiterungen sind **wortgleich** in beiden Skripten umgesetzt (per `git diff main -- build-deployment.sh build-deployment-docker.sh` verglichen) — kein Copy-Paste-Fehler, keine Diskrepanz zwischen den beiden Build-Pfaden.
- Beide neuen `.ini`-Dateien enthalten identische Werte (`upload_max_filesize = 10M`, `post_max_size = 12M`) — konsistent mit der in `design.md` dokumentierten "additiv, kein Konfliktrisiko"-Begründung.
- `DEPLOYMENT.md`-Ergänzung (neuer Abschnitt "Problem: Bild-Upload schlägt fehl") ist klar strukturiert, nennt alle drei Mechanismen und den `user_ini.cache_ttl`-Hinweis (300s) — hilfreich für den Betreiber beim Debugging.
- `task-T02.notes.md` dokumentiert transparent die vorbestehende `composer.json`-Diskrepanz (Root vs. `backend/`) statt sie zu verschweigen oder implizit als eigenes Problem dieses Tasks zu behandeln.
