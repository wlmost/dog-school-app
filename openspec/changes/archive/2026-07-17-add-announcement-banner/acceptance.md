# Abnahme: add-announcement-banner

**Status:** bereit-für-user-review

**Geprüft am:** 2026-07-17, auf Branch `feature/add-announcement-banner`
(Working Tree, noch nicht committet).

---

## 0. Strukturelle Validität

```
$ openspec validate add-announcement-banner --strict
Change 'add-announcement-banner' is valid
```

## 1. Vollständigkeit (tasks.md)

Alle 11 Tasks (T01–T11) sind in `tasks.md` vollständig abgehakt (`[x]` auf
allen Akzeptanzkriterien). Für jede Task existiert eine `task-T<ID>.notes.md`.
Zusätzlich dokumentiert `task-T11.bugfix-review-blocker.notes.md` eine
Nachkorrektur zu T11 (siehe Abschnitt 3).

**Abweichung von der in `CLAUDE.md`/`WORKFLOW.md` beschriebenen
Artefaktstruktur (nicht blockierend, aber zu dokumentieren):** Es existiert
kein separates `task-T<ID>.review.md` pro Task, wie in
`CLAUDE.md` Abschnitt 8 und `WORKFLOW.md` Schritt 9 vorgesehen. Stattdessen
liegt ein einziger, changeweiter `task-report.test-report.md` (Tester) sowie
eine einzelne `task-T11.bugfix-review-blocker.notes.md` (Entwickler-Notiz zur
Behebung eines Reviewer-Befunds) vor. Der eine dokumentierte
Reviewer-Blocker-Befund selbst ist inhaltlich nachvollziehbar beschrieben und
nachweislich behoben (siehe Abschnitt 3) — es fehlt aber die formale
`review.md`-Artefaktspur je Task. Empfehlung: kein Show-Stopper für dieses
Gate, aber künftige Changes sollten die in `CLAUDE.md` Abschnitt 8
vorgesehene Datei-Konvention einhalten, damit Review-Historie pro Task
nachvollziehbar bleibt.

## 2. Spec-Konformität (proposal.md "What Changes" gegen Code-Stand)

Stichprobenartig gegen den tatsächlichen Diff (`git status`/gelesene
Dateien, da noch nicht committet) geprüft:

- **Migration** (`backend/database/migrations/2026_07_17_100000_create_announcements_table.php`):
  Schema entspricht exakt `design.md` Abschnitt 2.2 (`id`, `title`, `body`,
  `image_path` nullable, `unsignedSmallInteger display_days`,
  `timestamp expires_at` nicht nullable, `timestamps()`, Index auf
  `expires_at`). Ausschließlich treiberneutrale Blueprint-Methoden, kein raw
  SQL — DB-Portabilitäts-Vorgabe aus `CLAUDE.md` Abschnitt 4.2 erfüllt.
- **Model** (`backend/app/Models/Announcement.php`): `booted()`/`saving`-Hook,
  `isActive()`, `scopeActive()` 1:1 wie in `design.md` Abschnitt 2.3
  spezifiziert, gelesen und verglichen.
- **Policy** (`backend/app/Policies/AnnouncementPolicy.php`): alle fünf
  Methoden nur `isAdmin()`-Check, wie spezifiziert.
- **FormRequests**: `StoreAnnouncementRequest`/`UpdateAnnouncementRequest`
  nutzen `SanitizesHtmlContent`-Trait (keine neue Abhängigkeit, wie in
  `proposal.md`/`design.md` Abschnitt 3 zugesagt), `image` erscheint korrekt
  nicht in `validatedSnakeCase()`, `sometimes`-Regeln in Update-Request für
  Teil-Updates.
- **Resource** (`AnnouncementResource.php`): Feldnamen/Shape exakt wie im
  verbindlichen JSON-Beispiel in `design.md` Abschnitt 5.1 (camelCase,
  `imageUrl` via `Storage::disk('public')->url()`).
- **Controller** (`AnnouncementController.php`): `publicIndex`/`index`/
  `store`/`update`/`destroy` 1:1 wie in `design.md` Abschnitt 5.1 (inkl.
  Bild-Löschung vor Ersetzung/beim Löschen).
