# Fix: TOCTOU-Race bei der Überzahlungs-Prüfung — Notes

Behebt den **Muss-Befund** aus `task-T03.review.md` Abschnitt "Muss (blockiert
Abnahme)" (Fund: `[Korrektheit/Sicherheit — TOCTOU]`,
`backend/app/Http/Controllers/Api/PaymentController.php:118-124` /
`backend/app/Services/InvoicePaymentRecorder.php:53-64`). Dieser Punkt wird
bewusst nicht in `task-T03.review.md` selbst abgehakt — das obliegt dem
Reviewer bei der erneuten Prüfung.

## Problem (Kurzfassung)

`PaymentController::store()` prüfte `amount > $invoice->remaining_balance`
**vor** dem `lockForUpdate()` in `InvoicePaymentRecorder::record()`.
`record()` selbst validierte den Betrag nach dem Lock nicht erneut. Zwei
nahezu gleichzeitige `store()`-Aufrufe für dieselbe Rechnung konnten beide
denselben, noch unveränderten Restbetrag lesen, beide die 422-Prüfung
bestehen und dann seriell, aber ungeprüft gebucht werden — Überzahlung trotz
der bindenden Anforderung "Überzahlung wird abgelehnt".

## Fix — gewählte Option

**Option A** aus der Aufgabenbeschreibung: `InvoicePaymentRecorder::record()`
wiederholt die Restbetrags-Prüfung **innerhalb** der durch `lockForUpdate()`
geschützten kritischen Sektion, gegen `$locked->remaining_balance` (basiert
auf `Invoice::getRemainingBalanceAttribute()`, das bei jedem Zugriff frisch
gegen `payments()->where('status','completed')->sum('amount')` rechnet —
kein gecachtes Attribut, daher innerhalb der Transaktion nach dem Lock
zuverlässig aktuell). Bei Überschreitung wird die neue
`App\Exceptions\InvoiceOverpaymentException` geworfen, **bevor** der
`Payment`-Datensatz angelegt wird.

Gewählt statt Option B (Prüfung injizieren), weil:
- `InvoicePaymentRecorder` bereits laut Klassen-Docblock der alleinige
  Ort ist, an dem Lock und Status-Synchronisation atomar zusammenlaufen
  (Decision D2 aus `design.md`) — die Betragsprüfung gehört fachlich an
  dieselbe Stelle, nicht in eine externe Callback-Abhängigkeit.
- Passt zum bestehenden Stil dedizierter Domain-Exceptions, wie ihn
  CLAUDE.md Abschnitt 6 verlangt ("Eigene Exception-Klassen pro Domäne,
  keine generischen `\Exception`-Würfe") — es gab noch keine
  `app/Exceptions/`-Klasse im Projekt, diese ist die erste.

### Geänderte/neue Dateien

- **`backend/app/Exceptions/InvoiceOverpaymentException.php`** (neu):
  `RuntimeException`-Subklasse mit `readonly` Properties `invoiceId`,
  `attemptedAmount`, `remainingBalance` (Constructor Promotion, PHP
  8.2-kompatibel). Docblock erklärt das Zusammenspiel mit dem
  Controller-seitigen Fail-Fast-Check.
- **`backend/app/Services/InvoicePaymentRecorder.php`**: `record()` liest
  `$amount = (float) $paymentData['amount']` und prüft
  `$amount > $locked->remaining_balance` direkt nach dem
  `Invoice::query()->lockForUpdate()->findOrFail()`, vor
  `Payment::create()`. Wirft bei Überschreitung
  `InvoiceOverpaymentException($locked->id, $amount, $locked->remaining_balance)`.
  Klassen-Docblock um einen Absatz ergänzt, der diese zweite,
  engere Race von der bereits dokumentierten Status-Sync-Race (D2)
  abgrenzt.
- **`backend/app/Http/Controllers/Api/PaymentController.php`**:
  - Vorab-Prüfung (`amount > $invoice->remaining_balance`) bleibt als
    Fail-Fast-UX-Pfad für den Normalfall erhalten (Kommentar ergänzt,
    der explizit klarstellt, dass sie **nicht** die Sicherheitsgrenze
    ist).
  - `$this->paymentRecorder->record(...)` läuft jetzt in einem
    `try`/`catch (InvoiceOverpaymentException $e)`-Block; der Catch-Zweig
    übersetzt die Exception in dieselbe 422-Response wie der Vorab-Check.
  - Beide Antwortpfade wurden in eine private `overpaymentResponse(float
    $remainingBalance): JsonResponse` extrahiert (DRY; war zuvor
    dupliziert), formatiert den Betrag identisch wie zuvor
    (`number_format(..., 2, ',', '.')`).

## Warum ein bestehender T02-Test angepasst werden musste

`backend/tests/Feature/Domain/Payment/InvoicePaymentRecorderTest.php`
enthielt den Test *"setzt den rechnungsstatus auf paid wenn mehrere
teilzahlungen in summe den gesamtbetrag übersteigen"* (60 € + 50 € = 110 €
bei `total_amount` 100 €, aus T02, dokumentiert in `task-T02.notes.md`
Abschnitt "Tests" Punkt (c)). Dieser Test rief `record()` zweimal
**direkt** auf (unterhalb des Controllers, ohne dessen Vorab-Prüfung) und
erwartete, dass die zweite, den Restbetrag (40 €) übersteigende Zahlung
(50 €) trotzdem anstandslos gebucht wird und die Rechnung auf `paid`
setzt. Das war genau das service-seitige Verhalten, das der Muss-Befund
als Sicherheitslücke identifiziert — der Test dokumentierte also
(unbeabsichtigt) den Bug als Feature.

