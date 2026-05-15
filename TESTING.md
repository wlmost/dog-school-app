# Test-Konventionen — dog-school-app

> **Verbindlich für `tester`-Agent und `reviewer`-Agent.**
> Diese Datei ist die einzige Wahrheitsquelle für Test-Konventionen.
> Bei Widerspruch zwischen dieser Datei und bestehenden Tests:
> diese Datei gewinnt für **neue** Tests. Bestand wird nicht rückwirkend
> angepasst, sondern nach Boy-Scout-Regel: wer eine alte Test-Datei
> sowieso anfasst, bringt sie bei der Gelegenheit auf den neuen Stand.

---

## 1. Test-Framework

- **Engine:** Pest (`vendor/bin/pest`), läuft über `composer test`.
- **Klassische PHPUnit-Klassen sind verboten** für neue Tests. `TestCase.php`
  existiert nur als Skeleton-Fundament, wird **nicht** direkt erweitert.
- Wenn ein bestehender Test im PHPUnit-Klassen-Stil angefasst werden muss
  (z. B. größerer Umbau), darf er bei Gelegenheit in Pest umgeschrieben werden
  — aber NICHT als alleinige Aufgabe ohne expliziten Auftrag.

## 2. Datei-Aufbau (kanonische Schablone)

Jede neue Test-Datei beginnt **exakt so**:

```php
<?php

declare(strict_types=1);

use App\Models\…;                                           // Domain-Models alphabetisch
use Illuminate\Foundation\Testing\RefreshDatabase;          // immer drin

uses(RefreshDatabase::class);
uses()->group('<typ>', '<feature>');                        // siehe Abschnitt 7

beforeEach(function () {
    // Fixtures, die JEDER Test in dieser Datei braucht.
    // Spezifisches gehört in den jeweiligen Test, nicht hierher.
});

it('liefert 404 wenn die anamnese nicht existiert', function () {
    // arrange — gegeben dieser Zustand
    // act     — wenn ich das mache
    // assert  — dann erwarte ich …
});
```

**Begründung der Wahl von `it()` statt `test()`:** BDD-Stil liest sich als
ganzer Satz, erzwingt ein Verb in der Beschreibung und macht den erwarteten
Effekt klarer. Bestand verwendet teils `test()` — das bleibt unberührt.

### 2.1 Test-Benennung (verbindlich)

**Form:** Dritte Person Indikativ, kleinschreibung, Deutsch.
Das `it` wird gedanklich vorangestellt — also "es liefert …", "es speichert …",
"es weist … zurück".

**Beispiele aus der Domäne:**

```php
it('listet alle anamnese-antworten für admins auf', …);
it('speichert eine neue anamnese-antwort wenn die daten valide sind', …);
it('weist die anfrage zurück wenn die rolle nicht admin ist', …);
it('gibt 404 zurück wenn die anamnese nicht existiert', …);
it('generiert ein pdf mit allen antworten des hundes', …);
it('lehnt das senden ab wenn pflichtfelder fehlen', …);
```

**Verboten** (häufige Anti-Patterns):

```php
it('test create response', …);                              // ❌ "test" + Englisch + ohne Verb
it('Speichert Antwort', …);                                 // ❌ Großschreibung, kein "es"-Kontext
it('Anamnese-Antwort-Speicherung', …);                      // ❌ kein Verb, nur Substantiv-Kette
it('should save response', …);                              // ❌ "should" ist Pest-Anti-Pattern, "it" reicht
```

**Regel für den Tester-Agent:** Beginne die Beschreibung mit einem konjugierten
Verb in dritter Person Singular (liefert, speichert, weist, gibt, generiert,
lehnt, validiert, akzeptiert, ignoriert, ruft, …). Wenn dir kein Verb einfällt,
ist der Test vermutlich zu unklar formuliert — überlege, was die Funktion *tut*.

## 3. Factory-Verwendung

### 3.1 User-Erstellung — verbindlich: Factory-States

**Richtig:**
```php
$admin    = User::factory()->admin()->create();
$trainer  = User::factory()->trainer()->create();
$customer = User::factory()->customer()->create();
```

