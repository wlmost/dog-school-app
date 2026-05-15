# Review: T04 — CourseController erweitern

**Reviewer:** reviewer-agent
**Datum:** 2026-05-15
**Status:** APPROVED

---

## Re-Review: 2026-05-15

**Status:** APPROVED

### Blocker-Checks

- **Blocker 1** ✅ — `destroySession()`: `$this->authorize('update', $course)` ist das **erste Statement** der Methode (vor dem Scope-Check und vor dem Delete). `backend/app/Http/Controllers/Api/CourseController.php`, ca. Zeile 349.

- **Blocker 2** ✅ — Trainer-Ownership-Check:
  - `storeSession()`: `$this->authorize('update', $course)` als erstes Statement vorhanden (ca. Zeile 248).
  - `updateSession()`: `$this->authorize('update', $course)` als erstes Statement vorhanden (ca. Zeile 295).
  `CoursePolicy::update()` prüft Trainer-Ownership korrekt — Trainer kann keine Kurse anderer Trainer mehr mutieren.

- **Blocker 3** ✅ — Scope-Check (Option B aus dem ursprünglichen Review):
  - `updateSession()`: `if ($session->course_id \!== $course->id) abort(404);` nach dem `authorize()`-Aufruf vorhanden (ca. Zeile 299).
  - `destroySession()`: `if ($session->course_id \!== $course->id) abort(404);` nach dem `authorize()`-Aufruf vorhanden (ca. Zeile 353).
  Die Reihenfolge ist korrekt: authorize zuerst (keine Informationsleckage über Existenz einer Session an Unautorisierte), dann Scope-Check.

- **Blocker 4** ✅ — `publicShow()` gibt für `trainer` ausschließlich `id`, `firstName`, `lastName` zurück (ca. Zeilen 398–402). Keine E-Mail, kein Telefon, keine Adresse. `UserResource` wird in dieser Methode nicht mehr verwendet.

### ACs aus tasks.md T04 (Vollständigkeit)

| AC | Status | Nachweis |
|----|--------|---------|
| `POST /courses` mit `recurrence` erstellt Sessions | ✅ | `store()` ruft `generateFromRecurrence()` + `syncSessions()` auf |
| `POST /courses` ohne `sessionsMode` erstellt ohne Sessions | ✅ | Null-Guard auf `getSessionsPayload()` / `getRecurrenceRule()` |
| `POST /courses/{course}/sessions` → 201 | ✅ | `storeSession()` gibt `->setStatusCode(201)` zurück |
| `PUT /{course}/sessions/{session}` mit Buchungen → 200 + warnings | ✅ | `updateSession()` prüft `$bookingCount > 0`, bettet `warnings` ein |
| `DELETE /{course}/sessions/{session}` mit Buchungen → 200 + warnings | ✅ | `destroySession()` prüft `$bookingCount > 0`, gibt `deleted: true, warnings: [...]` zurück |
| `DELETE /{course}/sessions/{session}` ohne Buchungen → 204 | ✅ | `destroySession()` gibt `response()->json(null, 204)` zurück |
| `GET /public/courses/{course}` ohne Auth erreichbar | ✅ | Route außerhalb `auth:sanctum`, mit `throttle:60,1` |
| `GET /public/courses/{course}` nicht-existierend → 404 | ✅ | Route Model Binding liefert automatisch 404 |

### Neue Befunde

Keine neuen Blocker. Die zuvor als **Sollte** eingestuften Non-Blocker-Befunde (manuelles `->resolve()` in `updateSession()`, fehlendes `$session->fresh()` in `storeSession()`) bleiben bestehen — sie verhindern die Abnahme nicht.

### Fazit

Alle vier Blocker sind korrekt und vollständig behoben. Die Reihenfolge der Sicherheitschecks (`authorize` vor Scope-Check) ist semantisch korrekt. Die PII-Reduktion in `publicShow()` ist vollständig. **T04 ist abgenommen.**

---

## Befunde (Erstreviews 2026-05-15)

### 🔴 MUSS (Blocker) — alle behoben

---

**[Sicherheit / OWASP A01] `destroySession()` hat keinerlei Autorisierungsprüfung**

`backend/app/Http/Controllers/Api/CourseController.php`, Methode `destroySession()` (ca. Zeile 340 ff.)

Im Gegensatz zu `storeSession` und `updateSession` wird `destroySession` ohne `FormRequest` und ohne `$this->authorize()`-Aufruf implementiert. Da die Route hinter `auth:sanctum` liegt, kann jeder authentifizierte User — inklusive Kunden — jede `TrainingSession` löschen, solange er eine gültige Kurs-ID und Session-ID kennt (IDs sind sequenziell und durch Enumeration erratbar).

**Angriffsvektor:** Authentifizierter Kunde sendet `DELETE /api/v1/courses/5/sessions/42` → Session wird gelöscht, alle Buchungen werden via Cascade vernichtet. Kein einziger Check wird ausgelöst.

