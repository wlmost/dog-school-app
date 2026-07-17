# Proposal: add-announcement-banner

**Change-ID:** add-announcement-banner
**Typ:** Feature (additiv, kein Breaking Change)
**Priorität:** mittel
**Datum:** 2026-07-17
**Triage:** `openspec/triage/20260717103000-announcement-banner.md`

---

## Why

Die Hundeschule hat aktuell keine Möglichkeit, kurzfristige Mitteilungen
(z. B. Kursausfälle, neue Angebote, Betriebsferien) prominent auf der
öffentlichen Landingpage zu kommunizieren, ohne den festen Seiteninhalt in
`HomeView.vue` zu bearbeiten. Admins brauchen einen einfachen Weg, um
zeitlich befristete Ankündigungen mit Bild zu veröffentlichen, die nach
einer festgelegten Anzahl Tage automatisch nicht mehr angezeigt werden —
ohne dass dafür ein Server-Scheduler oder manuelles Löschen nötig ist
(Shared-Hosting-Einschränkung, siehe `CLAUDE.md` Abschnitt 4.3).

## What Changes

- Neues Datenmodell `Announcement` (Tabelle `announcements`): Titel,
  Rich-Text-Inhalt (HTML, serverseitig sanitized), optionales Bild,
  Anzeigedauer in Tagen. Der Ablauf wird **anzeigeseitig berechnet**
  (`expires_at`, aus `created_at` + `display_days` beim Speichern
  vorausberechnet) — kein Cron-/Scheduler-Task nötig.
- Öffentlicher Endpunkt `GET /api/v1/announcements` liefert **alle aktuell
  aktiven** Ankündigungen als Liste (mehrere gleichzeitig aktive
  Ankündigungen sind laut Nutzerentscheidung ausdrücklich erlaubt).
- Neue öffentliche Komponente `AnnouncementBanner.vue`, eingebunden in
  `HomeView.vue` zwischen der Hero-Section (endet Zeile 26) und der
  Feature-Section "Unsere Leistungen" (beginnt Zeile 29). Bedingtes
  Rendering: Der Bereich erscheint nur, wenn mindestens eine aktive
  Ankündigung existiert.
- Neuer Admin-Bereich (`AnnouncementsView.vue`, Route `/app/announcements`,
  `requiresAdmin: true`) zum Erstellen/Bearbeiten/Löschen von
  Ankündigungen. Der Rich-Text wird über die bereits im Projekt vorhandene
  `HtmlEditor.vue`-Komponente (Tiptap-basiert) erfasst, das Bild über die
  bereits vorhandene `FileUpload.vue`-Komponente (`multiple=false`)
  hochgeladen. Die Admin-Liste zeigt **auch abgelaufene** Ankündigungen mit
  einem Status-Badge ("aktiv"/"abgelaufen") — es wird nichts automatisch
  gelöscht.
- Serverseitiges HTML-Sanitizing beim Speichern über den bereits
  bestehenden Trait `App\Http\Requests\Concerns\SanitizesHtmlContent`
  (aktuell verwendet von `StoreCourseRequest`/`UpdateCourseRequest` für das
  Kurs-Beschreibungsfeld) — **keine neue Composer-Abhängigkeit nötig**, da
  dieser Mechanismus bereits im Projekt etabliert ist und exakt zum
  clientseitigen Tiptap/DOMPurify-Editor passt, den `HtmlEditor.vue` bereits
  verwendet.

## Capabilities

### New Capabilities

- `announcement-management`: Erstellung, Sanitizing, Speicherung, zeitlich
  befristete öffentliche Anzeige und admin-seitige Verwaltung (inkl.
  Historie abgelaufener Einträge) von Ankündigungen mit Bild und
  Rich-Text-Inhalt.

### Modified Capabilities

*(keine — es gibt aktuell keine dokumentierte Capability für die
Landingpage-Struktur (`HomeView.vue`) oder das Admin-Navigationsmenü
(`DefaultLayout.vue`), deren bestehendes Verhalten durch dieses Feature
geändert würde; beide Änderungen sind rein additiv — ein neuer,
bedingt sichtbarer Abschnitt bzw. ein neuer Menüpunkt, kein bestehendes
Verhalten wird entfernt oder umdefiniert.)*

## Impact

**Betroffene Dateien (siehe `design.md` für Details und Task-Zuordnung):**

- Backend (neu): Migration `create_announcements_table`, Model
  `Announcement` (+ Factory), Policy `AnnouncementPolicy`, FormRequests
  `StoreAnnouncementRequest`/`UpdateAnnouncementRequest`, Resource
  `AnnouncementResource`, Controller `Api/AnnouncementController`,
  Routen-Ergänzung in `backend/routes/api.php`, Feature-Test
  `tests/Feature/Api/AnnouncementApiTest.php`.
- Frontend (neu): `frontend/src/api/announcements.ts`,
  `frontend/src/composables/useAnnouncements.ts`,
  `frontend/src/components/AnnouncementBanner.vue`,
  `frontend/src/views/AnnouncementsView.vue`, zugehörige Vitest-Tests.
