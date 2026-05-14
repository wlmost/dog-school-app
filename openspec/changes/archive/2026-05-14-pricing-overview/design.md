# Design: pricing-overview

**Change-ID:** pricing-overview
**Triage-Referenz:** `openspec/triage/20260514104852-pricing-overview.md`

---

## 1. Datenbank-Schema

### Tabelle `pricing_items`

| Spalte         | Typ                          | Constraints                  | Notiz                                         |
|----------------|------------------------------|------------------------------|-----------------------------------------------|
| `id`           | bigint unsigned              | PK, auto-increment           | `$table->id()`                                |
| `category`     | varchar(100)                 | NOT NULL                     | Gruppen-Überschrift, z. B. "Verhaltensberatung" |
| `title`        | varchar(200)                 | NOT NULL                     | Name der Leistung                             |
| `price`        | decimal(8,2)                 | NOT NULL                     | Preis in EUR                                  |
| `unit`         | varchar(100)                 | NULL                         | z. B. "je Einheit", "pro Kurs", leer          |
| `description`  | varchar(500)                 | NULL                         | Zusatzinfo, z. B. "max 6 Teilnehmer"          |
| `is_from_price`| tinyint(1) / boolean         | NOT NULL, default 0          | `true` → "ab X EUR" Anzeige                  |
| `created_at`   | timestamp                    | NULL                         | `$table->timestamps()`                        |
| `updated_at`   | timestamp                    | NULL                         | `$table->timestamps()`                        |

**Sortierung** (kein `sort_order`-Feld): Anzeigereihenfolge ist `ORDER BY category ASC, id ASC`.

**Portabilität:** Alle Felder nutzen ausschließlich Laravel-Blueprint-Methoden ohne DB-spezifische Typen. Kein `JSONB`, kein `SERIAL`, keine Postgres-Operatoren.

---

## 2. Backend

### 2.1 Migration

**Datei:** `backend/database/migrations/2026_05_14_000001_create_pricing_items_table.php`

```php
// Relevante Felder (anonyme Klasse, declare(strict_types=1) am Anfang der Datei nicht nötig bei Migrations)
Schema::create('pricing_items', function (Blueprint $table) {
    $table->id();
    $table->string('category', 100);
    $table->string('title', 200);
    $table->decimal('price', 8, 2);
    $table->string('unit', 100)->nullable();
    $table->string('description', 500)->nullable();
    $table->boolean('is_from_price')->default(false);
    $table->timestamps();

    $table->index('category');
});
```

`down()` ruft `Schema::dropIfExists('pricing_items')` auf.

**Risiko-Flag:** Migration muss gegen MySQL (CI-Matrix) und PostgreSQL (lokale Docker-Umgebung) getestet werden.

---

### 2.2 Model `PricingItem`

**Datei:** `backend/app/Models/PricingItem.php`

- Namespace: `App\Models`
- `declare(strict_types=1)`
- Erweitert `Illuminate\Database\Eloquent\Model`
- Trait: `HasFactory`
- `$fillable`: alle fünf editierbaren Felder (`category`, `title`, `price`, `unit`, `description`, `is_from_price`)
- `casts()` (Methode, nicht Property — Laravel 11+, PHP 8.2-kompatibel):
  ```php
  protected function casts(): array
  {
      return [
          'price'         => 'decimal:2',
          'is_from_price' => 'boolean',
          'created_at'    => 'datetime',
          'updated_at'    => 'datetime',
      ];
  }
  ```
- Keine Beziehungen nötig

**Vorlage:** `backend/app/Models/CreditPackage.php` (gleiche Struktur: `HasFactory`, `$fillable`, `casts()`-Methode).

---

### 2.3 API Resource `PricingItemResource`

**Datei:** `backend/app/Http/Resources/PricingItemResource.php`

Gibt camelCase-Keys zurück (konsistent mit allen anderen Resources im Projekt):

```php
public function toArray(Request $request): array
{
    return [
        'id'          => $this->id,
        'category'    => $this->category,
        'title'       => $this->title,
        'price'       => $this->price,
        'unit'        => $this->unit,
        'description' => $this->description,
        'isFromPrice' => $this->is_from_price,
        'createdAt'   => $this->created_at?->toISOString(),
        'updatedAt'   => $this->updated_at?->toISOString(),
    ];
}
```

