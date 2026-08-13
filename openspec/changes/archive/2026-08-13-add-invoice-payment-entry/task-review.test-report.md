# Test-Report: Vollständigkeitsprüfung `add-invoice-payment-entry` (Change 3/4)

**Status:** alle-gruen

## Vorgehen

Diff `feature/add-invoice-payment-entry` vs. `main` gesichtet (Branch hat
keine eigenen Commits — alle Änderungen liegen als uncommitted Working-Tree-
Diff/Untracked-Files vor, `git diff --stat main` zeigt 17 geänderte Dateien +
mehrere neue Dateien inkl. `backend/tests/Concurrency/`). Alle acht
`task-T0X.notes.md` sowie `proposal.md`/`design.md`/`tasks.md`/`TESTING.md`
gelesen. Bereits vorhandene Tests (`InvoicePaymentRecorderTest.php` 5 Tests,
`InvoicePaymentRecorderConcurrencyTest.php`, `InvoicePaymentApiTest.php`
8 Tests, `PaymentApiTest.php` angepasst, `InvoicePaymentDialog.test.ts`
9 Tests, `InvoicesView.test.ts`/`InvoiceDetailModal.test.ts` erweitert)
gegen die sechs benannten Lücken geprüft. Kein Produktivcode geändert.

## Hinzugefügte Tests

- `backend/tests/Feature/Api/InvoicePaymentApiTest.php`: 3 neue Cases
  (Überzahlungs-Grenzfall +0,01 €, Erfolgsfall `reminded`, Erfolgsfall
  `overdue`) — Datei jetzt 11 Tests.
- `backend/tests/Feature/Domain/Payment/InvoicePaymentRecorderTest.php`:
  2 neue Cases (3 aufeinanderfolgende Teilzahlungen mit Zwischen-Assertions
  auf `remaining_balance`/`status` nach jedem Schritt; Idempotenz von
  `completeExisting()` bei bereits `completed`-Zahlung) — Datei jetzt
  7 Tests.
- `frontend/src/components/InvoicePaymentDialog.test.ts`: 1 neuer Case
  (Konsistenz der 5 Zahlungsart-Optionen mit `StorePaymentRequest`-Enum) —
  Datei jetzt 10 Tests.

## Prüfung der sechs benannten Lücken

1. **Überzahlung-Grenzfälle:** Exakter Restbetrag war bereits abgedeckt
   (`InvoicePaymentApiTest.php::it akzeptiert eine zahlung exakt in höhe
   des restbetrags…`). Ergänzt: `+0,01 €` über Restbetrag → 422 mit
   `assertDatabaseMissing()`-Beleg, dass kein Payment-Datensatz entsteht.
2. **Mehrere Teilzahlungen in Folge:** Bestehender Test prüfte nur
   Zwischenstatus nach der ersten von zwei Zahlungen. Neuer Test mit 3
   Teilzahlungen (30 €, 25 €, 45 € bei 100 € Gesamtbetrag) prüft nach
   **jeder** Zahlung sowohl `status` als auch `remaining_balance` (70,00 €
   → 45,00 € → 0,00 €/`paid`).
3. **`completeExisting()` bei bereits abgeschlossener Zahlung:** War auf
   Controller-Ebene indirekt getestet (`PaymentApiTest.php::'cannot mark
   already completed payment'`, verhindert den Aufruf durch einen
   Guard-Check *vor* `completeExisting()`). Neuer Domain-Test ruft den
   Service jetzt direkt für eine bereits `completed`-Zahlung auf und
   belegt Idempotenz (Summe bleibt 100,00 €, genau 1 Payment-Datensatz,
   `status`/`paid_date` unverändert korrekt).
4. **Frontend 422-Fehlerpfad mit spezifischer Nachricht:** Bereits
   abgedeckt (`InvoicesView.test.ts::'zeigt bei einer 422-Ablehnung …'`)
   — prüft `handleApiError` wird mit dem Backend-Fehlerobjekt inkl. der
   konkreten Überzahlungs-Nachricht aufgerufen, exakt analog zum
   502-Message-Muster aus Change 2. Keine Ergänzung nötig.
