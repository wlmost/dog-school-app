# Verification: add-announcement-banner

**Gesamtstatus:** nacharbeit-am-design-nötig

`openspec validate add-announcement-banner` → **"Change 'add-announcement-banner' is valid"** (strukturell ok, daher inhaltlicher Realitätsabgleich durchgeführt).

---

## Bestätigt

### Backend — Sanitizing-Trait (proposal.md Z.45-51, design.md Abschnitt 3)
- `backend/app/Http/Requests/Concerns/SanitizesHtmlContent.php` existiert, Trait mit `ALLOWED_HTML_TAGS` (`p, br, strong, em, h2, h3, ul, ol, li, blockquote, code, pre`) und Methode `sanitizeHtmlDescription()` → bestätigt, Datei vollständig gelesen.
- Verwendung in `backend/app/Http/Requests/StoreCourseRequest.php:91` (Aufruf `$this->sanitizeHtmlDescription($snakeCase['description'])`, innerhalb des Blocks Z.89-92) → bestätigt (proposal.md zitiert "Z.89-92", tatsächlicher Aufruf ist Z.91, liegt aber exakt im zitierten Block). Ebenso in `UpdateCourseRequest.php:91`.
- `StoreCourseRequest.php:38`: `'description' => ['nullable', 'string', 'max:5000']` → bestätigt, `max:5000` ist identisch zur in design.md Abschnitt 4.2 referenzierten Grenze für `body`.

### Frontend — HtmlEditor / dompurify (proposal.md Z.39-40, 149; design.md Abschnitt 3)
- `frontend/src/components/HtmlEditor.vue` existiert, `defineProps<{ modelValue: string }>` + `emit('update:modelValue', ...)` (Z.80-92) → bestätigt, Tiptap-/DOMPurify-basiert wie behauptet.
- `frontend/package.json:21`: `"dompurify": "^3.3.1"` → bestätigt.
- `frontend/package.json:18-20`: `@tiptap/pm`, `@tiptap/starter-kit`, `@tiptap/vue-3` → bestätigt.

### CoursesView.vue-Anzeigemuster (proposal.md Z.150-152, design.md Abschnitt 3/7.1)
- `frontend/src/views/courses/CoursesView.vue:319-320`: `const ALLOWED_TAGS = [...]` mit exakt derselben Tag-Liste wie das Backend-Trait, Kommentar `/** Allowed HTML tags consistent with the backend sanitization allowlist. */` → bestätigt (design.md zitiert Z.319-325, tatsächliche Kernzeilen sind 319-320/324, liegt im zitierten Bereich).

### Multipart-`_method=PUT`-Override (proposal.md Z.154-156, design.md Abschnitt 5.2/6.1)
- `frontend/src/api/settings.ts:35-58`: `async updateSettings(...)` mit `formData.set('_method', 'PUT')` (Z.51) und `apiClient.post(..., { headers: { 'Content-Type': 'multipart/form-data' } })` (Z.60-64) → bestätigt, Zeilenangabe exakt.
- `openspec/changes/archive/2026-07-01-fix-settings-upload-put-multipart/` existiert als Verzeichnis mit `proposal.md`, `design.md`, `acceptance.md` etc. → bestätigt, Referenz auf archivierten Change ist korrekt.
- `backend/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php:143`: `$request->enableHttpMethodParameterOverride();` wird vom Framework automatisch aufgerufen (kein anwendungsseitiger Code nötig) → bestätigt, mechanistische Behauptung in design.md Abschnitt 5.2 trifft zu.

