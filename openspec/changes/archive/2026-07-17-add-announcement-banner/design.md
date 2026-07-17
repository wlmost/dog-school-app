# Design: add-announcement-banner

**Change-ID:** add-announcement-banner
**Datum:** 2026-07-17

---

## 1. Kontext / Entscheidungen des Users (2026-07-17)

Aus `openspec/triage/20260717103000-announcement-banner.md`, Abschnitt
"Entscheidungen des Users":

1. **Ablauf-Berechnung:** anzeigeseitig (`created_at`/`published_at` +
   `display_days` < `now()`), **kein** Scheduler/Cron-Task.
2. **Mehrfach-Ankündigungen:** mehrere gleichzeitig aktive Ankündigungen
   möglich (Liste, kein Überschreiben einer einzelnen Ankündigung).
3. **Bilder:** genau ein Bild pro Ankündigung.
4. **Text-Format:** Rich-Text (HTML) — Editor + serverseitiges Sanitizing.
5. **Admin-Historie:** abgelaufene Ankündigungen bleiben im Admin-Bereich
   sichtbar (Status-Badge aktiv/abgelaufen), keine automatische Löschung.

Diese fünf Entscheidungen bestimmen das Datenmodell (Abschnitt 2) und die
API-Form (Abschnitt 5) direkt.

---

## 2. Datenmodell

### 2.1 Warum ein berechnetes, aber **persistiertes** `expires_at` statt reiner Laufzeit-Berechnung?

CLAUDE.md Abschnitt 4.2 verbietet Datums-Arithmetik in raw SQL
(`whereRaw`, `DB::raw`), weil MySQL (`DATE_ADD(created_at, INTERVAL
display_days DAY)`) und PostgreSQL (`created_at + (display_days ||
' days')::interval`) inkompatible Syntax verwenden. Eine reine
Eloquent-`where`-Klausel kann aber keine Spalten-Arithmetik ausdrücken.

**Korrektur nach Skeptiker-Prüfung (verifiziert):** `Announcement` ist
**kein** Fall von "Ablauf-Berechnung", die per Model-Hook aus einem anderen
Feld hergeleitet wird — dieses konkrete Vorgehen ist im Projekt **neu**.
`backend/app/Models/CustomerCredit.php` enthält **keinen**
`booted()`/`saving`-Hook: `expiration_date` wird dort direkt vom Client im
Request übergeben (`StoreCustomerCreditRequest.php:70`,
`UpdateCustomerCreditRequest.php:61`) bzw. in der Factory hartkodiert
gesetzt (`CustomerCreditFactory.php:29,42,58`), niemals aus einem anderen
Feld berechnet. Nur die **nachgelagerte Filterung** — ein bereits
persistiertes Ablaufdatum per reiner, treiberneutraler
Eloquent-`where`-Bedingung abfragen (`scopeActive()`/`scopeExpired()`,
`CustomerCredit.php:121-138`) — ist ein tatsächlicher Präzedenzfall und
wird hier sinngemäß übernommen.

`Announcement` geht insofern über dieses Präzedenzmuster hinaus, als
`expires_at` **zusätzlich** aus einem anderen Feld (`display_days` +
Erstellungszeitpunkt) hergeleitet statt direkt vom Client übergeben wird.
Das ist die richtige Wahl für dieses Feature, weil `display_days` (nicht
ein Datum) die vom User festgelegte fachliche Eingabegröße ist (siehe
Nutzerentscheidung 1) — ein `booted()`/`saving`-Hook ist der naheliegende
Ort, um diese Ableitung zentral und konsistent (Create **und** Update)
umzusetzen, statt sie in Controller/FormRequest zu duplizieren (DRY). Die
Begründung für den Ansatz selbst bleibt bestehen: reine PHP-/Carbon-
Berechnung beim Speichern, kein raw SQL, kein Scheduler, MySQL/PostgreSQL-
portabel — nur eben als **neu eingeführtes** Muster, nicht als
Wiederverwendung eines bestehenden Hooks.

`Announcement` speichert `expires_at` beim Erstellen/Aktualisieren aus
`created_at` (bzw. `now()` bei einem neuen, noch nicht persistierten
Datensatz) + `display_days` berechnet in einer eigenen Spalte. Das ist
weiterhin "anzeigeseitig berechnet" im Sinne der Nutzerentscheidung — es
läuft **kein** Hintergrundprozess, der periodisch `expires_at`
aktualisiert oder Datensätze verändert; die Spalte
wird ausschließlich beim (durch einen Admin ausgelösten) Speichern
neu berechnet, die Aktiv-Prüfung selbst (`expires_at > now()`) findet erst
beim Lesen/Anzeigen statt.

