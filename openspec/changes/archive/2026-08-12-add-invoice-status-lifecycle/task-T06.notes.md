# T06 — Policy verschärfen (Entwurf-only Update/Delete) + Kunden-Sichtbarkeit + `InvoiceResource` erweitern

## Status

Implementiert. Alle Akzeptanzkriterien in `tasks.md` T06 sind erfüllt und
abgehakt. `composer qa` läuft grün (Pint, PHPStan, PHPCompatibility-Check,
Pest — 795 Tests, 2517 Assertions, Exit-Code 0, in der lokalen
Docker-Umgebung ausgeführt). Dies ist die letzte Backend-Task von Change 1
(`add-invoice-status-lifecycle`).

## Was wurde umgesetzt

### Geänderte Dateien

- `backend/app/Policies/InvoicePolicy.php`:
  - `view()` — wortgetreu nach `tasks.md`-Vorlage: Admin/Trainer sehen
    alles, Kunde nur eigene Rechnungen mit `status` in `['sent', 'paid',
    'overdue', 'reminded']`.
  - `update()` — jetzt zusätzlich `$invoice->status === 'draft'`.
  - `delete()` — jetzt zusätzlich `$invoice->status === 'draft'`.
  - **Neu, über die Task-Beschreibung hinaus:** `markAsPaid(User $user,
    Invoice $invoice): bool` (rollen-only, `isAdminOrTrainer()`) — siehe
    Abschnitt "Wichtiger Befund" unten für die Begründung.
  - Docblock von `finalize()` aktualisiert (Verweis auf `markAsPaid()`
    statt auf `InvoicePolicy::update()`, da letzteres seinen bisherigen
    "role-only"-Charakter durch T06 verloren hat).
- `backend/app/Http/Controllers/Api/InvoiceController.php`:
  - `index()`: `with([...])` um `originalInvoice`, `cancellationInvoice`,
    `dunnings` erweitert; im `elseif ($user->isCustomer())`-Zweig
    zusätzlich `->whereIn('status', ['sent', 'paid', 'overdue',
    'reminded'])` nach der bestehenden `where('customer_id', ...)`-Zeile.
  - `show()`: `load([...])` um dieselben drei Relationen erweitert.
  - `finalize()`: `fresh([...])` im Rückgabe-Statement um dieselben drei
    Relationen erweitert.
  - `cancel()`: `fresh([...])` im Rückgabe-Statement um dieselben drei
    Relationen erweitert.
  - **Neu, über die Task-Beschreibung hinaus:** `markAsPaid()` ruft jetzt
    `$this->authorize('markAsPaid', $invoice)` statt `$this->authorize('update',
    $invoice)` auf (siehe "Wichtiger Befund").
- `backend/app/Http/Resources/InvoiceResource.php`: `toArray()` um
  `remindedAt`, `dunningLevel`, `originalInvoiceId`,
  `originalInvoiceNumber`, `cancellationInvoiceId`,
  `cancellationInvoiceNumber` erweitert — wortgetreu nach dem
  Code-Beispiel in `tasks.md`, direkt nach `isOverdue`.

### Tests (`backend/tests/Feature/InvoiceApiTest.php`, `InvoicePdfTest.php`)

- **Reparatur bestehender, durch T06 gebrochener Tests** (Statuswerte
  waren implizit `draft`, siehe "Wichtiger Befund" unten):
  - `'customer can list their own invoices'` und `'customer can view
    their own invoice'`: Fixtures um `'status' => 'sent'` ergänzt.
  - `'customer can download their own invoice as PDF'`
    (`InvoicePdfTest.php`): `$this->invoice->update(['status' =>
    'sent'])` vor dem Request ergänzt (die geteilte `beforeEach`-Fixture
    selbst wurde bewusst **nicht** angefasst, um andere Tests der Datei
    nicht zu beeinflussen).