### SettingPolicy / DogController-Upload-Werte (proposal.md Z.100-108, design.md Abschnitt 4.1/4.2)
- `backend/app/Policies/SettingPolicy.php`: alle fünf Methoden (`viewAny`, `view`, `create`, `update`, `delete`) prüfen ausschließlich `$user->isAdmin()` → bestätigt, Muster ist 1:1 wie in design.md Abschnitt 4.1 vorgeschlagen.
- `backend/app/Models/User.php:91-94`: `public function isAdmin(): bool { return $this->role === 'admin'; }` → bestätigt.
- `backend/app/Http/Controllers/Api/DogController.php:185`: `'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120']` → bestätigt, exakte Übereinstimmung mit dem in design.md Abschnitt 4.2 zitierten Wert.
- `DogController.php:192-193, 197`: Alt-Bild-Löschung vor `Storage::disk('public')`/`storeAs()`-Speicherung mit `Str::uuid()` im Dateinamen → bestätigt, Muster stimmt mit dem in design.md Abschnitt 5.1 nachgebauten Controller-Code überein.
- `DogController.php:105-110` (`store()`): kein `$this->authorize()`-Aufruf, verlässt sich auf `StoreDogRequest::authorize()` → bestätigt, `DogController` nutzt `AuthorizesRequests`-Trait (Z.19, Z.34) wie behauptet.

### DB-Portabilität / Migration (design.md Abschnitt 2.2, tasks.md T01)
- `Schema::create()`, `string`, `text`, `unsignedSmallInteger`, `timestamp`, `timestamps`, `index` sind treiberneutrale Blueprint-Methoden; `unsignedSmallInteger` existiert in `backend/vendor/laravel/framework/src/Illuminate/Database/Schema/Blueprint.php:982` → bestätigt, keine Postgres-/MySQL-spezifischen Konstrukte in der Migration.

### PHP-Kompatibilität (CLAUDE.md Abschnitt 4.1)
- Vorgeschlagener Model-Code (`Announcement.php`, design.md Abschnitt 2.3) nutzt `protected function casts(): array` (Laravel-11-Konvention, kein PHP-8.3/8.4-Feature), keine Property Hooks, keine typed class constants, kein `#[\Override]`, keine `new MyClass()->method()`-Syntax → bestätigt, kein verbotenes Konstrukt gefunden.
- `backend/composer.json:16`: `"laravel/framework": "^11.31"` → bestätigt, Laravel 11, `casts(): array`-Konvention korrekt angewendet.

### `masterminds/html5` als transitive Abhängigkeit (design.md Abschnitt 3)
- `backend/composer.lock:693`: `"masterminds/html5": "^2.0"` als `require`-Eintrag von `dompdf/dompdf` (nicht direkt vom Projekt) → bestätigt.
- `backend/composer.json:15`: `"barryvdh/laravel-dompdf": "^3.1"` als direkte Projekt-Abhängigkeit, die `dompdf/dompdf` transitiv zieht → bestätigt, die Kette "masterminds/html5 kommt über barryvdh/laravel-dompdf" stimmt.

### Fehlende QA-Scripts (proposal.md "Out of Scope", Z.131-140)
- `backend/composer.json:48-63`: `"scripts"` enthält nur Laravel-Standard-Hooks (`post-autoload-dump`, `post-update-cmd`, `post-root-package-install`, `post-create-project-cmd`) — **kein** `test`, `lint`, `stan`, `qa`, `compat-check` → bestätigt.
- `frontend/package.json:6-16`: `"scripts"` enthält `dev`, `build`, `build:deploy`, `preview`, `test`, `test:ui`, `test:coverage`, `e2e`, `e2e:ui` — **kein** `lint` → bestätigt.