### 2.2 Migration

`backend/database/migrations/2026_07_17_100000_create_announcements_table.php`
(neu):

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

**DB-Portabilität (CLAUDE.md Abschnitt 4.2 — Pflichtprüfung):**

- Ausschließlich treiberneutrale Blueprint-Typen (`id`, `string`, `text`,
  `unsignedSmallInteger`, `timestamp`, `timestamps`, `index`) — kein
  `jsonb`, kein `uuid`, kein raw SQL, kein `DB::statement()`.
- `expires_at` ist bewusst **nicht nullable**: Der Wert wird immer aus dem
  Pflichtfeld `display_days` berechnet (siehe 2.3), es gibt keinen validen
  Zustand, in dem eine Ankündigung ohne Ablaufzeitpunkt existiert. Die
  DB-Constraint erzwingt diese Invariante zusätzlich zur Anwendungslogik.
- **Pflicht für T01 (dev-php):** Migration lokal gegen MySQL **und**
  PostgreSQL laufen lassen (`docker compose -f docker-compose.yml -f
  docker-compose.mysql.yml up -d`, `php artisan migrate:fresh`), gemäß
  CLAUDE.md Abschnitt 7.1.

### 2.3 Model `backend/app/Models/Announcement.php` (neu)

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Announcement Model
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string|null $image_path
 * @property int $display_days
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'image_path',
        'display_days',
    ];

    protected function casts(): array
    {
        return [
            'display_days' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Recompute expires_at from the original publish date whenever
     * display_days changes (create, or admin extends/shortens the
     * display duration on an existing announcement). Uses the existing
     * created_at as the base on update so editing an announcement does
     * not silently reset its display window to "now".
     */
    protected static function booted(): void
    {
        static::saving(function (Announcement $announcement): void {
            if (! $announcement->exists || $announcement->isDirty('display_days')) {
                $base = ($announcement->exists && $announcement->created_at)
                    ? $announcement->created_at
                    : now();

                $announcement->expires_at = $base->copy()->addDays((int) $announcement->display_days);
            }
        });
    }

    /**
     * Whether the announcement is currently within its display window.
     */
    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * Scope a query to only include currently active announcements.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
```

`backend/database/factories/AnnouncementFactory.php` (neu): Standard-Faker
für `title`/`body`/`display_days` (z. B. `display_days` zwischen 1 und 30).
`expires_at` wird **nicht** in der Factory gesetzt — das `saving`-Event des
Models berechnet es automatisch beim `create()`. Für Tests, die einen
bereits **abgelaufenen** Datensatz brauchen, definiert die Factory einen
State `expired()`:

```php
public function expired(): static
{
    return $this->afterCreating(function (Announcement $announcement) {
        $announcement->forceFill([
            'created_at' => now()->subDays(10),
            'expires_at' => now()->subDays(9),
        ])->saveQuietly();
    });
}
```

`saveQuietly()` verhindert, dass das `saving`-Event die manuell gesetzten
Testwerte wieder überschreibt (Laravel-Standardmechanismus zum Umgehen von
Model-Events für genau diesen Testfall).

---

## 3. HTML-Sanitizing — Wiederverwendung statt neuer Abhängigkeit

**Geprüft (siehe Recherche):** `backend/composer.lock` enthält **keine**
dedizierte HTML-Sanitizing-Bibliothek (`masterminds/html5` ist eine
transitive Abhängigkeit von `barryvdh/laravel-dompdf` für die PDF-Erzeugung,
nicht für Sanitizing nutzbar/vorgesehen). Das Projekt hat dieses Problem
jedoch bereits für ein anderes Rich-Text-Feld gelöst:

- `backend/app/Http/Requests/Concerns/SanitizesHtmlContent.php` — Trait mit
  einer festen Tag-Allowlist (`p, br, strong, em, h2, h3, ul, ol, li,
  blockquote, code, pre`) und zweistufiger Bereinigung (`strip_tags()` +
  Attribut-Entfernung per Regex, entfernt damit auch
  Event-Handler-Attribute wie `onclick` auf erlaubten Tags).
- Verwendet von `StoreCourseRequest`/`UpdateCourseRequest` für das
  Kurs-Beschreibungsfeld (`validatedSnakeCase()`,
  `StoreCourseRequest.php:89-92`).
- Der zugehörige clientseitige Editor `frontend/src/components/HtmlEditor.vue`
  ist Tiptap-basiert (`@tiptap/vue-3`, `@tiptap/starter-kit`, bereits in
  `frontend/package.json` vorhanden) und sanitized zusätzlich clientseitig
  mit `dompurify` (ebenfalls bereits vorhanden).
- Die Anzeige-Seite verwendet dieselbe Allowlist clientseitig erneut:
  `frontend/src/views/courses/CoursesView.vue:319-325`
  (`ALLOWED_TAGS`-Konstante, Kommentar *"consistent with the backend
  sanitization allowlist"*).

**Entscheidung:** `Announcement` verwendet exakt dasselbe Muster —
`SanitizesHtmlContent`-Trait in `StoreAnnouncementRequest`/
`UpdateAnnouncementRequest`, `HtmlEditor.vue` im Admin-Formular,
`DOMPurify.sanitize()` mit derselben `ALLOWED_TAGS`-Konstante in
`AnnouncementBanner.vue`. **Keine neue Composer- oder npm-Abhängigkeit.**
Das erfüllt die CLAUDE.md-Vorgabe zur Prüfung vorhandener Bibliotheken vor
Einführung einer neuen — hier ist keine neue nötig, weil eine
funktional identische, bereits produktiv genutzte Lösung existiert
(DRY: kein zweites Sanitizing-Muster für dasselbe Problem einführen).

**Defense in depth:** Sanitizing findet **serverseitig** beim Speichern
statt (nicht nur clientseitig im Editor) — ein Request direkt gegen die API
(ohne den Editor zu durchlaufen) kann kein rohes HTML/JS einschleusen. Die
clientseitige `DOMPurify`-Anwendung in `AnnouncementBanner.vue` beim
Rendern ist eine zusätzliche Verteidigungsschicht, falls sich die
Sanitizing-Regeln zwischen Backend und Frontend jemals unterscheiden
sollten — genau das bereits etablierte Muster aus `CoursesView.vue`.

---

## 4. Backend — Policy, FormRequests, Resource

### 4.1 `backend/app/Policies/AnnouncementPolicy.php` (neu)

Identisch zu `backend/app/Policies/SettingPolicy.php` (alle Methoden
`$user->isAdmin()`):

```php
class AnnouncementPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin(); }
    public function view(User $user, Announcement $announcement): bool { return $user->isAdmin(); }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, Announcement $announcement): bool { return $user->isAdmin(); }
    public function delete(User $user, Announcement $announcement): bool { return $user->isAdmin(); }
}
```

### 4.2 `backend/app/Http/Requests/StoreAnnouncementRequest.php` (neu)

```php
class StoreAnnouncementRequest extends FormRequest
{
    use SanitizesHtmlContent;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'displayDays' => ['required', 'integer', 'min:1', 'max:365'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ];
    }

    public function validatedSnakeCase(): array
    {
        $validated = $this->validated();

        return [
            'title' => $validated['title'],
            'body' => $this->sanitizeHtmlDescription($validated['body']),
            'display_days' => $validated['displayDays'],
        ];
    }
}
```

`max:5000` für `body`: identisch zur bestehenden Grenze in
`StoreCourseRequest.php:38` (Konsistenz statt einer neu erfundenen Zahl).
`max:365` für `displayDays`: verhindert versehentliche De-facto-Dauerwerbung
über Jahre (YAGNI — falls je eine längere Laufzeit gebraucht wird, ist das
eine triviale, separate Änderung der Validierungsgrenze). `image`-Regeln
identisch zu `DogController.php:185` (`mimes:jpg,jpeg,png,gif,webp|max:5120`).

**Wichtig:** `image` wird bewusst **nicht** in `validatedSnakeCase()`
zurückgegeben — der Controller behandelt den Datei-Upload separat (siehe
4.4), analog zu `DogController::uploadImage()`. Nur `image_path` (der
gespeicherte Pfad) landet in der DB, nie die `UploadedFile`-Instanz selbst.

### 4.3 `backend/app/Http/Requests/UpdateAnnouncementRequest.php` (neu)

Identisch zu 4.2, aber alle Felder mit `sometimes` (Teil-Updates erlaubt):

```php
public function rules(): array
{
    return [
        'title' => ['sometimes', 'string', 'max:255'],
        'body' => ['sometimes', 'string', 'max:5000'],
        'displayDays' => ['sometimes', 'integer', 'min:1', 'max:365'],
        'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
    ];
}

