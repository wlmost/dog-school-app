# Review: T01 + T02 — pricing-overview

**Reviewer-Prüfung:** 2026-05-14
**Gesamtbewertung:** APPROVED

---

## Zusammenfassung

Die Implementierung von T01 (Migration + Model) und T02 (Controller, FormRequests, Resource, Routen) ist vollständig spec-konform, PHP 8.2-kompatibel und sicher. Alle Sicherheitsanforderungen sind erfüllt: SQL-Injection-Schutz durch reines Eloquent, korrekter Mass-Assignment-Schutz, saubere Trennung von öffentlichen und Admin-Routen. Die DB-Portabilität ist gewährleistet — ausschließlich Blueprint-Methoden, kein DB-spezifisches SQL. Drei informative Hinweise ohne Handlungsbedarf für die Abnahme.

---

## Befunde

### `database/migrations/2026_05_14_000001_create_pricing_items_table.php`

Keine Befunde — alles korrekt.

- Ausschließlich `Blueprint`-Methoden, kein DB-spezifisches SQL. ✅
- `down()` korrekt mit `Schema::dropIfExists('pricing_items')`. ✅
- Index auf `category`-Spalte gemäß Spec. ✅
- `declare(strict_types=1)` in Migrations per design.md Abschnitt 2.1 explizit nicht erforderlich — Auslassung korrekt. ✅

---

### `app/Models/PricingItem.php`

Keine Befunde — alles korrekt.

- `declare(strict_types=1)`, korrekter Namespace `App\Models`. ✅
- `HasFactory` + `$fillable` mit allen 6 editierbaren Feldern. ✅
- `casts()` als **Methode** (nicht `$casts`-Property) — Laravel-11-konform und PHP 8.2-safe. ✅
- Casts: `price => 'decimal:2'`, `is_from_price => 'boolean'`, Timestamps `'datetime'` — exakt per Spec. ✅

---

### `app/Http/Resources/PricingItemResource.php`

Keine Befunde — alles korrekt.

- camelCase-Mapping vollständig: `is_from_price → isFromPrice`. ✅
- Timestamps via `$this->created_at?->toISOString()` (nullsafe, ISO 8601). ✅

---

### `app/Http/Requests/StorePricingItemRequest.php`

| # | Schwere | Befund | Zeile | Empfehlung |
|---|---------|--------|-------|------------|
| F01 | 🟢 INFO | `$this->user()->can('admin')` ohne Nullsafe-Operator. Wenn die Route je versehentlich ohne `auth:sanctum`-Middleware erreichbar ist, wirft PHP einen `TypeError` (500) statt 403. Konsistent mit Projektvorlage (`StoreCreditPackageRequest` L16, gleiche Schreibweise) und dem Design-Spec. | L20 | Optional: `return $this->user()?->can('admin') ?? false;` — nur relevant bei projektweiter Vereinheitlichung. Kein Handlungsbedarf für diese Abnahme. |

Alle Validierungsregeln spec-konform. `validatedSnakeCase()` mappt `isFromPrice → is_from_price` korrekt mit Default `false`. ✅

---

### `app/Http/Requests/UpdatePricingItemRequest.php`

| # | Schwere | Befund | Zeile | Empfehlung |
|---|---------|--------|-------|------------|
| F02 | 🟢 INFO | Identisches `authorize()`-Muster wie in `StorePricingItemRequest` (gleiche Analyse wie F01). | L20 | Wie F01. |

Regeln und `validatedSnakeCase()` identisch mit Store — korrekt für PUT-Semantik (alle Felder required oder nullable). ✅

---

### `app/Http/Controllers/Api/PricingItemController.php`

| # | Schwere | Befund | Zeile | Empfehlung |
|---|---------|--------|-------|------------|
| F03 | 🟢 INFO | Return-Typ `JsonResponse` (Implementierung) vs. `PricingItemResource` in der design.md-Methodentabelle. Die Implementierung ist **korrekt** — für HTTP 201 muss `->response()->setStatusCode(201)` genutzt werden, was zwingend `JsonResponse` zurückgibt. Der Design-Spec hat in der Tabellenspalte einen Schreibfehler. | L63 | Kein Handlungsbedarf im Code. Optional: design.md-Tabelle (Abschnitt 2.5) anpassen. |

