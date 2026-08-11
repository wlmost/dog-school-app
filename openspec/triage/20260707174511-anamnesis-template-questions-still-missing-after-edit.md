# Triage: Anamnesebogen-Vorlage — Fragen fehlen weiterhin nach Öffnen zum Bearbeiten

**Status: GESCHLOSSEN — kein Anwendungscode-Bug.** Ursache war Client-seitiges
Caching (siehe "Finale Klärung" unten). Kein Fix im Anwendungscode nötig.
Zwei unabhängige Hardening-Nebenfunde bleiben offen zur optionalen
Nachverfolgung (siehe Abschnitt "Offene Nebenfunde").

**Pfad:** trivial (kein Code-Fix für das gemeldete Symptom) — die zwei
offenen Nebenfunde wären, falls der User sie angehen möchte, jeweils
eigene **kleine** Changes.
**Geschätzter Umfang:** 0 Dateien für das gemeldete Symptom selbst.
**Risiko:** niedrig — bestätigt kein Datenverlust- oder Auth-Bug.
**Klarheit:** klar — durch User-Bestätigung aufgelöst.

## Anforderung (Zusammenfassung)
Der User berichtete, dass nach dem Erstellen einer neuen Anamnesebogen-Vorlage
auf Demo/Produktion (MySQL, Shared Hosting) und sofortigem erneutem Öffnen zur
Bearbeitung keine Fragen im Editor erschienen — obwohl der zugehörige Fix
(Commit 213479e, PR #68) kurz zuvor gemergt und deployed worden war.

## Untersuchungsverlauf (Kurzfassung)
1. Code-Trace der kompletten Kette (`store()` → `show()` → `getById()` →
   `AnamnesisView.openTemplateModal()` → `AnamnesisTemplateFormModal`-Watch)
   zeigte keinen Bruch; der exakte gemeldete Ablauf war bereits per echtem
   Playwright-E2E-Test (`frontend/e2e/anamnesis-templates.spec.ts`) lokal
   gegen Postgres verifiziert.
2. Auf Nachfrage bestätigte der User: Umgebung = Demo/Produktion (MySQL),
   Ablauf = sofort nach Anlegen erneut geöffnet, ohne Seiten-Reload.
3. Vertiefte Prüfung auf MySQL-spezifisches Fehlverhalten ergab
   **Gegenevidenz** statt Bestätigung: `.github/workflows/ci.yml` fährt die
   komplette Backend-Testsuite (inkl. `AnamnesisTemplateApiTest.php`) in
   einer Matrix gegen `mysql:8.0` **und** `postgres:16`; `gh pr view 68`
   bestätigt beide Jobs ("Backend tests (mysql)", "Backend tests (pgsql)")
   als `SUCCESS`. Ein Backend-Logikfehler spezifisch auf MySQL war damit
   unwahrscheinlich.
4. Stattdessen wurde ein evidenzbasierter Alternativverdacht auf
   Deploy-/Cache-Ebene gefunden: automatischer Deploy lief 15:14–15:36 UTC
   direkt nach dem Merge; `deploy.yml` resettet nach dem Deploy zwar
   Config-/View-Cache, aber **keinen PHP-OPcache**; zusätzlich könnte ein
   bereits vor dem Deploy geöffneter Browser-Tab mit altem JS-Bundle im
   Speicher exakt dieses Symptom erzeugen (alte `openTemplateModal()`-Logik
   ohne `getById()`-Nachladen).

## Finale Klärung
**Der User hat bestätigt: Nach Löschen des Browser-Caches funktioniert es.**
Damit ist verifiziert, dass es sich um einen **Client-seitigen
Cache-Effekt** handelte (vermutlich ein alter, im Browser/Tab
zwischengespeicherter JS-Bundle-Stand von vor dem Fix bzw. vor dem letzten
Deploy) — **kein Bug im aktuellen Anwendungscode**. Der ursprüngliche Fix
(213479e) funktioniert wie vorgesehen, sowohl gegen Postgres (lokal, CI) als
auch gegen MySQL (CI). Ein möglicher zusätzlicher Beitrag des fehlenden
OPcache-Resets im Deploy-Workflow (Nebenfund unten) konnte durch die
Cache-Löschung beim User nicht isoliert werden, bleibt aber als plausibler
Mitverursacher / künftiges Risiko bestehen.

## Offene Nebenfunde (unabhängig vom geschlossenen Bug, jeweils eigener
möglicher Change — Entscheidung liegt beim User)

### Nebenfund 1 — Kein OPcache-Reset im Deploy-Workflow
`.github/workflows/deploy.yml` (Schritt "Rebuild application caches",
ca. Zeile 216-230) führt nach `php artisan migrate --force` nur
`config:clear && config:cache && view:cache` aus. Es gibt **keinen**
PHP-OPcache-Reset (`opcache_reset()` bzw. PHP-FPM-Neustart). Auf
Shared-Hosting-Umgebungen mit aktiviertem OPcache und langer
`opcache.revalidate_freq`/`validate_timestamps=0`-Konfiguration kann das
dazu führen, dass nach einem Deploy für eine Weile weiterhin **alter
PHP-Bytecode** ausgeführt wird — ein struktureller Nährboden für künftige
"Phantom-Bugs" (Nutzer sehen scheinbar unbehobene oder wieder aufgetretene
Fehler kurz nach einem eigentlich erfolgreichen Deploy). Betrifft
potenziell alle Deploys, nicht nur diesen Change.

### Nebenfund 2 — `docker-compose.mysql.yml` fehlt im Repo
`CLAUDE.md` (Abschnitt 5 und 7.1) referenziert `docker-compose.mysql.yml`
als lokale MySQL-Testumgebung, u. a. für den dort vorgeschriebenen
Pre-Flight-Check "vor `git push`/PR". Diese Datei **existiert nicht** im
Repository (nur `docker-compose.yml` mit PostgreSQL). Der dokumentierte
lokale MySQL-Check ist damit aktuell für keinen Entwickler-Agenten
tatsächlich ausführbar; die einzige reale MySQL-Verifikation läuft aktuell
ausschließlich über die CI-Matrix (siehe Untersuchungsverlauf Punkt 3), nicht
lokal vor dem Push.

## Empfohlene nächste Aktion
**Diesen Fall schließen — kein weiterer Change für das gemeldete Symptom
nötig.** Kein `@architect`-Aufruf erforderlich.

**Optional, getrennt vom User zu entscheiden:** ein kleiner
Hardening-Change, der beide Nebenfunde adressiert:
- OPcache-Reset-Schritt in `.github/workflows/deploy.yml` ergänzen
  (z. B. `opcache_reset()` via Artisan-Command/Endpoint oder PHP-FPM-Reload,
  abhängig davon, was der jeweilige Shared-Hosting-Anbieter erlaubt —
  CLAUDE.md Abschnitt 3: kein `exec`/`shell_exec` verfügbar, daher
  ggf. über einen eigenen Artisan-Command lösen).
- `docker-compose.mysql.yml` als Overlay nachreichen, analog zur
  bestehenden `docker-compose.yml`, damit der in `CLAUDE.md` dokumentierte
  lokale MySQL-Pre-Flight-Check tatsächlich ausführbar wird.

Falls der User diesen Hardening-Change möchte: neue Anforderung formulieren
und regulär durch `@triage` laufen lassen (voraussichtlich Pfad "klein").