public function validatedSnakeCase(): array
{
    $validated = $this->validated();
    $result = [];

    if (array_key_exists('title', $validated)) {
        $result['title'] = $validated['title'];
    }
    if (array_key_exists('body', $validated)) {
        $result['body'] = $this->sanitizeHtmlDescription($validated['body']);
    }
    if (array_key_exists('displayDays', $validated)) {
        $result['display_days'] = $validated['displayDays'];
    }

    return $result;
}
```

### 4.4 `backend/app/Http/Resources/AnnouncementResource.php` (neu)

**Dies ist der Übergabepunkt zwischen T05 (Backend) und T09 (Frontend).**
Response-Shape (camelCase, Projektkonvention, siehe `DogResource.php`):

```php
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'imageUrl' => $this->image_path
                ? Storage::disk('public')->url($this->image_path)
                : null,
            'displayDays' => $this->display_days,
            'expiresAt' => $this->expires_at?->toISOString(),
            'isActive' => $this->isActive(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
```

**Verbindliches JSON-Beispiel** (`GET /api/v1/announcements`,
`GET /api/v1/admin/announcements`, sowie Response von `POST`/`PUT`):

```json
{
  "data": [
    {
      "id": 3,
      "title": "Kursausfall am 20.07.",
      "body": "<p>Der Welpenkurs am 20.07. entfällt <strong>krankheitsbedingt</strong>.</p>",
      "imageUrl": "https://example.test/storage/announcement-images/xyz.jpg",
      "displayDays": 7,
      "expiresAt": "2026-07-24T10:00:00.000000Z",
      "isActive": true,
      "createdAt": "2026-07-17T10:00:00.000000Z",
      "updatedAt": "2026-07-17T10:00:00.000000Z"
    }
  ]
}
```

Für Listen-Endpunkte immer `AnnouncementResource::collection(...)`
(Laravel wrappt automatisch in `{"data": [...]}`), für Einzel-Antworten
(`store`/`update`) `new AnnouncementResource($announcement)` (wrappt in
`{"data": {...}}`).

**Keine getrennte "öffentliche" vs. "Admin"-Resource nötig:** Im
Unterschied zur ursprünglichen Vermutung in der Triage gibt es kein
Admin-only-Feld, das vor Kunden verborgen werden müsste (kein `created_by`,
keine internen Notizen) — `AnnouncementResource` wird für beide Endpunkte
verwendet (YAGNI: keine zweite Resource-Klasse für identischen Output
einführen).

---

## 5. Backend — Controller und Routen

### 5.1 `backend/app/Http/Controllers/Api/AnnouncementController.php` (neu)

Struktur folgt dem Autorisierungs-Muster von `DogController.php` (Policy
über `$this->authorize()` für Aktionen mit vorhandener Modell-Instanz bzw.
`viewAny`; `store` verlässt sich auf `StoreAnnouncementRequest::authorize()`
allein, analog zu `DogController::store()`, das ebenfalls keinen
zusätzlichen `$this->authorize()`-Aufruf hat):

```php
class AnnouncementController extends Controller
{
    use AuthorizesRequests;

    public function publicIndex(): AnonymousResourceCollection
    {
        $announcements = Announcement::query()
            ->active()
            ->orderByDesc('created_at')
            ->get();

        return AnnouncementResource::collection($announcements);
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Announcement::class);

        return AnnouncementResource::collection(
            Announcement::query()->orderByDesc('created_at')->get()
        );
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $data = $request->validatedSnakeCase();

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request);
        }

        $announcement = Announcement::create($data);

        return (new AnnouncementResource($announcement))->response()->setStatusCode(201);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): AnnouncementResource
    {
        $this->authorize('update', $announcement);

        $data = $request->validatedSnakeCase();

        if ($request->hasFile('image')) {
            if ($announcement->image_path && Storage::disk('public')->exists($announcement->image_path)) {
                Storage::disk('public')->delete($announcement->image_path);
            }
            $data['image_path'] = $this->storeImage($request);
        }

        $announcement->update($data);

        return new AnnouncementResource($announcement->fresh());
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        if ($announcement->image_path && Storage::disk('public')->exists($announcement->image_path)) {
            Storage::disk('public')->delete($announcement->image_path);
        }

        $announcement->delete();

        return response()->json(null, 204);
    }

    private function storeImage(Request $request): string
    {
        $file = $request->file('image');
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = 'announcement_' . Str::uuid() . '.' . $extension;

        return $file->storeAs('announcement-images', $filename, 'public');
    }
}
```

Datei-Speicherung identisch zum Muster in
`DogController.php:196-197` (`Str::uuid()`-Dateiname,
`Storage::disk('public')`, Alt-Bild-Löschung vor Ersetzung wie
`DogController.php:192-193`).

**Kein separater `update()`-Aufruf von `$request->hasFile('image')` in
`store()` vs. `update()` unterschiedlich behandelt** — Logik ist bewusst
identisch strukturiert (DRY), einziger Unterschied ist das Löschen des
Alt-Bilds bei `update()`.

### 5.2 Routen (`backend/routes/api.php`)

**Import** (alphabetisch einsortiert, nach `AnamnesisTemplateController`,
vor `AuthController`, Zeile 6-7):

```php
use App\Http\Controllers\Api\AnnouncementController;
```

**Öffentliche Route** — neuer Block direkt nach dem bestehenden
"Public pricing route"-Block (`backend/routes/api.php:54-57`), vor dem
"Public course detail route"-Block (Zeile 59):

```php
// Public announcements route (no auth required)
Route::prefix('v1')->group(function () {
    Route::get('/announcements', [AnnouncementController::class, 'publicIndex']);
});
```

**Admin-Routen** — neuer Block innerhalb der bestehenden
`auth:sanctum`-Gruppe, nach dem "Settings Management"-Block
(`backend/routes/api.php:192-195`), vor der schließenden `});` der Gruppe
(Zeile 197):

```php
// Announcement Management (Admin only)
Route::middleware('can:admin')->group(function () {
    Route::get('/admin/announcements', [AnnouncementController::class, 'index']);
    Route::post('/admin/announcements', [AnnouncementController::class, 'store']);
    Route::put('/admin/announcements/{announcement}', [AnnouncementController::class, 'update']);
    Route::delete('/admin/announcements/{announcement}', [AnnouncementController::class, 'destroy']);
});
```

**Wichtig — Multipart + `PUT` (Shared-Hosting-Präzedenzfall):** Die Route
ist als `PUT` definiert (Laravel-Konvention, RESTful), der tatsächliche
HTTP-Request beim Bearbeiten mit Bild-Austausch wird aber — wie bei
`settingsApi.updateSettings()` — als `POST` mit `_method=PUT`-Override-Feld
gesendet (siehe Abschnitt 6.1). **Grund:** Bereits einmal produktiv
aufgetreten und behoben
(`openspec/changes/archive/2026-07-01-fix-settings-upload-put-multipart/`):
PHP befüllt `$_FILES` bei einem echten `multipart/form-data`-`PUT`-Request
nicht zuverlässig plattform-/PHP-Versions-unabhängig. Laravels
`enableHttpMethodParameterOverride()` löst das Method-Override aus `_method`
aber bereits vor dem Routing auf, sodass die Route selbst unverändert `PUT`
bleiben kann.

---

## 6. Frontend — API-Client und Composable

### 6.1 `frontend/src/api/announcements.ts` (neu)

Folgt `frontend/src/api/pricingItems.ts` für die Grundstruktur, aber
`create`/`update` senden `FormData` statt JSON (Bild-Upload), und `update`
übernimmt exakt das `_method=PUT`-Override-Muster aus
`frontend/src/api/settings.ts:35-58` (siehe Abschnitt 5.2).

**Korrektur nach Skeptiker-Prüfung (verifiziert):** `apiClient`
(`frontend/src/api/client.ts:33-40`) setzt auf der Axios-Instanz einen
festen Default-Header `'Content-Type': 'application/json'`. Wird dieser
Default nicht pro Request überschrieben, serialisiert Axios ein
`FormData`-Objekt **nicht** automatisch als
`multipart/form-data` — der Upload (und beim `update`-Call auch das
`_method=PUT`-Override-Feld) würde fehlschlagen. Beide bestehenden
FormData-Upload-Stellen im Projekt setzen deshalb explizit
`headers: { 'Content-Type': 'multipart/form-data' }` pro Request:
`frontend/src/api/settings.ts:60-64` und
`frontend/src/api/trainingAttachments.ts:54-58`. Der folgende Code
übernimmt diesen Header-Override für **beide** Methoden (`create` **und**
`update`, nicht nur `update` — auch `create` sendet `FormData` und war im
ursprünglichen Entwurf ohne den Override fehlerhaft):

```ts
export interface Announcement {
  id: number
  title: string
  body: string
  imageUrl: string | null
  displayDays: number
  expiresAt: string | null
  isActive: boolean
  createdAt: string | null
  updatedAt: string | null
}

