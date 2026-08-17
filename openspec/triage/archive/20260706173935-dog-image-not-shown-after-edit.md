# Triage: dog-image-not-shown-after-edit

**Pfad:** standard
**Geschätzter Umfang:** vermutlich 1 Kern-Datei (`.github/workflows/deploy.yml`), plus Dokumentations-Update (`DEPLOYMENT.md`) und ggf. ein Absicherungs-Test/Lint für die Pipeline-Datei — Sprache: YAML/Shell (GitHub-Actions-Workflow), kein PHP-/Vue-Anwendungscode betroffen
**Risiko:** mittel-hoch — kein Auth-/Datenmodell-/Migrationsbezug, aber es wird die **produktive Deployment-Pipeline** selbst geändert (jeder künftige Merge nach `main` löst automatisch einen Deploy aus); ein Fehler hier kann *alle* Datei-Uploads (nicht nur Hundebilder) auf Shared Hosting bei jedem Deploy erneut brechen oder im schlimmsten Fall den Deploy-Job insgesamt zum Scheitern bringen
**Klarheit:** mehrdeutig — Root-Cause ist eine sehr plausible, aber ohne Server-Zugriff nicht zu 100 % verifizierbare Hypothese (siehe unten); zusätzlich ist unklar, welcher `dev-*`-Agent laut `CLAUDE.md` für `.github/workflows/*.yml` zuständig ist

## Anforderung (Zusammenfassung)

Ein Kunde meldet aus der Produktionsumgebung: Beim Hochladen eines Hundebilds
in der Bearbeitungsmaske erscheint das Bild zunächst, verschwindet aber nach
dem Schließen der Maske aus der Kachel-Ansicht und bleibt auch beim erneuten
Öffnen der Bearbeitungsmaske unsichtbar. Erwartet wird, dass ein hochgeladenes
Profilbild dauerhaft gespeichert und überall angezeigt wird, wo es referenziert
ist (Formular-Vorschau, Kachel/Liste).

## Bezug zum bereits gemergten Fix vom selben Tag (Commit `043ba88`)

Der heute gemergte Change `fix-dog-image-upload-shared-hosting` hat zwei
unabhängige Ursachen behoben:

1. Fehlendes Apache-Äquivalent zu `client_max_body_size` (`.htaccess
   LimitRequestBody`, `.user.ini`, `php.ini`) für Shared Hosting.
2. `DogFormModal.vue` schloss das Formular, auch wenn der Bild-Upload
   fehlschlug (Fehler wurde nur als Toast angezeigt).

**Ich habe den aktuellen Code geprüft** (`frontend/src/components/DogFormModal.vue:432-495`):
Der Fix ist vorhanden und sieht korrekt aus — `uploadDogImage()` gibt bei
Fehlschlag `false` zurück, `handleSubmit()` bricht dann ab (`return` in Zeile
470), setzt einen persistenten `error`-Banner (Zeile 442) und schließt das
Modal **nicht** (`emit('saved')`/`closeModal()` werden übersprungen). Das
widerspricht der aktuellen Meldung: Der Kunde berichtet nicht von einer
sichtbaren Fehlermeldung, sondern von einem Bild, das kommentarlos
verschwindet — das deutet darauf hin, dass **kein Request-Fehler** auftritt
(sonst hätte der Kunde jetzt den neuen Error-Banner gesehen), sondern dass
der Upload serverseitig als Erfolg zurückgemeldet wird, das Bild aber
anschließend nicht mehr **erreichbar** ist.

## Neuer Root-Cause-Verdacht (verifiziert im Code, aber nicht am Server bestätigt)

**Hypothese:** Der öffentliche Storage-Symlink (`backend/public/storage` →
`backend/storage/app/public`) wird bei jedem automatisierten Deploy über
`.github/workflows/deploy.yml` gelöscht und nirgends wieder angelegt.

Belege:

- `backend/.gitignore:5` (`/public/storage`) — der Symlink ist absichtlich
  nicht versioniert (Laravel-Standard, korrekt).