**Falsch (nicht mehr verwenden):**
```php
$admin = User::factory()->create(['role' => 'admin']);     // Magic String
```

**Begründung:** semantischer, refactoring-sicher, IDE-unterstützt.
Falls ein State fehlt, MUSS er in `database/factories/UserFactory.php` ergänzt werden
(eigene Sub-Task im selben Change). Tester-Agent darf NICHT auf Magic Strings ausweichen.

### 3.2 Relations — Wenn-Dann-Regel

| Situation                                                              | Verwende                                                              |
|------------------------------------------------------------------------|-----------------------------------------------------------------------|
| Du brauchst nur das Beziehungs-Verhältnis, referenzierst den Record nie | `User::factory()->hasCustomer()->create()`                            |
| Du brauchst den verbundenen Record als Variable für Folge-Zuweisungen   | Manuell: `$customer = Customer::factory()->create(['user_id' => $u]);` |

**Beispiel für Variante 2** (Folge-Zuweisungen, aus dem Bestand):
```php
$user = User::factory()->customer()->create();
$customer = Customer::factory()->create(['user_id' => $user->id]);
$dog = Dog::factory()->create(['customer_id' => $customer->id]);
```

### 3.3 Mehrere Records auf einmal

```php
AnamnesisResponse::factory()->count(3)->create();
AnamnesisResponse::factory()->count(3)->for($dog)->create();
```

`count()` immer als erste Methode nach `factory()`, damit auf einen Blick
erkennbar ist, dass mehrere erzeugt werden.

## 4. Authentication in Tests

**Verbindlich:** `actingAs()` für alle Tests, die einen authentifizierten User brauchen.

```php
$response = $this->actingAs($this->admin)->getJson('/api/v1/anamnesis-responses');
```

**Nicht verwenden:**
- `Auth::login(...)` direkt
- `Sanctum::actingAs(...)` außer wenn explizit Sanctum-Token-Verhalten getestet wird
  (dann separate Test-Datei mit dritter Group `auth-sanctum`)

## 5. Assertion-Stile — Domain-getrennt

Die Regel ist deterministisch und mechanisch prüfbar: **welche Domäne, welcher Stil.**

### 5.1 HTTP-Responses → Laravel-Style

```php
$response->assertOk()
    ->assertJsonCount(3, 'data')
    ->assertJsonStructure(['data' => [['id', 'created_at']]]);

$response->assertCreated();
$response->assertForbidden();
$response->assertJsonValidationErrors(['email']);
```

**Verboten** für HTTP-Responses:
```php
expect($response->status())->toBe(200);                     // ❌ Laravel-Idiom verloren
```

### 5.2 Datenbank-Zustand → Laravel-Style

```php
$this->assertDatabaseHas('anamnesis_responses', [
    'dog_id' => $dog->id,
    'status' => 'submitted',
]);
$this->assertDatabaseCount('anamnesis_responses', 1);
$this->assertDatabaseMissing('anamnesis_responses', ['id' => $deleted->id]);
$this->assertSoftDeleted($model);
```

### 5.3 Domain-Werte und Sammlungen → Pest-`expect()`

```php
expect($response->refresh()->status)->toBe('completed');
expect($dog->customer_id)->toBe($customer->id);
expect($answers)->toHaveCount(3);
expect($pdf->bytes())->toBeGreaterThan(1000);
expect($dogs)->each->toBeInstanceOf(Dog::class);
expect($collection->pluck('name')->toArray())->toContain('Bello');
```

**Verboten** für reine Werte:
```php
$this->assertEquals('completed', $response->status);        // ❌ veraltet im Pest-Kontext
$this->assertCount(3, $answers);                            // ❌ Pest-expect ist ausdrucksstärker
$this->assertTrue($dog->is_active);                         // ❌ `expect(...)->toBeTrue()`
```

### 5.4 Entscheidungsbaum für den Tester-Agent

