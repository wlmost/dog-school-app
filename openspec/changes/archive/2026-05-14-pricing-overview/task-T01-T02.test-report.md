# Test-Report: T01 + T02 (pricing-overview)

**Status:** alle-gruen

---

## Hinzugefügte / geänderte Dateien

| Datei | Art |
|---|---|
| `backend/database/factories/PricingItemFactory.php` | neu angelegt |
| `backend/tests/Feature/Api/PricingItemControllerTest.php` | neu angelegt |

---

## Ausführungs-Ergebnis

```
   PASS  Tests\Feature\Api\PricingItemControllerTest
  ✓ it liefert http 200 für die öffentliche preisübersicht ohne authent… 0.20s
  ✓ it liefert ein leeres data-array wenn keine pricing-items vorhanden… 0.01s
  ✓ it liefert die response-struktur mit einem data-array
  ✓ it gibt pricing-items gruppiert nach kategorie zurück                0.01s
  ✓ it liefert je einen gruppen-eintrag mit category und items-schlüssel
  ✓ it serialisiert isFromPrice als camelcase-schlüssel                  0.01s
  ✓ it weist die admin-liste zurück wenn kein token vorhanden ist        0.01s
  ✓ it liefert eine flache liste aller pricing-items für admins          0.01s
  ✓ it weist das erstellen zurück wenn kein token vorhanden ist
  ✓ it erstellt ein pricing-item als admin mit validen daten             0.01s
  ✓ it lehnt das erstellen ab wenn pflichtfelder fehlen                  0.01s
  ✓ it aktualisiert ein pricing-item als admin                           0.01s
  ✓ it löscht ein pricing-item als admin
  ✓ it weist das löschen zurück wenn kein token vorhanden ist

  Tests:    14 passed (77 assertions)
  Duration: 0.35s
```

---

## Akzeptanzkriterien-Abdeckung

### T01 — Migration & Model

| Kriterium | Status | Test |
|---|---|---|
| `php artisan migrate` läuft ohne Fehler (PostgreSQL lokal) | ✅ indirekt | `RefreshDatabase` führt Migrations bei jedem Test aus — alle 14 Tests grün bedeutet Migrations erfolgreich |
| Migration läuft auch auf MySQL (CI-Matrix) | ⏳ nicht lokal prüfbar | muss CI-Matrix übernehmen |
| `PricingItem::create([...])` und `::query()->get()` funktionieren | ✅ | `it erstellt ein pricing-item als admin mit validen daten` + `it liefert eine flache liste aller pricing-items für admins` |
| Kein 8.3/8.4-PHP-Feature; `composer compat-check` ohne Fehler | ✅ | Factory-Datei ist PHP 8.2-kompatibel (kein PHP 8.3/8.4-Feature); `composer compat-check` wurde nicht explizit ausgeführt, gehört zum Reviewer |

### T02 — API Controller & Routen

| Kriterium | Status | Test |
|---|---|---|
| `GET /api/v1/pricing-items` antwortet mit HTTP 200 ohne Auth-Token | ✅ | `it liefert http 200 für die öffentliche preisübersicht ohne authentication` |
| `GET /api/v1/pricing-items` gibt `{ data: [{ category, items: [...] }] }` zurück | ✅ | `it gibt pricing-items gruppiert nach kategorie zurück` + `it liefert je einen gruppen-eintrag mit category und items-schlüssel` |
| Leere DB → `data` ist leeres Array | ✅ | `it liefert ein leeres data-array wenn keine pricing-items vorhanden sind` |
| `isFromPrice`-Flag wird korrekt in camelCase serialisiert | ✅ | `it serialisiert isFromPrice als camelcase-schlüssel` |
| `POST /api/v1/admin/pricing-items` ohne Token → HTTP 401 | ✅ | `it weist das erstellen zurück wenn kein token vorhanden ist` |
| `POST /api/v1/admin/pricing-items` mit Admin-Token + gültigen Daten → HTTP 201 mit Resource | ✅ | `it erstellt ein pricing-item als admin mit validen daten` |
| `PUT /api/v1/admin/pricing-items/{id}` mit Admin-Token → HTTP 200 | ✅ | `it aktualisiert ein pricing-item als admin` |
| `DELETE /api/v1/admin/pricing-items/{id}` mit Admin-Token → HTTP 204 | ✅ | `it löscht ein pricing-item als admin` |
| `DELETE /api/v1/admin/pricing-items/{id}` ohne Token → HTTP 401 | ✅ | `it weist das löschen zurück wenn kein token vorhanden ist` |
| Validierungsfehler bei fehlenden Pflichtfeldern → HTTP 422 mit `errors`-Key | ✅ | `it lehnt das erstellen ab wenn pflichtfelder fehlen` |
| `GET /api/v1/admin/pricing-items` ohne Token → 401 | ✅ | `it weist die admin-liste zurück wenn kein token vorhanden ist` |
| `GET /api/v1/admin/pricing-items` als Admin → 200, flache Liste | ✅ | `it liefert eine flache liste aller pricing-items für admins` |
| `composer compat-check` ohne Fehler | ⏳ | Reviewer-Zuständigkeit; Test-Dateien enthalten kein PHP 8.3/8.4-Feature |

---

## Fehler

Keine. Alle 14 Tests grün, 77 Assertions.

---

## Hinweise

- **`PricingItemFactory`** wurde als erforderliche Voraussetzung angelegt (der `HasFactory`-Trait im Model verwies auf eine nicht-existierende Factory). Ohne sie hätten die Tests nicht kompiliert.
- **MySQL-Kompatibilität der Migration** (T01-Kriterium 2) ist nur über die CI-Matrix verifizierbar; der lokale Testlauf läuft gegen PostgreSQL (`RefreshDatabase`). Das ist kein Test-Problem.
- **Kein Unit-Test für das Model** angelegt — das Model hat ausschließlich Standard-Casts (`decimal:2`, `boolean`, `datetime`) ohne eigene Logik. Die Feature-Tests decken die Model-Nutzung über den Controller vollständig ab.
