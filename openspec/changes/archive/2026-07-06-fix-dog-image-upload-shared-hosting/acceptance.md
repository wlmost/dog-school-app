# Abnahme: fix-dog-image-upload-shared-hosting

**Status:** bereit-für-user-review

---

## Schritt 0 — Strukturelle Validität

```
openspec validate fix-dog-image-upload-shared-hosting --strict
→ Change 'fix-dog-image-upload-shared-hosting' is valid
```

Erneut ausgeführt, nachdem die Spec-Delta-Datei
(`specs/dog-image-upload/spec.md`) im Rahmen dieser Abnahme ergänzt wurde
(siehe "Spec-Konformität" unten) — weiterhin `valid`.

## Verifikationsmethode

Alle Aussagen unten wurden nicht aus den Agenten-Berichten übernommen,
sondern selbst gegen den tatsächlichen Working-Tree-Diff geprüft:

```bash
git status --short
git diff -- deployment-templates/htaccess/backend-public.htaccess
git diff -- .github/workflows/deploy.yml
git diff -- build-deployment.sh build-deployment-docker.sh
git diff -- DEPLOYMENT.md
git diff -- frontend/src/components/DogFormModal.vue
git diff -- frontend/src/views/dogs/DogsView.vue   # bestätigt: keine Änderung
git log --diff-filter=A --oneline -- backend/public/.htaccess.production
ls backend/public/.htaccess.production             # bestätigt: existiert nicht mehr
cat deployment-templates/htaccess/backend-public.user.ini
cat deployment-templates/htaccess/backend-public.php.ini
```

(Branch `change/fix-dog-image-upload-shared-hosting`, Änderungen noch nicht
committet — Diff daher gegen den zuletzt committeten Stand von `main`
gebildet, wie von den Agenten dokumentiert.)

---

## Vollständigkeit (Tasks)

Alle vier Tasks in `tasks.md` sind vollständig abgehakt (`[x]`), inklusive
der beiden nachträglichen Review-/Test-Fixes, die in
`task-T02.review-fixes.md` dokumentiert sind:

- **T01** (Apache `LimitRequestBody` + `php_value`-Fallback): im Diff
  bestätigt — Block ist wortgleich mit `tasks.md`/`design.md` eingefügt,
  bestehender Inhalt der Datei unverändert.
- **T02** (`.user.ini`/`php.ini`-Templates + Build-Skripte + Doku): im Diff
  bestätigt — beide neuen Dateien enthalten exakt `upload_max_filesize = 10M`
  / `post_max_size = 12M`; `build-deployment.sh` und
  `build-deployment-docker.sh` kopieren und verifizieren beide Dateien
  wortgleich (Diff beider Skripte verglichen, identisch bis auf Kommentare);
  `DEPLOYMENT.md` enthält den beschriebenen Troubleshooting-Abschnitt.
- **T03** (`DogFormModal.vue`): im Diff bestätigt — `saveDogRecord()` /
  `uploadDogImage()` / `savedDogId`-Mechanismus exakt wie in `design.md`
  Abschnitt 3 ("Entscheidung — Lösung (a)") festgelegt; bei
  Bild-Upload-Fehler wird **weder** `emit('saved')` noch `closeModal()`
  aufgerufen (`return` direkt nach `uploadDogImage()`); `DogsView.vue`
  nachweislich **nicht** verändert (`git diff` liefert keinen Treffer).
- **T04** (`backend/public/.htaccess.production` entfernen): im Diff
  bestätigt (`D  backend/public/.htaccess.production`), Datei existiert
  nicht mehr im Working Tree; Ursprungs-Commit (`5a8f185`) über
  `git log --diff-filter=A` nachvollzogen.

**Nachträgliche Fixes (nicht als eigene Task, sondern als Nacharbeit an
T01/T02 dokumentiert):**

- **Fix 1 (Reviewer-Muss/Sicherheit):** `<FilesMatch "^(\.user\.ini|php\.ini)$">`-
  Deny-Block in `deployment-templates/htaccess/backend-public.htaccess` —
  im Diff bestätigt vorhanden, exakt wie in `task-T02.review-fixes.md`
  beschrieben (duale Apache-2.2/2.4-Syntax, Platzierung nach dem
  `php_value`-Block).