- **Neue Tests für die T06-Akzeptanzkriterien:**
  - `'customer cannot view their own draft invoice'`,
    `'customer cannot view their own cancelled invoice'`,
    `'customer does not see draft or cancelled invoices in the list'`.
  - `'admin and trainer still see invoices of every status'` (inkl.
    `reminded`).
  - `'admin cannot update a non-draft invoice'`,
    `'admin cannot delete a non-draft invoice'` (403 auch für Admin).
  - `'customer cannot mark invoice as paid'` (Regressionstest für die
    neue `markAsPaid`-Policy-Methode).
  - `'InvoiceResource exposes cancellation invoice fields on both sides
    of the relation'`, `'InvoiceResource exposes dunningLevel and
    remindedAt derived from invoice_dunnings'`,
    `'InvoiceResource returns null dunningLevel and remindedAt when no
    dunning exists'`.
  - Ergänzung des bestehenden T05-Tests `'trainer can cancel a sent
    invoice, creating a negated cancellation invoice'` um
    `assertJsonPath('data.originalInvoiceId', ...)` /
    `assertJsonPath('data.originalInvoiceNumber', ...)` — schließt genau
    die in `task-T05.notes.md` dokumentierte Lücke ("Bekannte,
    dokumentierte Lücke: `originalInvoiceId` fehlt noch im
    Response-Body").

## Wichtiger Befund: `markAsPaid()` wäre durch die wörtliche `update()`-Änderung unbenutzbar geworden

`tasks.md` T06 spezifiziert `update()` wortgetreu als:

```php
public function update(User $user, Invoice $invoice): bool
{
    return $user->isAdminOrTrainer() && $invoice->status === 'draft';
}
```

`InvoiceController::markAsPaid()` (Bestandscode, unverändert seit vor
Change 1) rief bislang `$this->authorize('update', $invoice)` auf. Der
**einzige** sinnvolle Anwendungsfall von `markAsPaid()` ist eine
**nicht-Entwurfs**-Rechnung (`sent`/`overdue`/`reminded` → `paid`) — der
bestehende Test `'trainer can mark invoice as paid'` verwendet
`status = 'sent'` und erwartet `assertOk()`.

Bei wortgetreuer Umsetzung der `update()`-Änderung ohne weitere Anpassung
hätte `$this->authorize('update', $invoice)` in `markAsPaid()` für
**jede** reale Rechnung (die niemals `draft` ist, wenn sie bezahlt werden
soll) fehlgeschlagen — `markAsPaid()` wäre für seinen einzigen
Anwendungsfall komplett unbenutzbar geworden (403 statt 200), und der
bestehende, für T06 nicht explizit in der Dateiliste genannte Test
`'trainer can mark invoice as paid'` wäre rot geworden. Das habe ich vor
der Implementierung durch gezieltes Nachlesen aller `authorize('update', ...)`-Aufrufe
im Projekt verifiziert (`grep -rn "authorize('update'" backend/app` → nur
die zwei Stellen `update()` und `markAsPaid()` in
`InvoiceController.php`).

**Befund, nicht nur behauptet, sondern in `design.md` bereits vorgesehen:**
`design.md` Decision D4 sagt explizit: *"`status` ändert sich
ausschließlich über named Actions (`finalize`, `cancel`, `markAsPaid`,
künftig `remind`), jede mit eigener Policy-Methode, die den aktuellen
Status prüft."* — das heißt, `markAsPaid()` war laut Architektur-Vorgabe
von Anfang an als **eigene** Policy-Methode vorgesehen, wurde aber in
keiner Task (T01–T06) tatsächlich angelegt; der Bestandscode nutzte
weiterhin `update`, was bis einschließlich T05 unschädlich war (`update()`
war bis dahin rein rollenbasiert, ohne Status-Prüfung).

**Entscheidung:** Ich habe eine neue `InvoicePolicy::markAsPaid()`
(rollen-only, `isAdminOrTrainer()`, exakt nach dem etablierten Muster von
`finalize()`) ergänzt und `InvoiceController::markAsPaid()` auf diese neue
Ability umgestellt — das setzt `design.md` D4 nachträglich exakt so um,
wie dort vorgesehen, statt eine bestehende Funktionalität stillschweigend
zu brechen. `markAsPaid()`s eigene 422-Prüfung ("bereits bezahlt", Zeile
210–214 des Controllers) bleibt unverändert im Controller — identischer
Split wie bei `finalize()`.

**Warum das nicht "eine andere Task" ist:** Die Dateien
(`InvoicePolicy.php`, `InvoiceController.php`) sind beide bereits
regulärer T06-Scope; die Änderung ist eine **direkte, notwendige
Konsequenz** der wortgetreu geforderten `update()`-Änderung, kein
eigenständiges Feature. Ohne diese Korrektur wäre `composer qa` **nicht**
grün gelaufen (bestehender Test hätte fehlgeschlagen) — das
Akzeptanzkriterium "`composer qa` läuft grün" wäre sonst nicht erfüllbar
gewesen, ohne den Bestandstest zu entfernen (was schlechter gewesen wäre
als die Ursache zu beheben).

## Weitere entdeckte, direkt durch T06 gebrochene Bestandstests (repariert, nicht ignoriert)

`InvoiceFactory`s Status-Default ist `'draft'` (siehe
`backend/database/factories/InvoiceFactory.php:23`). Mehrere
Bestandstests aus T01–T05 erzeugten Kunden-sichtbare Rechnungen ohne
expliziten Status und verließen sich damit unbeabsichtigt auf den
Draft-Default:

- `'customer can list their own invoices'`
  (`backend/tests/Feature/InvoiceApiTest.php`)
- `'customer can view their own invoice'` (dieselbe Datei)
- `'customer can download their own invoice as PDF'`
  (`backend/tests/Feature/InvoicePdfTest.php`)

Nach der `view()`-/`index()`-Änderung in T06 sind Draft-Rechnungen für
Kunden per Definition unsichtbar — diese drei Tests wären sonst rot
geworden. Ich habe **nur** die betroffenen Fixtures um einen expliziten,
kunden-sichtbaren Status (`'sent'`) ergänzt, keine Assertions oder
Testabsicht verändert. Das ist dieselbe, in `task-T03.notes.md` etablierte
Vorgehensweise ("nur direkt durch die Task gebrochene Tests").

Ich habe zusätzlich per `grep` alle weiteren Testdateien geprüft, die
Kunden-Rollen mit Rechnungen kombinieren (`PaymentApiTest.php`,
`DashboardApiTest.php`) — dort ist entweder der Status bereits explizit
`'sent'` gesetzt (`PaymentApiTest.php:beforeEach`) oder die Endpunkte
laufen nicht über `InvoicePolicy` (`DashboardController` hat keine
`authorize('view'/'update'/..., $invoice)`-Aufrufe) — keine weiteren
Regressionen gefunden.

## Tests / Checks — Ergebnisse

Alle Checks liefen in der lokalen Docker-Umgebung (`docker compose exec
php composer qa`).

- `composer qa` (Pint --test, PHPStan, PHPCompatibility-Check, Pest):
  **grün**, Exit-Code 0. 795 Tests bestanden (2517 Assertions, 785 → 795
  durch die 10 neuen/erweiterten Tests dieser Task), Pint 305 Dateien
  ohne Änderungsbedarf, PHPStan ohne Fehler (205 Dateien).
- Manuelle funktionale Verifikation via `artisan tinker` gegen die
  laufende **PostgreSQL**-Dev-Instanz (Entwicklungs-DB laut CLAUDE.md
  Abschnitt 3), in einer zurückgerollten Transaktion (`DB::beginTransaction()`
  / `DB::rollBack()`, keine Testdaten zurückgelassen):
  - `Invoice::with('cancellationInvoice')`/`with('originalInvoice')`:
    beide Relationen liefern korrekt die jeweils andere Rechnung.
  - `InvoiceDunning`-Datensätze (Level 1 und 2, unterschiedliche Daten)
    angelegt: `$invoice->dunning_level` liefert `2` (höchste Stufe),
    `$invoice->reminded_at` liefert das Datum der jüngsten Mahnung —
    exakt wie in `InvoiceResource` über `whenLoaded`/direkte Accessor-
    Zugriffe verdrahtet.
- Kein `docker-compose.mysql.yml` im Repo (bekannt aus T01–T05); T06
  enthält keine Migration und keine treiberspezifische SQL-Logik (reines
  Eloquent — `whereIn()`, `whenLoaded()`, Accessor-basierte Attribute),
  daher laut CLAUDE.md Abschnitt 4.2 unkritisch für MySQL-Portabilität;
  keine separate MySQL-Verifikation für T06 durchgeführt.

## Abweichungen von der Task-Beschreibung

1. **`InvoicePolicy::markAsPaid()` neu hinzugefügt** und
   `InvoiceController::markAsPaid()` darauf umgestellt (statt weiterhin
   `authorize('update', ...)` zu nutzen) — nicht in T06s Dateiliste/
   Code-Beispiel explizit genannt, aber zwingend notwendig, um die
   wortgetreu geforderte `update()`-Änderung umzusetzen, ohne
   `markAsPaid()` (und den zugehörigen Bestandstest) zu brechen. Siehe
   "Wichtiger Befund" oben für die vollständige Begründung und den Bezug
   zu `design.md` Decision D4.
2. Docblock von `InvoicePolicy::finalize()` redaktionell angepasst
   (Verweis von `InvoicePolicy::update()` auf `markAsPaid()` korrigiert,
   da `update()` nach T06 nicht mehr rein rollenbasiert ist) — keine
   Verhaltensänderung, nur Kommentar-Korrektheit.
3. Testfixtures dreier Bestandstests um einen expliziten Status ergänzt
   (siehe Abschnitt "Weitere entdeckte ... Bestandstests" oben) — keine
   Assertion-Änderung.

## Für Architekt/Skeptiker: Inkonsistenz zwischen den Backend-Tasks T01–T06

Zusammengefasst für die Abnahme (Modus B) dieses Changes:

- **`design.md` Decision D4 vs. tatsächlicher Task-Zuschnitt:** D4 sagt
  klar "jede [Status-ändernde Aktion] mit eigener Policy-Methode", aber
  keine der Tasks T01–T06 hat `markAsPaid()` (Bestandscode, nicht Teil
  dieses Changes) tatsächlich von `update` auf eine eigene Ability
  umgestellt — das wurde erst durch die in T06 geforderte
  `update()`-Verschärfung *erzwungen*, faktisch als Nebenprodukt dieser
  Task und nicht als eigene, geplante Aufgabe. Wäre T06 rein additiv
  (z. B. hätte `update()` unverändert rollenbasiert bleiben können und
  nur eine neue, separate Draft-Prüfung wäre eingeführt worden), wäre
  dieser Fund nicht aufgetreten. Für künftige Changes empfehle ich, bei
  Policy-Verschärfungen an Bestandsmethoden (`update`/`delete`) explizit
  zu prüfen, welche anderen Controller-Methoden dieselbe Ability
  wiederverwenden (`grep -rn "authorize('<ability>'"`), bevor die Task
  geschrieben wird.
- Kein weiterer Statuswert-Inkonsistenzfund: `reminded` (T01),
  `overdue`-Whitelist-Rechtfertigung (T01/design D3), `cancelled` (T05)
  sind in `view()`/`index()`/`cancel()` durchgängig konsistent behandelt.