**Angepasst** zu *"lehnt eine teilzahlung ab, wenn sie zusammen mit
bereits abgeschlossenen zahlungen den gesamtbetrag übersteigt"*: derselbe
Ausgangszustand (60 € gebucht), aber die zweite `record()`-Aufruf (50 €)
wird jetzt per `expect(fn () => ...)->toThrow(InvoiceOverpaymentException::class)`
geprüft; Rechnung bleibt `sent`, `paid_date` bleibt `null`, nur die erste
Zahlung (60 €) ist gebucht. Der Ursprungstest aus T02 zur PostgreSQL-
Concurrency-Suite (zwei Teilzahlungen à 50 €, Summe **exakt** 100 €, kein
Überzahlungsfall) ist von diesem Fix nicht betroffen und läuft unverändert
grün — wie in der Aufgabenbeschreibung explizit gefordert.

## Neuer Test — echter Zwei-Prozess-Beweis

`backend/tests/Concurrency/Domain/Payment/InvoicePaymentRecorderConcurrencyTest.php`
um einen zweiten `it()`-Test ergänzt, der demselben `pcntl_fork()`-Muster
wie der bestehende T02-Test folgt (gemeinsames `beforeEach()`/
`afterEach()`, gemeinsame Rechnung `total_amount = 100.00`):

- Beide Kindprozesse versuchen `record()` mit je 80 € — einzeln jeweils
  unter dem zum (hypothetischen) Prüfzeitpunkt sichtbaren Restbetrag
  (100 €), aber ihre Summe (160 €) übersteigt ihn.
- Beide starten synchronisiert auf denselben Zeitpunkt (`microtime(true) +
  0.3` + Busy-Wait), damit sie tatsächlich um die Zeilensperre
  konkurrieren.
- Erwartung: **genau ein** Kindprozess erfolgreich (`exit(0)`), der andere
  lehnt mit `InvoiceOverpaymentException` ab (`exit(1)`, separat von
  `exit(2)` für unerwartete Fehler unterschieden). Reihenfolge der
  Lock-Vergabe ist nicht deterministisch, daher `sort($exitStatuses)` vor
  dem Vergleich mit `[0, 1]`.
- Danach: Rechnung bleibt `sent`, genau 1 `Payment`-Datensatz, Summe
  abgeschlossener Zahlungen = 80 €.

Ein echter Zwei-Prozess-Test wurde hier bewusst gewählt (statt eines
einfacheren sequenziellen Tests) — bei einer Geld-/Sicherheitsfrage ist der
tatsächliche Nachweis unter echter DB-Zeilensperre aussagekräftiger als ein
Test, der die Race nur simuliert. Der bereits vorhandene, deterministische
Unit-Test (`InvoicePaymentRecorderTest.php`, s. o.) deckt den nicht-
nebenläufigen Fall (sequenzielle Aufrufe, Überzahlung durch die zweite
Zahlung) zusätzlich ab.

## QA

```
docker compose exec -T php composer lint           # 316 files, PASS
docker compose exec -T php composer stan            # No errors
docker compose exec -T php composer compat-check     # exit 0, keine Ausgabe
docker compose exec -T php composer test             # 849 passed, 2 skipped (Concurrency, SQLite-No-Op), 2643 Assertions
```

PostgreSQL-Verifikation (gemäß Aufgabenbeschreibung, Env-Variablen an den
`pest`-Aufruf selbst gebunden, analog zum in `task-T02.notes.md`
dokumentierten Muster):

```
docker compose exec postgres createdb -U dog_school_user dog_school_test   # bereits vorhanden
docker compose exec php sh -c "DB_CONNECTION=pgsql DB_DATABASE=dog_school_test php artisan migrate:fresh --force"
docker compose exec php sh -c "DB_CONNECTION=pgsql DB_DATABASE=dog_school_test vendor/bin/pest --testsuite=Concurrency --no-coverage"
# → 2 passed (9 assertions) — beide Concurrency-Tests, inkl. des neuen Race-Tests, laufen jetzt gegen echtes MVCC.

docker compose exec php sh -c "DB_CONNECTION=pgsql DB_DATABASE=dog_school_test vendor/bin/pest --no-coverage"
# → 849 passed (2652 assertions), volle Suite, keine Skips (echte Zeilensperren statt SQLite-No-Op).

docker compose exec php sh -c "DB_CONNECTION=pgsql DB_DATABASE=dog_school_test php artisan migrate:fresh --force"
# Test-DB danach zurückgesetzt.
```

## Offene Punkte / Annahmen

- Die vom Reviewer unter "Sollte" vorgeschlagene Extraktion einer
  `assertPayable()`-Methode wurde **nicht** vollständig umgesetzt — sie
  wäre über den Scope dieses gezielten Bugfixes hinausgegangen (der
  Reviewer selbst formuliert sie als "guter Kandidat […] bei einem der
  nächsten Changes"). Stattdessen wurde die kleinere, für diesen Fix
  ohnehin notwendige Duplikat-Bereinigung (`overpaymentResponse()`)
  vorgenommen, die denselben Kern-Vorschlag (eine gemeinsame Stelle für
  die 422-Antwort) in minimalem Umfang aufgreift.
- `completeExisting()` ist unverändert — der Reviewer-Befund und dieser
  Fix betreffen ausdrücklich nur `record()`/`store()` (kein neu
  eingegebener Betrag bei `completeExisting()`).
- Kein Eintrag in `tasks.md` für diesen Fix angelegt — er behebt einen
  Review-Befund zum bestehenden Change, ist keine neue Task mit eigener
  T-ID.