export interface AnnouncementFormData {
  title: string
  body: string
  displayDays: number
  image?: File | null
}

function buildFormData(data: Partial<AnnouncementFormData>): FormData {
  const formData = new FormData()
  if (data.title !== undefined) formData.append('title', data.title)
  if (data.body !== undefined) formData.append('body', data.body)
  if (data.displayDays !== undefined) formData.append('displayDays', String(data.displayDays))
  if (data.image) formData.append('image', data.image)
  return formData
}

export const announcementsApi = {
  async getPublic(): Promise<Announcement[]> {
    const response = await apiClient.get<{ data: Announcement[] }>('/api/v1/announcements')
    return response.data.data
  },
  async getAll(): Promise<Announcement[]> {
    const response = await apiClient.get<{ data: Announcement[] }>('/api/v1/admin/announcements')
    return response.data.data
  },
  async create(data: AnnouncementFormData): Promise<Announcement> {
    const response = await apiClient.post<{ data: Announcement }>(
      '/api/v1/admin/announcements',
      buildFormData(data),
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      },
    )
    return response.data.data
  },
  async update(id: number, data: Partial<AnnouncementFormData>): Promise<Announcement> {
    const formData = buildFormData(data)
    formData.set('_method', 'PUT')
    const response = await apiClient.post<{ data: Announcement }>(
      `/api/v1/admin/announcements/${id}`,
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      },
    )
    return response.data.data
  },
  async delete(id: number): Promise<void> {
    await apiClient.delete(`/api/v1/admin/announcements/${id}`)
  },
}
```

### 6.2 `frontend/src/composables/useAnnouncements.ts` (neu)

Folgt `frontend/src/composables/usePricingItems.ts` 1:1 im Aufbau
(`ref`-State, `loading`/`error`, `loadPublic`/`loadAll`/`createAnnouncement`/
`updateAnnouncement`/`deleteAnnouncement`, jeweils mit try/catch und
deutschsprachiger Fehlermeldung).

---

## 7. Frontend — öffentliche Anzeige

### 7.1 `frontend/src/components/AnnouncementBanner.vue` (neu)

- Lädt beim Mount über `useAnnouncements().loadPublic()`.
- `v-if="announcements.length"` auf der äußeren `<section>` — kein Rendern,
  wenn keine aktive Ankündigung existiert (Kernanforderung der Triage).
- **Layout-Entscheidung (User hat Detail dem Architekten überlassen):**
  einfache vertikal gestapelte Liste (`v-for`), **kein** Karussell/Slider.
  Begründung (KISS): Ein Slider bräuchte zusätzliche
  State-Verwaltung/Timer-Logik und ggf. eine neue Bibliothek, für eine in
  der Praxis meist einstellige Anzahl gleichzeitig aktiver Ankündigungen
  nicht gerechtfertigt (YAGNI). Jede Ankündigung ist eine eigene Karte mit
  optionalem Bild links, Titel + Rich-Text rechts (analog zum bestehenden
  Karten-Layout der Feature-Section, konsistentes Look-and-Feel).
- HTML-Ausgabe: `v-html="sanitizeHtml(announcement.body)"` mit **derselben**
  `ALLOWED_TAGS`-Konstante wie `CoursesView.vue:320`
  (`p, br, strong, em, h2, h3, ul, ol, li, blockquote, code, pre`), inkl.
  `<!-- eslint-disable-next-line vue/no-v-html -->`-Kommentar (bestehende
  Projektkonvention für alle `v-html`-Stellen).

### 7.2 `frontend/src/views/HomeView.vue` (ändern)

Neue Zeile zwischen dem schließenden `</section>` der Hero-Section
(Zeile 26) und dem Kommentar `<!-- Features Section -->` (Zeile 28/29):

```html
    <AnnouncementBanner />