- Frontend (ändern): `frontend/src/views/HomeView.vue` (neue Sektion
  zwischen Zeile 26/29), `frontend/src/router/index.ts` (neue Route),
  `frontend/src/layouts/DefaultLayout.vue` (neuer Navigationspunkt für
  Admins).

**Keine betroffenen Drittsysteme, keine Queue-/Scheduler-Änderungen, kein
Shell-Exec, keine WebSockets.** Kein neues npm- oder Composer-Paket
erforderlich (Rich-Text-Editor, HTML-Sanitizing client- und serverseitig
sind bereits im Projekt vorhanden, siehe `design.md` Abschnitt 3).

**DB-Portabilität:** Die neue Migration verwendet ausschließlich
`Schema::create()` mit treiberneutralen Blueprint-Typen (`string`, `text`,
`unsignedSmallInteger`, `timestamp`, `timestamps`) — kein raw SQL, kein
Driver-Switch nötig (siehe `design.md` Abschnitt 2).

**Datei-Upload:** folgt dem etablierten `Storage::disk('public')`-Muster
(`backend/app/Http/Controllers/Api/DogController.php:192-199`) — Bilder
werden im bereits per `storage:link` öffentlich verlinkten Verzeichnis
abgelegt, keine neuen Shared-Hosting-Anforderungen. Die bestehenden
`.htaccess`-Body-Size-Limits (`LimitRequestBody 10485760`,
`upload_max_filesize 10M`) decken die geplante Laravel-Validierungsgrenze
von `max:5120` (5 MB, identisch zu `DogController`) bereits ab — keine
Änderung an `deployment-templates/htaccess/backend-public.htaccess`
nötig.

## Out of Scope

- Kein Scheduler-/Cron-Task zum "Aufräumen" abgelaufener Ankündigungen —
  laut Nutzerentscheidung wird der Ablauf ausschließlich anzeigeseitig
  berechnet, abgelaufene Einträge bleiben in der DB und im Admin-Bereich
  sichtbar (Status-Badge), bis ein Admin sie manuell löscht.
- Kein manueller "jetzt veröffentlichen/sofort deaktivieren"-Schalter —
  nicht angefordert; die Anzeigedauer in Tagen ab Erstellung ist die
  einzige Steuerungsgröße (YAGNI).
- Keine Galerie/Mehrfachbilder pro Ankündigung — laut Nutzerentscheidung
  genau ein Bild pro Ankündigung.
- Kein Karussell/Slider für mehrere gleichzeitig aktive Ankündigungen —
  `design.md` legt eine einfache vertikal gestapelte Liste fest (KISS,
  keine zusätzliche JS-Abhängigkeit für einen Slider).
- Keine Begrenzung der Anzahl gleichzeitig aktiver Ankündigungen auf der
  Landingpage — laut Nutzerentscheidung sind mehrere gleichzeitig aktive
  Ankündigungen ausdrücklich erwünscht; eine Obergrenze wurde nicht
  angefordert (liegt in der redaktionellen Verantwortung der Admins).
- Keine neue Composer-/npm-Abhängigkeit für Rich-Text oder HTML-Sanitizing
  — bestehende Mechanismen (`SanitizesHtmlContent`-Trait, `HtmlEditor.vue`,
  `dompurify`) werden wiederverwendet (siehe `design.md` Abschnitt 3).
- **Fehlende QA-Scripts (bereits im Vorgänger-Change
  `add-dog-owner-history-fields` festgestellt, weiterhin unverändert):**
  `backend/composer.json` enthält aktuell **keine** Scripts `test`, `lint`,
  `stan`, `qa` oder `compat-check` (geprüft, Stand 2026-07-17); `frontend/
  package.json` enthält **kein** `lint`-Script. Die tatsächlich
  verfügbaren Befehle sind `./vendor/bin/pest` (Backend) und `npm run
  test` / `npm run build` (Frontend). Die Akzeptanzkriterien in
  `tasks.md` referenzieren daher nur tatsächlich existierende Befehle.
  Das Einrichten der in `CLAUDE.md` referenzierten QA-Scripts bleibt ein
  eigenständiges, separates Vorhaben.

## Referenzen

- Triage: `openspec/triage/20260717103000-announcement-banner.md`
- Neue Capability `announcement-management` (siehe
  `specs/announcement-management/spec.md`)
- Bestehendes Sanitizing-Muster: `backend/app/Http/Requests/Concerns/SanitizesHtmlContent.php`,
  verwendet von `backend/app/Http/Requests/StoreCourseRequest.php:89-92`
- Bestehender Rich-Text-Editor: `frontend/src/components/HtmlEditor.vue`
- Bestehendes Anzeige-Sanitizing-Muster: `frontend/src/views/courses/CoursesView.vue:319-325`
  (`ALLOWED_TAGS`-Konstante + `DOMPurify.sanitize()`, "consistent with the
  backend sanitization allowlist")
- Bestehendes Upload-Muster: `backend/app/Http/Controllers/Api/DogController.php:180-202`
- Bestehendes Multipart-PUT-Muster (Fix-Präzedenzfall):
  `openspec/changes/archive/2026-07-01-fix-settings-upload-put-multipart/`,
  umgesetzt in `frontend/src/api/settings.ts:35-58`
