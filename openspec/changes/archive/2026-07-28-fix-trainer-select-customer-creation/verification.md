# Verification: fix-trainer-select-customer-creation

**Gesamtstatus:** ok

## Schritt 0: openspec validate

```
$ openspec validate fix-trainer-select-customer-creation
Change 'fix-trainer-select-customer-creation' is valid

$ openspec validate fix-trainer-select-customer-creation --strict
Change 'fix-trainer-select-customer-creation' is valid
```

Strukturell einwandfrei (auch unter `--strict`). Inhaltlicher Realitätsabgleich folgt.

## Bestätigt

### Backend — Root Cause
- `proposal.md` Z.11-13: "`CustomerFormModal.vue:338-345` (`loadTrainers()`) ruft `GET /api/v1/trainers` auf" → bestätigt, `frontend/src/components/CustomerFormModal.vue:338-345` ist exakt die `loadTrainers()`-Funktion mit `apiClient.get('/api/v1/trainers')`.
- `proposal.md` Z.13-14: "Route liegt in `backend/routes/api.php:193-196` hinter `can:admin`" → bestätigt, Z.193 Kommentar "Trainer Management (Admin only)", Z.194 `Route::middleware('can:admin')->group(...)`, Z.195 `Route::apiResource('trainers', TrainerController::class)`, Z.196 schließende Klammer.
- `proposal.md` Z.15-16: "Gate definiert in `AppServiceProvider.php:61-63` (`Gate::define('admin', fn ($user) => $user->isAdmin());`)" → bestätigt, `backend/app/Providers/AppServiceProvider.php:61-63`.
- `proposal.md` Z.18-20: Fehler wird in `CustomerFormModal.vue:342-343` nur mit `console.error(...)` geloggt → bestätigt, Z.343: `console.error('Error loading trainers:', err)`, kein weiterer Error-Handler.
- `proposal.md` Z.21-24: Vorauswahl-Logik `CustomerFormModal.vue:291-294` (`form.value.trainer_id = currentUser.value.id`, wenn Rolle `trainer`) → bestätigt, Z.291-294 exakt so.
- `proposal.md` Z.25-27: `CourseFormModal.vue:276-283` hat dieselbe Abhängigkeit von `GET /api/v1/trainers` → bestätigt, Z.276-283 ist `loadTrainers()` mit identischem `console.error`-only-Pattern.
- `proposal.md` Z.28-32: `UserResource::toArray()` (`UserResource.php:24-45`) liefert volle Profildaten inkl. `email`, `phone`, `mobilePhone`, `street`, `postalCode`, `city`, `country`, `qualifications`, `specializations` → bestätigt, `backend/app/Http/Resources/UserResource.php:24-45` exakt diese Felder.
- `proposal.md` Z.32/`design.md` Z.94-95: `TrainerApiTest.php:56-61` erwartet 403 für Trainer auf `GET /api/v1/trainers` → bestätigt, `backend/tests/Feature/TrainerApiTest.php:56-61` (`describe('Trainer-Rolle', ...)`, `it('erhält 403 beim auflisten von trainern', ...)`, `assertForbidden()`).
- `proposal.md` Z.34-36: Admin-Fall funktioniert bereits (`TrainerApiTest.php:17-22`) → bestätigt, `describe('Admin', ...)`, `it('listet alle trainer auf', ...)`, `assertOk()`.

### Design-Entscheidung 1: neue Route vor apiResource, Präzedenzfall
- `design.md` Z.74-76: Präzedenzfall `/customers/profile` vor `Route::apiResource('customers', ...)` in `api.php:100-101` → **bestätigt**, `backend/routes/api.php:100`: `Route::get('/customers/profile', [CustomerController::class, 'profile']);`, Z.101: `Route::apiResource('customers', CustomerController::class);`. Reihenfolge und Zeilennummern stimmen exakt.
- `design.md` Z.77-78: Platzierung "direkt vor dem bestehenden `can:admin`-Block für `trainers` (api.php:193-196), innerhalb der äußeren `auth:sanctum`-Gruppe (api.php:76)" → Block bei Z.193-196 bestätigt (s.o.); äußere `auth:sanctum`-Gruppe bei Z.76 nicht separat verifiziert, aber plausibel (alle geprüften Routen liegen im selben eingerückten Block).