Restliche Punkte:
- Kein `AuthorizesRequests`-Trait — Design-Warnung korrekt befolgt. ✅
- `publicIndex()`: `orderBy('category')->orderBy('id')`, `groupBy('category')`, `->values()`, `PricingItemResource::collection($group)->resolve()` — Struktur `{ data: [{ category, items: [...] }] }` spec-konform. ✅
- `store()`: HTTP 201 via `->response()->setStatusCode(201)`. ✅
- `update()`: `$pricingItem->fresh()` nach Update — gute Praxis, stellt sicher dass zurückgegebener Ressourcen-Zustand mit DB übereinstimmt. ✅
- `destroy()`: `response()->json(null, 204)` — spec-konform. ✅

---

### `routes/api.php`

Keine Befunde — alles korrekt.

- `use App\Http\Controllers\Api\PricingItemController;` korrekt ergänzt. ✅
- Öffentliche Route `GET /api/v1/pricing-items` ausserhalb aller Auth-Middleware-Gruppen. ✅
- Admin-Routen korrekt innerhalb `prefix('v1') → middleware('auth:sanctum') → middleware('can:admin') → prefix('admin')`. Vollständige Middleware-Stack: `auth:sanctum` + `can:admin`. ✅
- Kein Routen-Namenskonflikt zwischen `/v1/pricing-items` (public) und `/v1/admin/pricing-items` (admin). ✅
- Kein Rate-Limiting auf öffentlicher Route: explizit per design.md akzeptiert. ✅

---

### Gesamtbild: Fehlende `PricingItemFactory`

| # | Schwere | Befund | Datei | Empfehlung |
|---|---------|--------|-------|------------|
| F04 | 🟢 INFO | `PricingItem` verwendet `HasFactory`, aber `database/factories/PricingItemFactory.php` existiert nicht (Dateisuche negativ). Kein Laufzeit-Problem für Produktionsbetrieb, aber `PricingItem::factory()` in Feature-Tests wird mit `InvalidArgumentException` scheitern. | [app/Models/PricingItem.php](app/Models/PricingItem.php#L9) | Factory vor Test-Tasks (T0x) anlegen — blockiert sonst die Tester-Arbeit. |

---

## Offene Punkte für Implementierer

Keine Pflichtpunkte. Alle Befunde sind 🟢 INFO:

- **F01/F02:** `$this->user()?->can('admin') ?? false` als robustere Form — nur bei projektweiter Vereinheitlichung relevant, da das Projekt dieses Muster durchgängig so verwendet.
- **F03:** design.md Abschnitt 2.5, Methodentabelle: Return-Typ für `store()` von `PricingItemResource` auf `JsonResponse` korrigieren — reine Doku-Korrektur, kein Code-Änderungsbedarf.
- **F04:** `PricingItemFactory` vor Test-Implementierung anlegen, um `PricingItem::factory()->create()` in Feature-Tests zu ermöglichen.

---

## Lob

- **Saubere Auth-Separierung:** Öffentliche Route hat null Auth-Middleware, Admin-Routen haben volle `sanctum` + `can:admin`-Absicherung — kein einziges Versehen.
- **`casts()` als Methode:** Korrekt umgesetzt (nicht als deprecated `$casts`-Property), konsistent mit Laravel 11 und PHP 8.2.
- **`$pricingItem->fresh()` in `update()`:** Stellt sicher, dass der zurückgegebene Ressourcen-Zustand die echte DB-Realität widerspiegelt (inkl. allfälliger DB-Defaults).
- **`PricingItemResource::collection($group)->resolve()`:** Die richtige Methode für verschachtelte Strukturen in einer custom `JsonResponse` — korrekt gegenüber dem Anti-Pattern `toArray()`.
- **Design-Warnung befolgt:** `AuthorizesRequests`-Trait und `$this->authorize()` nicht aus `CreditPackageController` übernommen.
- **DB-Portabilität:** Migration ist 100 % Blueprint-basiert — läuft auf MySQL und PostgreSQL ohne Anpassung.
