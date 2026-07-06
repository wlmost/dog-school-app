# Test-Report: fix-dog-image-upload-shared-hosting (T01–T04)

**Status:** alle-gruen (mit einer dokumentierten, nicht durch den Tester behebbaren Lücke, siehe unten)

---

## Hinzugefügte / geänderte Tests

- `backend/tests/Unit/Deployment/HtaccessTemplatesTest.php` (neu, 1 Test):
  Datei-Existenz-Prüfung für T04 (`backend/public/.htaccess.production` wurde
  entfernt). Bewusst **kein** Test für T01/T02-Inhalte — siehe Abschnitt
  "Verbleibende Lücken" unten für die Begründung.
- `frontend/src/components/DogFormModal.test.ts` (bereits vom Entwickler
  angelegt, 7 Tests): kritisch gegengelesen (siehe Abschnitt "Prüfung der
  bestehenden T03-Tests"), **keine Ergänzung nötig** — die Datei erfüllt
  bereits alle in der Aufgabenstellung geforderten Prüfpunkte.

Kein Produktivcode wurde geändert.

---

## Akzeptanzkriterien-Abdeckung

### T01 — `LimitRequestBody` + `php_value`-Fallback in `backend-public.htaccess`

- [x] `LimitRequestBody 10485760` sowie beide `php_value`-`IfModule`-Blöcke
      vorhanden — manuell verifiziert per `Read`/`grep`
      (`deployment-templates/htaccess/backend-public.htaccess:1-20`).
- [x] Bestehender Inhalt (Rewrite-Regeln, Security-Header) unverändert —
      manuell verifiziert (Datei vollständig gelesen, Diff zeigt reinen
      Insert am Dateianfang).
- [ ] **Nicht automatisiert testbar** in der vorgeschriebenen Pipeline
      (`docker compose exec php vendor/bin/pest` bzw. CI) — siehe
      "Verbleibende Lücken".
- [x] Shared-Hosting-Smoke-Test-Hinweis in `task-T01.notes.md` vorhanden —
      kein Code-Test, reine Dokumentationsprüfung, gegengelesen.

### T02 — `.user.ini`/`php.ini`-Templates + Build-Skripte + Doku

- [x] `backend-public.user.ini` und `backend-public.php.ini` existieren mit
      exakt `upload_max_filesize = 10M` / `post_max_size = 12M`, identisch
      zueinander — manuell per `Read` verifiziert.
- [x] `build-deployment.sh` und `build-deployment-docker.sh` kopieren beide
      Dateien und verifizieren deren Existenz in `verify_htaccess_files()` —
      manuell per `grep`/`Read` verifiziert (Zeilen 246-254, 284-285 bzw.
      342-350, 375-376).
- [x] `DEPLOYMENT.md` enthält den beschriebenen Troubleshooting-Hinweis zu
      allen drei Mechanismen — manuell verifiziert.
- [ ] **Nicht automatisiert testbar** in der vorgeschriebenen Pipeline — siehe
      "Verbleibende Lücken".
- [x] Lokaler Build-Probelauf laut `task-T02.notes.md` dokumentiert (vom
      Entwickler durchgeführt, vom Tester nicht wiederholt, da destruktiv für
      die laufende Dev-Container-Umgebung, siehe dortige Notiz zum
      `composer install --no-dev`-Nebeneffekt).

### T03 — `DogFormModal.vue` — Bild-Upload-Fehler nicht mehr verschlucken

- [x] Modal bleibt bei gescheitertem Bild-Upload offen, dauerhafter
      Fehlerbanner sichtbar — `DogFormModal.test.ts::zeigt einen dauerhaften
      Fehlerbanner statt nur eines Toasts`.
- [x] Weder `emit('saved')` noch `emit('close')` bei gescheitertem
      Bild-Upload — `DogFormModal.test.ts::emittiert weder saved noch close,
      wenn der Bild-Upload fehlschlägt` (per `wrapper.emitted()`, echte
      Prüfung auf Abwesenheit beider Events, nicht nur eines).
- [x] Kein zweiter Hund-Datensatz bei Retry —
      `DogFormModal.test.ts::legt beim Retry keinen zweiten Hund an` —
      prüft **exakt** die Anzahl der `POST /api/v1/dogs`-Aufrufe
      (`toHaveLength(1)`), nicht nur den DB-/Mock-Endzustand, und zusätzlich,
      dass der Bild-Upload-Endpunkt zweimal aufgerufen wurde
      (`toHaveLength(2)`) — genau die vom Auftrag geforderte Präzision.
- [x] Erfolgreicher Retry nach gescheitertem Bild-Upload löst `saved` +
      `close` aus, weiterhin nur ein Create-Request —
      `DogFormModal.test.ts::erlaubt einen erfolgreichen Retry...`.
- [x] Abbrechen-Button nach gescheitertem Bild-Upload schließt Modal ohne
      zweiten Hund — `DogFormModal.test.ts::erlaubt das Schließen über den
      Abbrechen-Button...`.
- [x] Regression: erfolgreicher Bild-Upload — `...emittiert saved und
      schließt, wenn der Bild-Upload erfolgreich war`.
- [x] Regression: kein Bild ausgewählt — `...emittiert saved und schließt,
      wenn kein Bild ausgewählt wurde` (verifiziert zusätzlich, dass **kein**
      `upload-image`-Request abgesetzt wird).
- [x] `npm run test`, `npm run build` fehlerfrei; `npm run lint` existiert
      projektweit nicht (vorbestehende Lücke, nicht Teil dieses Changes).

### T04 — `backend/public/.htaccess.production` entfernt

- [x] Datei entfernt — `HtaccessTemplatesTest.php::existiert nicht mehr im
      Backend-Public-Verzeichnis` (automatisiert, läuft in Docker/CI grün).
- [x] Keine Referenz mehr in Build-Skripten/`DEPLOYMENT.md` — manuell per
      `grep -rln "htaccess.production" --include="*.sh" --include="*.md" .`
      verifiziert (kein Treffer außerhalb der Git-Historie und der
      Change-eigenen Prozessdokumente).

---

## Ausführungs-Ergebnis

### Backend — volle Pest-Suite (Docker, `docker compose exec php`)

```
Tests:    671 passed (2087 assertions)
Duration: 24.56s
```

(670 vorbestehende Tests + 1 neuer Test aus `HtaccessTemplatesTest.php`;
keine Regression.)

### Backend — neuer Test isoliert

```
docker compose exec php vendor/bin/pest --no-coverage tests/Unit/Deployment
PASS  Tests\Unit\Deployment\HtaccessTemplatesTest
✓ backend/public/.htaccess.production wurde entfernt (T04) → it existiert nicht mehr im Backend-Public-Verzeichnis
Tests:    1 passed (1 assertions)
```

### Frontend — `DogFormModal.test.ts` isoliert

```
docker compose exec node npx vitest run src/components/DogFormModal.test.ts
✓ src/components/DogFormModal.test.ts (7 tests) 58ms
Test Files  1 passed (1)
Tests       7 passed (7)
```

### Frontend — volle Vitest-Suite

```
docker compose exec node npm run test -- run
Test Files  12 passed (12)
Tests       128 passed (128)
```

(Konsolen-`stderr`-Ausgaben im Lauf sind erwartete `console.error`-Logs aus
absichtlich gemockten Fehlerfällen in `SettingsView.test.ts`, keine
Testfehler.)

### Frontend — Build

```
docker compose exec node npm run build
> vue-tsc -b && vite build
✓ 636 modules transformed.
✓ built in 2.15s
```

Keine Type-Errors, kein Build-Fehler, keine Warnungen (Chunk-Größen wie vom
Entwickler dokumentiert, kein „large chunk"-Hinweis).

---

## Prüfung der bestehenden T03-Tests (kritisches Gegenlesen, wie beauftragt)

Alle vier explizit angefragten Punkte wurden verifiziert:

1. **Weder `saved` noch `close` bei gescheitertem Bild-Upload:** Test
   `emittiert weder saved noch close, wenn der Bild-Upload fehlschlägt`
   prüft **beide** Events explizit auf `toBeFalsy()` via `wrapper.emitted()`
   — keine Lücke, keine Prüfung nur eines der beiden Events.
2. **Exakt ein Create-Request bei Retry:** Test `legt beim Retry keinen
   zweiten Hund an` filtert `apiClient.post.mock.calls` nach der URL
   `/api/v1/dogs` und prüft `toHaveLength(1)` — das ist eine echte
   Call-Count-Prüfung, keine bloße "ein Hund existiert am Ende"-Prüfung.
   Zusätzlich wird geprüft, dass der Bild-Upload-Endpunkt bei Retry
   tatsächlich zweimal aufgerufen wird (`toHaveLength(2)`), was das
   Retry-Verhalten selbst mit abdeckt.
3. **Regressionstests Erfolgspfad (mit/ohne Bild):** Beide vorhanden im
   `describe('Erfolgreicher Speichervorgang (Regression)')`-Block; der
   Ohne-Bild-Fall verifiziert zusätzlich explizit, dass **kein**
   `upload-image`-Request abgesetzt wird.
4. **Abbrechen-Button nach gescheitertem Upload:** Test `erlaubt das
   Schließen über den Abbrechen-Button...` klickt den echten
   "Abbrechen"-Button (per Text-Suche über alle `button[type="button"]`),
   prüft `close`-Emission und dass weiterhin nur ein Create-Request
   abgesetzt wurde.

**Bewertung:** Die vom Entwickler geschriebenen Tests sind korrekt,
präzise und decken exakt die in `tasks.md` (T03) geforderten
Akzeptanzkriterien ab. Keine Korrektur oder Ergänzung nötig.

**Kleinere, nicht blockierende Beobachtung (kein Fehler, nur Hinweis):**
Kein dedizierter Test für den Update-Flow (`props.dog` gesetzt), bei dem der
Bild-Upload fehlschlägt und `savedDogId` einen erneuten `PUT` verhindert —
`design.md` (Abschnitt 3, "Neuer State: `savedDogId`") erwähnt explizit,
dass die Vermeidung unnötiger PUT-Wiederholungen im Update-Fall ein
Nebeneffekt ist, aber die Akzeptanzkriterien in `tasks.md` T03 verlangen das
nicht explizit (nur der Create-Flow ist als Kriterium benannt). Daher keine
Ergänzung vorgenommen (kein Akzeptanzkriterium verletzt), aber hier zur
Transparenz vermerkt.

---

## Verbleibende Lücken

### 1. T01/T02: Kein automatisierter Regressionsschutz für Inhalte außerhalb von `backend/`

Ich habe zunächst einen Pest-Test ergänzt, der den Inhalt von
`deployment-templates/htaccess/backend-public.htaccess`,
`backend-public.user.ini`, `backend-public.php.ini` sowie den relevanten
`cp`/Verify-Zeilen in `build-deployment.sh`/`build-deployment-docker.sh`
prüft. Dieser Test lief **grün, wenn mit vollem Repo-Zugriff ausgeführt**
(Host-PHP außerhalb des Containers: `cd backend && php vendor/bin/pest
tests/Unit/Deployment` → 10/10 grün) — die inhaltliche Umsetzung von T01/T02
ist damit bestätigt korrekt.

Er schlägt jedoch **systematisch fehl**, wenn er über das laut `CLAUDE.md`
Abschnitt 7.1 vorgeschriebene Pre-Flight-Kommando
(`docker compose exec php vendor/bin/pest`) oder in der echten CI
(`.github/workflows/ci.yml`, Job `backend-tests`) ausgeführt wird, weil
**beide** Umgebungen ausschließlich `./backend` in den PHP-Container mounten
(`docker-compose.yml:27-28`: `- ./backend:/var/www/html`;
`.github/workflows/ci.yml`: `-v "${{ github.workspace }}/backend:/var/www/html"`).
Dateien außerhalb von `backend/` (also `deployment-templates/`,
`build-deployment.sh`, `build-deployment-docker.sh`, `DEPLOYMENT.md`)
existieren im gemounteten Container-Dateisystem schlicht nicht —
`file_get_contents()` liefert dort `false`/leeren String statt eines
Fehlers.

**Entscheidung:** Diesen Teil des Tests wieder entfernt (siehe finale
Fassung von `HtaccessTemplatesTest.php`, die nur noch die T04-Prüfung
enthält, welche innerhalb von `backend/public/` liegt und damit in
Docker/CI erreichbar ist). Eine dauerhaft rote Testdatei in der
vorgeschriebenen Pipeline zu hinterlassen wäre schlechter als die Lücke
transparent zu dokumentieren. Eine neue Test-Infrastruktur einzuführen
(z. B. Erweiterung des `docker-compose.yml`-Volume-Mounts auf den
Repo-Root, oder eine separate Bats-Suite außerhalb des Pest-Laufs) wäre ein
Eingriff in Produktiv-/Infra-Konfiguration bzw. neue Test-Infrastruktur und
liegt damit außerhalb des Tester-Mandats (YAGNI, wie im Auftrag verlangt).
Diese Lücke sollte dem Architekten/Reviewer zur Entscheidung vorgelegt
werden (z. B. als eigener kleiner Folge-Task: Volume-Mount erweitern oder
Test bewusst außerhalb der `backend/`-Testsuite ansiedeln).

**Ersatzweise Absicherung (manuell, dokumentiert, nicht automatisiert):**
Alle T01/T02-Akzeptanzkriterien wurden von mir manuell per `Read`/`grep`
gegen den tatsächlichen Diff verifiziert (siehe Abschnitt
"Akzeptanzkriterien-Abdeckung" oben) — inhaltlich korrekt, nur ohne
automatisierten Regressionsschutz gegen versehentliches Verlieren bei einem
künftigen Merge/Rebase.

### 2. Kritischer Fund (außerhalb des Test-Scopes, aber sicherheitsrelevant für das Change-Ziel): `deploy.yml` kopiert die neuen T02-Dateien nicht

Bei der Untersuchung der CI/CD-Pipeline (`.github/workflows/deploy.yml`,
Job "Prepare deployment package", Zeilen 127-133) habe ich festgestellt,
dass der **tatsächliche automatisierte Produktions-Deploy** die
Deployment-Artefakte **nicht** über `build-deployment.sh`/
`build-deployment-docker.sh` erzeugt, sondern die relevanten
`.htaccess`-Kopien manuell per `cp` repliziert — und dabei **weder**
`backend-public.user.ini` **noch** `backend-public.php.ini` kopiert:

```yaml
# .github/workflows/deploy.yml:127-133
cp deployment-templates/htaccess/root-post-install.htaccess  deploy-package/.htaccess
cp deployment-templates/htaccess/backend-public.htaccess      deploy-package/backend/public/.htaccess
cp deployment-templates/htaccess/backend-root.htaccess        deploy-package/backend/.htaccess
cp deployment-templates/htaccess/storage.htaccess             deploy-package/backend/storage/.htaccess
cp deployment-templates/htaccess/frontend.htaccess            deploy-package/frontend/.htaccess
cp deployment-templates/htaccess/frontend-dist.htaccess       deploy-package/frontend/dist/.htaccess
```

**Konsequenz:** T01 (`.htaccess`) würde über den automatisierten
GitHub-Actions-Deploy tatsächlich ausgeliefert (die `cp`-Zeile für
`backend-public.htaccess` ist vorhanden). **T02 (`.user.ini`/`php.ini`)
würde über diesen Pfad jedoch NICHT auf den Shared-Hosting-Server
gelangen**, obwohl `build-deployment.sh`/`build-deployment-docker.sh`
korrekt angepasst wurden — diese beiden Skripte werden vom automatisierten
Deploy-Workflow gar nicht aufgerufen, ihre Änderungen wirken sich also
nicht auf `deploy.yml` aus.

Das ist **kein Bug meiner Tests und kein Produktivcode, den ich anfassen
darf** (`.github/workflows/deploy.yml` gehört nicht zu den in `tasks.md`
T01-T04 benannten Dateien) — ich dokumentiere es hier, weil es das
eigentliche Ziel des Changes ("Bild-Upload funktioniert auf dem
Ziel-Shared-Hosting") unterläuft, falls das Deployment tatsächlich über
diesen GitHub-Actions-Workflow läuft und nicht manuell per
`build-deployment.sh`. **Empfehlung:** als Befund an Architekt/Reviewer
weiterreichen — vermutlich ein fehlender fünfter Task ("T05: `deploy.yml`
um die beiden neuen `cp`-Zeilen ergänzen") vor dem finalen User-Gate.

### 3. `npm run lint` existiert nicht im Projekt

Vorbestehende Lücke (kein `lint`-Skript, keine ESLint-Konfiguration unter
`frontend/`), bereits in `task-T03.notes.md` dokumentiert. Nicht Teil dieses
Changes, hier nur zur Vollständigkeit erwähnt, da CLAUDE.md Abschnitt 7.1
`npm run lint` als Teil des Frontend-Pre-Flights vorschreibt.

---

## Fehler

Keine — alle ausgeführten Tests sind grün (671 Backend, 128 Frontend,
Frontend-Build fehlerfrei). Die oben genannten Punkte sind **Lücken/Befunde**,
keine fehlschlagenden Tests im committeten Zustand.
