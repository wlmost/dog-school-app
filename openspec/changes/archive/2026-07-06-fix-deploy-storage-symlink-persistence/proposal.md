# Proposal: fix-deploy-storage-symlink-persistence

**Change-ID:** fix-deploy-storage-symlink-persistence
**Typ:** Bug-Fix (Deployment-Pipeline)
**Priorität:** hoch (Produktion betroffen, Kundenmeldung)
**Datum:** 2026-07-06
**Umgebung:** bestätigt durch User — **Produktion, Shared Hosting**

---

## Problem-Statement

Ein Kunde meldet: Beim Hochladen eines Hundebilds in der Bearbeitungsmaske
(`frontend/src/components/DogFormModal.vue:320-324`) erscheint das Bild
zunächst (lokale `FileReader`-Vorschau), verschwindet aber nach dem Schließen
der Maske aus der Kachel-Ansicht und bleibt auch beim erneuten Öffnen der
Maske unsichtbar.

Der am selben Tag bereits gemergte und archivierte Fix
`fix-dog-image-upload-shared-hosting` (Commit `043ba88`) ist **nicht**
betroffen und **nicht** die Ursache: Der clientseitige Error-Handling-Fix in
`DogFormModal.vue` greift nur, wenn der Upload-Request selbst fehlschlägt.
Hier meldet der Server den Upload als Erfolg — die Datei ist nur
**nachträglich nicht erreichbar** (kein Request-Fehler, daher kein
Error-Banner beim Kunden sichtbar).

## Root-Cause (vom User am Produktionsserver bestätigt)

Auf dem Produktionsserver existiert **kein** Symlink `backend/public/storage`
→ `backend/storage/app/public`. `public` und `storage` liegen als normale,
unabhängige Verzeichnisse nebeneinander. Hochgeladene Dateien landen korrekt
in `storage/app/public/...` (`DogController::uploadImage()`,
`backend/app/Http/Controllers/Api/DogController.php:180-202`, unverändert
korrekt), sind aber über die öffentliche URL nie erreichbar (404), weil der
Auslieferungspfad (`deployment-templates/htaccess/root-post-install.htaccess:23`,
`RewriteRule ^storage/(.*)$ backend/public/storage/$1 [L]`) auf ein
nicht-existentes Verzeichnis zeigt.

**Mechanismus, verifiziert im Code (`.github/workflows/deploy.yml`,
vollständig gelesen, 12 Schritte):**

- `backend/.gitignore:5` (`/public/storage`) — der Symlink ist absichtlich
  nicht versioniert (Laravel-Standard). Er existiert nie im CI-Checkout.
- Der Schritt „Deploy files via rsync" (`.github/workflows/deploy.yml:171-182`)
  synchronisiert mit `rsync -az --delete` und excludet aktuell nur
  `backend/.env`, `backend/storage/app/`, `backend/storage/logs/` sowie drei
  `backend/storage/framework/*`-Unterverzeichnisse — **nicht**
  `backend/public/storage`. Da der Symlink nie in der rsync-Quelle
  (`deploy-package/`) existiert, aber `--delete` gesetzt ist, entfernt rsync
  einen auf dem Server bereits vorhandenen Symlink bei **jedem**
  automatisierten Deploy.
- Kein Schritt in `.github/workflows/deploy.yml` führt `php artisan
  storage:link` erneut aus (die vorhandenen Schritte sind: Wartungsmodus an
  → rsync → Migrationen → Cache-Rebuild → Wartungsmodus aus → Cleanup →
  Summary). Ohne einen solchen Schritt wird der Symlink nach einem Deploy,
  bei dem er fehlte oder gelöscht wurde, nie wieder angelegt.
- `DEPLOY-WORKFLOW.md` (dediziertes Nutzungs-Dokument für genau diese
  Pipeline) listet in Abschnitt 5 ("Was der Workflow im Detail macht",
  Zeilen 167-197) alle tatsächlich ausgeführten Schritte sowie die
  „Geschützten Verzeichnisse" (Zeilen 188-197) — auch hier fehlt
  `backend/public/storage` sowohl in der Schritt-Tabelle als auch in der
  Exclude-Liste. Die Dokumentation beschreibt also exakt den fehlerhaften
  Ist-Zustand.
- `DEPLOYMENT.md:600-608` dokumentiert `php artisan storage:link` nur als
  **manuellen Erstinstallations-Schritt** für den separaten,
  wizard-basierten Installationspfad (`build-deployment.sh` +
  `install.php`, siehe `openspec/changes/shared-hosting-installer/`, ein
  eigener, aktuell noch offener Change) — **nicht** für den hier relevanten
  automatisierten `.github/workflows/deploy.yml`-Pfad. Diese beiden
  Deployment-Mechanismen sind unabhängig voneinander; `install.php` führt
  `storage:link` bereits selbst aus (`install.php:2085,2095`). Der
  hier gemeldete Bug betrifft ausschließlich den automatisierten
  GitHub-Actions-Pfad.
- Bestätigt: Seit dem Fix `043ba88` wurde bereits ein automatischer Deploy
  über `.github/workflows/deploy.yml` durchgeführt (User-Aussage) — das ist
  der Mechanismus, der den Symlink entfernt bzw. nie neu anlegt.

