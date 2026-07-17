# Tasks für add-announcement-banner

**Change-ID:** add-announcement-banner

**Übergabepunkt Backend → Frontend:** `AnnouncementResource`-JSON-Shape,
siehe `design.md` Abschnitt 5.1 ("Verbindliches JSON-Beispiel"). T09
(Frontend-API-Client) kann implementiert werden, sobald T05 (Resource) und
T07 (Routen) abgeschlossen sind — der Response-Shape ist bereits jetzt in
`design.md` final festgelegt und ändert sich durch die restlichen
Backend-Tasks nicht mehr.

---

## T01: Migration `create_announcements_table`

- **Agent:** dev-php
- **Dateien:**
  - `backend/database/migrations/2026_07_17_100000_create_announcements_table.php` *(neu)*
- **Abhängigkeiten:** keine
- **Beschreibung:**
  Neue Migration gemäß `design.md` Abschnitt 2.2:

  ```php
  Schema::create('announcements', function (Blueprint $table) {
      $table->id();
      $table->string('title', 255);
      $table->text('body');
      $table->string('image_path')->nullable();
      $table->unsignedSmallInteger('display_days');
      $table->timestamp('expires_at');
      $table->timestamps();

      $table->index('expires_at');
  });
  ```

  `down()`: `Schema::dropIfExists('announcements');`
- **Akzeptanzkriterien:**
  - [x] `php artisan migrate` läuft ohne Fehler gegen PostgreSQL (lokale
        Docker-Standardumgebung)
  - [x] `php artisan migrate` läuft ohne Fehler gegen MySQL
        (`docker-compose.mysql.yml`, siehe CLAUDE.md Abschnitt 7.1)
  - [x] `php artisan migrate:rollback` löscht die Tabelle korrekt auf
        beiden Treibern
  - [x] Kein raw SQL, kein `DB::statement()` in der Migration
  - [x] Manuelle Prüfung: keine der in CLAUDE.md Abschnitt 4.1 gelisteten
        PHP-8.3/8.4-Konstrukte verwendet (kein automatisiertes
        `compat-check`-Script im Projekt vorhanden, siehe `proposal.md`
        "Out of Scope")

---

## T02: Model `Announcement` + Factory

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Models/Announcement.php` *(neu)*
  - `backend/database/factories/AnnouncementFactory.php` *(neu)*
- **Abhängigkeiten:** T01
- **Beschreibung:**
  Model gemäß `design.md` Abschnitt 2.3: `$fillable` (`title`, `body`,
  `image_path`, `display_days`), `casts()`, `booted()`-Hook zur Berechnung
  von `expires_at` aus `created_at`/`now()` + `display_days` bei jedem
  Speichern mit geändertem `display_days` (oder Neuanlage), Methode
  `isActive(): bool`, Scope `scopeActive()`.

  Factory mit realistischen Faker-Werten für `title`/`body`/`display_days`
  (1–30 Tage) sowie einem `expired()`-State (setzt `created_at`/`expires_at`
  über `forceFill(...)->saveQuietly()` in die Vergangenheit, siehe
  `design.md` Abschnitt 2.3 für den exakten Code).
- **Akzeptanzkriterien:**
  - [x] `Announcement::factory()->create()` erzeugt einen Datensatz mit
        korrekt berechnetem `expires_at` (`created_at + display_days`)
  - [x] `Announcement::factory()->expired()->create()` erzeugt einen
        Datensatz, dessen `isActive()` `false` liefert
  - [x] Wird `display_days` auf einem bestehenden, nicht abgelaufenen
        Datensatz erhöht und gespeichert, wird `expires_at` neu ab dem
        ursprünglichen `created_at` berechnet (nicht ab `now()`)
  - [x] `scopeActive()` liefert nur Datensätze mit `expires_at > now()`

---

## T03: Policy `AnnouncementPolicy`

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Policies/AnnouncementPolicy.php` *(neu)*
- **Abhängigkeiten:** T02
- **Beschreibung:**
  Policy analog zu `backend/app/Policies/SettingPolicy.php` — alle
  Methoden (`viewAny`, `view`, `create`, `update`, `delete`) prüfen
  ausschließlich `$user->isAdmin()`. Siehe `design.md` Abschnitt 4.1 für
  den vollständigen Code.
- **Akzeptanzkriterien:**
  - [x] Alle fünf Policy-Methoden implementiert, jeweils `isAdmin()`-Check
  - [x] Kein automatisches Policy-Discovery-Problem: Laravel 11 löst
        `App\Models\Announcement` → `App\Policies\AnnouncementPolicy` über
        die Standard-Namenskonvention auf (kein manueller Eintrag in einem
        Service-Provider nötig — wie bei `SettingPolicy`/`DogPolicy`
        bereits der Fall; falls das Projekt dennoch einen expliziten
        Policy-Provider pflegt, dort ergänzen)