- `DEPLOYMENT.md:600-608` — `php artisan storage:link` wird nur als
  **manueller Erstinstallations-Schritt** dokumentiert ("4. Storage-Verzeichnisse
  vorbereiten"), nicht als Teil eines wiederholbaren Deploy-Prozesses.
- `.github/workflows/deploy.yml:171-182` (Schritt „Deploy files via rsync"):
  ```
  rsync -az --delete \
    --exclude='backend/.env' \
    --exclude='backend/storage/app/' \
    --exclude='backend/storage/logs/' \
    --exclude='backend/storage/framework/sessions/' \
    --exclude='backend/storage/framework/cache/' \
    --exclude='backend/storage/framework/views/' \
    ...
  ```
  `backend/public/storage` ist **nicht** in der Exclude-Liste. Da der
  Symlink laut `.gitignore` nie im Git-Checkout und damit nie im
  `deploy-package/` der CI-Umgebung existiert, aber `--delete` gesetzt ist,
  löscht rsync den auf dem Server bereits vorhandenen Symlink bei **jedem**
  automatisierten Deploy.
- In `.github/workflows/deploy.yml` (komplett gelesen, alle 12 Schritte)
  gibt es **keinen** Schritt, der `php artisan storage:link` nach dem Sync
  erneut ausführt. Auch `build-deployment.sh`/`build-deployment-docker.sh`
  enthalten keinen entsprechenden SSH-Aufruf für den automatisierten Pfad.
- Die Route für ausgelieferte Storage-Dateien
  (`deployment-templates/htaccess/root-post-install.htaccess:23`,
  `RewriteRule ^storage/(.*)$ backend/public/storage/$1 [L]`) funktioniert
  nur, solange der Symlink existiert — fehlt er, führt jede
  `profileImageUrl` (erzeugt über `Storage::disk('public')->url()`) zu
  einem 404, obwohl der Datensatz in der DB korrekt auf den gespeicherten
  Pfad zeigt.
- **Der clientseitige "es erscheint zunächst"-Effekt ist dadurch erklärbar,
  dass `imagePreview` in `DogFormModal.vue:320-324` ein lokales
  `FileReader`-Objekt-URL/Base64 des ausgewählten Files ist, das immer
  angezeigt wird — unabhängig davon, ob der Server-Request je erfolgreich
  war oder ob die resultierende URL später erreichbar ist.**

**Cross-Feature-Auswirkung (relevant für Risiko-Einstufung):** Derselbe
Symlink wird laut `FILE-UPLOAD-SYSTEM.md:46,156-165,325-333` auch für
`training-attachments` verwendet. Wäre die Hypothese korrekt, betrifft der
Bug **nicht nur Hundebilder**, sondern potenziell jede über die `public`-Disk
ausgelieferte Datei in Produktion — und das bei **jedem** künftigen
automatisierten Deploy erneut, bis der Fix greift.

## Warum "mehrdeutig" statt "bestätigt"

- Ich habe **keinen Zugriff auf den Produktionsserver** und kann daher nicht
  direkt prüfen, ob `backend/public/storage` dort aktuell fehlt oder
  existiert. Die Analyse stützt sich ausschließlich auf den Repo-Code der
  Pipeline.
- Alternativ denkbare (aber durch Code-Prüfung nicht bestätigte) Ursachen,
  die ausgeschlossen werden sollten, bevor implementiert wird:
  - `APP_URL`/`ASSET_URL` in der Produktions-`.env` zeigt auf eine falsche
    Basis-URL, wodurch `Storage::url()` einen falschen Host erzeugt
    (Server-Konfiguration außerhalb des Repos — **ungeprüfte Referenz**).
  - Datei-Berechtigungen (`chmod -R 775 storage`, `DEPLOYMENT.md:606`)
    wurden nach einem der letzten Deploys nicht erneut gesetzt.
  - Es handelt sich um einen erneuten Fall der bereits heute gefixten
    Body-Size-Problematik, falls der Body-Size-Fix (Commit `043ba88`) auf
    dem konkreten Kundenserver noch nicht ausgerollt wurde (Timing-Frage:
    wurde nach `043ba88` bereits ein automatischer Deploy gelaufen, *bevor*
    der Kunde getestet hat?).
- **Ungeprüfte Referenz:** Ob und wann die automatisierte Deploy-Pipeline
  (`.github/workflows/deploy.yml`, eingeführt in Commit `c1a3e09`,
  "feat: add GitHub Actions deploy workflow for shared hosting") für dieses
  Produktionsziel bereits gelaufen ist, und ob dort initial per Hand
  `php artisan storage:link` ausgeführt wurde, kann ich aus dem Repo nicht
  feststellen.

## Warum Pfad "standard" statt "klein"

Der reine Code-Umfang der wahrscheinlichsten Lösung ist klein (im Kern eine
Ergänzung in `.github/workflows/deploy.yml`: Symlink beim rsync von der
`--delete`-Bereinigung ausnehmen **und/oder** `php artisan storage:link`
idempotent nach dem Sync erneut ausführen). Trotzdem empfehle ich den vollen
Workflow, weil:

1. **Risiko-Konzentration:** Es wird direkt die produktive
   Deployment-Pipeline verändert, die bei jedem Merge nach `main` automatisch
   ausgeführt wird (`workflow_run`-Trigger). Ein Fehler in `rsync`-Excludes
   oder ein nicht-idempotenter `storage:link`-Aufruf kann den Deploy-Job
   künftig fehlschlagen lassen oder erneut Dateien beschädigen — dafür gibt
   es keine lokale CI-Absicherung, die das vor dem echten Deploy abfängt.
2. **Cross-Feature-Wirkung:** Betrifft potenziell mehrere Capabilities
   (Hundebilder **und** Trainings-Anhänge), nicht nur das gemeldete Symptom.
3. **Unbestätigte Hypothese:** Ohne Server-Zugriff bleibt die Root-Cause
   eine begründete Vermutung. Der Skeptiker-Schritt und ein User-Spec-Gate
   vor der Umsetzung sind hier sinnvoller als beim "klein"-Pfad direkt in die
   Implementierung zu gehen.
4. **Unklare Agenten-Zuständigkeit:** `CLAUDE.md` Abschnitt 2/7.2 listet
   `dev-php` (u. a. `app/`, `routes/`, `database/`, `config/`, `tests/`,
   Blade) und `dev-javascript` (`resources/js/**/*.vue`, JS) — beide
   Pfad-Muster passen nicht exakt auf `.github/workflows/*.yml`. Zusätzlich
   weicht die reale Repo-Struktur (`frontend/`, `backend/` als getrennte
   Top-Level-Ordner) von der in `CLAUDE.md` beschriebenen
   `resources/js/`-Struktur ab. Der Architekt sollte diese Zuordnung im
   `design.md` explizit klären, statt dass ich das hier vorwegnehme.

## Rückfragen an den User

1. **BEANTWORTET (2026-07-06):** User bestätigt: Es gibt **keinen** Symlink
   auf dem Produktionsserver. `public` und `storage` liegen als normale,
   voneinander unabhängige Verzeichnisse auf derselben Ebene — kein
   `public/storage` → `storage/app/public`-Symlink vorhanden. Damit ist die
   Root-Cause-Hypothese **bestätigt**: Hochgeladene Dateien landen korrekt in
   `storage/app/public/...`, sind aber über die öffentliche URL nie erreichbar
   (404), da der Auslieferungspfad fehlt. Dies erklärt das gemeldete Verhalten
   vollständig, unabhängig davon, ob der Symlink initial nie erzeugt wurde
   oder durch einen früheren Deploy gelöscht wurde — die Lösung ist in beiden
   Fällen identisch (siehe „Empfohlene nächste Aktion" oben).

   ~~Kann jemand mit Server-/Hoster-Zugriff kurz prüfen, ob auf dem
   Produktionsserver aktuell ein gültiger Symlink unter
   `<DEPLOY_PATH>/backend/public/storage` existiert (z. B. `ls -la
   backend/public/ | grep storage`)? Das würde die Hypothese direkt
   bestätigen oder widerlegen.~~
2. **BEANTWORTET (2026-07-06):** User bestätigt: Seit dem letzten Fix wurde
   bereits ein automatischer Deploy über den GitHub-Actions-Workflow
   durchgeführt (löst nach jedem Merge nach `main` aus). Der Body-Size-Fix
   aus `043ba88` ist also live — das schließt "Fix noch nicht ausgerollt"
   als Alternativerklärung aus und untermauert zusätzlich die
   Symlink-Hypothese: Der automatisierte Deploy-Lauf ist der Mechanismus,
   der den Symlink (falls er je existierte) entfernt bzw. nie neu anlegt,
   da `deploy.yml` keinen `storage:link`-Schritt enthält.
3. Zeigt die Browser-Netzwerk-Konsole beim erneuten Öffnen der
   Bearbeitungsmaske einen 404 (oder anderen Fehlerstatus) für die
   Bild-URL, oder wird gar keine `profileImageUrl` vom Server geliefert
   (leeres Feld)? Das unterscheidet "Symlink fehlt" von "DB-Feld leer".
4. **BEANTWORTET (2026-07-06):** User bestätigt: Es ist dieselbe
   Produktionsumgebung wie beim heutigen Fix (`043ba88`).

## Empfohlene nächste Aktion

`@architect` (Modus A) erstellt einen neuen Change (Vorschlag für
change-id: `fix-deploy-storage-symlink-persistence` o. Ä.), sobald mindestens
Rückfrage 1 beantwortet ist (im Zweifel parallel zur Rückfrage bereits die
Spec entwerfen, da die Lösung in beiden Fällen — Symlink fehlt initial oder
wird bei jedem Deploy gelöscht — identisch ist: rsync-Exclude **und**
idempotenter `storage:link`-Schritt). Vorschlag für Tasks:

1. **Pflicht (Zuständigkeit vom Architekten zu klären, da `.github/workflows/`
   in `CLAUDE.md` keinem `dev-*`-Agenten explizit zugeordnet ist — vermutlich
   `dev-php` als bester Fit, da Laravel-/Deployment-nah):**
   `.github/workflows/deploy.yml` — `backend/public/storage` zur
   Exclude-Liste des rsync-Schritts hinzufügen (schützt einen bereits
   vorhandenen Symlink vor `--delete`) **und** einen neuen Schritt nach dem
   Datei-Sync ergänzen, der `php artisan storage:link` erneut ausführt
   (muss idempotent/fehlertolerant sein, falls der Symlink schon existiert
   — Exit-Code/`--force`-Verhalten der Laravel-Version aus
   `backend/composer.lock` prüfen, siehe CLAUDE.md Abschnitt 9, Punkt 6).
2. **Pflicht (Dokumentation):** `DEPLOYMENT.md` ergänzen, dass
   `storage:link` künftig automatisiert läuft, damit der manuelle Schritt
   bei Erstinstallationen nicht in Widerspruch zur Pipeline gerät.
3. **Sofortmaßnahme, unabhängig vom Code-Fix (kein openspec-Task, da
   operativ):** Falls Rückfrage 1 bestätigt, dass der Symlink aktuell fehlt,
   sollte jemand mit Server-Zugriff **jetzt** einmalig manuell
   `php artisan storage:link` auf dem Produktionsserver ausführen, damit
   bereits hochgeladene/betroffene Bilder sofort wieder erreichbar sind —
   das ist unabhängig vom Merge des Codefixes und behebt das akute
   Kundenproblem schneller.
4. Der Skeptiker sollte insbesondere prüfen, ob der neue rsync-Exclude
   syntaktisch korrekt ist und ob ein erneuter `storage:link`-Aufruf bei
   bereits bestehendem Symlink den Deploy-Job nicht fehlschlagen lässt
   (Exit-Code-Verhalten testen, z. B. lokal gegen die Docker-Umgebung).
