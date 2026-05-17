# Dev Notes: trainer-authorization

**Agent:** dev-php  
**Datum:** 2026-05-17  
**Status:** ✅ alle Tasks erledigt

---

## Änderungen

### Task 1.1 — `backend/routes/api.php`

- **Entfernt:** `Route::apiResource('trainers', TrainerController::class)` aus dem allgemeinen `auth:sanctum`-Block (war unter dem Kommentar `// Trainer Management` ohne weitere Einschränkung)
- **Hinzugefügt:** Neuer `can:admin`-Block am Ende des `auth:sanctum`-Blocks (direkt vor dem Settings-Block):

```php
// Trainer Management (Admin only)
Route::middleware('can:admin')->group(function () {
    Route::apiResource('trainers', TrainerController::class);
});
```

- **Kein** `Route::prefix('admin')` hinzugefügt — URL bleibt `/api/v1/trainers` (analog zum Settings-Block, nicht zum PricingItem-Block)
- Skeptic-Befund #1 beachtet: bewusst den Settings-Block als Vorlage gewählt, nicht den PricingItem-Block

### Tasks 2.1–2.4 — `backend/tests/Feature/TrainerApiTest.php` (neu)

Neue Datei mit 18 Tests in 4 `describe`-Blöcken:

| Gruppe | Tests | Erwartung |
|--------|-------|-----------|
| Admin | index, store, show, update, destroy | 200 / 201 / 200 / 200 / 200 |
| Trainer-Rolle | alle 5 Actions | 403 |
| Customer-Rolle | alle 5 Actions | 403 |
| Unauthenticated | index, store, destroy | 401 |

**Anmerkung:** `destroy` gibt HTTP 200 (mit JSON-Body) zurück, nicht 204 — entsprechend im Test `assertOk()` statt `assertNoContent()`. Der Controller (`TrainerController::destroy()`) hat kein `response()->noContent()`, sondern `response()->json(['message' => ...])`.

**Skeptic-Befund #5 beachtet:** `show`, `update`, `destroy` im Controller haben intern `abort_if($trainer->role !== 'trainer', 404)`. `$this->trainer` aus `beforeEach` hat `role = 'trainer'`, wird für diese Tests als Route-Parameter verwendet.

**Skeptic-Befund #4 beachtet:** `store` dispatcht `UserRegistered`-Event. Da `MAIL_MAILER=array` und `QUEUE_CONNECTION=sync` in `phpunit.xml` konfiguriert sind, läuft der Listener (SendWelcomeEmail) synchron mit In-Memory-Mail-Transport — kein Event::fake() nötig.

---

## Test-Ergebnisse

### TrainerApiTest (isoliert)

```
Tests:    18 passed (18 assertions)
Duration: 0.26s
```

### Volle Test-Suite (Regression)

```
Tests:    638 passed (2024 assertions)
Duration: 9.50s
```

**Nicht mitgezählt:** `AnamnesisResponsePdfTest` — pre-existing OOM-Fehler in dompdf (128 MB Limit überschritten). Diese Tests crashed bereits vor diesem Change. Kein Zusammenhang mit trainer-authorization.

---

## Offene Punkte

- Der `AnamnesisResponsePdfTest`-OOM-Fehler ist ein separates Problem (dompdf Memory Limit). Sollte als eigener Change behoben werden.