---

## T04: FormRequests `StoreAnnouncementRequest` / `UpdateAnnouncementRequest`

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Requests/StoreAnnouncementRequest.php` *(neu)*
  - `backend/app/Http/Requests/UpdateAnnouncementRequest.php` *(neu)*
- **Abhängigkeiten:** T02
- **Beschreibung:**
  Siehe `design.md` Abschnitt 4.2/4.3 für den vollständigen Code beider
  Klassen. Beide verwenden
  `App\Http\Requests\Concerns\SanitizesHtmlContent` (bestehender Trait,
  **keine neue Abhängigkeit**) zum Sanitizing von `body` in
  `validatedSnakeCase()`. `image` wird validiert, aber **nicht** in
  `validatedSnakeCase()` zurückgegeben (Controller behandelt den Upload
  separat, siehe T06).
- **Akzeptanzkriterien:**
  - [x] `StoreAnnouncementRequest::authorize()` gibt `false` zurück, wenn
        der User nicht `isAdmin()` ist
  - [x] `body` wird über `sanitizeHtmlDescription()` bereinigt, bevor es
        in `validatedSnakeCase()` erscheint
  - [x] `image` erscheint **nicht** als Schlüssel in
        `validatedSnakeCase()`
  - [x] `displayDays` außerhalb von 1–365 wird mit HTTP 422 abgelehnt
  - [x] `UpdateAnnouncementRequest` erlaubt Teil-Updates (`sometimes`),
        nur übergebene Felder erscheinen in `validatedSnakeCase()`

---

## T05: Resource `AnnouncementResource`

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Resources/AnnouncementResource.php` *(neu)*
- **Abhängigkeiten:** T02
- **Beschreibung:**
  Siehe `design.md` Abschnitt 5.1 für den vollständigen Code und das
  verbindliche JSON-Beispiel. Felder: `id`, `title`, `body`, `imageUrl`
  (über `Storage::disk('public')->url()`), `displayDays`, `expiresAt`
  (ISO 8601), `isActive` (bool), `createdAt`, `updatedAt`.
- **Akzeptanzkriterien:**
  - [x] Response-Shape entspricht exakt dem JSON-Beispiel in `design.md`
        Abschnitt 5.1 (Feldnamen camelCase)
  - [x] `imageUrl` ist `null`, wenn kein Bild gesetzt ist
  - [x] `isActive` spiegelt `Announcement::isActive()` wider

---

