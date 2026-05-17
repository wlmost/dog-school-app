# Acceptance: trainer-authorization

**Architekt:** Architect Agent
**Datum:** 2026-05-17
**Status:** ✅ ACCEPTED

---

## Abnahme-Checkliste

| Punkt | Status | Anmerkung |
|-------|--------|-----------|
| Alle Tasks erledigt | ✅ | Alle 3 Task-Gruppen (1.1, 2.1–2.4, 3.1) mit `[x]` markiert |
| Route korrekt im can:admin-Block | ✅ | Separater `Route::middleware('can:admin')->group(...)` ohne `prefix('admin')` — URL `/api/v1/trainers` unverändert (api.php Z. 180–182) |
| Spec vollständig erfüllt | ✅ | Alle 3 Requirements abgedeckt — Delta bei Admin-DELETE (200 statt 204) ist Pre-existing Issue, kein Change-Befund |
| Reviewer-Befund behoben | ✅ | Magic-String-Befund (TESTING.md §3.1) behoben: `->admin()`, `->trainer()`, `->customer()` Factory States verwendet |
| Tests grün (18/18) | ✅ | 18 passed, 18 assertions, 0 fehlgeschlagen (test-report bestätigt) |
| Keine Regression (638 Tests) | ✅ | Volle Suite 638 passed / 2024 assertions — `AnamnesisResponsePdfTest`-OOM ist pre-existing, kein Change-Zusammenhang |
| Scope eingehalten | ✅ | Nur `routes/api.php` und `tests/Feature/TrainerApiTest.php` — kein Frontend, keine Migration, keine Policy-Änderung |

---

## Offene Punkte

### Delta: HTTP 200 statt 204 bei Admin-DELETE

`TrainerController::destroy()` gibt `response()->json(['message' => ...])` zurück (HTTP 200), nicht `response()->noContent()` (HTTP 204). Die Spec schreibt 204 vor (Requirement 1 / Admin-delete-Szenario), und `php-api-standards.instructions.md` bestätigt „204 No Content for successful DELETE operations".

**Bewertung:** Kein Merge-Blocker für diesen Change. Die Autorisierungslücke (OWASP A01) ist korrekt geschlossen. Das Response-Format-Delta ist ein Pre-existing Issue im Controller, das vor `trainer-authorization` bestand und dessen Behebung einen separaten Change rechtfertigt. Reviewer und Tester haben es explizit als außerhalb des Scopes eingestuft.

**Empfehlung:** Eigener Change `trainer-destroy-204` anlegen, der `TrainerController::destroy()` auf `response()->noContent()` umstellt und den Test von `assertOk()` auf `assertNoContent()` aktualisiert.

---

## Befund-Übersicht nach Schweregrad

| Befund | Quelle | Schwere | Erledigt |
|--------|--------|---------|---------|
| Magic Strings in beforeEach (§3.1) | Reviewer | Muss | ✅ |
| HTTP 200 vs 204 bei DELETE | Reviewer + Tester | Könnte (Pre-existing) | — (separater Change) |

---

## Fazit

Der Change löst die in Proposal und Spec beschriebene Autorisierungslücke (OWASP A01: Broken Access Control) korrekt und minimal: eine Route-Verschiebung in `api.php`, konsistent mit dem bestehenden `can:admin`-Muster für Settings und PricingItems. Die zugehörige Testsuite (18 Tests, 4 Rollen × 5 Actions) ist vollständig, spec-konform, TESTING.md-konform und grün. Alle Pflichtbefunde des Reviewers wurden vor der Testausführung behoben; die Regression-Suite bleibt unberührt.

Der Change ist merge-bereit. Das bekannte HTTP-200/204-Delta am DELETE-Endpoint ist als Pre-existing Issue dokumentiert und für einen Folge-Change vorgemerkt.

---

## Freigabe

✅ Freigegeben für `openspec archive` und PR-Erstellung.