**Behebung:** Entweder `$this->authorize('update', $course)` (CoursePolicy::update prüft Trainer-Ownership bereits korrekt) oder eine `DestroyCourseSessionRequest` analog zu den anderen Session-Requests erstellen und dort Ownership prüfen.

---

**[Sicherheit / OWASP A01] `storeSession` und `updateSession`: Trainer kann Kurse anderer Trainer mutieren**

`backend/app/Http/Requests/StoreCourseSessionRequest.php`, Zeile 22
`backend/app/Http/Requests/UpdateCourseSessionRequest.php`, Zeile 22

Beide `authorize()`-Methoden prüfen nur `$this->user()?->isAdminOrTrainer()`. Die `CoursePolicy` enthält eine funktionierende Ownership-Prüfung in `update()` (`$course->trainer_id === $user->id`), die hier aber nie aufgerufen wird. Trainer B kann damit Sessions für Trainer A's Kurse anlegen und bearbeiten.

**Angriffsvektor:** Trainer B kennt die ID eines Kurses von Trainer A (z.B. durch die `GET /api/v1/courses`-Liste, die alle Kurse liefert). Trainer B sendet `POST /api/v1/courses/{A_id}/sessions` → Session wird in Trainer A's Kurs eingetragen.

**Behebung:** In `storeSession()` und `updateSession()` im Controller `$this->authorize('update', $course)` aufrufen, bevor die Session-Logik ausgeführt wird. Alternativ: `authorize()`-Methode in den FormRequests auf `$this->user()->can('update', $this->route('course'))` erweitern (Route-Model-Binding liefert `$course` bereits).

---

**[Sicherheit / OWASP A01] Route Model Binding ohne Scope: Session-ID nicht gegen Kurs-ID geprüft**

`backend/routes/api.php`, Zeilen 131–132
`backend/app/Http/Controllers/Api/CourseController.php`, Methoden `updateSession()` und `destroySession()`

Laravel bindet `{session}` unabhängig von `{course}` (kein `->scopeBindings()`, keine Dot-Notation, kein manueller Check im Controller). Jemand kann `PUT /api/v1/courses/1/sessions/99` aufrufen, wobei Session 99 zu Kurs 2 gehört — der Controller aktualisiert Session 99 trotzdem, da nur `$session` gebunden wird, nicht `$session` gefiltert nach `course_id`.

**Angriffsvektor:** Trainer X hat Zugriff auf Kurs Z (sein eigener). Er kennt die Session-ID einer Session aus Kurs Y (fremder Kurs). Er sendet `PUT /api/v1/courses/{Z_id}/sessions/{Y_session_id}` → Session aus Kurs Y wird aktualisiert (Authcheck schaut nur auf Kurs Z).

**Behebung (eine von zwei Optionen):**
- Option A: `->scopeBindings()` auf die Route-Gruppe oder die einzelnen Routen anwenden (`Route::put(...)->scopeBindings()`). Laravel schaut dann, ob die Session zum Kurs gehört (via `course_id`-FK) und gibt sonst 404.
- Option B: Manuellen Check am Anfang von `updateSession()` und `destroySession()`: `if ($session->course_id \!== $course->id) abort(404);`

---

**[Sicherheit / OWASP A03 — Sensitive Data Exposure] `publicShow()` exponiert Trainer-PII ohne Authentifizierung**

`backend/app/Http/Controllers/Api/CourseController.php`, Methode `publicShow()` (ca. Zeile 375 ff.)
`backend/app/Http/Resources/UserResource.php`, Zeilen 29–43

`publicShow()` lädt den `trainer` via `CourseResource`, der wiederum `UserResource` verwendet. `UserResource` gibt `email`, `phone`, `mobilePhone`, `street`, `postalCode`, `city`, `country` — also die vollständige Kontaktadresse des Trainers — im JSON zurück. Dieser Endpoint ist unauthentifiziert erreichbar.

**Angriffsvektor:** Anonymer Scraper sendet iterativ `GET /api/v1/public/courses/1`, `.../2`, `.../3` etc. und sammelt Name, E-Mail, Telefon und Adresse aller Trainer. Rate Limit (60/min) verlangsamt dies auf ca. 60 Datensätze/Minute — bei kleinen Hundeschulen mit wenigen Kursen ist die gesamte Trainer-PII in Sekunden extrahiert.

**DSGVO-Relevanz:** Die gezielte Veröffentlichung vollständiger Kontaktdaten inkl. E-Mail über einen öffentlichen Endpoint ohne Authentifizierung stellt eine unzulässige Verarbeitung im Sinne von Art. 5(1)(f) DSGVO dar.

**Behebung:** `publicShow()` darf `trainer` entweder gar nicht laden oder muss eine reduzierte Projektion verwenden (z.B. nur `firstName`, `lastName` — keine E-Mail, kein Telefon, keine Adresse). Sauberste Lösung: eigene `PublicTrainerResource` mit `firstName`, `lastName` und optional `qualifications`.

---

### 🟡 SOLLTE (Non-Blocker)

---

**[Konsistenz] `updateSession()` baut JSON-Antwort manuell statt über Resource-API**