## T06: Controller `Api/AnnouncementController`

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Controllers/Api/AnnouncementController.php` *(neu)*
- **Abhängigkeiten:** T03, T04, T05
- **Beschreibung:**
  Siehe `design.md` Abschnitt 5.1 für den vollständigen Code:
  `publicIndex()` (nur aktive, ungeschützt), `index()` (alle, admin-only
  via Policy `viewAny`), `store()` (admin-only via
  `StoreAnnouncementRequest::authorize()`, inkl. Bild-Upload analog
  `DogController::uploadImage()`), `update()` (admin-only via Policy
  `update`, ersetzt vorhandenes Bild bei neuem Upload, löscht das alte),
  `destroy()` (admin-only via Policy `delete`, löscht zugehöriges Bild).
- **Akzeptanzkriterien:**
  - [x] `publicIndex()` liefert ausschließlich Ankündigungen mit
        `expires_at > now()`
  - [x] `index()` liefert alle Ankündigungen (aktiv + abgelaufen)
  - [x] `store()` speichert ein hochgeladenes Bild unter
        `announcement-images/` auf der `public`-Disk und setzt
        `image_path` korrekt
  - [x] `update()` löscht das alte Bild von der Disk, wenn ein neues
        hochgeladen wird
  - [x] `destroy()` löscht das zugehörige Bild von der Disk mit
  - [x] Alle vier Admin-Aktionen (`index`/`store`/`update`/`destroy`)
        antworten mit HTTP 403, wenn der authentifizierte User kein Admin
        ist

---

## T07: Routen-Ergänzung `backend/routes/api.php`

- **Agent:** dev-php
- **Dateien:**
  - `backend/routes/api.php` *(ändern)*
- **Abhängigkeiten:** T06
- **Beschreibung:**
  Siehe `design.md` Abschnitt 5.2 für die exakten Einfügepunkte:
  `use`-Import für `AnnouncementController` (alphabetisch zwischen
  `AnamnesisTemplateController` und `AuthController`), öffentliche Route
  `GET /api/v1/announcements` (neuer Block nach dem bestehenden
  "Public pricing route"-Block, Zeile 54-57), Admin-Routen
  `GET/POST/PUT/DELETE /api/v1/admin/announcements[/{announcement}]`
  (neuer Block nach dem bestehenden "Settings Management"-Block,
  Zeile 192-195, innerhalb derselben `auth:sanctum`-Gruppe).
- **Akzeptanzkriterien:**
  - [x] `GET /api/v1/announcements` ist ohne Authentifizierung erreichbar
  - [x] `GET/POST/PUT/DELETE /api/v1/admin/announcements[/...]` erfordern
        `auth:sanctum` **und** `can:admin`
  - [x] `php artisan route:list` zeigt alle fünf neuen Routen korrekt an

---

## T08: Backend-Tests `AnnouncementApiTest`

- **Agent:** dev-php
- **Dateien:**
  - `backend/tests/Feature/Api/AnnouncementApiTest.php` *(neu)*
- **Abhängigkeiten:** T01–T07
- **Beschreibung:**
  Pest-Feature-Test gemäß `TESTING.md` (Gruppen `api`, `announcement`;
  `RefreshDatabase`; Factory-States `User::factory()->admin()`/`customer()`;
  `it(...)`-Beschreibungen, dritte Person Indikativ, Deutsch). Mindestens
  folgende Szenarien:
  - Öffentlicher Endpunkt liefert nur aktive Ankündigungen
    (`Announcement::factory()->expired()->create()` erscheint **nicht**)
  - Admin-Endpunkt liefert auch abgelaufene Ankündigungen
  - Nicht-Admin erhält HTTP 403 auf alle vier Admin-Aktionen
  - Erstellen einer Ankündigung mit Bild-Upload speichert `image_path`
    korrekt und liefert eine gültige `imageUrl`
  - Rohes `<script>`/`onclick`-HTML im `body`-Feld wird beim Speichern
    entfernt (Sanitizing-Verifikation, `assertDatabaseHas` mit bereinigtem
    HTML)
  - Aktualisieren mit neuem Bild löscht das alte Bild von der
    `public`-Disk (`Storage::fake('public')` +
    `Storage::disk('public')->assertMissing(...)`)
  - Löschen einer Ankündigung löscht das zugehörige Bild mit
  - `displayDays` außerhalb 1–365 liefert HTTP 422
    (`assertJsonValidationErrors(['displayDays'])`)
- **Akzeptanzkriterien:**
  - [x] Alle Szenarien oben als eigene `it(...)`-Tests umgesetzt
  - [x] `./vendor/bin/pest --filter=AnnouncementApiTest` läuft grün
  - [x] Testdatei erfüllt die Checkliste aus `TESTING.md` Abschnitt 10
        (Gruppen, `it()` statt `test()`, Assertion-Stil-Trennung,
        Factory-States)

---

## T09: Frontend-API-Client + Composable

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/api/announcements.ts` *(neu)*
  - `frontend/src/api/announcements.test.ts` *(neu)*
  - `frontend/src/composables/useAnnouncements.ts` *(neu)*
- **Abhängigkeiten:** T05, T07 (Response-Shape ist ab T05 final, siehe
  Übergabepunkt oben — Implementierung kann parallel zu T06/T08 beginnen,
  sobald `design.md` Abschnitt 5.1/6.1 als Vertrag akzeptiert ist)
- **Beschreibung:**
  Siehe `design.md` Abschnitt 6.1/6.2 für den vollständigen Code-Vorschlag:
  `announcementsApi.getPublic/getAll/create/update/delete`, wobei `create`
  **und** `update` `FormData` (Multipart, Bild-Upload) senden und `update`
  zusätzlich das `_method=PUT`-Override-Feld setzt (analog
  `frontend/src/api/settings.ts:35-58` — **kein** echter HTTP-`PUT` mit
  multipart Body, siehe Begründung in `design.md` Abschnitt 5.2). **Beide**
  Methoden (`create` und `update`) müssen den Request explizit mit
  `headers: { 'Content-Type': 'multipart/form-data' }` senden, da
  `apiClient` (`frontend/src/api/client.ts:33-40`) standardmäßig
  `Content-Type: application/json` setzt und `FormData` sonst nicht korrekt
  serialisiert wird (identisches Muster wie
  `frontend/src/api/settings.ts:60-64` und
  `frontend/src/api/trainingAttachments.ts:54-58`).
  `useAnnouncements()`-Composable folgt 1:1 dem Aufbau von
  `frontend/src/composables/usePricingItems.ts`.