### Design-Entscheidung 2: Gate `trainer`
- `design.md` Z.100-102 / proposal.md-Verweis: `Gate::define('trainer', ...)` (`AppServiceProvider.php:65-67`) existiert und deckt "Trainer oder Admin" ab → **bestätigt**, `backend/app/Providers/AppServiceProvider.php:65-67`: `Gate::define('trainer', function ($user) { return $user->isTrainer() || $user->isAdmin(); });`. Deckt exakt "Trainer ODER Admin" ab, nicht nur Trainer exklusiv.
- `design.md` Z.14: Gate `customer` deckt "Customer/Trainer/Admin" ab → bestätigt, `AppServiceProvider.php:69-71`: `isCustomer() || isTrainer() || isAdmin()`.

### Design-Entscheidung 3: TrainerOptionResource
- `design.md` Z.111-112: "analog zu den bestehenden domänenspezifischen Resources ... z. B. `CustomerResource`, `DogResource`" → bestätigt, beide Dateien existieren unter `backend/app/Http/Resources/`.
- Ziel-Pfad `backend/app/Http/Resources/TrainerOptionResource.php` existiert noch nicht → bestätigt (Verzeichnislisting enthält keine solche Datei), kein Namenskonflikt.
- `'fullName' => $this->full_name` ist ein valider Eloquent-Accessor → bestätigt, `backend/app/Models/User.php`: `getFullNameAttribute(): ?string` (Magic-Accessor `full_name`), Zeile ~123-129.
- Kein `#[\Override]`-Attribut, keine Property Hooks etc. im Codesnippet → bestätigt durch Lesen des Snippets in `design.md` Z.114-127, keine 8.3/8.4-Features enthalten.

### Design-Entscheidung 4: `TrainerController::options()`
- `design.md` Z.147-148: "Identische Sortier-/Filterlogik wie `TrainerController::index()` (api.php-Zeile 25, 37)" → **teilweise widerlegt (Zitierfehler)**, siehe unten unter "Widerlegt".
- Inhaltlich bestätigt: `TrainerController::index()` (`backend/app/Http/Controllers/Api/TrainerController.php:23-40`) nutzt `User::query()->where('role', 'trainer')` (Z.25) und `$query->orderBy('last_name')->orderBy('first_name')->get()` (Z.37) — identisch zur in `design.md` Z.137-141 vorgeschlagenen `options()`-Logik.
- `design.md` Z.21-23: `TrainerController::index()` "unterstützt einen optionalen Such-Parameter über `App\Helpers\DatabaseHelper::caseInsensitiveLike()`" → bestätigt, `TrainerController.php:31-33` sowie `backend/app/Helpers/DatabaseHelper.php:20` (`caseInsensitiveLike()` existiert).