**Vorlage:** `backend/app/Http/Resources/CourseResource.php` (camelCase-Mapping, `toISOString()` für Timestamps).

---

### 2.4 Form Requests

**Muster:** Analog zu `StoreCreditPackageRequest` — camelCase Input-Keys, `validatedSnakeCase()`-Methode.

#### `StorePricingItemRequest`
**Datei:** `backend/app/Http/Requests/StorePricingItemRequest.php`

- `authorize()`: `return $this->user()->can('admin');`
- `rules()`:
  - `category`:    `required|string|max:100`
  - `title`:       `required|string|max:200`
  - `price`:       `required|numeric|min:0|max:999999.99`
  - `unit`:        `nullable|string|max:100`
  - `description`: `nullable|string|max:500`
  - `isFromPrice`: `nullable|boolean`

#### `UpdatePricingItemRequest`
**Datei:** `backend/app/Http/Requests/UpdatePricingItemRequest.php`

- Identische `authorize()` und `rules()` wie Store (alle Felder `required` oder `nullable` — PUT semantics).

**Beide Requests** implementieren `validatedSnakeCase()`, die `isFromPrice` → `is_from_price` mappt.

---

### 2.5 Controller `PricingItemController`

**Datei:** `backend/app/Http/Controllers/Api/PricingItemController.php`

- Namespace: `App\Http\Controllers\Api`
- `declare(strict_types=1)`
- Erweitert `App\Http\Controllers\Controller`
- Kein `AuthorizesRequests`-Trait nötig (Auth-Schutz liegt auf Route-Ebene, Autorisierung in FormRequests)
- **Achtung:** `CreditPackageController` als Vorlage verwendet diesen Trait und `$this->authorize()` — diese Teile **nicht** übernehmen

#### Methoden

| Methode        | Signatur                                                  | Beschreibung                                      |
|----------------|-----------------------------------------------------------|---------------------------------------------------|
| `publicIndex`  | `public function publicIndex(): JsonResponse`             | Öffentlich: alle Items, nach Kategorie gruppiert  |
| `index`        | `public function index(): AnonymousResourceCollection`    | Admin: flache Liste aller Items                   |
| `store`        | `public function store(StorePricingItemRequest $r): PricingItemResource` | Admin: neues Item anlegen  |
| `update`       | `public function update(UpdatePricingItemRequest $r, PricingItem $pricingItem): PricingItemResource` | Admin: bearbeiten |
| `destroy`      | `public function destroy(PricingItem $pricingItem): JsonResponse` | Admin: löschen            |

**`publicIndex`** — gibt eine nach Kategorie gruppierte Struktur zurück:
```php
$items = PricingItem::query()
    ->orderBy('category')
    ->orderBy('id')
    ->get();

$grouped = $items->groupBy('category')->map(function ($group, $category) {
    return [
        'category' => $category,
        'items'    => PricingItemResource::collection($group)->resolve(),
    ];
})->values();

return response()->json(['data' => $grouped]);
```

**`destroy`** gibt `response()->json(null, 204)` zurück (konsistent mit anderen Controllern).

**Vorlage:** `backend/app/Http/Controllers/Api/CourseController.php` und `CreditPackageController` für Struktur und Return-Types.

---

### 2.6 Routen

**Datei:** `backend/routes/api.php`

**Öffentliche Route** (außerhalb des `auth:sanctum`-Blocks, neue Gruppe):
```php
// Public pricing route
Route::prefix('v1')->group(function () {
    Route::get('/pricing-items', [PricingItemController::class, 'publicIndex']);
});
```

> **Begründung:** Keine `throttle`-Middleware nötig für einen einfachen Lese-Endpunkt.
> Falls gewünscht, kann `throttle:60,1` ergänzt werden (60 Req/Min/IP = Laravel-Default).

**Admin-Routen** (innerhalb des bestehenden `auth:sanctum` + `can:admin`-Blocks):
```php
// Im bestehenden Block: Route::prefix('v1')->middleware('auth:sanctum')->group(...)
// → Im bestehenden Block: Route::middleware('can:admin')->group(...)
Route::prefix('admin')->group(function () {
    Route::get('/pricing-items', [PricingItemController::class, 'index']);
    Route::post('/pricing-items', [PricingItemController::class, 'store']);
    Route::put('/pricing-items/{pricingItem}', [PricingItemController::class, 'update']);
    Route::delete('/pricing-items/{pricingItem}', [PricingItemController::class, 'destroy']);
});
```