- **Akzeptanzkriterien:**
  - [x] `announcementsApi.create(...)` **und** `announcementsApi.update(...)`
        senden den Request mit explizitem
        `headers: { 'Content-Type': 'multipart/form-data' }` (kein Verlass
        auf Axios-Default-Erkennung von `FormData`, da der
        `apiClient`-Default `application/json` das sonst überschreiben
        würde)
  - [x] `announcementsApi.update(...)` sendet ein `POST` mit
        `_method=PUT` im `FormData`, **kein** echtes HTTP-`PUT`
  - [x] TypeScript-Interface `Announcement` bildet exakt den
        Response-Shape aus `design.md` Abschnitt 5.1 ab
  - [x] `npm run test` (Vitest) läuft grün für
        `announcements.test.ts`
  - [x] `npm run build` läuft ohne TypeScript-Fehler (`vue-tsc -b`)

---

## T10: Öffentliche Anzeige-Komponente `AnnouncementBanner.vue`

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/AnnouncementBanner.vue` *(neu)*
  - `frontend/src/components/AnnouncementBanner.test.ts` *(neu)*
  - `frontend/src/views/HomeView.vue` *(ändern)*
- **Abhängigkeiten:** T09
- **Beschreibung:**
  Siehe `design.md` Abschnitt 7.1/7.2. Komponente lädt selbst über
  `useAnnouncements().loadPublic()` bei `onMounted`, rendert **nichts**,
  wenn keine aktive Ankündigung vorhanden ist (`v-if="announcements.length"`),
  zeigt alle aktiven Ankündigungen als vertikal gestapelte Liste (kein
  Karussell, siehe Begründung `design.md` Abschnitt 7.1), sanitized `body`
  clientseitig mit `DOMPurify` + derselben `ALLOWED_TAGS`-Konstante wie
  `frontend/src/views/courses/CoursesView.vue:319-320`. Einbindung in
  `HomeView.vue` exakt zwischen Zeile 26 (Ende Hero-Section) und Zeile 29
  (Beginn Feature-Section).
- **Akzeptanzkriterien:**
  - [x] Komponente rendert keinen sichtbaren Bereich, wenn
        `announcements` leer ist
  - [x] Komponente rendert eine Karte pro aktiver Ankündigung, inkl. Bild
        falls `imageUrl` gesetzt ist
  - [x] `v-html`-Nutzung mit `eslint-disable-next-line vue/no-v-html`-
        Kommentar (Projektkonvention)
  - [x] `<AnnouncementBanner />` ist in `HomeView.vue` zwischen Hero- und
        Feature-Section eingebunden
  - [x] `npm run test` (Vitest) läuft grün für
        `AnnouncementBanner.test.ts`
  - [x] `npm run build` läuft ohne TypeScript-/Build-Fehler

---

## T11: Admin-Bereich `AnnouncementsView.vue`

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/views/AnnouncementsView.vue` *(neu)*
  - `frontend/src/views/AnnouncementsView.test.ts` *(neu)*
  - `frontend/src/router/index.ts` *(ändern)*
  - `frontend/src/layouts/DefaultLayout.vue` *(ändern)*
- **Abhängigkeiten:** T09
- **Beschreibung:**
  Siehe `design.md` Abschnitt 8.1–8.3. Liste aller Ankündigungen
  (`useAnnouncements().loadAll()`) inkl. abgelaufener mit Status-Badge
  ("Aktiv"/"Abgelaufen" basierend auf `announcement.isActive`),
  Create/Edit-Formular mit `<HtmlEditor v-model="form.body" />`
  (bestehende Komponente) und `<FileUpload :multiple="false" />`
  (bestehende Komponente), Zahlenfeld `displayDays` (1–365), Löschen mit
  Bestätigung. Neue Route `announcements` (`requiresAdmin: true`) und
  neuer Navigations-Eintrag ("Ankündigungen", `MegaphoneIcon`,
  `roles: ['admin']`).
- **Akzeptanzkriterien:**
  - [x] Liste zeigt sowohl aktive als auch abgelaufene Ankündigungen mit
        korrektem Status-Badge
  - [x] Formular nutzt `HtmlEditor.vue` für `body` (kein neuer
        Rich-Text-Editor)
  - [x] Formular nutzt `FileUpload.vue` mit `:multiple="false"` für das
        Bild
  - [x] Route `/app/announcements` ist nur für `role === 'admin'`
        erreichbar (Router-Guard `requiresAdmin`); Navigation zu dieser
        Route für Nicht-Admins nicht sichtbar
  - [x] Neuer Navigationspunkt "Ankündigungen" erscheint im Seitenmenü nur
        für Admins
  - [x] `npm run test` (Vitest) läuft grün für
        `AnnouncementsView.test.ts`
  - [x] `npm run build` läuft ohne TypeScript-/Build-Fehler
