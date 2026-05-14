# Tasks: pricing-overview

**Change-ID:** pricing-overview
**Stand:** 2026-05-14

---

## T01 — Migration & Model (dev-php)

**Agent:** `dev-php`
**Abhängigkeiten:** keine

### Zu erstellende Dateien
- `backend/database/migrations/2026_05_14_000001_create_pricing_items_table.php`
- `backend/app/Models/PricingItem.php`

### Beschreibung
Erstelle die Datenbank-Migration und das Eloquent-Model für `PricingItem`.

**Migration:**
- Anonyme Klasse, `up()` erstellt Tabelle `pricing_items` mit den Feldern aus `design.md` Abschnitt 2.1
- Index auf `category`-Spalte
- `down()` ruft `Schema::dropIfExists('pricing_items')` auf
- Ausschließlich Laravel-Blueprint-Methoden — kein DB-spezifisches SQL

**Model:**
- `declare(strict_types=1)`, Namespace `App\Models`
- Trait `HasFactory`
- `$fillable` mit allen 6 editierbaren Feldern
- `casts()`-Methode (keine `$casts`-Property): `price => 'decimal:2'`, `is_from_price => 'boolean'`, Timestamps als `'datetime'`
- Keine Beziehungen

**Vorlage:** `backend/app/Models/CreditPackage.php`

### Akzeptanzkriterien
- [x] `php artisan migrate` läuft ohne Fehler (PostgreSQL lokal)
- [x] Migration läuft auch auf MySQL (CI-Matrix / `docker-compose.mysql.yml`)
- [x] `PricingItem::create([...])` und `PricingItem::query()->get()` funktionieren in Tinker
- [x] Kein 8.3/8.4-PHP-Feature im Code; `composer compat-check` ohne Fehler

---

## T02 — API Controller & Routen (dev-php)

**Agent:** `dev-php`
**Abhängigkeiten:** T01

### Zu erstellende Dateien
- `backend/app/Http/Controllers/Api/PricingItemController.php`
- `backend/app/Http/Requests/StorePricingItemRequest.php`
- `backend/app/Http/Requests/UpdatePricingItemRequest.php`
- `backend/app/Http/Resources/PricingItemResource.php`

### Zu ändernde Dateien
- `backend/routes/api.php`

### Beschreibung

**`PricingItemResource`:**
- camelCase-Mapping: `is_from_price → isFromPrice`, Timestamps als `toISOString()`
- Vorlage: `backend/app/Http/Resources/CourseResource.php`

**`StorePricingItemRequest` / `UpdatePricingItemRequest`:**
- `authorize()`: `return $this->user()->can('admin');`
- Regeln laut `design.md` Abschnitt 2.4 (camelCase Keys)
- `validatedSnakeCase()`-Methode: mappt `isFromPrice → is_from_price`
- Vorlage: `backend/app/Http/Requests/StoreCreditPackageRequest.php`

**`PricingItemController`:**
- Namespace `App\Http\Controllers\Api`, `declare(strict_types=1)`
- 5 Methoden: `publicIndex`, `index`, `store`, `update`, `destroy`
- `publicIndex`: Items geordnet nach `category ASC, id ASC`, dann via `groupBy('category')` gruppiert → Response-Struktur: `['data' => [['category' => '...', 'items' => [...]], ...]]`
- `destroy`: gibt `response()->json(null, 204)` zurück
- Vorlage: `backend/app/Http/Controllers/Api/CreditPackageController.php`

> ⚠️ **Achtung:** `CreditPackageController` verwendet den `AuthorizesRequests`-Trait und `$this->authorize()` — diese Teile **nicht** übernehmen. Auth-Schutz liegt bei `pricing-overview` vollständig auf Route-Ebene (Middleware) und in den FormRequests.

**`routes/api.php`:**
- Neue öffentliche Route **außerhalb** aller Middleware-Gruppen:
  ```php
  Route::prefix('v1')->group(function () {
      Route::get('/pricing-items', [PricingItemController::class, 'publicIndex']);
  });
  ```
