# Tasks für trainer-authorization

**Agent:** `dev-php`

## 1. Route Absicherung

- [x] 1.1 `routes/api.php`: `Route::apiResource('trainers', TrainerController::class)` in den `can:admin`-Middleware-Block verschieben (Konsistenz mit Settings/PricingItem-Routes)

## 2. Feature-Tests

- [x] 2.1 `tests/Feature/TrainerApiTest.php` anlegen: Admin-Zugriff auf alle 5 CRUD-Actions (index, show, store, update, destroy) → 200/201/204
- [x] 2.2 Trainer-Rolle auf allen 5 Actions → 403 testen
- [x] 2.3 Customer-Rolle auf allen 5 Actions → 403 testen
- [x] 2.4 Unauthenticated auf index, store, destroy → 401 testen

## 3. Regression

- [x] 3.1 Bestehende Test-Suite (`composer test`) lokal durchlaufen lassen — alle Tests müssen grün bleiben