5. **`PAYABLE_STATUSES`-Konsistenz:** Bislang nur `sent`-Erfolgsfall
   getestet. 2 neue API-Tests belegen, dass Zahlungen für `reminded`- und
   `overdue`-Rechnungen ebenfalls akzeptiert werden (inkl. Teilzahlung bei
   `overdue`, die den Status bewusst nicht auf `paid` springen lässt, da
   Restbetrag > 0 bleibt).
6. **Zahlungsart-Werte Frontend/Backend:** Manuell verglichen — identisch
   (`cash, bank_transfer, paypal, stripe, credit_card`). Neuer Test macht
   das mechanisch/regressionssicher, statt nur implizit durch einen
   Einzelwert-Test (`bank_transfer`) abgedeckt zu sein.

## Ausführungs-Ergebnis

```
# SQLite (Standard-Testsuite)
docker compose exec php vendor/bin/pest --no-coverage
Tests:    1 skipped, 847 passed (2641 assertions)
(WARN: Concurrency-Test korrekt übersprungen — kein MVCC auf SQLite)

# PostgreSQL (dog_school_test, migrate:fresh davor)
docker compose exec -e DB_CONNECTION=pgsql -e DB_DATABASE=dog_school_test \
  php vendor/bin/pest --no-coverage
Tests:    848 passed (2646 assertions)
(inkl. PASS Concurrency\Domain\Payment\InvoicePaymentRecorderConcurrencyTest)

docker compose exec php composer lint         # PASS, 315 files
docker compose exec php composer stan         # No errors, 207 files
docker compose exec php composer compat-check # kein Output, exit 0

# Frontend
docker compose exec node npm run test -- run
Test Files  25 passed (25) | Tests  308 passed (308)

docker compose exec node npm run lint
0 errors, 3179 warnings (ausschließlich Bestands-Warnklassen, exit 0)

docker compose exec node npm run build
✓ built in 2.35s (vue-tsc -b, keine Fehler)
```

Test-DB `dog_school_test` nach dem PostgreSQL-Lauf wieder via
`migrate:fresh --force` zurückgesetzt.

## Akzeptanzkriterien-Abdeckung (bezogen auf die geprüften Lücken)

- [x] Überzahlungs-Grenzfall exakt am Restbetrag — bereits vorhanden,
  verifiziert grün.
- [x] Überzahlungs-Grenzfall +0,01 € — neu, `InvoicePaymentApiTest.php::it
  lehnt eine zahlung ab, die den restbetrag nur um einen cent übersteigt`.
- [x] 3+ Teilzahlungen mit Zwischenstands-Prüfung — neu,
  `InvoicePaymentRecorderTest.php::it aktualisiert restbetrag und status
  korrekt nach jeder von drei aufeinanderfolgenden teilzahlungen`.
- [x] `completeExisting()`-Idempotenz — neu,
  `InvoicePaymentRecorderTest.php::it bleibt idempotent wenn
  completeExisting für eine bereits abgeschlossene zahlung erneut
  aufgerufen wird`.
- [x] Frontend-422-Nachricht im Toast — bereits vorhanden, verifiziert.
- [x] `PAYABLE_STATUSES`-Konsistenz (`overdue`/`reminded`) — neu, 2 Tests
  in `InvoicePaymentApiTest.php`.
- [x] Zahlungsart-Werte Frontend/Backend — neu,
  `InvoicePaymentDialog.test.ts::it bietet exakt die vom Backend
  akzeptierten Zahlungsart-Werte an`.

## Fehler

Keine. Ein Zwischenlauf der beiden neuen `reminded`/`overdue`-Tests schlug
initial fehl (Payload ohne `status: 'completed'` — Default bleibt laut
`StorePaymentRequest::validatedSnakeCase()` `pending`, wodurch `syncStatus()`
die Zahlung nicht als abgeschlossen zählt). Als Testfehler in den neu
geschriebenen Tests selbst erkannt und korrigiert (nicht im Produktivcode),
bevor der finale grüne Lauf dokumentiert wurde.