### Design-Entscheidung 5: Frontend
- `design.md` Z.36-38: `CustomerFormModal.vue:240` importiert `handleApiError` bereits, aber nicht in `loadTrainers()` → bestätigt, Z.240: `import { handleApiError, showSuccess } from '@/utils/errorHandler'`; `loadTrainers()` (Z.338-345) nutzt es tatsächlich nicht, nur `console.error`.
- `design.md` Z.39-40: `CourseFormModal.vue:210` importiert `handleApiError` ebenfalls bereits, `loadTrainers()` nutzt es nicht → bestätigt, Z.210: `import { handleApiError, showSuccess, showWarning } from '@/utils/errorHandler'`; `loadTrainers()` (Z.276-283) nutzt nur `console.error`.
- `design.md` Z.33-35: `handleApiError` behandelt HTTP 403 explizit mit "Zugriff verweigert" → bestätigt, `frontend/src/utils/errorHandler.ts:40-44`.
- `design.md` Z.162-163: Response-Parsing `response.data.data || response.data` in `CustomerFormModal.vue:341` → bestätigt, Z.341 exakt: `trainers.value = response.data.data || response.data`.
- `design.md` Z.163: `response.data.data` in `CourseFormModal.vue:279` → bestätigt, Z.279 exakt: `trainers.value = response.data.data`.
- `design.md` Z.166-168 / `proposal.md` Z.57-61: `CourseFormModal.vue:45` verwendet `trainer.fullName || trainer.email` als Fallback → bestätigt, Z.45: `{{ trainer.fullName || trainer.email }}`. Nach Umstellung auf den reduzierten Endpoint (kein `email`-Feld mehr) würde dieser Ausdruck zu `trainer.fullName || undefined` degenerieren — der vom Architekten benannte Bugfix-Bedarf ist real.
- `design.md` Z.168 / `proposal.md` Z.61: Analoges bestehendes Muster in `CustomerFormModal.vue:106` → bestätigt, Z.106: `{{ trainer.fullName || \`${trainer.firstName} ${trainer.lastName}\` }}`.
- `design.md` Z.172-174: Vorauswahl-Logik `CustomerFormModal.vue:291-294` bleibt unverändert korrekt → bestätigt (s.o., bereits als Root-Cause-Beleg geprüft).

### Weitere Detailbelege
- `proposal.md` Z.88-91: "beide Komponenten haben bisher keine Vitest-Testdatei" → bestätigt, `frontend/src/components/` enthält keine `CustomerFormModal.test.ts` oder `CourseFormModal.test.ts`.
- `proposal.md` Z.98-99: "Bestehende Route/Response von `GET /api/v1/trainers` bleibt exakt wie zuvor" → plausibel, da `design.md`s Vorschlag die bestehende Route/den bestehenden Controller-Code (`index()`, `show()`, `store()`, `update()`, `destroy()`) unverändert lässt (kein Eingriff in bestehende Methoden im vorgeschlagenen Diff).
- Response-Envelope-Annahme (`design.md` Z.163-165): `TrainerOptionResource::collection()` liefert wie `UserResource::collection()` ein `{ data: [...] }`-Envelope → bestätigt indirekt: kein `JsonResource::withoutWrapping()`-Aufruf im Projekt gefunden (`grep` über `backend/app/Providers/`, `backend/bootstrap/` ergab 0 Treffer), Laravel-Standardverhalten (`AnonymousResourceCollection` wrapped standardmäßig in `data`) bleibt somit aktiv, konsistent mit dem bereits funktionierenden Envelope-Handling für die Admin-Rolle am bestehenden Endpoint.

## Widerlegt

- `design.md` Z.147-148: "Identische Sortier-/Filterlogik wie `TrainerController::index()` (**api.php**-Zeile 25, 37)" → Zitierfehler. Zeile 25 und 37 in `backend/routes/api.php` enthalten Route-Definitionen für Payment/Trainer-Management-Gruppierung, nicht die Query-Logik von `index()`. Die tatsächlich gemeinten Zeilen 25 (`$query = User::query()->where('role', 'trainer');`) und 37 (`$trainers = $query->orderBy('last_name')->orderBy('first_name')->get();`) befinden sich in `backend/app/Http/Controllers/Api/TrainerController.php`, nicht in `api.php`. Rein redaktioneller Fehler (Dateiname vertauscht), die inhaltliche Aussage selbst ist korrekt (siehe "Bestätigt" oben) und hat keine Auswirkung auf die Umsetzbarkeit.

## Nicht auffindbar

- Keine Behauptungen gefunden, die nicht anhand des Repos nachvollziehbar wären. Alle referenzierten Dateien, Zeilen, Gates, Methoden und Tests existieren wie beschrieben.

## Neue Elemente (Plausibilität)