### Router / Layout / HomeView-Einfügepunkte (design.md Abschnitt 7.2, 8.2, 8.3)
- `frontend/src/views/HomeView.vue:26`: schließendes `</section>` der Hero-Section; Z.28: Kommentar `<!-- Features Section -->`; Z.29: öffnendes `<section>` → bestätigt, exakte Zeilenangabe.
- `HomeView.vue:205-206`: `import PricingModal ...` (Z.205), `import { usePricingItems } ...` (Z.206) → bestätigt.
- `HomeView.vue:209, 217`: `const { groups, loading: pricingLoading, loadPublic } = usePricingItems()` (Z.209), `onMounted(() => loadPublic())` (Z.217) → bestätigt, design.md-Begründung zu `PricingModal` vs. `AnnouncementBanner` ist korrekt hergeleitet.
- `frontend/src/router/index.ts:127-131`: `settings`-Routen-Eintrag; Z.133: `training-logs`-Eintrag beginnt → bestätigt.
- `frontend/src/router/index.ts:181-185`: Router-Guard `if (to.meta.requiresAdmin && !authStore.isAdmin)` (konkret Z.182-185, Kommentar Z.181) → bestätigt.
- `frontend/src/layouts/DefaultLayout.vue:110-126`: Heroicons-Import-Liste → bestätigt.
- `DefaultLayout.vue:227-232`: "Einstellungen"-Navigationseintrag; Z.233-238: "Kontakt"-Eintrag → bestätigt.
- `frontend/node_modules/@heroicons/vue/24/outline/MegaphoneIcon.js` existiert → bestätigt.

### Routen-Einfügepunkte Backend (design.md Abschnitt 5.2, tasks.md T07)
- `backend/routes/api.php:6-7`: `AnamnesisTemplateController`-Import (Z.6), `AuthController`-Import (Z.7); alphabetisch liegt `AnnouncementController` dazwischen (`Anam...` < `Announ...` < `Auth...`) → bestätigt.
- `backend/routes/api.php:54-57`: "Public pricing route"-Block; Z.59: "Public course detail route"-Kommentar → bestätigt, exakte Zeilenangabe.
- `backend/routes/api.php:192-196`: "Settings Management"-Block; Z.197: schließendes `});` der äußeren `auth:sanctum`-Gruppe → bestätigt (design.md nennt "Z.192-195" für den Block und "Z.197" für die Klammer — der Block selbst endet tatsächlich bei Z.196, was in etwa im zitierten Bereich liegt; die Klammer-Zeile 197 stimmt exakt).
- `backend/routes/api.php:80, 188, 193`: bestehende `Route::middleware('can:admin')->group(...)`-Verwendung → bestätigt, Middleware-Name `can:admin` existiert bereits im Projekt.

### Frontend-Vorbilder für T09/T11 (design.md Abschnitt 6.1, 6.2, 8.1)
- `frontend/src/api/pricingItems.ts` existiert mit vergleichbarer Struktur (`getPublic`, `getAll`, `create`, `update`) → bestätigt.
- `frontend/src/composables/usePricingItems.ts` existiert → bestätigt.
- `frontend/src/views/SettingsView.vue`, `frontend/src/components/FileUpload.vue`, `frontend/src/components/PricingItemForm.vue`, `frontend/src/components/DogFormModal.vue` existieren alle → bestätigt.
- `frontend/src/components/FileUpload.vue:170,177`: Prop `multiple?: boolean`, Default `false` → bestätigt, `:multiple="false"` ist eine gültige, bereits unterstützte Prop-Belegung.

## Widerlegt

### CustomerCredit.php als Vorbild für den `saving`-Hook-Berechnungsansatz (design.md Abschnitt 2.1, Z.37-52)
Design.md behauptet: *"Das Projekt hat für exakt dieses Problem bereits ein etabliertes Muster: `backend/app/Models/CustomerCredit.php:118-129` — ein Ablaufdatum wird **einmalig in PHP berechnet** und in einer eigenen Spalte gespeichert (`expiration_date`)"*.