- Admin-Routen **innerhalb** des bestehenden `auth:sanctum`-Blocks, im bestehenden `can:admin`-Sub-Block:
  ```php
  Route::prefix('admin')->group(function () {
      Route::get('/pricing-items', [PricingItemController::class, 'index']);
      Route::post('/pricing-items', [PricingItemController::class, 'store']);
      Route::put('/pricing-items/{pricingItem}', [PricingItemController::class, 'update']);
      Route::delete('/pricing-items/{pricingItem}', [PricingItemController::class, 'destroy']);
  });
  ```
- `use App\Http\Controllers\Api\PricingItemController;` am Anfang der Datei ergänzen

### Akzeptanzkriterien
- [x] `GET /api/v1/pricing-items` antwortet mit HTTP 200 ohne Auth-Token
- [x] `GET /api/v1/pricing-items` gibt `{ data: [{ category, items: [...] }] }` zurück
- [x] `POST /api/v1/admin/pricing-items` ohne Token → HTTP 401
- [x] `POST /api/v1/admin/pricing-items` mit Admin-Token + gültigen Daten → HTTP 201 mit Resource
- [x] `PUT /api/v1/admin/pricing-items/{id}` mit Admin-Token → HTTP 200
- [x] `DELETE /api/v1/admin/pricing-items/{id}` mit Admin-Token → HTTP 204
- [x] Validierungsfehler bei fehlenden Pflichtfeldern → HTTP 422 mit `errors`-Key
- [x] `composer compat-check` ohne Fehler

---

## T03 — Frontend: PricingModal & HomeView-Kachel (dev-javascript)

**Agent:** `dev-javascript`
**Abhängigkeiten:** T02

### Zu erstellende Dateien
- `frontend/src/api/pricingItems.ts`
- `frontend/src/composables/usePricingItems.ts`
  _(Verzeichnis `composables/` muss neu angelegt werden)_
- `frontend/src/components/PricingModal.vue`

### Zu ändernde Dateien
- `frontend/src/views/HomeView.vue`

### Beschreibung

**`frontend/src/api/pricingItems.ts`:**
- TypeScript-Interfaces `PricingItem` und `PricingGroup` (Felder laut `design.md` Abschnitt 3.1)
- `price` im Interface als `string` (Backend gibt `decimal:2` als String zurück)
- `pricingItemsApi`-Objekt mit Methoden: `getPublic()`, `getAll()`, `create()`, `update()`, `delete()`
- `getPublic()` ruft `GET /api/v1/pricing-items` auf
- Admin-Methoden rufen `GET|POST|PUT|DELETE /api/v1/admin/pricing-items[/{id}]` auf
- Vorlage: `frontend/src/api/settings.ts`

**`frontend/src/composables/usePricingItems.ts`:**
- `groups` (`Ref<PricingGroup[]>`) für öffentliche Anzeige
- `items` (`Ref<PricingItem[]>`) für Admin-Tabelle
- `loading` (`Ref<boolean>`)
- `error` (`Ref<string | null>`)
- Methoden: `loadPublic()`, `loadAll()`, `createItem()`, `updateItem()`, `deleteItem()`

**`frontend/src/components/PricingModal.vue`:**
- Props: `visible: boolean`, `groups: PricingGroup[]`
- Emits: `'close'`
- Overlay mit `v-if="visible"` (kein Teleport nötig, aber möglich)
- Für jede Gruppe: Kategorieüberschrift + Preisliste
- Preisformatierung: `parseFloat(item.price).toLocaleString('de-DE', { minimumFractionDigits: 2 })` + " €"
- `is_from_price = true` → Präfix "ab " vor dem Preis
- Schließen: X-Button + Klick auf Overlay
- Leer-Zustand: "Noch keine Preise hinterlegt."

**`frontend/src/views/HomeView.vue`:**
- Import: `CurrencyEuroIcon` aus `@heroicons/vue/24/outline` (bereits installiert)
- Import: `PricingModal` und `usePricingItems`
- `showPricingModal = ref(false)`
- `onMounted(() => loadPublic())`
- Neue 7. Kachel im bestehenden Feature-Grid (letzter Eintrag in `<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">`)
- Kachel ist klickbar (`@click="showPricingModal = true"`, `cursor-pointer`), keine `RouterLink`
- `<PricingModal>` wird am Ende des Templates (vor `</div>` des Haupt-Containers) eingebunden