- `tasks.md` T01: legt `backend/app/Http/Resources/TrainerOptionResource.php` an → Pfad existiert noch nicht (siehe oben), konsistent mit bestehenden domänenspezifischen Resources im selben Verzeichnis (`CustomerResource.php`, `DogResource.php`, `UserResource.php` u.a.). Kleine Stilabweichung: der Codevorschlag in `design.md` nutzt `final class`, während alle bestehenden Resources im Verzeichnis (`CustomerResource`, `DogResource`, `UserResource`) ohne `final`-Modifier deklariert sind — keine funktionale Auswirkung, nur ein Stilbruch, der dem Entwickler-Agenten auffallen könnte.
- `tasks.md` T01: neue Route `GET /api/v1/trainers/options` und neue Controller-Methode `TrainerController::options()` → Route-Name `trainers.options` kollidiert nicht mit vorhandenen Routennamen (kein `grep`-Treffer für `trainers.options` im Bestand). Methode `options()` existiert noch nicht in `TrainerController.php` (siehe vollständiger Datei-Inhalt oben, Methoden sind `index`, `store`, `show`, `update`, `destroy`).
- `tasks.md` T02/T03: neue Testdateien `CustomerFormModal.test.ts` / `CourseFormModal.test.ts` → beide existieren noch nicht (bestätigt oben), Pfad konsistent mit Vitest-Konvention (Test neben Komponente).

## Bekannter offener Punkt: "No tasks" in `openspec list`

Geprüft: `openspec list` zeigt tatsächlich `fix-trainer-select-customer-creation  No tasks  8m ago`. Das `## T01: ...`-Format ist jedoch **kein Sonderfall dieses Changes**, sondern bereits mehrfach in erfolgreich archivierten Changes verwendet worden, z. B. `openspec/changes/archive/2026-07-18-fix-dark-mode-coverage/tasks.md:36` (`## T01: Layout & geteilte Komponenten ...`) bis `:233` (`## T06: ...`) — dieser Change wurde bereits durch den vollen Workflow inkl. `openspec archive` geführt. Der CLI-Fortschrittszähler erkennt offenbar nur ein anderes Task-Muster (in `shared-hosting-installer/tasks.md` z. B. `## 1. Build Script Foundation` gefolgt von nicht eingerückten `- [x] 1.1 ...`-Zeilen), während `fix-trainer-select-customer-creation/tasks.md` Akzeptanzkriterien als **eingerückte** Checkboxen unter `- **Akzeptanzkriterien:**` führt (`grep -n "\[ \]"` zeigt ausschließlich Treffer mit führenden Leerzeichen, kein `^- [ ]` am Zeilenanfang). Inhaltlich ist `tasks.md` vollständig: 3 klar abgegrenzte Tasks (T01-T03) mit `Agent`, `Dateien`, `Abhängigkeiten`, `Beschreibung` und je 5-7 konkreten, prüfbaren Akzeptanzkriterien. Es handelt sich damit **nachweislich um ein rein kosmetisches CLI-Anzeigeproblem**, keine strukturelle Lücke in `tasks.md`.

## Empfehlung

Die Spec ist ungewöhnlich präzise mit dem echten Code verzahnt: praktisch jede Datei:Zeile-Referenz in `proposal.md` und `design.md` konnte 1:1 im Repository nachvollzogen werden, inklusive Detailaussagen wie der genauen Gate-Definition, dem bestehenden Import von `handleApiError`, den exakten Response-Parsing-Zeilen und dem als "Bugfix-Bedarf" markierten `trainer.email`-Fallback in `CourseFormModal.vue:45`. Der einzige Fund ist ein redaktioneller Zitierfehler in `design.md` (Z.147-148: falscher Dateiname in der Zeilenreferenz), der die Umsetzbarkeit nicht beeinträchtigt. Das "No tasks"-Verhalten von `openspec list` ist bestätigt kosmetisch und deckt sich mit einem bereits erfolgreich durch den gesamten Workflow gelaufenen Präzedenzfall. **Der Change ist bereit für User-Gate 1.**