Tatsächlich (vollständig gelesen: `backend/app/Models/CustomerCredit.php`, `backend/app/Http/Requests/StoreCustomerCreditRequest.php`, `backend/database/factories/CustomerCreditFactory.php`):
- `CustomerCredit.php` enthält **keinen** `booted()`/`saving`-Hook und **keine** Methode, die `expiration_date` aus `purchase_date` + einer Dauer berechnet. Die Zeilen 118-129 des tatsächlichen Files sind die Dokumentation und der Body von `scopeActive()` (reine Filterung, keine Berechnung).
- `StoreCustomerCreditRequest.php:32,70`: `expirationDate` ist ein **vom Admin direkt im Request mitgegebenes, optionales Feld** (`'expirationDate' => ['nullable', 'date', 'after:purchaseDate']`), das unverändert in `expiration_date` übernommen wird (`$validated['expirationDate'] ?? null`) — es wird **nicht** aus `purchase_date` + einer Tages-/Laufzeitangabe berechnet.
- `CustomerCreditFactory.php:27,40`: auch dort wird `expiration_date` mit hartkodierten `now()->addDays(...)`-Werten direkt gesetzt, nicht über ein Model-Event berechnet.

**Fazit:** Der von design.md als Präzedenzfall zitierte Mechanismus für "automatische Berechnung eines Ablaufdatums beim Speichern via Model-Hook" existiert in `CustomerCredit` **nicht**. Bestätigt ist lediglich der allgemeinere, unstrittige Teil des Musters: ein Ablaufzeitpunkt wird in einer eigenen Spalte persistiert und über eine treiberneutrale Eloquent-`where`-Bedingung (`scopeActive()`/`scopeExpired()`) gefiltert, statt Datums-Arithmetik in raw SQL auszudrücken. Der `saving`-Hook-Ansatz für `Announcement` (design.md Abschnitt 2.3) ist somit ein **neuer** Mechanismus im Projekt, kein wiederverwendetes, bereits produktiv erprobtes Muster, auch wenn die DB-Portabilitäts-Begründung (kein raw SQL) für sich genommen korrekt bleibt.

### `announcementsApi.update()` übernimmt NICHT "exakt" das Multipart-Header-Muster aus `settings.ts` (design.md Abschnitt 6.1, Behauptung Z.556-559)
Design.md behauptet, der Code in Abschnitt 6.1 übernehme "exakt das `_method=PUT`-Override-Muster aus `frontend/src/api/settings.ts:35-58`". Tatsächlich fehlt im in design.md Abschnitt 6.1 gezeigten Code (`announcementsApi.create`/`update`) der in `settings.ts:60-64` **und** `frontend/src/api/trainingAttachments.ts:52-55` (zweites, unabhängiges Beispiel im Projekt) konsistent vorhandene explizite Header-Override `headers: { 'Content-Type': 'multipart/form-data' }`.

Das ist folgenreich, nicht nur kosmetisch: `frontend/src/api/client.ts:34-38` setzt `apiClient` mit dem **Default-Header** `'Content-Type': 'application/json'` für **alle** Requests auf. In `frontend/node_modules/axios/lib/defaults/index.js:49-56` (`transformRequest`) prüft Axios `isFormData(data)` **und** `hasJSONContentType` (aus dem bereits gesetzten `Content-Type`-Header): Ist Letzteres `true`, wird die `FormData` **nicht** als multipart gesendet, sondern über `formDataToJSON(data)` in einen JSON-String umgewandelt (`return hasJSONContentType ? JSON.stringify(formDataToJSON(data)) : data;`). Ohne den expliziten Header-Override würde ein `apiClient.post(url, buildFormData(data))`-Aufruf mit dem aktuellen `apiClient`-Default-Header also **kein** echtes `multipart/form-data` senden — der Bild-Upload und das `_method=PUT`-Override-Feld würden fehlschlagen bzw. anders serialisiert werden als beabsichtigt.

**Fazit:** Die Behauptung "exakt dasselbe Muster" trifft auf den in design.md Abschnitt 6.1 abgedruckten Code **nicht** zu — ihm fehlt ein Element, das in **beiden** bestehenden FormData-Upload-Stellen im Projekt (`settings.ts`, `trainingAttachments.ts`) konsistent vorhanden ist und das laut verifiziertem Axios-Verhalten für die korrekte Funktion notwendig ist.

## Nicht auffindbar

- Keine Behauptung in proposal.md/design.md/tasks.md konnte nicht auffindbar bewertet werden — alle konkreten Codebasis-Behauptungen waren entweder bestätigbar oder widerlegbar.