Vollständige Admin-URL: `GET /api/v1/admin/pricing-items`, `POST /api/v1/admin/pricing-items`, etc.

**Import** am Anfang von `api.php`:
```php
use App\Http\Controllers\Api\PricingItemController;
```

---

## 3. Frontend

### 3.1 API-Modul `pricingItems.ts`

**Datei:** `frontend/src/api/pricingItems.ts`

TypeScript-Interfaces und API-Funktionen, analog zu `frontend/src/api/settings.ts`:

```ts
import apiClient from './client'

export interface PricingItem {
  id: number
  category: string
  title: string
  price: string           // decimal als String (JSON-Dezimalzahl)
  unit: string | null
  description: string | null
  isFromPrice: boolean
  createdAt: string | null
  updatedAt: string | null
}

export interface PricingGroup {
  category: string
  items: PricingItem[]
}

export const pricingItemsApi = {
  async getPublic(): Promise<PricingGroup[]>  // GET /api/v1/pricing-items
  async getAll(): Promise<PricingItem[]>       // GET /api/v1/admin/pricing-items (auth)
  async create(data: Partial<PricingItem>): Promise<PricingItem>   // POST
  async update(id: number, data: Partial<PricingItem>): Promise<PricingItem>  // PUT
  async delete(id: number): Promise<void>     // DELETE
}
```

---

### 3.2 Composable `usePricingItems.ts`

**Datei:** `frontend/src/composables/usePricingItems.ts`
(Verzeichnis `composables/` muss neu angelegt werden — existiert noch nicht)

```ts
// Kapselt reaktiven State + API-Calls für Admin-CRUD:
const items = ref<PricingItem[]>([])
const groups = ref<PricingGroup[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// Exponiert: loadPublic(), loadAll(), createItem(), updateItem(), deleteItem()
```

Das Composable hält `groups` (für das öffentliche Modal) und `items` (für die Admin-Tabelle) getrennt.

---

### 3.3 Neue Kachel in `HomeView.vue`

**Datei:** `frontend/src/views/HomeView.vue`

Das Feature-Grid hat aktuell 6 Kacheln im Raster `md:grid-cols-2 lg:grid-cols-3`. Eine neue 7. Kachel "Preise" wird hinzugefügt — das Raster bleibt unverändert, die 7. Kachel füllt die erste Position der neuen Reihe.

**Kachel-Markup** (exakt gleiche Struktur wie bestehende Kacheln, aber klickbar):

```html
<!-- Preise-Kachel (klickbar, öffnet Modal) -->
<div
  class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow cursor-pointer"
  @click="showPricingModal = true"
>
  <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center mb-4">
    <CurrencyEuroIcon class="w-6 h-6 text-primary-600 dark:text-primary-400" />
  </div>
  <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">
    Preise
  </h3>
  <p class="text-gray-600 dark:text-gray-300">
    Transparente Preisübersicht für alle Leistungen der Hundeschule
  </p>
</div>
```

**Import:** `CurrencyEuroIcon` aus `@heroicons/vue/24/outline` (bereits als Dependency vorhanden).

**State + Modal:**
```ts
import { ref, computed, onMounted } from 'vue'
import PricingModal from '@/components/PricingModal.vue'
import { usePricingItems } from '@/composables/usePricingItems'

const showPricingModal = ref(false)
const { groups, loadPublic } = usePricingItems()
onMounted(() => loadPublic())
```

---

### 3.4 `PricingModal.vue`

**Datei:** `frontend/src/components/PricingModal.vue`

Props:
```ts
defineProps<{
  visible: boolean
  groups: PricingGroup[]
}>()
defineEmits(['close'])
```

Struktur des Modals:
- Overlay (`fixed inset-0 z-50 bg-black/50`)
- Modalkarte mit max-width, scrollbar
- Schließen-Button (X) oben rechts
- Für jede Gruppe: Kategorie-Überschrift (`<h3>`) + Tabelle/Liste der Items
- Item-Zeile: `title` | `price` formatiert als `"ab X,XX €"` wenn `isFromPrice`, sonst `"X,XX €"` | `unit` | `description`
- Leer-Zustand: "Noch keine Preise hinterlegt."

