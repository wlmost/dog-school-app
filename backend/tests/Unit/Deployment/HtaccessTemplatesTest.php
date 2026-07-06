<?php

declare(strict_types=1);

uses()->group('unit', 'deployment');

/*
 * Datei-Inhalts-Test für T04 des Changes "fix-dog-image-upload-shared-hosting":
 * `backend/public/.htaccess.production` wurde als toter Code entfernt (siehe
 * design.md Abschnitt 4, task-T04.notes.md).
 *
 * Bewusst OHNE Laravel/RefreshDatabase (reine Dateisystem-Prüfung, kein
 * DB-Bezug) und mit `dirname(__DIR__, 3)` statt `base_path()`, da diese
 * Unit-Tests laut tests/Pest.php NICHT an Tests\TestCase gebunden sind
 * (`pest()->extend(...)->in('Feature')` bindet nur Feature-Tests) und daher
 * kein gebootstrapptes Laravel-Application-Objekt zur Verfügung steht.
 *
 * Umfasst NUR den Teil des Changes, der innerhalb von `backend/` liegt.
 * T01 (`deployment-templates/htaccess/backend-public.htaccess`) und T02
 * (`deployment-templates/htaccess/backend-public.user.ini`/`.php.ini`,
 * `build-deployment.sh`, `build-deployment-docker.sh`) liegen außerhalb von
 * `backend/` und sind daher aus einem Pest-Test in `backend/tests` heraus
 * NICHT erreichbar: sowohl der lokale `php`-Docker-Service
 * (`docker-compose.yml:27-28`, mountet ausschließlich `./backend:/var/www/html`)
 * als auch der CI-Job `backend-tests` (`.github/workflows/ci.yml`, Step
 * "Run backend tests", `-v "${{ github.workspace }}/backend:/var/www/html"`)
 * stellen dem Testlauf nur den Inhalt von `backend/` zur Verfügung — Dateien
 * außerhalb davon existieren im gemounteten Container-Dateisystem schlicht
 * nicht. Ein entsprechender Test wurde probeweise ergänzt und lief nur bei
 * Ausführung mit vollem Repo-Zugriff (Host-PHP außerhalb des Containers)
 * grün; unter `docker compose exec php vendor/bin/pest` (dem in CLAUDE.md
 * Abschnitt 7.1 vorgeschriebenen Pre-Flight-Kommando) sowie in der echten
 * CI schlägt er fehl (leerer Dateiinhalt). Siehe task-report.md, Abschnitt
 * "Verbleibende Lücken", für die Details statt hier neue Test-Infrastruktur
 * (z. B. einen erweiterten Volume-Mount oder eine Bats-Suite) einzuführen.
 */

beforeEach(function () {
    $this->backendRoot = dirname(__DIR__, 3);
});

describe('backend/public/.htaccess.production wurde entfernt (T04)', function () {
    it('existiert nicht mehr im Backend-Public-Verzeichnis', function () {
        expect(file_exists($this->backendRoot.'/public/.htaccess.production'))->toBeFalse();
    });
});