```
Was prüfst du?
├── HTTP-Response-Eigenschaft (Status, Headers, JSON-Body)
│   → Laravel-Style: $response->assert*()
├── Datenbank-Zustand (Zeile da/nicht da, Anzahl)
│   → Laravel-Style: $this->assertDatabase*()
├── Eigenschaft eines Eloquent-Models (Spalte, Beziehung)
│   → Pest-expect(): expect($model->property)->toBe(...)
├── Collection / Array / Wert
│   → Pest-expect(): expect($value)->to*()
└── Exception / Boolean / Null
    → Pest-expect(): expect(...)->toBeTrue() / ->toThrow(...)
```

**Mischen in einer Test-Funktion ist erlaubt und idiomatisch** — solange die
Domain-Trennung respektiert wird. Beispiel aus einem realistischen Test:

```php
it('speichert eine anamnese-antwort und gibt sie als JSON zurück', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/anamnesis-responses', $payload);

    $response->assertCreated()                              // 5.1 HTTP
        ->assertJsonPath('data.status', 'draft');           // 5.1 HTTP

    $this->assertDatabaseHas('anamnesis_responses', [       // 5.2 DB
        'dog_id' => $this->dog->id,
    ]);

    $created = AnamnesisResponse::latest()->first();
    expect($created->answers)->toHaveCount(5);              // 5.3 Sammlung
    expect($created->submitted_at)->toBeNull();             // 5.3 Wert
});
```

## 6. RefreshDatabase

- **Immer verwenden** in Feature-Tests, die DB-Operationen ausführen.
- **NICHT verwenden** in reinen Unit-Tests, die keine DB anfassen.
- Begründung: `RefreshDatabase` ist langsam auf MySQL (kein Transactional DDL).
  Pro Test ein Schema-Refresh kostet — wenn der Test keine DB braucht, weglassen.

## 7. Groups — verbindlich für alle neuen Tests

**Regel:** Jede neue Test-Datei MUSS genau eine `uses()->group(...)`-Zeile mit
mindestens zwei Group-Namen haben: Test-Typ + Feature-Bereich.

### 7.1 Schema

| Group-Name (erste) | Bedeutung                                                          | Pfad                          |
|--------------------|--------------------------------------------------------------------|-------------------------------|
| `api`              | HTTP-Endpunkte unter `/api/v1/...`                                 | `tests/Feature/Api/`          |
| `feature`          | Feature-Tests ohne HTTP (Mailables, Jobs, Notifications, Events)   | `tests/Feature/`              |
| `pdf`              | PDF-Generierung                                                    | `tests/Feature/Pdf/`          |
| `domain`           | Reine Geschäftslogik mit DB-Zugriff                                | `tests/Feature/Domain/`       |
| `unit`             | Unit-Tests ohne DB-Zugriff, ohne Container                         | `tests/Unit/`                 |

**Zweite Group:** Feature- oder Domänen-Bereich in **Singular**, kleinschreibung.
Beispiele: `anamnesis`, `dog`, `customer`, `trainer`, `course`, `booking`,
`payment`, `notification`.

### 7.2 Beispiele

```php
// tests/Feature/Api/AnamnesisResponseApiTest.php
uses()->group('api', 'anamnesis');

// tests/Feature/Pdf/AnamnesisResponsePdfTest.php
uses()->group('pdf', 'anamnesis');

// tests/Feature/Domain/Customer/RegisterCustomerTest.php
uses()->group('domain', 'customer');

// tests/Unit/Support/CurrencyFormatterTest.php
uses()->group('unit', 'support');
```

### 7.3 Selektives Ausführen

```bash
composer test -- --group=api                                # alle API-Tests
composer test -- --group=anamnesis                          # alle Anamnese-Tests, egal welcher Typ
composer test -- --group=api --group=anamnesis              # Schnittmenge: API-Tests im Anamnese-Bereich
composer test -- --exclude-group=pdf                        # alles außer PDF (z. B. wenn dompdf gerade kaputt ist)
```

### 7.4 Mehr als zwei Groups

Erlaubt, aber nur wenn ein zusätzlicher Aspekt zweifelsfrei zutrifft. Beispiele:

```php
uses()->group('api', 'anamnesis', 'slow');                  // Test deutlich länger als Durchschnitt
uses()->group('api', 'anamnesis', 'auth-sanctum');          // Sanctum-spezifisch (siehe Abschnitt 4)
```