- **Routen** (`backend/routes/api.php`, `git diff` gelesen): Import
  alphabetisch korrekt einsortiert, öffentliche Route
  `GET /api/v1/announcements` außerhalb der `auth:sanctum`-Gruppe, vier
  Admin-Routen innerhalb `auth:sanctum` + `can:admin` — exakt wie in
  `design.md` Abschnitt 5.2 und in den Spec-Szenarien gefordert.
- **Frontend API-Client** (`frontend/src/api/announcements.ts`): `create`
  **und** `update` setzen explizit
  `headers: { 'Content-Type': 'multipart/form-data' }` — der vom Skeptiker in
  `verification.md` (Abschnitt "Widerlegt") aufgedeckte Fehlpunkt im
  ursprünglichen `design.md`-Codevorschlag wurde in der tatsächlichen
  Implementierung korrigiert (nicht der ursprüngliche fehlerhafte
  Entwurfscode übernommen). `update()` sendet `POST` mit `_method=PUT`, kein
  echtes HTTP-`PUT` — bestätigt.
- **`AnnouncementBanner.vue`**: `v-if="announcements.length"` auf der
  äußeren `<section>`, `DOMPurify.sanitize()` mit identischer
  `ALLOWED_TAGS`-Liste wie `CoursesView.vue`, `v-html` mit
  `eslint-disable-next-line vue/no-v-html`-Kommentar — bestätigt, exakt wie
  spezifiziert.
- **`HomeView.vue`**-Diff (`git diff` gelesen): `<AnnouncementBanner />`
  exakt zwischen dem schließenden Hero-`</section>` und dem
  `<!-- Features Section -->`-Kommentar eingefügt, Import ergänzt — exakt an
  der in `design.md` Abschnitt 7.2 vorgegebenen Stelle.
- **`AnnouncementsView.vue`**: Liste inkl. abgelaufener Ankündigungen mit
  Status-Badge (`isActive`), `HtmlEditor`/`FileUpload`-Wiederverwendung,
  Löschen mit Bestätigung — bestätigt.
- **`router/index.ts`/`DefaultLayout.vue`**-Diffs (`git diff` gelesen): neue
  Route `announcements` (`requiresAdmin: true`) exakt zwischen `settings`
  und `training-logs`; neuer Navigationseintrag "Ankündigungen"
  (`MegaphoneIcon`, `roles: ['admin']`) exakt zwischen "Einstellungen" und
  "Kontakt" — jeweils exakt an den in `design.md` Abschnitt 8.2/8.3
  vorgegebenen Stellen.

Keine Abweichung zwischen `proposal.md`/`design.md` und dem tatsächlichen
Code-Stand gefunden, mit Ausnahme der bereits im Skeptiker-`verification.md`
dokumentierten und in der Implementierung korrekt behobenen Design-Lücke
(fehlender Multipart-Header im ursprünglichen Codevorschlag).

## 3. Review-Befunde (Reviewer)

Ein "Muss"-Befund ist dokumentiert und geprüft:

- **Blocker:** geteilter `error`-Ref in `useAnnouncements.ts` versteckte die
  Ankündigungsliste in `AnnouncementsView.vue` bei fehlgeschlagenen
  Mutationen (Lösch-/Update-Fehler), weil die Template-`v-if`/`v-else-if`-
  Kette den Fehlerblock **statt** der Liste rendert, obwohl die Liste
  weiterhin gültige Daten enthielt.
- **Fix verifiziert:** `useAnnouncements.ts` wurde tatsächlich auf zwei
  getrennte Refs (`loadError`/`mutationError`) umgestellt (gelesener
  Code-Stand, siehe Abschnitt 2). `AnnouncementsView.vue` rendert
  `mutationError` jetzt in einem eigenen, von der Lade-/Leer-/Listen-Kette
  unabhängigen `<div v-if="mutationError">`-Block (Zeile 22 der Datei,
  geprüft per `grep`) — die Liste (`<ul v-else>`) bleibt bei einem
  Mutationsfehler sichtbar. Damit ist der Blocker sachlich behoben, nicht
  nur kosmetisch.