### Akzeptanzkriterien
- [x] Neue Kachel "Preise" ist im Feature-Grid sichtbar (7. Kachel)
- [x] Klick auf Kachel öffnet das Modal
- [x] Modal zeigt Preiseinträge gruppiert nach Kategorie
- [x] "ab"-Präfix wird bei `isFromPrice = true` angezeigt
- [x] Modal schließt sich bei Klick auf X oder Overlay
- [x] Bei leerem Backend-Response zeigt das Modal "Noch keine Preise hinterlegt."
- [x] `npm run build` ohne Fehler oder Warnings
- [x] Vitest-Tests für `usePricingItems.ts` (mock API-Calls, prüfe State-Übergänge)

---

## T04 — Frontend: Admin-Tab in SettingsView (dev-javascript)

**Agent:** `dev-javascript`
**Abhängigkeiten:** T02

### Zu erstellende Dateien
- `frontend/src/components/PricingItemForm.vue`

### Zu ändernde Dateien
- `frontend/src/views/SettingsView.vue`

### Beschreibung

**Wichtiger Kontext:** `SettingsView.vue` ist ein einzelnes scrollbares Formular. **Kein Tab-Umbau** — der Preise-Bereich wird als neuer `<section>`-Block am Ende der Seite eingefügt, im gleichen visuellen Design wie bestehende Abschnitte. Das bestehende Formular und `saveSettings()` bleiben vollständig unverändert.

**Erweiterung `SettingsView.vue`:**
- Neuen `<section>`-Block am Ende einfügen (nach letztem bestehenden Abschnitt)
- Abschnittstitel "Preise" (gleiche CSS-Klassen wie bestehende Abschnittstitel)
- Inhalt: "Neuen Preis anlegen"-Button + Tabelle der vorhandenen Items
- Lädt Items mit `loadAll()` via `usePricingItems()` beim Mounten
- Bearbeiten/Löschen/Anlegen über `PricingItemForm`

**`frontend/src/components/PricingItemForm.vue`:**
- Props: `visible: boolean`, `item: PricingItem | null` (`null` = Neueintrag)
- Emits: `'saved'`, `'cancel'`
- Felder laut `design.md` Abschnitt 3.5
- Formularvalidierung im Frontend (Pflichtfelder nicht leer, `price` ≥ 0)
- Ruft `createItem()` oder `updateItem()` aus dem Composable auf
- Gibt `'saved'` Emit ab nach erfolgreichem Save, um die Liste zu refreshen

**Preise-Abschnitt in `SettingsView.vue`:**
- Lädt Items mit `loadAll()` beim Mounten (`onMounted`)
- Tabelle: Spalten `Kategorie | Titel | Preis | Einheit | Beschreibung | Ab-Preis? | Aktionen`
- Aktionsbuttons: "Bearbeiten" (öffnet `PricingItemForm`), "Löschen" (Bestätigungs-Dialog)
- "Neuen Preis anlegen"-Button öffnet `PricingItemForm` mit `item = null`
- Löschen zeigt `window.confirm()` vor dem API-Call (keine eigene Confirm-Komponente nötig)
- Loading- und Error-Zustände aus dem Composable anzeigen

### Akzeptanzkriterien
- [x] `SettingsView.vue` zeigt am Ende der Seite einen neuen Abschnitt "Preise" im gleichen Design wie bestehende Abschnitte
- [x] Bestehende Stammdaten- und E-Mail-Funktionalität ist vollständig unverändert
- [x] Im Preise-Abschnitt werden alle Einträge tabellarisch angezeigt
- [x] "Neuen Preis anlegen" öffnet `PricingItemForm` und der neue Eintrag erscheint nach Speichern in der Tabelle
- [x] Bearbeiten öffnet das Formular vorausgefüllt
- [x] Löschen entfernt den Eintrag nach Bestätigung
- [x] `npm run build` ohne Fehler oder Warnings
- [x] Vitest-Tests für `PricingItemForm.vue` (Formularvalidierung, Submit-Verhalten)