Drei Groups sind die Obergrenze. Mehr macht die `--group=`-Filterung wertlos.

## 8. Naming und Datei-Struktur

- **Test-Dateien:** `<Subject><Art>Test.php`, z. B. `AnamnesisResponseApiTest.php`, `AnamnesisResponsePdfTest.php`.
- **Pfad:** entspricht dem Typ aus Abschnitt 7.1.
- **Eine Klasse/Domain-Aspekt = eine Test-Datei.** Wenn eine Datei länger als ~300 Zeilen wird, in Unter-Dateien splitten (`AnamnesisResponseApiListTest.php`, `AnamnesisResponseApiStoreTest.php`, …).

## 9. Was der Tester-Agent NIE darf

- `markTestSkipped()` ohne Begründung in einem Kommentar.
- `markTestIncomplete()` als Workaround für rote Tests.
- Tests entfernen oder auskommentieren, um sie grün zu kriegen.
- `dd()`, `dump()`, `var_dump()`, `print_r()` im Test-Code committen.
- `@beforeAll`/`@afterAll`-PHPUnit-Annotations — Pest hat eigene Helper (`beforeAll()`, `afterAll()`).
- Eigene Database-Truncations oder direkte `DB::statement('TRUNCATE …')`-Aufrufe — `RefreshDatabase` macht das.
- Tests gegen die Produktiv-DB schreiben — `phpunit.xml` setzt `testing`-DB; das nicht überschreiben.
- `test('...', …)` statt `it('...', …)` für neue Tests verwenden.
- `$this->assertEquals(...)`, `$this->assertTrue(...)` etc. für Werte verwenden — das ist `expect()`-Territorium (siehe 5.3).
- `expect($response->status())->toBe(200)` o. ä. statt `$response->assertOk()` — das ist Laravel-Territorium (siehe 5.1).
- Die `uses()->group(...)`-Zeile weglassen.

## 10. Was der Reviewer-Agent prüft

Bei jedem PR mit Test-Änderungen prüft der Reviewer zusätzlich zur normalen Code-Review **diese Checkliste mechanisch**:

- [ ] **`it(` statt `test(`** in neuen Test-Definitionen (`grep -n "test('" <datei>` sollte 0 Treffer haben in neuen Dateien)
- [ ] **`uses()->group(` vorhanden** und mindestens zweistellig
- [ ] **Erste Group passt zum Pfad** (`api` ↔ `Api/`, `pdf` ↔ `Pdf/`, etc.)
- [ ] **Factory-States verwendet**: kein `factory()->create(['role' => ...])`
- [ ] **Datei-Header passt zur Schablone** aus Abschnitt 2 (`declare(strict_types=1)`, `RefreshDatabase` wenn DB)
- [ ] **HTTP-Assertions Laravel-Style**: kein `expect($response->status())`
- [ ] **Werte-Assertions Pest-Style**: kein `$this->assertEquals(`, `$this->assertTrue(`, `$this->assertCount(`
- [ ] **DB-Assertions Laravel-Style**: bleibt `$this->assertDatabase*()`, kein `expect()` für DB-Zustand
- [ ] **Keine `dd()`, `dump()`, auskommentierte Tests**, oder leere `it()`-Stubs

Diese Checkliste ist **zusätzlich** zur allgemeinen Review-Checkliste in `~/.claude/agents/reviewer.md`. Jeder Punkt, der fehlschlägt, ist mindestens ein "Sollte"-Befund; bei mehreren Fehlschlägen "Muss"-Befund (= blockiert Abnahme).

## 11. Erweiterung dieser Datei

Wenn der Tester-Agent während einer Task auf eine Situation trifft, die hier
nicht abgedeckt ist:

1. **Sofortlösung** — er trifft eine pragmatische Entscheidung und dokumentiert
   sie in `task-T<ID>.notes.md` unter "Annahmen".
2. **Dauerhafte Lösung** — beim User-Gate 2 schlägt der Architekt vor, ob die
   getroffene Annahme als neue Regel hier verankert werden soll.

So wächst diese Datei mit dem Projekt mit, statt zu veralten.