## Neue Elemente (Plausibilität)

- `backend/database/migrations/2026_07_17_100000_create_announcements_table.php` (T01) → Migrationsverzeichnis `backend/database/migrations/` existiert, Namenskonvention (`YYYY_MM_DD_HHMMSS_create_x_table.php`) konsistent mit vorhandenen Migrationen; kein Namenskonflikt gefunden.
- `backend/app/Models/Announcement.php`, `backend/database/factories/AnnouncementFactory.php` (T02) → `backend/app/Models/` und `backend/database/factories/` existieren, kein `Announcement`/`AnnouncementFactory` bereits vorhanden.
- `backend/app/Policies/AnnouncementPolicy.php` (T03) → `backend/app/Policies/` existiert (`SettingPolicy.php`, `CustomerCreditPolicy.php` als Nachbarn bestätigt gelesen), kein Konflikt.
- `backend/app/Http/Requests/StoreAnnouncementRequest.php` / `UpdateAnnouncementRequest.php` (T04), `backend/app/Http/Resources/AnnouncementResource.php` (T05), `backend/app/Http/Controllers/Api/AnnouncementController.php` (T06) → jeweilige Zielverzeichnisse existieren mit gleichartig benannten Geschwister-Klassen (`StoreCourseRequest.php`, `DogController.php` etc.), kein Namenskonflikt.
- `backend/tests/Feature/Api/AnnouncementApiTest.php` (T08) → `backend/tests/Feature/Api/` existiert (Konvention laut `TESTING.md` bestätigt), kein Konflikt.
- `frontend/src/api/announcements.ts`, `frontend/src/composables/useAnnouncements.ts` (T09) → Zielverzeichnisse existieren mit vergleichbaren Dateien (`pricingItems.ts`, `usePricingItems.ts`), kein Konflikt.
- `frontend/src/components/AnnouncementBanner.vue` (T10) → `frontend/src/components/` existiert, kein Namenskonflikt.
- `frontend/src/views/AnnouncementsView.vue`, Route `/app/announcements` (T11) → `frontend/src/views/` existiert, Pfad `announcements` ist im Router noch nicht vergeben (geprüft über `router/index.ts`-Auszug Z.120-140), kein Konflikt mit bestehenden Routen (`invoices`, `settings`, `training-logs`).
- `announcement-images/`-Verzeichnis auf der `public`-Disk → `backend/storage/app/public/` enthält bereits `settings/` und `dog-images/` als Geschwister-Verzeichnisse, kein Namenskonflikt mit `announcement-images/`.

## Empfehlung

Die Spec ist strukturell solide und die überwiegende Mehrheit der konkreten Codebasis-Behauptungen (Backend-Routen-Einfügepunkte, Frontend-Einfügepunkte, Policy-/Validierungs-Muster, DB-Portabilität, PHP-8.2-Konformität, fehlende QA-Scripts) ist exakt mit Datei:Zeile belegbar. Zwei Punkte sind vor Freigabe zu korrigieren: (1) der als Präzedenzfall zitierte `CustomerCredit`-"berechnet-beim-Speichern"-Mechanismus existiert so nicht im Code — der Architekt sollte den `saving`-Hook-Ansatz für `Announcement` als **neuen** Mechanismus kennzeichnen statt als Wiederverwendung eines bestehenden Musters; (2) der Code-Vorschlag für `announcementsApi.create`/`update` in design.md Abschnitt 6.1 fehlt der in beiden bestehenden FormData-Upload-Stellen des Projekts (`settings.ts`, `trainingAttachments.ts`) vorhandene explizite `Content-Type: multipart/form-data`-Header-Override — ohne ihn würde der Multipart-Body laut verifiziertem Axios-Verhalten fälschlich zu JSON serialisiert. Architekt sollte design.md Abschnitt 6.1 entsprechend korrigieren, bevor T09 implementiert wird.