- Kein weiterer offener "Muss"-Befund in den vorliegenden Artefakten.

## 4. Testergebnisse

**Backend** (`docker compose exec php vendor/bin/pest`, selbst ausgeführt,
nicht nur den Berichten vertraut):

```
Tests:    718 passed (2259 assertions)
Duration: 26.62s
```

Deckt sich exakt mit dem im Tester-Report dokumentierten
Nach-Ergänzungs-Stand (718 passed).

**Frontend** (`npm run test -- --run`, selbst ausgeführt):

```
Test Files  17 passed (17)
Tests       191 passed (191)
```

191 (nicht 189) — deckt sich mit dem Stand **nach** dem in
`task-T11.bugfix-review-blocker.notes.md` dokumentierten Bugfix
(189 Basis + 2 neue Testfälle für den Blocker-Nachweis).

**Frontend-Build** (`npm run build`, selbst ausgeführt):

```
vue-tsc -b && vite build
✓ 643 modules transformed.
✓ built in 1.36s
```

Keine TypeScript-Fehler, keine Build-Warnungen (Akzeptanzkriterium gemäß
`CLAUDE.md` Abschnitt "Projektspezifische Workflow-Regeln").

Keine ungetesteten Akzeptanzkriterien aus `tasks.md` gefunden — die vom
Tester ergänzten 29 Tests (14 Backend + 15 Frontend) schließen konkret
benannte Lücken (Model-Ebene `display_days`-Neuberechnung ab ursprünglichem
`created_at`, Composable-Fehlerpfade, Integrationstest mit echtem
Composable, Update-/Delete-Fehlerfälle in der Admin-View).

**Hinweis (kein Blocker):** Die in `CLAUDE.md`/`proposal.md` referenzierten
Composer-Scripts (`composer qa`, `composer test`, `composer compat-check`)
existieren weiterhin nicht in `backend/composer.json` (bereits im
Vorgänger-Change `add-dog-owner-history-fields` und in diesem Change
konsistent dokumentiert, siehe `proposal.md` "Out of Scope"). Ebenso fehlt
`npm run lint` in `frontend/package.json`. Beides ist als bekannte,
vorbestehende Lücke außerhalb des Scopes dieses Changes dokumentiert, nicht
neu durch diesen Change eingeführt — kein Abnahme-Hindernis für
`add-announcement-banner`, aber ein guter Kandidat für einen eigenen
zukünftigen Change (bereits so vom Architekten in Modus A vermerkt).

## 5. Spec-Delta-Konformität (`specs/announcement-management/spec.md`)

Alle sieben Requirements gegen den tatsächlichen Code/Test-Stand geprüft:

| Requirement | Status | Beleg |
|---|---|---|
| Admins can create announcements with rich text, image, display duration | erfüllt | `StoreAnnouncementRequest.php` (Regeln 1–365, `image` optional), `AnnouncementController::store()`, Tests in `AnnouncementApiTest.php` (Bild-Upload, Randwerte 1/365) |
| Non-admins rejected (403/401) | erfüllt | Policy + `authorize()` in beiden FormRequests, vier eigene Tests in `AnnouncementApiTest.php` |
| Body HTML sanitized server-side | erfüllt | `SanitizesHtmlContent`-Trait-Nutzung in beiden FormRequests, Test "rohes `<script>`/`onclick`-HTML wird entfernt" laut `tasks.md` T08 |
| Multiple announcements active simultaneously | erfüllt | `publicIndex()` liefert Liste (`AnonymousResourceCollection`), kein Mechanismus zum Deaktivieren anderer Einträge; Test "liefert am öffentlichen endpunkt nur aktive ankündigungen" mit mehreren Datensätzen |
| Expiry computed without scheduler | erfüllt | `booted()`/`saving`-Hook in `Announcement.php`, `scopeActive()`, keine Cron-/Queue-Abhängigkeit; Model-Tests in `AnnouncementModelTest.php` (u. a. "ändert `expires_at` nicht bei reiner Textänderung", "berechnet ab ursprünglichem `created_at`") |
| Public landing page shows only active announcements, conditional section | erfüllt | `AnnouncementBanner.vue` `v-if="announcements.length"`, Platzierung in `HomeView.vue` verifiziert; `AnnouncementBanner.integration.test.ts` deckt Leer-/Fehlerfall ab |
| Admin area lists all incl. expired, with status + delete removes image | erfüllt | `index()`-Endpunkt liefert alle, `AnnouncementsView.vue` Status-Badge nach `isActive`, `destroy()` löscht Bild-Datei; Tests in `AnnouncementApiTest.php` ("Admin-Endpunkt liefert auch abgelaufene", Lösch-Test mit `Storage::fake`) |