```

Import im `<script setup>`-Block (nach den bestehenden Imports,
Zeile 205/206):

```ts
import AnnouncementBanner from '@/components/AnnouncementBanner.vue'
```

**Kein zusätzlicher `onMounted()`-Aufruf in `HomeView.vue` nötig** — die
Komponente lädt ihre Daten selbst beim eigenen Mount (Kapselung, analog zu
`PricingModal`, das ebenfalls seine eigenen Daten über
`usePricingItems()` lädt, siehe Zeile 209/217 — dort wird `loadPublic()`
zwar in `HomeView.vue` aufgerufen, weil die Preisdaten auch für das
`PricingModal`-Prop `groups` gebraucht werden; `AnnouncementBanner`
dagegen hat keine Eltern-Abhängigkeit auf die geladenen Daten, kapselt sie
also selbst — DRY/Separation of Concerns).

---

## 8. Frontend — Admin-Bereich

### 8.1 `frontend/src/views/AnnouncementsView.vue` (neu)

Nächstliegendes Vorbild: `frontend/src/views/SettingsView.vue`
("Admin bearbeitet globale Inhalte"-Seite, laut Triage-Codebasis-Befund).
Struktur:

- Liste **aller** Ankündigungen (`useAnnouncements().loadAll()`),
  **inklusive abgelaufener** (Kernanforderung Punkt 5), je Eintrag:
  - Status-Badge: grün "Aktiv" wenn `announcement.isActive === true`,
    grau "Abgelaufen" sonst.
  - Titel, gekürzte Textvorschau, Miniaturbild falls vorhanden,
    Anzeigedauer, Ablaufdatum (`expiresAt`, formatiert).
  - Bearbeiten-Button (öffnet Formular vorausgefüllt) und
    Löschen-Button (mit Bestätigungsdialog, analog bestehender
    Lösch-Bestätigungen im Projekt, z. B. `PricingItemForm.vue`, falls
    dort vorhanden — sonst einfaches `confirm()`/Toast-Muster wie in
    `CoursesView.vue`).
- Formular (Create/Edit), eingebettet oder als Modal (Detailentscheidung
  liegt beim `dev-typescript`-Agenten, orientiert an
  `PricingItemForm.vue`/`DogFormModal.vue` je nachdem, was für die
  Seitengröße angemessener ist — beide Muster existieren im Projekt):
  - Textfeld `title` (`<input type="text">`, `maxlength="255"`).
  - `<HtmlEditor v-model="form.body" />` (bestehende Komponente,
    Abschnitt 3).
  - `<FileUpload :multiple="false" accepted-types="image/*" @upload="..." />`
    (bestehende Komponente, `multiple=false` deckt Kernanforderung
    Punkt 3 — genau ein Bild — bereits auf Komponentenebene ab).
  - Zahlenfeld `displayDays` (`<input type="number" min="1" max="365">`).
  - Speichern ruft `createAnnouncement`/`updateAnnouncement` auf; beim
    Bearbeiten mit neuem Bild wird `image` im Payload gesetzt, sonst
    weggelassen (bestehendes Bild bleibt unverändert — **kein**
    "Bild entfernen"-Schalter in diesem Change, YAGNI, nicht angefordert).

### 8.2 `frontend/src/router/index.ts` (ändern)

Neuer Eintrag im `/app`-Kind-Routen-Array, nach dem `settings`-Eintrag
(Zeile 127-131), vor `training-logs` (Zeile 133):

```ts
{
  path: 'announcements',
  name: 'Announcements',
  component: () => import('@/views/AnnouncementsView.vue'),
  meta: { title: 'Ankündigungen', requiresAdmin: true }
},
```

Folgt exakt dem bestehenden `Settings`-Eintrag-Muster (lazy import,
`requiresAdmin: true`, ausgewertet vom bestehenden Router-Guard
`frontend/src/router/index.ts:181-185`).

### 8.3 `frontend/src/layouts/DefaultLayout.vue` (ändern)

Neuer Navigations-Eintrag im `navigation`-Computed
(nach dem `Einstellungen`-Eintrag, Zeile 227-232, vor `Kontakt`,
Zeile 233-238):

```ts
{
  name: 'Ankündigungen',
  to: { name: 'Announcements' },
  icon: MegaphoneIcon,
  roles: ['admin']
},
```

`MegaphoneIcon`-Import ergänzen in der bestehenden
`@heroicons/vue/24/outline`-Import-Liste (Zeile 110-126); verifiziert
vorhanden unter
`frontend/node_modules/@heroicons/vue/24/outline/MegaphoneIcon.js`.

---

## 9. Übersicht neuer/geänderter Dateien

### Backend

| Datei | Status | Task |
|---|---|---|
| `backend/database/migrations/2026_07_17_100000_create_announcements_table.php` | neu | T01 |
| `backend/app/Models/Announcement.php` | neu | T02 |
| `backend/database/factories/AnnouncementFactory.php` | neu | T02 |
| `backend/app/Policies/AnnouncementPolicy.php` | neu | T03 |
| `backend/app/Http/Requests/StoreAnnouncementRequest.php` | neu | T04 |
| `backend/app/Http/Requests/UpdateAnnouncementRequest.php` | neu | T04 |
| `backend/app/Http/Resources/AnnouncementResource.php` | neu | T05 |
| `backend/app/Http/Controllers/Api/AnnouncementController.php` | neu | T06 |
| `backend/routes/api.php` | ändern | T07 |
| `backend/tests/Feature/Api/AnnouncementApiTest.php` | neu | T08 |

### Frontend

| Datei | Status | Task |
|---|---|---|
| `frontend/src/api/announcements.ts` | neu | T09 |
| `frontend/src/api/announcements.test.ts` | neu | T09 |
| `frontend/src/composables/useAnnouncements.ts` | neu | T09 |
| `frontend/src/components/AnnouncementBanner.vue` | neu | T10 |
| `frontend/src/components/AnnouncementBanner.test.ts` | neu | T10 |
| `frontend/src/views/HomeView.vue` | ändern | T10 |
| `frontend/src/views/AnnouncementsView.vue` | neu | T11 |
| `frontend/src/views/AnnouncementsView.test.ts` | neu | T11 |
| `frontend/src/router/index.ts` | ändern | T11 |
| `frontend/src/layouts/DefaultLayout.vue` | ändern | T11 |

---

## 10. Shared-Hosting-Kompatibilität (CLAUDE.md Abschnitt 3/4)

| Aspekt | Bewertung |
|---|---|
| PHP 8.2 | Zu prüfen (kein automatisiertes `compat-check`-Script vorhanden, siehe `proposal.md` "Out of Scope"): geplanter Code verwendet nur Standard-Eloquent, Enums/Attribute aus 8.3/8.4 werden nicht benötigt und dürfen von `dev-php` nicht eingesetzt werden |
| MySQL + PostgreSQL | ✓ — Migration nur `string()`/`text()`/`unsignedSmallInteger()`/`timestamp()`/`timestamps()`/`index()`, kein raw SQL (Abschnitt 2.2) |
| Kein Queue-Worker/Scheduler | ✓ — Ablauf wird beim Lesen berechnet (`scopeActive()`), keine Hintergrundverarbeitung (Abschnitt 2.1) |
| Kein Shell-Exec | ✓ — reine Eloquent-/Validierungs-/Storage-Facade-Operationen |
| Datei-Upload | ✓ — `Storage::disk('public')`, bestehende `.htaccess`-Limits (10 MB) decken `max:5120` (5 MB) bereits ab, keine Deployment-Template-Änderung nötig |
| Build-Artefakte | ✓ — keine neue npm-/Composer-Abhängigkeit, bestehender Vite-Build deckt `AnnouncementBanner.vue`/`AnnouncementsView.vue` ab |

---

## 11. Risiken

| Risiko | Bewertung / Gegenmaßnahme |
|---|---|
| Admin erstellt sehr viele gleichzeitig aktive Ankündigungen, Landingpage wird unübersichtlich lang | Bewusst nicht technisch begrenzt (siehe `proposal.md` "Out of Scope") — redaktionelle Verantwortung, kein angefordertes Akzeptanzkriterium für eine Obergrenze (YAGNI) |
| `expires_at` wird beim Bearbeiten einer Ankündigung nur neu berechnet, wenn `display_days` sich ändert — Admin ändert nur den Text und erwartet fälschlich eine Verlängerung | Dokumentiertes Verhalten (Abschnitt 2.3): Textänderungen verlängern die Anzeige **nicht** automatisch; dies entspricht der Nutzerentscheidung ("anzeigeseitig berechnet aus `display_days`"), nicht "jede Bearbeitung verlängert automatisch". Sollte sich das als unerwünscht herausstellen, ist eine spätere separate Änderung (expliziter "Anzeigedauer verlängern"-Button) trivial nachrüstbar |
| Zwei Sanitizing-Allowlists (Backend-Trait, Frontend-Konstante) könnten auseinanderlaufen, falls künftig eine Stelle geändert wird, die andere nicht | Vorbestehendes Risiko, nicht neu durch diesen Change eingeführt (`CoursesView.vue` hat dasselbe Muster bereits); der bestehende Code-Kommentar *"consistent with the backend sanitization allowlist"* wird im neuen Frontend-Code wortgleich übernommen, um die Kopplung sichtbar zu halten |
