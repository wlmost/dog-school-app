# Test-Report: T01

**Status:** alle-gruen

## Hinzugefügte / geänderte Tests

- `backend/tests/Feature/SettingsValidationTest.php`: 6 vorgefundene Cases + 1 neu ergänzt

### Ergänzter Test (Coverage-Lücke aus Spec)

Der ursprüngliche Teststand deckte `image/x-icon` ab, aber nicht `image/vnd.microsoft.icon`.
Die Spec-Zeile "MIME-Typ `image/x-icon` oder `image/vnd.microsoft.icon`" erforderte beide Varianten.
Ergänzt:
- `akzeptiert eine ico-datei mit mime-typ image/vnd.microsoft.icon als company_favicon`

## Akzeptanzkriterien-Abdeckung

- [x] `company_favicon` hat Regel `file` statt `image` — geprüft in `UpdateSettingsRequest.php` Zeile 46 (Code-Inspektion)
- [x] `company_logo` behält `image` — getestet in `SettingsValidationTest::akzeptiert weiterhin eine png-datei als company_logo`
- [x] ICO-Upload (`image/x-icon`) wird akzeptiert — getestet in `SettingsValidationTest::akzeptiert eine ico-datei als company_favicon`
- [x] ICO-Upload (`image/vnd.microsoft.icon`) wird akzeptiert — getestet in `SettingsValidationTest::akzeptiert eine ico-datei mit mime-typ image/vnd.microsoft.icon als company_favicon` (neu ergänzt)
- [x] PNG-Upload wird akzeptiert — getestet in `SettingsValidationTest::akzeptiert eine png-datei als company_favicon`
- [x] EXE-Upload wird abgelehnt (422) — getestet in `SettingsValidationTest::weist eine exe-datei als company_favicon zurück`
- [x] Datei > 512 KB wird abgelehnt (422) — getestet in `SettingsValidationTest::weist eine datei über 512 kb als company_favicon zurück`
- [x] Fehlendes Feld löst keinen Fehler aus (`sometimes`) — getestet in `SettingsValidationTest::verursacht keinen validierungsfehler wenn company_favicon nicht gesendet wird`

## Konventions-Prüfung (TESTING.md)

| Punkt | Status | Anmerkung |
|-------|--------|-----------|
| `it()` statt `test()` | ok | alle 7 Tests verwenden `it()` |
| `declare(strict_types=1)` | ok | Zeile 3 |
| `uses(RefreshDatabase::class)` | ok | Zeile 10 |
| `uses()->group(...)` mit mind. 2 Einträgen | ok | `'api', 'setting'` |
| Factory-States (kein Magic String) | ok | `User::factory()->admin()->create()` |
| HTTP-Assertions Laravel-Style | ok | `->assertOk()`, `->assertUnprocessable()`, `->assertJsonValidationErrors()` |
| Keine `expect()` für HTTP-Responses | ok | nicht verwendet |
| Gruppen-Pfad-Konformität | Abweichung dokumentiert | Datei liegt in `tests/Feature/`, nicht `tests/Feature/Api/` — folgt bestehendem Muster von `CourseRequestValidationTest.php` (notes.md, Abschnitt "Annahmen") |

## Ausführungs-Ergebnis

```
   PASS  Tests\Feature\SettingsValidationTest
  ✓ it akzeptiert eine ico-datei als company_favicon                     0.21s
  ✓ it akzeptiert eine ico-datei mit mime-typ image/vnd.microsoft.icon…  0.02s
  ✓ it akzeptiert eine png-datei als company_favicon                     0.02s
  ✓ it weist eine exe-datei als company_favicon zurück                   0.02s
  ✓ it weist eine datei über 512 kb als company_favicon zurück           0.02s
  ✓ it verursacht keinen validierungsfehler wenn company_favicon nicht…  0.02s
  ✓ it akzeptiert weiterhin eine png-datei als company_logo              0.02s

  Tests:    7 passed (11 assertions)
  Duration: 0.54s

Gesamtsuite (zur Regression-Prüfung):
  Tests:    658 passed (2046 assertions)
  Duration: 24.72s
```

## Fehler

Keine.