- **Fix 2 (Tester-kritischer Fund):** `.github/workflows/deploy.yml` kopiert
  jetzt `backend-public.user.ini` → `deploy-package/backend/public/.user.ini`
  und `backend-public.php.ini` → `deploy-package/backend/public/php.ini` —
  im Diff bestätigt, an der vom Tester benannten Stelle (direkt nach dem
  bestehenden `.htaccess`-Kopierblock).

Fazit: **Alle vier Tasks plus beide Nacharbeiten sind im Working Tree
tatsächlich umgesetzt** — keine Diskrepanz zwischen Berichten und Code
gefunden.

---

## Spec-Konformität

Die Spec-Delta-Datei (`specs/dog-image-upload/spec.md`) war nach den
beiden Nacharbeiten nicht mehr vollständig deckungsgleich mit der finalen
Implementierung: Sie beschrieb `LimitRequestBody`, den `php_value`-Fallback
und die `.user.ini`/`php.ini`-Mechanik korrekt, erwähnte aber weder den
neuen `<FilesMatch>`-Deny-Block (Fix 1) noch die Anforderung, dass **alle**
Packaging-Pfade (inkl. `deploy.yml`, Fix 2) die beiden neuen Dateien
ausliefern müssen.

**Durchgeführte Korrektur (im Rahmen dieser Abnahme):** Die Requirement
"Dog profile image upload accepts files up to 5 MB" in
`specs/dog-image-upload/spec.md` wurde um zwei Absätze und ein neues
Szenario ergänzt:

- Absatz, der explizit fordert, dass `.user.ini`/`php.ini` von **jedem**
  Packaging-Pfad (`build-deployment.sh`, `build-deployment-docker.sh`,
  `.github/workflows/deploy.yml`) ausgeliefert werden müssen.
- Absatz, der fordert, dass `backend-public.htaccess` direkten öffentlichen
  HTTP-Zugriff auf beide Dateien unterbindet.
- Neues Szenario "PHP config override files are not exposed via public
  HTTP access" (`GET /.user.ini` / `GET /php.ini` → HTTP 403).

`openspec validate --strict` wurde nach der Änderung erneut ausgeführt und
bleibt grün. Die restlichen Requirements/Szenarien der Spec-Delta-Datei
entsprechen bereits vor dieser Korrektur 1:1 der Implementierung (Werte,
Dateipfade, Verhalten bei Bild-Upload-Fehlern).

---

## Review-Befunde

- **T01:** Gesamtempfehlung "ok", keine Muss-/Sollte-Befunde.
- **T02:** Gesamtempfehlung ursprünglich "nacharbeit-nötig" (ein
  Muss-Befund: `.user.ini`/`php.ini` öffentlich per HTTP abrufbar) — **durch
  Fix 1 behoben und im Diff verifiziert** (siehe oben).
- **T03:** Gesamtempfehlung "ok". Ein "Könnte"-Befund (fehlender Erfolgs-Toast
  beim Retry-Erfolgsfall) ist optional und nicht blockierend — dokumentiert,
  nicht behoben; das ist zulässig (Workflow verlangt nur Auflösung von
  "Muss"-Befunden).
- **T04:** Gesamtempfehlung "ok", keine Muss-/Sollte-Befunde.
- **Tester-Fund (kritisch, außerhalb des ursprünglichen Review-Scopes):**
  `deploy.yml` lieferte T02-Dateien nicht aus — **durch Fix 2 behoben und im
  Diff verifiziert** (siehe oben).

**Alle "Muss"-Befunde sind aufgelöst.** Keine offenen "Muss"-Punkte aus
Review oder Test-Report.

---

## Test-Ergebnisse

Laut `task-report.md` und `task-T02.review-fixes.md` (nach beiden Fixes):

- Backend: 671 Pest-Tests grün (2087 Assertions).
- Frontend: 128 Vitest-Tests grün (inkl. 7 neuer Tests in
  `DogFormModal.test.ts`, die alle acht Akzeptanzkriterien von T03 abdecken).