**Keine eigene Route** — das Modal wird direkt in `HomeView.vue` eingebunden:
```html
<PricingModal :visible="showPricingModal" :groups="groups" @close="showPricingModal = false" />
```

---

### 3.5 Preise-Bereich in `SettingsView.vue` und `PricingItemForm.vue`

**Designentscheidung:** `SettingsView.vue` ist ein einzelnes, scrollbares Formular mit Abschnitten. **Kein Tab-Umbau** — der neue Preise-Bereich wird als zusätzlicher Abschnitt am Ende der Seite angefügt, im gleichen visuellen Design wie die bestehenden Abschnitte (Überschrift, Trennlinie, Karten-/Panel-Struktur).

**Integration in `SettingsView.vue`:**
- Neuer `<section>`-Block am Ende der Seite, nach dem letzten bestehenden Abschnitt
- Abschnittstitel: "Preise" (gleiche `<h2>`/`<h3>`-Klassen wie bestehende Abschnitte)
- Inhalt: "Neuen Preis anlegen"-Button + Tabelle der bestehenden Einträge
- Das bestehende Formular und `saveSettings()`-Verhalten bleiben vollständig unverändert
- Preise-Bereich verwendet **kein** `<form>`-Submit — direkte API-Calls über den Composable

**Datei:** `frontend/src/components/PricingItemForm.vue`

Reusable Modal/Formular zum Anlegen und Bearbeiten:
```ts
defineProps<{
  visible: boolean
  item?: PricingItem | null   // null = Anlegen, PricingItem = Bearbeiten
}>()
defineEmits(['saved', 'cancel'])
```

Felder:
- `category` (text, required) — mit Hinweis "z. B. Verhaltensberatung"
- `title` (text, required)
- `price` (number, min 0, required)
- `unit` (text, optional) — Placeholder: "je Einheit / pro Kurs"
- `description` (textarea, optional)
- `is_from_price` (checkbox) — Label: "Ab-Preis (zeigt 'ab X €')"

---

## 4. Risiko-Flags

| # | Risiko                                  | Maßnahme                                                              |
|---|-----------------------------------------|-----------------------------------------------------------------------|
| R1 | Migration MySQL vs. PostgreSQL          | Nur `$table->string()`, `$table->decimal()`, `$table->boolean()` — keine DB-spezifischen Typen |
| R2 | Öffentliche Route ohne Auth             | Route außerhalb des `auth:sanctum`-Blocks platzieren; kein `auth:sanctum`-Middleware im Construct des Controllers |
| R3 | PHP 8.2-Kompatibilität                  | Kein `#[\Override]`, keine Typed Class Constants, kein `json_validate()`, keine Property Hooks |
| R4 | `SettingsView.vue`-Erweiterung          | **Kein Tab-Umbau** — neuer Abschnitt wird ans Ende der Seite angefügt; bestehendes Formular und `saveSettings()` bleiben unverändert |
| R5 | `composables/`-Verzeichnis neu          | `frontend/src/composables/` existiert noch nicht — muss angelegt werden |
| R6 | `price`-Typ im Frontend                 | Laravel gibt `decimal:2` als String in JSON zurück (z. B. `"120.00"`) — im Frontend via `parseFloat()` konvertieren für Anzeige/Rechnung |

---

## 5. Verzeichnisstruktur (neue Dateien)

```
backend/
  database/migrations/
    2026_05_14_000001_create_pricing_items_table.php   [T01]
  app/
    Models/
      PricingItem.php                                   [T01]
    Http/
      Controllers/Api/
        PricingItemController.php                       [T02]
      Requests/
        StorePricingItemRequest.php                     [T02]
        UpdatePricingItemRequest.php                    [T02]
      Resources/
        PricingItemResource.php                         [T02]

frontend/src/
  api/
    pricingItems.ts                                     [T03]
  composables/
    usePricingItems.ts                                  [T03]
  components/
    PricingModal.vue                                    [T03]
    PricingItemForm.vue                                 [T04]

Geänderte Dateien:
  backend/routes/api.php                               [T02]
  frontend/src/views/HomeView.vue                      [T03]
  frontend/src/views/SettingsView.vue                  [T04]
```