**Cross-Feature-Risiko (verifiziert):** Laut `FILE-UPLOAD-SYSTEM.md:46,156-165,325-333`
nutzt auch das Feature `training-attachments`
(`backend/app/Http/Controllers/Api/TrainingAttachmentController.php`,
Storage-Pfad `storage/app/public/training-attachments/{trainingLogId}/`)
dieselbe `public`-Disk und denselben Symlink. Der Fix muss daher generisch
für die Storage-Auslieferung greifen, nicht nur für Hundebilder.

## Ziel

1. Ein bereits auf dem Server vorhandener `backend/public/storage`-Symlink
   wird von keinem automatisierten Deploy über
   `.github/workflows/deploy.yml` mehr gelöscht.
2. Fehlt der Symlink (erster Deploy, oder weil er zuvor bereits entfernt
   wurde), wird er bei jedem automatisierten Deploy automatisch (wieder)
   angelegt — ohne den Deploy-Job fehlschlagen zu lassen, falls er bereits
   existiert.
3. Die Dokumentation (`DEPLOY-WORKFLOW.md`) beschreibt den tatsächlichen,
   korrigierten Ablauf, damit Betreiber nicht mehr von einer Lücke
   ausgehen, die es nach diesem Fix nicht mehr gibt.

## Proposed Solution

### T01 (Pflicht): `.github/workflows/deploy.yml` korrigieren

1. `backend/public/storage` zur Exclude-Liste des rsync-Schritts hinzufügen
   (schützt einen bereits vorhandenen Symlink vor `--delete`).
2. Einen neuen Schritt nach dem rsync-Schritt (vor den Migrationen)
   ergänzen, der per SSH `php artisan storage:link` ausführt. Verifiziert
   (`backend/vendor/laravel/framework/src/Illuminate/Foundation/Console/StorageLinkCommand.php`,
   Laravel `v11.51.0` laut `backend/composer.lock:1507`): `handle()` gibt
   bei bereits existierendem Symlink lediglich eine Info-Meldung über
   `$this->components->error(...)` aus (eine reine Konsolen-Ausgabe-
   Formatierung, kein Fehler-Return) und **kehrt ohne Rückgabewert zurück**
   — der Exit-Code ist in beiden Fällen (Symlink fehlt / existiert bereits)
   `0`. Der Schritt ist damit **ohne** `--force` und **ohne** zusätzliches
   Fehler-Handling sicher wiederholbar bei jedem Deploy.

### T02 (Pflicht, Dokumentation): `DEPLOY-WORKFLOW.md` aktualisieren

Die Schritt-Tabelle (Abschnitt 5) und die Liste der geschützten
Verzeichnisse um den neuen rsync-Exclude und den neuen `storage:link`-
Schritt ergänzen, damit die Dokumentation den korrigierten Ablauf
widerspiegelt.

### T03 (könnte, nicht blockierend): Automatisierte Absicherung in der CI

Ein einfacher, Docker-unabhängiger Prüfschritt in `.github/workflows/ci.yml`,
der per Grep sicherstellt, dass `deploy.yml` sowohl den
`backend/public/storage`-Exclude als auch einen `storage:link`-Schritt
enthält — als Regressionsschutz gegen zukünftige, versehentliche
Wieder-Entfernung. Kein PHPUnit/Pest-Test, da der Backend-Test-Container
laut `.github/workflows/ci.yml:74` (`-v
"${{ github.workspace }}/backend:/var/www/html"`) ausschließlich
`backend/` mountet und `.github/workflows/deploy.yml` damit für Pest-Tests
strukturell unerreichbar ist (bereits so dokumentiert in
`backend/tests/Unit/Deployment/HtaccessTemplatesTest.php:18-35` für ein
analoges Problem im vorherigen Change).

## Out of Scope

- Der separate, wizard-basierte Installationspfad (`build-deployment.sh`,
  `build-deployment-docker.sh`, `install.php`,
  `openspec/changes/shared-hosting-installer/`) — führt `storage:link`
  bereits selbst aus, ist von diesem Bug nicht betroffen.
- Eine sofortige manuelle Behebung auf dem konkreten Kundenserver (einmaliges
  `php artisan storage:link` per SSH durch eine Person mit Server-Zugriff) —
  das ist eine **operative Sofortmaßnahme außerhalb dieses Changes**, um das
  akute Kundenproblem unabhängig vom Merge des Codefixes zu beheben, kein
  openspec-Task.
- Änderungen an `backend/app/Http/Controllers/Api/DogController.php` oder
  `TrainingAttachmentController.php` — beide sind bereits strukturell
  korrekt (verifiziert), der Fehler liegt ausschließlich in der
  Deployment-Pipeline.
- `DEPLOYMENT.md` (wizard-Pfad-Dokumentation) — bleibt für ihren eigenen,
  unveränderten Installationspfad korrekt; keine Änderung nötig.

## Referenzen

- Triage: `openspec/triage/20260706173935-dog-image-not-shown-after-edit.md`
- Verwandter, bereits archivierter Change (selber Tag, andere Ursache):
  `openspec/changes/archive/2026-07-06-fix-dog-image-upload-shared-hosting/`
- Aktive Spec (neu, dieser Change): `openspec/specs/deployment-pipeline/spec.md`
  (existiert noch nicht — wird durch diesen Change als neue Capability
  angelegt)
