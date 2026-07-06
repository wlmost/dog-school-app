# Verification: fix-deploy-storage-symlink-persistence

**Gesamtstatus:** ok

`openspec validate fix-deploy-storage-symlink-persistence` lief erfolgreich
(„Change 'fix-deploy-storage-symlink-persistence' is valid"), strukturell
kein Problem. Es folgt der inhaltliche Realitätsabgleich.

## Bestätigt

- `proposal.md` Z.14: „Bearbeitungsmaske (`frontend/src/components/DogFormModal.vue:320-324`)" →
  bestätigt, `handleImageChange()` mit `FileReader`-Vorschau exakt in
  `frontend/src/components/DogFormModal.vue:315-327` (Zeilen 320-324 liegen
  darin, `selectedImageFile.value = file` bis `reader.readAsDataURL(file)`).
- `proposal.md` Z.32-34: „`DogController::uploadImage()`,
  `backend/app/Http/Controllers/Api/DogController.php:180-202`, unverändert
  korrekt" → bestätigt, Methode beginnt Z.180, endet Z.202,
  `Storage::disk('public')` und `storeAs(..., 'public')` Z.192-197.
- `proposal.md` Z.35-37: „`deployment-templates/htaccess/root-post-install.htaccess:23`,
  `RewriteRule ^storage/(.*)$ backend/public/storage/$1 [L]`" → bestätigt
  wortgleich in `deployment-templates/htaccess/root-post-install.htaccess:23`.
- `proposal.md` Z.42: „`backend/.gitignore:5` (`/public/storage`)" → bestätigt,
  `backend/.gitignore:5` enthält exakt `/public/storage`.
- `proposal.md` Z.44-52: rsync-Schritt „Deploy files via rsync"
  (`.github/workflows/deploy.yml:171-182`) excludet aktuell nur
  `backend/.env`, `backend/storage/app/`, `backend/storage/logs/`,
  `backend/storage/framework/sessions/`, `backend/storage/framework/cache/`,
  `backend/storage/framework/views/` — **kein** Exclude für
  `backend/public/storage` → bestätigt 1:1 in `.github/workflows/deploy.yml:171-182`
  (Exclude-Liste exakt wie zitiert, kein `public/storage`-Eintrag).
- `proposal.md` Z.52-56 / design.md Abschnitt 3: „Kein Schritt in
  `.github/workflows/deploy.yml` führt `php artisan storage:link` erneut
  aus" → bestätigt per Volltextlesung von `.github/workflows/deploy.yml`
  (244 Zeilen, 12 nummerierte Schritte: Checkout, Backend-Build,
  Frontend-Build, Paket zusammenstellen, SSH-Setup, Wartungsmodus an,
  rsync, Migrationen, Cache-Rebuild, Wartungsmodus aus, Cleanup, Summary)
  — kein `storage:link`-Aufruf enthalten (`grep -n "storage:link"
  .github/workflows/deploy.yml` liefert keinen Treffer).
- `proposal.md` Z.57-63 / design.md Abschnitt 5: `DEPLOY-WORKFLOW.md`
  (282 Zeilen) beschreibt in der Schritt-Tabelle (`DEPLOY-WORKFLOW.md:171-186`)
  und der Liste „Geschützte Verzeichnisse" (`DEPLOY-WORKFLOW.md:188-197`)
  exakt den fehlerhaften Ist-Zustand — kein Eintrag für
  `backend/public/storage` an beiden Stellen → bestätigt, beide
  Zeilenbereiche wörtlich gelesen und geprüft.
- `proposal.md` Z.64-73 / design.md Abschnitt 5: `DEPLOYMENT.md:600-608`
  dokumentiert `php artisan storage:link` als manuellen
  Erstinstallations-Schritt („Storage-Verzeichnisse vorbereiten") für den
  Wizard-Pfad → bestätigt wörtlich (`DEPLOYMENT.md:605-608`). Der
  separate, generische VPS-Deploy-Mechanismus mit `git pull`, Supervisor,
  `systemctl restart php8.4-fpm` in „### Automatisiertes Deployment
  (Optional)" → bestätigt in `DEPLOYMENT.md:940-977` (Header bei Z.940,
  `deploy.sh`-Inhalt mit `git pull origin master`, `supervisorctl restart`,
  `systemctl restart php8.4-fpm` bis Z.976, Abschnittsende `---` Z.979).
  `install.php` führt `storage:link` bereits selbst aus → bestätigt,
  `install.php:2085` (`php artisan storage:link 2>&1` im Shell-Kommando)
  und `install.php:2095` (`$kernel->call('storage:link')`).
- `proposal.md` Z.78-83: Cross-Feature-Risiko `training-attachments`
  nutzt dieselbe `public`-Disk → bestätigt: `FILE-UPLOAD-SYSTEM.md:46`
  („File Storage in `storage/app/public/training-attachments/{trainingLogId}/`"),
  `FILE-UPLOAD-SYSTEM.md:156-165` (Setup-Abschnitt „Storage Link erstellen",
  `php artisan storage:link`, „Dies erstellt einen symbolischen Link von
  `public/storage` zu `storage/app/public`" bei Z.159) und
  `FILE-UPLOAD-SYSTEM.md:325-333` (Troubleshooting „Storage link not
  found"). Code-seitig zusätzlich verifiziert:
  `backend/app/Http/Controllers/Api/TrainingAttachmentController.php:96-99`
  (`storeAs(..., 'public')`) und Zeilen 143/147/164-165 nutzen
  `Storage::disk('public')` — identische Disk wie `DogController.php:192-197`.
- `design.md` Z.67-82 (Abschnitt 3.1, rsync-Exclude-Codeblock) → bestätigt
  wortgleich mit `.github/workflows/deploy.yml:171-182`.
- `design.md` Z.101-102 (neuer Schritt „einfügen ... vor dem Migrations-Schritt,
  aktuell Zeilen 184-192") → bestätigt: Kommentarblock „# 8. Run database
  migrations" beginnt `.github/workflows/deploy.yml:184`, der eigentliche
  `ssh ... migrate --force`-Aufruf endet Z.192.
- `design.md` Z.128-134: nachfolgende Kommentar-Nummern `# 8.` bis `# 12.`
  existieren tatsächlich exakt in dieser Reihenfolge und mit diesen Titeln
  (Run database migrations Z.185, Rebuild application caches Z.195,
  Disable maintenance mode Z.211, Cleanup Z.224, Deployment summary Z.231)
  → bestätigt, müssten nach Einfügen des neuen Schritts korrekt auf 9-13
  erhöht werden.
- `design.md` Abschnitt 3.3, Z.139-142: „Enable maintenance mode" und
  „Disable maintenance mode" haben Best-Effort-Fallback (`|| echo
  "::warning::..."`), „Run database migrations" (Z.185-192) hat **keinen**
  Fallback → bestätigt: `.github/workflows/deploy.yml:164`
  (`|| echo "::warning::Maintenance mode could not be activated..."`),
  Z.221 (`|| echo "::warning::Could not disable maintenance mode..."`),
  Migrations-Schritt Z.187-192 ohne `||`-Fallback. Kleine Ungenauigkeit:
  design.md zitiert für „Enable maintenance mode" „Zeile 156" — das ist die
  Kommentarzeile („# 6. Enable maintenance mode (best-effort...)"), nicht
  die tatsächliche Fallback-Zeile (Z.164). Für „Disable maintenance mode"
  ist „Zeile 221" hingegen exakt die Fallback-Zeile selbst. Inhaltlich
  ändert das nichts an der Kernaussage, ist aber eine uneinheitliche
  Zitierweise.
- `design.md` Abschnitt 4 (Z.163-195): `StorageLinkCommand::handle()` hat
  keinen expliziten `return`-Wert, `$this->components->error(...)` ist
  reine Konsolenausgabe ohne Exception → bestätigt in
  `backend/vendor/laravel/framework/src/Illuminate/Foundation/Console/StorageLinkCommand.php:32-54`
  (kein `return`-Statement im `handle()`-Rumpf). Zusätzlich selbst
  verifiziert (nicht nur vom Architekten übernommen): Laravels
  `Illuminate\Console\Command::execute()`
  (`backend/vendor/laravel/framework/src/Illuminate/Console/Command.php:197-223`)
  führt `return (int) $this->laravel->call([$this, $method]);` aus — ein
  `null`-Rückgabewert von `handle()` wird also via `(int) null` zu `0`
  gecastet. Damit ist die Behauptung „Exit-Code in beiden Fällen 0"
  eigenständig bestätigt, nicht nur behauptet.
- `design.md` Z.166-167: Laravel-Version `v11.51.0`,
  `backend/composer.lock:1507` → bestätigt exakt: `composer.lock:1507`
  enthält `"version": "v11.51.0"` (Block-Start `"name": "laravel/framework"`
  bei Z.1506).
- `design.md` Abschnitt 2 (Z.41-49): Präzedenzfall im archivierten Change
  `fix-dog-image-upload-shared-hosting`, T02 weist `build-deployment.sh`,
  `build-deployment-docker.sh`, `DEPLOYMENT.md` dem Agenten `dev-php` zu
  → bestätigt in
  `openspec/changes/archive/2026-07-06-fix-dog-image-upload-shared-hosting/tasks.md:75-78`
  (`**Agent:** dev-php`, `**Dateien:** ... build-deployment.sh,
  build-deployment-docker.sh, DEPLOYMENT.md`).
- `design.md` Abschnitt 6 (Z.254-266) / `proposal.md` Z.123-135: Docker-
  Test-Container mountet nur `backend/` → bestätigt:
  `.github/workflows/ci.yml:74` enthält exakt
  `-v "${{ github.workspace }}/backend:/var/www/html"` (identisch auch
  Z.90, Z.99). `backend/tests/Unit/Deployment/HtaccessTemplatesTest.php:18-35`
  dokumentiert dasselbe strukturelle Problem für einen vorherigen Change
  wortgleich mit dem zitierten Inhalt (Docker-Mount-Grenze, Pest kann nicht
  außerhalb `backend/` prüfen).
- `design.md` Abschnitt 6, Z.276-291 (Grep-Check-Codeblock für
  `deploy-workflow-lint`): Job-Platzierung „parallel zu
  backend-tests/frontend-tests" ist plausibel — beide Jobs existieren
  tatsächlich unter diesen Namen in `.github/workflows/ci.yml:13` bzw.
  `.github/workflows/ci.yml:122`, ein neuer Top-Level-Job-Name
  `deploy-workflow-lint` kollidiert mit keinem bestehenden Job.
- `proposal.md` Referenzen, Z.156-158: archivierter Change
  `openspec/changes/archive/2026-07-06-fix-dog-image-upload-shared-hosting/`
  existiert → bestätigt (Verzeichnis vorhanden).
- `proposal.md` Z.139-141 / Out of Scope: `openspec/changes/shared-hosting-installer/`
  existiert als eigener, referenzierter Change → bestätigt (Verzeichnis
  vorhanden unter `openspec/changes/shared-hosting-installer`).

## Widerlegt

Keine inhaltliche Behauptung wurde als sachlich falsch widerlegt. Eine
Zitat-Ungenauigkeit (kein Sachfehler, aber Datei:Zeile stimmt nicht exakt):

- `design.md` Z.94-95: „im „Prepare deployment package"-Schritt,
  `.github/workflows/deploy.yml:87-129` gelesen, wird dort ebenfalls kein
  `public/storage` angelegt" → Zeile 87 gehört tatsächlich noch zum
  vorherigen Schritt „Setup Node.js 20" (`cache-dependency-path:
  frontend/package.json`, `.github/workflows/deploy.yml:87`). Der
  „Prepare deployment package"-Schritt beginnt erst bei
  `.github/workflows/deploy.yml:97` (Kommentarblock „# 4. Assemble
  deployment package") bzw. `:102` (`- name: Prepare deployment package`)
  und der zitierte Inhalt (kein `public/storage`-Anlegen) liegt tatsächlich
  in `.github/workflows/deploy.yml:104-125`. Die Zeilenangabe „87-129" ist
  um ca. 10 Zeilen zu früh angesetzt. Die **inhaltliche Kernaussage**
  (der Schritt legt nirgends `backend/public/storage` an) ist dennoch
  korrekt — verifiziert über den vollständigen Skript-Inhalt
  `.github/workflows/deploy.yml:102-142`, kein `mkdir`/`ln`/`cp` erzeugt
  einen `public/storage`-Pfad.

## Nicht auffindbar

Keine Behauptung konnte nicht verifiziert werden — alle referenzierten
Dateien, Zeilenbereiche und Code-Zitate waren auffindbar und prüfbar.

## Neue Elemente (Plausibilität)

- `tasks.md` T03 / `design.md` Abschnitt 6: neuer Job
  `deploy-workflow-lint` in `.github/workflows/ci.yml` → Datei existiert
  bereits mit den Jobs `backend-tests` (Z.13) und `frontend-tests`
  (Z.122); ein zusätzlicher Top-Level-Job ist strukturell unproblematisch
  und kollidiert nicht mit bestehenden Job-Namen. Plausibel.
- `tasks.md` T01/T02: Änderungen ausschließlich an bereits existierenden
  Dateien (`.github/workflows/deploy.yml`, `DEPLOY-WORKFLOW.md`) — kein
  neuer Pfad, kein Konflikt.

## Zusätzlich eigenständig geprüfte Punkte (Aufgabenstellung Nr. 3–6)

3. **Idempotenz von `storage:link`:** Eigenständig (nicht nur die
   Architekten-Behauptung übernommen) über zwei Dateien nachvollzogen:
   `StorageLinkCommand.php:32-54` (kein `return` in `handle()`) und
   `Illuminate\Console\Command::execute()`
   (`backend/vendor/laravel/framework/src/Illuminate/Console/Command.php:197-223`,
   `return (int) $this->laravel->call([...]);`). Ergebnis: Exit-Code ist
   in beiden Fällen (Symlink existiert bereits / wird neu angelegt) `0`.
   Die Behauptung ist bestätigt und für den neuen Deploy-Schritt
   unbedenklich (kein `--force` nötig, kein Job-Abbruch-Risiko).
4. **`DEPLOY-WORKFLOW.md` vs. `DEPLOYMENT.md` als Doku-Ziel:** Beide
   Dateien vollständig an den relevanten Stellen gelesen.
   `DEPLOY-WORKFLOW.md:3-5` beschreibt sich selbst explizit als Dokument
   für „den automatisierten Deployment-Workflow
   (`.github/workflows/deploy.yml`)" und enthält bereits eine 1:1-passende
   Schritt-Tabelle (Z.171-186) sowie „Geschützte Verzeichnisse"-Liste
   (Z.188-197), die exakt dem aktuellen, fehlerhaften `deploy.yml`-Stand
   entsprechen. `DEPLOYMENT.md:600-608` und `:940-977` beschreiben
   nachweislich einen separaten, manuellen Wizard-/VPS-Installationspfad
   (`php8.4-fpm`, `supervisorctl`, `git pull`) ohne Bezug zu
   `.github/workflows/deploy.yml`. Die Korrektur des Architekten ist
   damit bestätigt: `DEPLOY-WORKFLOW.md` ist das richtige Dokumentationsziel.
5. **Agenten-Zuständigkeit `dev-php` für YAML+Markdown:** `CLAUDE.md`
   Abschnitt 2/7.2 listet tatsächlich weder `.github/workflows/*.yml` noch
   Top-Level-Markdown explizit unter `dev-php` oder `dev-javascript` — die
   Lücke in der Agentenzuordnung ist real, nicht erfunden. Der vom
   Architekten angeführte Präzedenzfall
   (`openspec/changes/archive/2026-07-06-fix-dog-image-upload-shared-hosting/tasks.md:75-78`)
   ist bestätigt: dort wurden Bash-Skripte und `DEPLOYMENT.md` bereits
   `dev-php` zugewiesen. Die Begründung ist konsistent mit gelebter
   Projektpraxis; da kein `dev-devops`/`dev-yaml`-Agent existiert
   (`CLAUDE.md` Abschnitt 2, bestätigt: nur `dev-php`, `dev-javascript`
   gelistet), ist die Zuordnung an `dev-php` die einzig verfügbare
   sinnvolle Wahl im Projekt.
6. **T03-Testbarkeits-Behauptung:** Bestätigt über
   `.github/workflows/ci.yml:74/90/99` (`-v "${{ github.workspace
   }}/backend:/var/www/html"`, dreifach identisch) und
   `backend/tests/Unit/Deployment/HtaccessTemplatesTest.php:18-35`
   (identische Begründung für einen früheren, analogen Fall bereits im
   Code dokumentiert). `.github/workflows/deploy.yml` liegt außerhalb von
   `backend/` und ist damit für den in `backend/tests` laufenden
   Pest-Testlauf strukturell nicht erreichbar — die Aussage ist korrekt,
   kein reines Postulat des Architekten.
7. **Cross-Feature-Behauptung `training-attachments`:** Bestätigt über
   `FILE-UPLOAD-SYSTEM.md:46,156-165,325-333` sowie zusätzlich, über die
   reine Doku-Prüfung hinaus, im Code selbst:
   `TrainingAttachmentController.php:96-99,143,147,164-165` nutzt
   durchgängig `Storage::disk('public')` / `storeAs(..., 'public')` —
   identisch zu `DogController.php:192-197`. Der Fix in `deploy.yml`
   (rsync-Exclude + `storage:link`) wirkt disk-/symlink-weit und ist damit
   tatsächlich auch für `training-attachments` relevant, nicht nur für
   Hundebilder.

## Empfehlung

Die Spec ist inhaltlich außergewöhnlich sorgfältig gegen die Codebasis
verifiziert (fast jede Zeilenangabe stimmt exakt). Der einzige Befund ist
eine kosmetische Zitat-Ungenauigkeit (`design.md`, Prepare-deployment-
package-Zeilenbereich „87-129" statt korrekt „~97/102-142"), die die
Kernaussage nicht entkräftet. Spec ist verlässlich genug, um ohne weitere
Architekten-Nacharbeit fortzufahren; die kleine Zeilenangabe-Korrektur kann
optional beim nächsten Architekten-Durchlauf mitgezogen werden, ist aber
kein Blocker für User-Gate 1.