Kein Requirement/Szenario ohne Code- und Test-Deckung gefunden.

## Erfüllt

- Alle 11 Tasks vollständig und nachvollziehbar umgesetzt, Code entspricht
  `proposal.md`/`design.md` exakt (Stichproben mit Datei:Zeile-Beleg
  durchgeführt, keine Abweichung außer der bereits korrigierten
  Design-Lücke).
- `openspec validate --strict` erfolgreich.
- Backend-Testsuite komplett grün (718/718), Frontend-Testsuite komplett
  grün (191/191), `npm run build` fehlerfrei ohne Warnungen.
- Der einzige dokumentierte Reviewer-"Muss"-Befund (geteilter `error`-Ref)
  ist nachweislich und korrekt behoben, inkl. Regressionstests.
- Alle sieben Requirements aus `specs/announcement-management/spec.md` sind
  durch Code und Tests gedeckt.
- Shared-Hosting-Kompatibilität (kein Scheduler, kein raw SQL, keine neue
  Abhängigkeit, treiberneutrale Migration) wie in `design.md` Abschnitt 10
  behauptet, durch Codelektüre bestätigt.

## Offen / Nacharbeit

- **Nicht blockierend:** Fehlende formale `task-T<ID>.review.md`-Dateien pro
  Task (siehe Abschnitt 1) — Prozessabweichung von `CLAUDE.md` Abschnitt 8,
  aber der eine dokumentierte Reviewer-Befund selbst ist inhaltlich
  nachvollziehbar und nachweislich behoben. Empfehlung: für künftige Changes
  die Datei-Konvention einhalten.
- **Nicht blockierend, bereits vorbestehend:** `composer qa`/`compat-check`/
  `npm run lint` fehlen weiterhin im Projekt (dokumentiert in `proposal.md`
  "Out of Scope", nicht durch diesen Change verursacht). Empfehlung: eigener
  zukünftiger Change zur Einrichtung dieser Scripts.
- **Zur Kenntnisnahme, kein Defekt:** Der Tester weist auf eine UX-Beobachtung
  hin (nicht Teil der Akzeptanzkriterien): unmittelbar nach einem
  fehlgeschlagenen Löschversuch wird kurzzeitig ein Fehlerbanner
  **zusätzlich zur** Liste angezeigt (nach dem Bugfix korrekt, nicht mehr
  "statt" der Liste) — funktional unbedenklich, keine Aktion nötig.
- **Vor Push/PR laut `CLAUDE.md` Abschnitt 7.1 noch ausstehend:** Der
  lokale MySQL-Gegentest der Migration wurde laut `task-T01.notes.md` bereits
  über einen Ad-hoc-Container durchgeführt (da `docker-compose.mysql.yml`
  im Projekt fehlt) — das ersetzt den in `CLAUDE.md` beschriebenen
  Standard-Workflow-Schritt nicht vollständig, ist aber als gleichwertiger
  Nachweis dokumentiert. Kein Abnahme-Hindernis, aber vor dem eigentlichen
  `git push` sollte erneut geprüft werden, ob eine reguläre
  `docker-compose.mysql.yml` inzwischen existiert.

## Empfehlung an den User

Der Change ist inhaltlich vollständig, spec-konform und vollständig
grün getestet (718 Backend- + 191 Frontend-Tests, sauberer Build). Der
einzige harte Reviewer-Befund ist behoben und verifiziert. Empfehlung:
**Freigabe für User-Gate 2**, mit Kenntnisnahme der oben genannten
nicht-blockierenden Prozess- und Altlast-Punkte (fehlende `review.md`-Dateien
je Task, weiterhin fehlende QA-Scripts im Projekt).