`backend/app/Http/Controllers/Api/CourseController.php`, ca. Zeile 310–325

```php
$response = ['data' => (new TrainingSessionResource($session->fresh()))->resolve()];
```

`->resolve()` extrahiert das Array aus der Resource und umgeht dabei den vollständigen Resource-Response-Stack (inkl. `with()`, `additional()`, Response-Wrapper). Das weicht vom Muster aller anderen Controller-Methoden ab, die entweder `(new Resource(...))->response()->setStatusCode(...)` oder `Resource::collection(...)` verwenden.

Wenn `TrainingSessionResource` zukünftig `with()` oder `additional()` nutzt, fehlen diese Daten in `updateSession()`-Antworten. Der `warnings`-Key liegt zudem auf `root`-Ebene (`{ data: ..., warnings: ... }`), während `store()`/`update()` `meta.warnings` verwenden — diese Asymmetrie ist so im Design spezifiziert, aber erhöht die Frontend-Komplexität.

**Empfehlung:** Entweder manuell beibehalten und dies im Code kommentieren, oder — falls das Verhalten vereinheitlicht werden soll — Rücksprache mit dem Architekten über Response-Shape-Konsistenz halten.

---

**[Korrektheit] `storeSession()`: `$session->fresh()` fehlt in der Response**

`backend/app/Http/Controllers/Api/CourseController.php`, ca. Zeile 270

```php
return (new TrainingSessionResource($session))
    ->response()
    ->setStatusCode(201);
```

`updateSession()` ruft `$session->fresh()` auf — `storeSession()` gibt das direkt aus `TrainingSession::create()` zurück. Berechnete Attribute (z.B. `available_spots`, `isPast()`, `duration`), die auf DB-seitigen Defaults basieren oder im Accessor berechnet werden, könnten von der tatsächlichen DB-Repräsentation abweichen.

**Empfehlung:** `new TrainingSessionResource($session->fresh())` für Konsistenz.

---

### 🟢 OK

- **`declare(strict_types=1)`** in allen neuen Dateien gesetzt.
- **`DB::transaction()` in `store()` und `update()`** korrekt implementiert; `$warnings` als Referenz (`&$warnings`) korrekt in die Closure gebunden.
- **`normalizeSessionKeys()`** (camelCase→snake_case) und **`camelizeRuleKeys()`** (snake_case→camelCase) sauber getrennt und gut dokumentiert — Abweichung von der Spec ist korrekt begründet (Notes Abschnitt 2).
- **`trainer_id`-Injection** über `array_merge` nach `normalizeSessionKeys()` — saubere Lösung für den fehlenden Spec-Aspekt (Notes Abschnitt 3).
- **Abwärtskompatibilität** in `store()` und `update()`: ohne `sessionsMode` wird `syncSessions()` nicht aufgerufen — Spec-Anforderung korrekt erfüllt.
- **`destroySession()`-Logik**: Booking-Count vor Delete, Response-Format korrekt (`deleted: true, warnings: [...]` bei Buchungen, 204 ohne).
- **`publicShow()` — 404-Verhalten**: Route Model Binding liefert automatisch 404 bei nicht-existierendem Kurs — AC ist erfüllt.
- **`publicShow()` — Session-Sortierung**: `orderBy('session_date')` korrekt.
- **Öffentliche Route** korrekt außerhalb `auth:sanctum`, mit `throttle:60,1`.
- **Session-Routen** korrekt innerhalb `auth:sanctum`.
- **Dependency Injection** von `CourseSessionService` im Konstruktor — korrekt, kein `new CourseSessionService()`.
- **PHP-Kompatibilität (8.2)**: Keine 8.3/8.4-Features erkennbar; `Str::snake()`, `Str::camel()` sind 8.2-kompatibel.
- **Mass-Assignment in `storeSession()`**: explizites Key-Mapping (`session_date`, `trainer_id`, etc.) — kein blindes `$request->all()` verwendet.
- **DB-Portabilität**: Kein raw SQL erkennbar; alle Queries über Eloquent.

---

## Fazit

Vier Blocker verhindern die Abnahme. Drei davon betreffen dieselbe Klasse von Problemen (fehlende/unvollständige Autorisierungsprüfungen), ein Blocker betrifft unbeabsichtigte PII-Exposition im neuen öffentlichen Endpoint. Die Business-Logik (Transaktionen, Session-Sync, Warnings-Format, Route-Struktur) ist korrekt und gut implementiert.

**Pflicht vor Re-Review:**
1. `destroySession()` — Autorisierung hinzufügen (min. `$this->authorize('update', $course)`)
2. `storeSession()` / `updateSession()` — Kurs-Ownership-Check in Controller oder FormRequest ergänzen
3. `updateSession()` / `destroySession()` — Route-Scope-Binding aktivieren (Option A oder B aus Befund 3)
4. `publicShow()` — Trainer-Felder auf öffentlich sichtbare Felder einschränken (Name, ggf. Qualifikationen; keine E-Mail/Telefon/Adresse)