- `npm run build`: fehlerfrei, keine Type-Errors, keine Warnungen.

**Dokumentierte, nicht-blockierende Lücken (aus `task-report.md`, vom Tester
selbst benannt, kein Fehlschlag):**

1. Kein automatisierter Regressionsschutz für T01/T02-Dateiinhalte
   innerhalb der vorgeschriebenen Docker-/CI-Pipeline, da `docker-compose.yml`
   und `.github/workflows/ci.yml` (Job `backend-tests`) ausschließlich
   `backend/` in den PHP-Container mounten — Dateien unter
   `deployment-templates/`, `build-deployment*.sh` sind darin nicht
   erreichbar. Der Tester hat dies transparent gemacht und einen
   probeweise ergänzten, dort rot laufenden Test bewusst wieder entfernt,
   statt eine dauerhaft fehlschlagende Testdatei zu hinterlassen. Manuelle
   Verifikation (durch Tester und jetzt zusätzlich durch mich selbst per
   `git diff`) bestätigt die inhaltliche Korrektheit.
2. `npm run lint` existiert projektweit nicht (kein Skript, keine
   ESLint-Konfiguration) — vorbestehende Repo-Lücke, nicht durch diesen
   Change verursacht oder behebbar.
3. `composer qa`/`composer stan`/`composer compat-check` sind im
   tatsächlich verwendeten `backend/composer.json` nicht definiert
   (Diskrepanz zum Root-`composer.json`, das offenbar ein inaktiver
   Rest-Skeleton ist) — ebenfalls vorbestehend, in `task-T01.notes.md` und
   `task-T02.notes.md` transparent dokumentiert. Da dieser Change keine
   PHP-Anwendungslogik unter `app/` ändert, ist PHP-8.2-Kompatibilität
   (CLAUDE.md Abschnitt 4.1) ohnehin nicht einschlägig; `vendor/bin/pest`
   wurde als inhaltlich aussagekräftiger Ersatz durchgehend ausgeführt.

Keine dieser drei Lücken ist auf diesen Change zurückzuführen oder blockiert
die Abnahme — sie betreffen vorbestehende Repo-/Tooling-Zustände außerhalb
des Scopes von T01–T04.

---

## Erfüllt

- Apache-seitiges Body-Size-Limit (`LimitRequestBody`) für Shared Hosting
  ergänzt, ohne bestehende Rewrite-/Security-Header-Logik zu verändern.
- Zwei zusätzliche, additive PHP-Ini-Mechanismen (`.user.ini`, `php.ini`)
  für PHP-FPM- bzw. CGI/FastCGI-Hosts, deren genauer Modus beim Ziel-Hoster
  nicht bekannt ist.
- Sicherheitslücke (öffentlich abrufbare `.user.ini`/`php.ini`) noch vor
  Abschluss des Changes erkannt und behoben, lokal mit echtem Apache 2.4
  verifiziert (403 bestätigt für beide Dateien, Rewrite-Fallback und
  Security-Header weiterhin funktionsfähig).
- Kritische Deployment-Lücke (`deploy.yml` dupliziert die Kopierlogik und
  hätte T02 nicht ausgeliefert) noch vor Abschluss des Changes erkannt und
  behoben.
- Frontend verschluckt Bild-Upload-Fehler nicht mehr: Modal bleibt offen,
  dauerhafter Fehlerbanner, kein doppeltes Anlegen bei Retry, funktionierender
  Retry-Pfad, "Abbrechen" weiterhin nutzbar — alles durch neue,
  gezielte Komponententests abgedeckt und vom Tester kritisch gegengelesen.
- Toter Code (`backend/public/.htaccess.production`) entfernt, keine
  verbleibenden Referenzen in Build-Skripten oder Betriebsdokumentation.
- Spec-Delta jetzt vollständig deckungsgleich mit der finalen
  Implementierung (inkl. beider Nacharbeiten).
- `openspec validate --strict` grün.

## Offen / Nacharbeit

Keine blockierende Nacharbeit. Folgende Punkte sind bewusste, dokumentierte
Restrisiken bzw. Out-of-Scope-Beobachtungen — dem User zur Kenntnisnahme,
nicht zur Blockade:

- **Echter Shared-Hosting-Smoke-Test nach Deployment weiterhin nötig.**
  Sowohl der `LimitRequestBody`/`AllowOverride`-Mechanismus (T01) als auch
  die `.user.ini`/`php.ini`-Auswertung (T02) sind aus dem Repo heraus nicht
  gegen den tatsächlichen Ziel-Hoster verifizierbar — die lokale
  Docker-Umgebung nutzt Nginx, nicht Apache; der Deny-Block-Fix wurde nur
  gegen einen lokalen macOS-System-Apache 2.4 simuliert, nicht gegen den
  echten Hoster. **Empfehlung:** nach dem nächsten Produktions-Deployment
  einmalig ein Testbild zwischen 2 MB und 8 MB hochladen und zusätzlich
  `GET /.user.ini` sowie `GET /php.ini` gegen die Live-Domain prüfen
  (erwartet: 403).
- **Kein automatisierter Regressionsschutz** für T01/T02-Konfigurationsdateien
  innerhalb der vorgeschriebenen Docker-/CI-Pipeline (siehe "Test-Ergebnisse",
  Punkt 1) — ein künftiger Merge/Rebase könnte den Inhalt versehentlich
  verändern, ohne dass ein automatisierter Test dies bemerkt. Kein
  Blocker für diesen Change, aber ein sinnvoller Kandidat für einen
  eigenen kleinen Folge-Change (z. B. Docker-Compose-Volume-Mount auf den
  Repo-Root erweitern oder eine separate Bats-/Shell-Test-Suite).
- **Strukturelle Diskrepanz `deploy.yml` vs. Build-Skripte** (bereits vom
  Tester und in `task-T02.review-fixes.md` als "außerhalb des Scopes"
  vermerkt): `deploy.yml` dupliziert die Kopierlogik von
  `build-deployment.sh`/`build-deployment-docker.sh` manuell, statt eines
  der beiden Skripte aufzurufen. Der jetzige Fix behebt die konkrete
  Symptomatik (fehlende T02-Dateien), beseitigt aber nicht die
  Drift-Quelle selbst — ein dritter künftiger Kopierpfad müsste erneut an
  allen drei Stellen manuell nachgezogen werden. Empfehlung: eigener
  openspec-Change ("`deploy.yml` auf `build-deployment.sh` umstellen").
- **Vorbestehende Tooling-Lücken** (kein `npm run lint`, keine
  `composer qa`/`stan`/`compat-check`-Scripts im tatsächlich genutzten
  `backend/composer.json`) — nicht durch diesen Change verursacht, aber
  seit `CLAUDE.md` Abschnitt 5/7.1 formal vorgeschrieben. Sollte
  unabhängig von diesem Change nachgezogen werden (eigener kleiner Change:
  "CLAUDE.md-QA-Kommandos mit tatsächlichem Repo-Zustand synchronisieren").
- Im Arbeitsverzeichnis liegen zusätzlich unbeteiligte, unversionierte
  Artefakte eines anderen Vorhabens (`openspec/changes/course-run-booking/`,
  `openspec/triage/20260517184957-course-run-booking.md`). Diese gehören
  nicht zu `fix-dog-image-upload-shared-hosting` und sollten beim Commit
  dieses Changes **nicht** mit eingecheckt werden (separates Vorhaben,
  separater Branch).

## Empfehlung an den User

Der Change ist inhaltlich vollständig, alle Muss-Befunde sind behoben und
durch eigene Diff-Prüfung bestätigt (nicht nur aus den Agenten-Berichten
übernommen); die Spec-Delta-Datei wurde im Rahmen dieser Abnahme an die
finale Implementierung angeglichen. **Empfehlung: Freigabe für User-Gate 2**
unter der Auflage, nach dem nächsten Produktions-Deployment den in
"Offen / Nacharbeit" beschriebenen Shared-Hosting-Smoke-Test (Bild-Upload
2–8 MB, `GET /.user.ini`/`GET /php.ini` → 403) durchzuführen — dies ist ein
Post-Deployment-Verifikationsschritt, kein Grund, den Change selbst
zurückzuhalten.
