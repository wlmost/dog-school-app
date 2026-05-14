# Verification: pricing-overview

**Skeptiker-Prüfung:** 2026-05-14
**Gesamtstatus:** nacharbeit-am-design-nötig

---

## Zusammenfassung

14 Annahmen geprüft: **10 ✅ bestätigt**, **1 ❌ widerlegt**, **3 ⚠️ abweichend / nacharbeit-nötig**.

**Kritischer Befund:** T04 (`SettingsView` Tab-Umbau) muss vollständig umgeschrieben werden — die Beschreibung widerspricht der expliziten User-Anforderung und dem tatsächlichen Code-Aufbau. Alle Backend-Annahmen sind substanziell korrekt; drei Abweichungen vom Vorlage-Verhalten des `CreditPackageController` müssen dokumentiert werden.

---

## Befunde

### B01 — Namespace `App\Http\Controllers\Api\` existiert
**Status:** ✅  
**Fundstelle:** `backend/app/Http/Controllers/Api/` (18 Controller-Dateien)  
**Befund:** Verzeichnis existiert. Enthält u. a. `CreditPackageController.php`, `CourseController.php`, `SettingsController.php` ist **nicht** darin (liegt unter `App\Http\Controllers\`, Import in api.php Zeile 24: `use App\Http\Controllers\SettingsController`). Kein Namens-Konflikt mit dem neuen `PricingItemController`.  
**Konsequenz:** Keine Änderung nötig.

---

### B02 — Auth-Middleware-Struktur in `routes/api.php`
**Status:** ✅  
**Fundstelle:** `backend/routes/api.php:54` (`auth:sanctum`-Block), Z. 64–67 (leerer `can:admin`-Block), Z. 162–166 (Settings `can:admin`-Block)  
**Befund:** Zwei `can:admin`-Blöcke innerhalb des `auth:sanctum`-Blocks bestätigt:
1. Leerer Block Z. 64–67: `Route::middleware('can:admin')->group(function () { // Admin specific routes can go here });`
2. Settings-Block Z. 162–166 mit `Route::get('/settings', ...)` und `Route::put('/settings', ...)`.

Das `Route::prefix('admin')` URL-Muster (`/api/v1/admin/...`) existiert noch **nirgendwo** im Projekt — es ist für Pricing neu. Die bestehenden Routen (credit-packages, courses etc.) sind alle direkt unter `auth:sanctum` ohne `can:admin`-Wrapper und ohne `/admin/`-Prefix. Die Design-Entscheidung, Pricing-Admin-Routen unter `can:admin` zu schützen, ist konsistenter als das CreditPackage-Muster, aber ist ein **neues URL-Schema** im Projekt.  
**Konsequenz:** Keine Korrektur nötig, aber dev-php muss die Routen in den **leeren** `can:admin`-Block (Z. 64–67) einbauen, nicht in den Settings-Block. Die Tasks-Anweisung „im bestehenden `can:admin`-Sub-Block" ist ausreichend klar.

---

### B03 — `CreditPackageController` als Vorlage: `AuthorizesRequests`-Abweichung
**Status:** ⚠️  
**Fundstelle:** `backend/app/Http/Controllers/Api/CreditPackageController.php:13` (`use AuthorizesRequests;`), Z. 72 (`$this->authorize('create', CreditPackage::class)`), Z. 96 (`$this->authorize('update', ...)`), Z. 108 (`$this->authorize('delete', ...)`)  
**Befund:** Der `CreditPackageController` verwendet den `AuthorizesRequests`-Trait **und** Policy-basierte `$this->authorize()`-Aufrufe. `design.md` Abschnitt 2.5 sagt: „Kein `AuthorizesRequests`-Trait nötig (Auth-Schutz liegt auf Route-Ebene, Autorisierung in FormRequests)". Das ist eine **bewusste Abweichung** vom Vorlage-Muster, nicht ein Irrtum. Die Route-Level-`can:admin`-Middleware + FormRequest-`authorize()` ersetzen die Policy. Diese Abweichung muss in `task-T02.notes.md` explizit dokumentiert werden, damit dev-php nicht reflexartig den Trait aus der Vorlage übernimmt.  
**Konsequenz:** design.md ist inhaltlich richtig, aber missverständlich. In T02 ergänzen: „**Achtung:** `CreditPackageController` ist nur als strukturelle Vorlage gemeint. Den `AuthorizesRequests`-Trait und `$this->authorize()`-Aufrufe **nicht** übernehmen."

---

### B04 — `CreditPackage`-Model: `casts()`-Methode und `$fillable`
**Status:** ✅  
**Fundstelle:** `backend/app/Models/CreditPackage.php:31` (`protected $fillable`), Z. 42 (`protected function casts(): array`)  
**Befund:** Model verwendet `HasFactory`, `$fillable`, `casts()`-Methode (keine `$casts`-Property) — exakt wie design.md beschreibt. Hinweis: `CreditPackage` nutzt `'price' => 'float'` in casts (Z. 46), während design.md `'price' => 'decimal:2'` für `PricingItem` vorsieht. Das ist eine bewusste Design-Entscheidung für präzisere Dezimaldarstellung — korrekt und PHP-8.2-kompatibel.  
**Konsequenz:** Keine Änderung nötig.

---

### B05 — `CourseResource` als Vorlage (camelCase, `toISOString()`)
**Status:** ✅  
**Fundstelle:** `backend/app/Http/Resources/CourseResource.php:17–37`  
**Befund:** `CourseResource` gibt camelCase-Keys zurück (`trainerId`, `maxParticipants`, etc.) und verwendet `$this->created_at?->toISOString()` / `$this->updated_at?->toISOString()` (Z. 34–35). Vorlage-Behauptungen in design.md Abschnitt 2.3 bestätigt.  
**Konsequenz:** Keine Änderung nötig.

---

### B06 — `StoreCreditPackageRequest` hat `validatedSnakeCase()`-Methode
**Status:** ✅  
**Fundstelle:** `backend/app/Http/Requests/StoreCreditPackageRequest.php:52–63`  
**Befund:** Methode `validatedSnakeCase()` existiert und mappt camelCase-Input-Keys auf snake_case-DB-Felder. Design-Annahme bestätigt.  
**Konsequenz:** Keine Änderung nötig.

---

### B07 — `StoreCreditPackageRequest::authorize()` Berechtigungs-Logik
**Status:** ⚠️  
**Fundstelle:** `backend/app/Http/Requests/StoreCreditPackageRequest.php:15–17`  
**Befund:** `authorize()` gibt `$this->user()->can('admin') || $this->user()->can('trainer')` zurück (Admin **oder** Trainer darf Credit Packages anlegen). design.md Abschnitt 2.4 sieht für `StorePricingItemRequest` nur `return $this->user()->can('admin');` vor. Das ist eine **bewusste Einschränkung** (Preispflege = nur Admin), nicht ein Fehler in der Vorlage-Abbildung — aber die Abweichung ist nirgendwo in design.md erklärt.  
**Konsequenz:** In design.md Abschnitt 2.4 Klarstellungs-Satz ergänzen: „Nur Admins dürfen Preise pflegen — Trainer-Berechtigung wird abweichend von `StoreCreditPackageRequest` nicht gewährt."

---

### B08 — `HomeView.vue` Feature-Grid (6 Kacheln, CSS-Klassen)
**Status:** ✅  
**Fundstelle:** `frontend/src/views/HomeView.vue:44` (Grid-Wrapper), Z. 46–118 (Kacheln 1–6)  
**Befund:** Grid `class="grid md:grid-cols-2 lg:grid-cols-3 gap-8"` bestätigt. Genau 6 Kacheln vorhanden. Kachel-Markup exakt wie design.md Abschnitt 3.3 beschreibt: `bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow`. Icon-Container: `w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-lg flex items-center justify-center mb-4`. Alle 6 Icons aus `@heroicons/vue/24/outline` importiert (Z. 157–164). `CurrencyEuroIcon` ist noch nicht importiert — muss neu hinzugefügt werden.  
**Konsequenz:** Keine Änderung nötig.

---

### B09 — `SettingsView.vue` ist KEIN Tab-Interface; design.md / T04 beschreiben Tab-Umbau
**Status:** ❌  
**Fundstelle:** `frontend/src/views/SettingsView.vue:1` (Gesamtstruktur), Z. 10–388 (`<form @submit.prevent="saveSettings" class="space-y-8">`), Z. 27 (Abschnitt „Stammdaten"), Z. 202 (Abschnitt „E-Mail-Konfiguration"), Z. 353 (`EmailTemplateEditor`-Komponente)  
**Befund:** `SettingsView.vue` ist ein einziges, scrollbares `<form>`-Element mit zwei Karten-Abschnitten — **kein Tab-Interface**. Das stimmt mit design.md Abschnitt 3.5 überein. Allerdings **schlussfolgert** design.md daraus, die Integration des Preise-Bereichs erfordere eine „minimale Tab-Umstrukturierung" (3 Tabs: Stammdaten / E-Mail / Preise). T04 beschreibt diesen Umbau ausführlich (`activeTab`, `v-show`, Action-Buttons per Tab). **Der User hat explizit klargestellt, dass kein Tab-Umbau gewünscht ist** — es soll ein einfaches Formular im bestehenden Design angelegt werden.

Bestehendes Karten-Muster:
- Wrapper: `bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden`
- Header: `<div class="bg-gray-50 px-6 py-4 border-b border-gray-200"><h2 class="text-xl font-semibold text-gray-900">…</h2></div>`
- Body: `<div class="p-6 space-y-6">…</div>`

**Konsequenz:** T04 muss vollständig umgeschrieben werden. Kein `activeTab`, kein `v-show` über bestehenden Abschnitten, kein Tab-Navigation-Element. Stattdessen: neuen Karten-Block **nach** dem letzten bestehenden Abschnitt (`EmailTemplateEditor`) und **vor** den Action-Buttons einfügen — oder **außerhalb** des `<form>`-Elements, da die Preis-CRUD eigene API-Calls verwendet und keinen `saveSettings()`-Submit benötigt. Letztes ist zu bevorzugen, um keine Regression im Save-Verhalten zu riskieren.

---

### B10 — `composables/`-Verzeichnis existiert nicht
**Status:** ⚠️  
**Fundstelle:** `frontend/src/` (Verzeichnis-Listing)  
**Befund:** `frontend/src/composables/` existiert noch nicht. Design-Dokument Risiko-Flag R5 und tasks.md T03 kennzeichnen das korrekt: „Verzeichnis `composables/` muss neu angelegt werden". Kein Befund-Fehler, nur Bestätigung der Annotation.  
**Konsequenz:** Keine Korrektur nötig.

---

### B11 — Heroicons installiert und Verwendungs-Muster
**Status:** ✅  
**Fundstelle:** `frontend/package.json:26` (`"@heroicons/vue": "^2.2.0"`), `frontend/src/views/HomeView.vue:156–164` (Import-Block)  
**Befund:** `@heroicons/vue` Version 2.2.x installiert. Import-Muster in HomeView: `import { AcademicCapIcon, BookOpenIcon, ... } from '@heroicons/vue/24/outline'`. Design-Annahme des `CurrencyEuroIcon`-Imports bestätigt.  
**Konsequenz:** Keine Änderung nötig.

---

### B12 — API-Client und `settings.ts` als Vorlage
**Status:** ✅  
**Fundstelle:** `frontend/src/api/client.ts:1–57`, `frontend/src/api/settings.ts:1–52`  
**Befund:** `apiClient` (Axios-Instanz) exportiert aus `client.ts`. Auth-Token wird via Request-Interceptor injiziert (Z. 44–50 in `client.ts`). `settings.ts` importiert `apiClient from './client'` und exportiert `settingsApi`-Objekt mit `async`-Methoden. Muster konsistent mit design.md Abschnitt 3.1.  
**Konsequenz:** Keine Änderung nötig.

---

### B13 — Vue-Router: Admin-Bereich und Settings-Route
**Status:** ✅  
**Fundstelle:** `frontend/src/router/index.ts:48–53` (`/app`-Wrapper), Z. 113–118 (Settings-Route)  
**Befund:** Admin-Routen unter `/app/` mit `meta: { requiresAuth: true }`. Settings-Route: `path: 'settings'`, `name: 'Settings'`, `meta: { requiresAdmin: true }`. Keine `/preise`-Route vorhanden (nicht nötig — Modal). Design sieht keine Router-Änderung für T03/T04 vor — korrekt.  
**Konsequenz:** Keine Änderung nötig.

---

### B14 — `destroy()` gibt `response()->json(null, 204)` zurück
**Status:** ✅  
**Fundstelle:** `backend/app/Http/Controllers/Api/CreditPackageController.php:118` (`return response()->json(null, 204);`)  
**Befund:** Muster bestätigt. Design-Annahme für `PricingItemController::destroy()` konsistent mit Vorlage.  
**Konsequenz:** Keine Änderung nötig.

---

## Korrekturen die im Design/Tasks vorgenommen werden müssen

### K01 — KRITISCH: T04 komplett umschreiben (kein Tab-Umbau)
**Betrifft:** `tasks.md` T04, `design.md` Abschnitt 3.5  
**Aktion:** T04-Beschreibung ersetzen. Anforderung laut User: neues Formular/Karten-Block im **gleichen Design** wie bestehende Abschnitte in `SettingsView.vue`, **ohne** Tab-Umstrukturierung.

Konkrete Implementierungs-Vorgabe für T04:
- Neuen `<div>`-Block **außerhalb** des `<form>`-Elements einfügen (nach `</form>`, aber innerhalb des äußeren `<div class="settings-view">`)
- Gleiche Karten-Struktur: `bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden` + Header `bg-gray-50 px-6 py-4 border-b border-gray-200` mit Titel „Preise"
- Laden via `onMounted(() => loadAll())` aus `usePricingItems`-Composable
- Tabelle + „Neuen Preis anlegen"-Button + `PricingItemForm`-Modal direkt im Block
- `SettingsController`-Submit-Flow bleibt unberührt, `activeTab`-Ref entfällt vollständig
- Akzeptanzkriterien in T04 anpassen: kein Kriterium für Tab-Navigation

### K02 — design.md 3.5 Einleitungssatz korrigieren
**Betrifft:** `design.md` Abschnitt 3.5  
**Aktion:** Satz „Um den neuen ‚Preise'-Bereich sauber zu integrieren, muss `SettingsView.vue` auf eine **Tab-Navigation** umgestellt werden." ersetzen durch: „Der neue ‚Preise'-Bereich wird als eigenständiger Karten-Block **außerhalb** des bestehenden `<form>`-Elements eingefügt — kein Tab-Umbau."

### K03 — T02 Hinweis zu `AuthorizesRequests`-Abweichung ergänzen
**Betrifft:** `tasks.md` T02 (Controller-Beschreibung)  
**Aktion:** Klarstellungs-Hinweis einfügen: „`CreditPackageController` ist nur als strukturelle Vorlage gemeint (Methoden-Signaturen, Return-Types). Den `AuthorizesRequests`-Trait und `$this->authorize()`-Policy-Calls **nicht** übernehmen — der Route-Level-`can:admin`-Schutz und `FormRequest::authorize()` sind ausreichend."

### K04 — design.md 2.4 Abweichung bei `authorize()` dokumentieren
**Betrifft:** `design.md` Abschnitt 2.4  
**Aktion:** Klarstellungs-Satz ergänzen: „Abweichend von `StoreCreditPackageRequest` wird Trainer-Berechtigung nicht gewährt — nur `can('admin')`. Preispflege ist ausschließlich Admin-Funktion."
