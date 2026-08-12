# Tasks für add-invoice-status-lifecycle

## T01: Migration — Status "reminded" + Mahnstufen-Datenmodell [DB-KRITISCH]

- **Agent:** dev-php
- **Dateien:**
  - `backend/database/migrations/2026_08_12_130001_add_reminded_status_to_invoices_table.php` (neu)
  - `backend/database/migrations/2026_08_12_130002_create_invoice_dunnings_table.php` (neu)
  - `backend/app/Models/InvoiceDunning.php` (neu)
  - `backend/app/Models/Invoice.php` (Relation + berechnete Attribute ergänzen)
  - `backend/database/factories/InvoiceDunningFactory.php` (neu, für Testbarkeit)
- **Abhängigkeiten:** keine
- **Beschreibung:**
  **Migration M1** (`add_reminded_status_to_invoices_table`): erweitert
  `invoices.status` treiberspezifisch um den Wert `reminded`, exakt nach
  dem Muster von
  `backend/database/migrations/2026_05_04_110001_add_cancellation_requested_status_to_bookings_table.php`
  (siehe `design.md` Abschnitt "Migrationen", M1). Drei Zweige nötig:
  `mysql` (raw `MODIFY COLUMN ... ENUM(...)`), `pgsql` (CHECK-Constraint
  droppen + neu anlegen), `sqlite` (Tabelle per Copy-Rename neu anlegen,
  **alle** bestehenden Spalten der aktuellen `invoices`-Tabelle
  nachbilden: `id, customer_id, invoice_number, status, total_amount,
  issue_date, due_date, paid_date, notes, timestamps` plus Indizes
  `customer_id, invoice_number, status, issue_date`). `down()` muss
  symmetrisch zurückbauen (Zeilen mit `status = 'reminded'` vor dem
  Downgrade auf `sent` zurücksetzen, wie im Präzedenzfall).

  **Migration M2** (`create_invoice_dunnings_table`): neue Tabelle mit
  `id`, `foreignId('invoice_id')->constrained()->onDelete('cascade')`,
  `unsignedTinyInteger('level')`, `date('dunning_date')`,
  `decimal('fee_amount', 10, 2)->default(0)`, `timestamps()`, Index auf
  `invoice_id`. Kein treiberspezifischer Code nötig (Standard-Migration).

  **`InvoiceDunning`-Model:** `$fillable = ['invoice_id', 'level',
  'dunning_date', 'fee_amount']`, `casts()` mit `dunning_date => 'date'`,
  `fee_amount => 'decimal:2'`, Relation `invoice(): BelongsTo`.

  **`Invoice`-Model ergänzen:**
  ```php
  public function dunnings(): HasMany
  {
      return $this->hasMany(InvoiceDunning::class);
  }

  public function getDunningLevelAttribute(): ?int
  {
      return $this->dunnings->max('level');
  }

  public function getRemindedAtAttribute(): ?Carbon
  {
      return $this->dunnings->sortByDesc('dunning_date')->first()?->dunning_date;
  }
  ```
  (Analog zu `getTotalPaidAttribute()`/`getRemainingBalanceAttribute()`,
  `backend/app/Models/Invoice.php:118-131`.) Keine Trigger-Logik, kein
  Endpoint, der `InvoiceDunning`-Datensätze erzeugt — reines
  Datenmodell für Change 4 (siehe `design.md` Decision D7 und
  Non-Goals).
- **Akzeptanzkriterien:**
  - [x] `php artisan migrate:fresh` läuft fehlerfrei durch (lokale
    Docker-Postgres-Umgebung).
  - [x] `docker compose -f docker-compose.yml -f docker-compose.mysql.yml
    up -d && docker compose exec app php artisan migrate:fresh` läuft
    fehlerfrei durch (MySQL). *(siehe Notes: kein
    `docker-compose.mysql.yml` im Repo vorhanden, stattdessen Ad-hoc-
    MySQL-8.4-Container manuell verifiziert.)*
  - [x] `php artisan test` (SQLite In-Memory) läuft fehlerfrei durch,
    d. h. die SQLite-Rebuild-Migration bildet das bestehende Schema
    vollständig nach (keine fehlenden Spalten/Indizes).
  - [x] Ein `Invoice`-Datensatz kann per `update(['status' =>
    'reminded'])` direkt in der DB (nicht über die API, siehe T05/T06)
    den neuen Statuswert annehmen, ohne DB-Fehler.
  - [x] `InvoiceDunning::create([...])` legt einen Datensatz an;
    `$invoice->dunnings` liefert ihn über die Relation zurück.
  - [x] `$invoice->dunning_level` liefert `null` ohne Mahnungen, sonst
    die höchste `level`; `$invoice->reminded_at` liefert `null` bzw. das
    Datum der jüngsten Mahnung.
  - [x] `migrate:rollback` für M1 setzt betroffene `reminded`-Zeilen auf
    `sent` zurück, bevor der Enum-Wert entfernt wird (kein DB-Fehler).
  - [x] `composer qa` läuft grün.

---

## T02: Migration — Rechnungsnummer nullable + Storno-Referenz-Spalte [DB-KRITISCH]

- **Agent:** dev-php
- **Dateien:**
  - `backend/database/migrations/2026_08_12_130003_make_invoice_number_nullable_on_invoices_table.php` (neu)
  - `backend/database/migrations/2026_08_12_130004_add_original_invoice_id_to_invoices_table.php` (neu)
  - `backend/app/Models/Invoice.php` (Relationen `originalInvoice()`,
    `cancellationInvoice()`, `$fillable` um `original_invoice_id`
    erweitern)
- **Abhängigkeiten:** T01 (Migrations-Reihenfolge M1 → M2 → M3 → M4, siehe
  `design.md`; M3/M4 müssen nach der SQLite-Rebuild-Migration M1
  einsortiert sein, damit sie als einfache additive
  `Schema::table()`-Migrationen laufen können, siehe `design.md`
  Abschnitt "Migrationen")
- **Beschreibung:**
  **Migration M3:** `$table->string('invoice_number')->nullable()
  ->change();` — kein `doctrine/dbal` nötig (Laravel 11, siehe
  `design.md` Context). Der bestehende `unique()`-Index bleibt
  unverändert (Mehrfach-NULL ist auf MySQL, PostgreSQL und SQLite
  zulässig).

  **Migration M4:** `$table->foreignId('original_invoice_id')
  ->nullable()->after('invoice_number')->constrained('invoices')
  ->nullOnDelete();` plus Index.

  **`Invoice`-Model ergänzen:**
  ```php
  public function originalInvoice(): BelongsTo
  {
      return $this->belongsTo(Invoice::class, 'original_invoice_id');
  }

  public function cancellationInvoice(): HasOne
  {
      return $this->hasOne(Invoice::class, 'original_invoice_id');
  }
  ```
  `$fillable` um `'original_invoice_id'` ergänzen
  (`backend/app/Models/Invoice.php:47-56`).
- **Akzeptanzkriterien:**
  - [x] Migrationen laufen fehlerfrei auf SQLite (Test-Suite), MySQL und
    PostgreSQL (lokale Docker-Läufe, siehe CLAUDE.md Abschnitt 7.1).
  - [x] `Invoice::create([...])` **ohne** `invoice_number` ist möglich
    (Spalte akzeptiert `NULL`), zwei solcher Entwürfe gleichzeitig
    verletzen **nicht** den Unique-Index.
  - [x] `$invoice->originalInvoice` und `$invoice->cancellationInvoice`
    liefern die jeweils verknüpfte Rechnung bzw. `null`, wenn keine
    Verknüpfung besteht.
  - [x] Löschen einer Original-Rechnung mit verknüpfter Stornorechnung
    setzt `original_invoice_id` der Stornorechnung auf `NULL`
    (`nullOnDelete()`), statt einen FK-Fehler zu werfen.
  - [x] `composer qa` läuft grün.

---

## T03: Service `InvoiceNumberGenerator` + Requests anpassen (keine Nummer/kein Status bei Erstellung, kein Auto-Mail)

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Services/InvoiceNumberGenerator.php` (neu)
  - `backend/app/Http/Requests/StoreInvoiceRequest.php`
  - `backend/app/Http/Requests/UpdateInvoiceRequest.php`
  - `backend/app/Http/Controllers/Api/InvoiceController.php`
    (`store()`-Methode: Event-Dispatch entfernen)
- **Abhängigkeiten:** T02 (nullable `invoice_number`)
- **Beschreibung:**
  **`InvoiceNumberGenerator`** (analog `app/Services/CourseSessionService.php`
  als Stilvorbild): extrahiert die Logik aus
  `StoreInvoiceRequest::generateInvoiceNumber()`
  (`backend/app/Http/Requests/StoreInvoiceRequest.php:103-118`) in eine
  öffentliche Methode `generate(): string`. Kapselt die Abfrage in
  `DB::transaction()` mit `Invoice::where('invoice_number', 'like',
  "RE-{$year}-%")->orderByDesc('invoice_number')->lockForUpdate()
  ->first()` (siehe `design.md` Decision D2, Concurrency-Sicherheit).
  Format bleibt `RE-{Jahr}-{4-stellig, zero-padded}`.

  **`StoreInvoiceRequest`:**
  - `rules()`: Regel für `'status'` (Zeile 33) entfernen.
  - `validatedSnakeCase()`: `'invoice_number' => $invoiceNumber`-Zeile
    (85) und den Aufruf von `generateInvoiceNumber()` entfernen; Rückgabe
    setzt `'status' => 'draft'` fest (nicht mehr aus `$validated`
    abgeleitet).
  - Private Methode `generateInvoiceNumber()` (Zeilen 103-118) vollständig
    entfernen.

  **`UpdateInvoiceRequest`:**
  - `rules()`: Regel für `'status'` (Zeile 28) entfernen.
  - `validatedSnakeCase()`: den `if (isset($validated['status']))`-Block
    (Zeilen 62-64) entfernen.
  - `attributes()`: Eintrag `'status' => 'Status'` (Zeile 44) entfernen.

  **`InvoiceController::store()`:** Zeile 140
  (`InvoiceWasCreated::dispatch($invoice);`) inkl. des zugehörigen
  Kommentars (Zeile 139) entfernen. Der `use
  App\Events\InvoiceWasCreated;`-Import (Zeile 7) entfällt, **falls** er
  nirgends sonst in der Datei benötigt wird (prüfen). Event-Klasse
  `App\Events\InvoiceWasCreated`, Listener
  `App\Listeners\SendInvoiceCreatedEmail` und Mailable
  `App\Mail\InvoiceCreated` **nicht löschen** — sie bleiben für Change 2
  bestehen (siehe `design.md`/`proposal.md`).
- **Akzeptanzkriterien:**
  - [x] `POST /api/v1/invoices` erzeugt eine Rechnung mit
    `invoice_number = null` und `status = 'draft'`, unabhängig davon, ob
    `status` im Request-Body mitgeschickt wird.
  - [x] Ein im Request-Body mitgeschicktes `status`-Feld bei `POST` wird
    ignoriert (kein Validierungsfehler, aber auch keine Wirkung — die
    Rechnung bleibt `draft`).
  - [x] `PUT /api/v1/invoices/{id}` mit `status` im Body liefert
    **keinen** Validierungsfehler mehr für das Feld selbst, aber `status`
    wird **nicht** übernommen (kein `$data['status']`-Eintrag mehr in
    `validatedSnakeCase()`).
  - [x] Erstellen einer Rechnung (egal welcher Status) löst **keine**
    E-Mail mehr aus (`Mail::fake()`-Assertion: `Mail::assertNothingSent()`
    bzw. `Mail::assertNothingQueued()`).
  - [x] `InvoiceNumberGenerator::generate()` liefert bei leerer
    `invoices`-Tabelle `RE-{aktuelles Jahr}-0001`, bei vorhandenen
    Nummern die nächsthöhere vierstellige Zahl.
  - [ ] Zwei parallele Aufrufe von `InvoiceNumberGenerator::generate()`
    (simuliert z. B. über zwei Datensätze in einer Transaktion oder einen
    gezielten Concurrency-Test) erzeugen **keine** doppelte Nummer.
    **Nicht vollständig erfüllt bei wörtlicher Umsetzung von D2** — siehe
    "Wichtiger Befund" in `task-T03.notes.md` (leere Ergebnismenge lässt
    sich per `lockForUpdate()` nicht sperren; Datenintegrität bleibt durch
    den bestehenden Unique-Index gewahrt, aber echte Gleichzeitigkeit kann
    zu einer `UniqueConstraintViolationException` statt einer sauberen
    Serialisierung führen). Offene Entscheidung für Architekt/Skeptiker
    vor T04/T05.
  - [x] `composer qa` läuft grün.

---

## T04: `InvoiceController::finalize()` — Entwurf → Offen mit Nummernvergabe

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Controllers/Api/InvoiceController.php`
  - `backend/app/Policies/InvoicePolicy.php`
  - `backend/routes/api.php`
- **Abhängigkeiten:** T02, T03
- **Beschreibung:**
  Neue Route `Route::post('/invoices/{invoice}/finalize',
  [InvoiceController::class, 'finalize']);` direkt unterhalb der
  bestehenden `mark-paid`-Route (`backend/routes/api.php:182`).

  Neue Policy-Methode:
  ```php
  public function finalize(User $user, Invoice $invoice): bool
  {
      return $user->isAdminOrTrainer() && $invoice->status === 'draft';
  }
  ```
  (Analog zum bestehenden Muster in `InvoicePolicy.php:52-56`.)

  Neue Controller-Methode (Stilvorbild `markAsPaid()`,
  `backend/app/Http/Controllers/Api/InvoiceController.php:192-208`):
  ```php
  public function finalize(Invoice $invoice, InvoiceNumberGenerator $numberGenerator): InvoiceResource|JsonResponse
  {
      $this->authorize('finalize', $invoice);

      if ($invoice->status !== 'draft') {
          return response()->json([
              'message' => 'Nur Entwürfe können freigegeben werden.',
          ], 422);
      }

      $invoice->update([
          'invoice_number' => $numberGenerator->generate(),
          'status' => 'sent',
      ]);

      return new InvoiceResource($invoice->fresh(['customer.user', 'items', 'payments']));
  }
  ```
  **Kein** Mail-Dispatch in dieser Methode (siehe `design.md` Decision D1
  — Versand ist explizit Change 2).
- **Akzeptanzkriterien:**
  - [x] `POST /api/v1/invoices/{id}/finalize` für eine Entwurfsrechnung
    liefert HTTP 200, `status = 'sent'` und eine nicht-leere
    `invoiceNumber` im Format `RE-{Jahr}-{4-stellig}`.
  - [x] Wiederholter Aufruf auf dieselbe (jetzt offene) Rechnung liefert
    HTTP 422 mit passender Fehlermeldung, ohne die Nummer erneut zu
    ändern. *(Erfordert, dass `InvoicePolicy::finalize()` bewusst nur die
    Rolle prüft statt zusätzlich `status === 'draft'` wie im
    Code-Beispiel oben — siehe "Abweichungen von der
    Task-Beschreibung" in `task-T04.notes.md`, sonst würde der zweite
    Aufruf per `authorize()` mit HTTP 403 statt HTTP 422 abgewiesen.)*
  - [x] Kunde erhält HTTP 403 bei Aufruf des Endpunkts.
  - [x] Nach `finalize()` wird **keine** E-Mail versendet
    (`Mail::assertNothingSent()`).
  - [x] `composer qa` läuft grün.
  - [x] **Zusätzlich (über die ursprüngliche Task-Beschreibung hinaus,
    User-Entscheidung nach dem T03-Befund zur Concurrency-Lücke in
    `InvoiceNumberGenerator::generate()`):** Retry-on-Conflict in
    `InvoiceController::finalize()` — bei einer
    `UniqueConstraintViolationException` beim `$invoice->update([...])`
    wird Nummerngenerierung + Update bis zu 3 Mal wiederholt, bevor der
    Fehler weitergereicht wird. Siehe "Retry-on-Conflict" in
    `task-T04.notes.md` für Details zur (treiberunabhängigen)
    Kollisionserkennung und den gezielten Retry-Test.

---

## T05: `InvoiceController::cancel()` — Stornorechnung erzeugen

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Http/Controllers/Api/InvoiceController.php`
  - `backend/app/Policies/InvoicePolicy.php`
  - `backend/routes/api.php`
- **Abhängigkeiten:** T02, T03
- **Beschreibung:**
  Neue Route `Route::post('/invoices/{invoice}/cancel',
  [InvoiceController::class, 'cancel']);`.

  Neue Policy-Methode:
  ```php
  public function cancel(User $user, Invoice $invoice): bool
  {
      return $user->isAdminOrTrainer()
          && in_array($invoice->status, ['sent', 'paid', 'reminded'], true)
          && $invoice->original_invoice_id === null;
  }
  ```
  (Verhindert Storno von Entwürfen — die werden gelöscht, nicht
  storniert, siehe `Anforderung-Rechnungsworkflow.txt:6-12` — und Storno
  einer bereits stornierten bzw. selbst schon als Storno erzeugten
  Rechnung, siehe `design.md` Decision D5/Non-Goals.)

  Neue Controller-Methode, in einer `DB::transaction()`:
  1. Autorisierung prüfen (`$this->authorize('cancel', $invoice)`).
  2. Neue `Invoice` anlegen: `customer_id` = Original, `invoice_number`
     = `$numberGenerator->generate()`, `status = 'sent'`,
     `original_invoice_id = $invoice->id`, `issue_date = today()`,
     `due_date = today()` (siehe `design.md` Decision D5 —
     Stornorechnung ist sofort ausgeglichen), `total_amount =
     -$invoice->total_amount`, `notes = "Storno zu Rechnung
     {$invoice->invoice_number}"`.
  3. Für jedes `InvoiceItem` der Original-Rechnung einen neuen
     `InvoiceItem`-Datensatz auf der Stornorechnung anlegen mit
     `quantity = -$item->quantity`, unverändertem `unit_price` und
     `tax_rate`, `amount = -$item->amount` (siehe `design.md` Decision
     D6).
  4. Original-Rechnung auf `status = 'cancelled'` setzen.
  5. Beide Rechnungen mit `['customer.user', 'items', 'payments']`
     laden, die neue Stornorechnung als `InvoiceResource` zurückgeben.
- **Akzeptanzkriterien:**
  - [x] `POST /api/v1/invoices/{id}/cancel` für eine Rechnung im Status
    `sent` oder `paid` liefert HTTP 200/201 mit der neu erzeugten
    Stornorechnung (`status = 'sent'`, `originalInvoiceId` = ID der
    Original-Rechnung, `totalAmount` negativ, betragsmäßig identisch zur
    Original-Rechnung).
  - [x] Die Summe aus `original.totalAmount + storno.totalAmount` ergibt
    `0`.
  - [x] Jede `InvoiceItem`-Position der Stornorechnung hat negierte
    `quantity`/`amount` gegenüber der entsprechenden Original-Position.
  - [x] Nach dem Aufruf hat die Original-Rechnung `status = 'cancelled'`.
  - [x] Aufruf für eine Entwurfsrechnung (`status = 'draft'`) liefert
    HTTP 403 (Policy verweigert).
  - [x] Aufruf für eine bereits stornierte Rechnung liefert HTTP 403.
  - [x] Aufruf für eine Stornorechnung selbst (`original_invoice_id !=
    null`) liefert HTTP 403.
  - [x] Kunde erhält HTTP 403 bei Aufruf des Endpunkts.
  - [x] Bricht die Erstellung der Storno-Items aus irgendeinem Grund ab,
    bleibt die Original-Rechnung unverändert im Ausgangsstatus
    (Transaktions-Rollback).
  - [x] `composer qa` läuft grün.

---

## T06: Policy verschärfen (Entwurf-only Update/Delete) + Kunden-Sichtbarkeit + `InvoiceResource` erweitern

- **Agent:** dev-php
- **Dateien:**
  - `backend/app/Policies/InvoicePolicy.php`
  - `backend/app/Http/Controllers/Api/InvoiceController.php` (`index()`)
  - `backend/app/Http/Resources/InvoiceResource.php`
- **Abhängigkeiten:** T01, T02
- **Beschreibung:**
  **`InvoicePolicy::view()`** (`backend/app/Policies/InvoicePolicy.php:29-38`)
  um Status-Prüfung für Kunden erweitern:
  ```php
  public function view(User $user, Invoice $invoice): bool
  {
      if ($user->isAdminOrTrainer()) {
          return true;
      }

      if (! $user->isCustomer() || $invoice->customer->user_id !== $user->id) {
          return false;
      }

      return in_array($invoice->status, ['sent', 'paid', 'overdue', 'reminded'], true);
  }
  ```
  (`overdue` bleibt in der Whitelist für evtl. noch vorhandene
  Alt-Datensätze, siehe `design.md` Decision D3 — praktisch wird dieser
  Status laut Context nicht mehr aktiv geschrieben.)

  **`InvoicePolicy::update()`** (Zeile 52-56) und **`delete()`**
  (Zeile 61-65) um Status-Prüfung erweitern:
  ```php
  public function update(User $user, Invoice $invoice): bool
  {
      return $user->isAdminOrTrainer() && $invoice->status === 'draft';
  }

  public function delete(User $user, Invoice $invoice): bool
  {
      return $user->isAdmin() && $invoice->status === 'draft';
  }
  ```

  **`InvoiceController::index()`** (Zeile 47-56): im
  `elseif ($user->isCustomer())`-Zweig zusätzlich
  `$query->whereIn('status', ['sent', 'paid', 'overdue', 'reminded'])`
  ergänzen (nach der bestehenden `where('customer_id', ...)`-Bedingung).

  **`InvoiceResource::toArray()`** (Zeile 25-59) um neue Felder
  ergänzen, nach dem bestehenden `isOverdue`-Feld (Zeile 50):
  ```php
  'remindedAt' => $this->reminded_at?->toDateString(),
  'dunningLevel' => $this->dunning_level,
  'originalInvoiceId' => $this->original_invoice_id,
  'originalInvoiceNumber' => $this->whenLoaded('originalInvoice', fn () => $this->originalInvoice?->invoice_number),
  'cancellationInvoiceId' => $this->whenLoaded('cancellationInvoice', fn () => $this->cancellationInvoice?->id),
  'cancellationInvoiceNumber' => $this->whenLoaded('cancellationInvoice', fn () => $this->cancellationInvoice?->invoice_number),
  ```
  `InvoiceController::index()`/`show()`/`finalize()`/`cancel()` laden
  zusätzlich `originalInvoice`, `cancellationInvoice`, `dunnings`, damit
  diese Felder befüllt sind (bestehende `->with([...])`-Aufrufe
  entsprechend ergänzen, z. B. Zeile 37, 151, 231).
- **Akzeptanzkriterien:**
  - [x] Kunde kann per `GET /api/v1/invoices` und
    `GET /api/v1/invoices/{id}` keine Rechnungen mit `status = 'draft'`
    oder `status = 'cancelled'` sehen (weder in der Liste noch per
    direktem Abruf der eigenen Rechnungs-ID → HTTP 403).
  - [x] Admin/Trainer sehen weiterhin alle Status.
  - [x] `PUT`/`DELETE` auf eine Rechnung mit `status != 'draft'` liefert
    HTTP 403, auch für Admin.
  - [x] `PUT`/`DELETE` auf eine Entwurfsrechnung funktioniert weiterhin
    wie bisher (bestehende Tests `admin can delete invoice without
    payments`, `trainer can update invoice`-Grundfall bleiben mit
    Draft-Fixtures grün — Status-Feld-Erwartung siehe T03).
  - [x] `InvoiceResource` liefert `remindedAt`, `dunningLevel`,
    `originalInvoiceId`, `originalInvoiceNumber`,
    `cancellationInvoiceId`, `cancellationInvoiceNumber` korrekt befüllt
    bzw. `null`, wenn nicht zutreffend.
  - [x] `composer qa` läuft grün.

---

## T07: `InvoicesView.vue` — Buttons, Badges und Zahlungs-/Mahndatum pro Status

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/views/invoices/InvoicesView.vue`
- **Abhängigkeiten:** T04, T05, T06
- **Beschreibung:**
  **Status-Filter-Select** (Zeile 6-13): neue Option `<option
  value="reminded">Gemahnt</option>` ergänzen.

  **Aktionen-Spalte** (Zeile 84-88) ersetzen durch status-abhängige
  Buttons:
  - `draft`: PDF (bestehend), Bearbeiten (bestehend, Zeile 86), **neu:**
    Löschen (`deleteInvoice(invoice)`, mit `confirm()`-Dialog analog
    `markAsPaid()` Zeile 246-261, ruft `DELETE
    /api/v1/invoices/{id}`), **neu:** Freigeben (`finalizeInvoice(invoice)`,
    `confirm()`-Dialog, ruft `POST /api/v1/invoices/{id}/finalize`).
  - `sent`: PDF, **neu:** Senden — sichtbar, aber `disabled` mit
    `title="Versand-Dialog folgt in einem späteren Update"` (siehe
    `design.md` Decision D1 — keine Klick-Logik in diesem Change), **neu:**
    Stornieren (`cancelInvoice(invoice)`, `confirm()`-Dialog, ruft `POST
    /api/v1/invoices/{id}/cancel`, danach `loadInvoices()`).
  - `paid`: PDF, Stornieren (wie oben), **zusätzlich** Anzeige des
    Zahlungseingangsdatums (`invoice.paidDate`, vorhandenes Feld aus
    `InvoiceResource`) in der Tabellenzeile (z. B. als zusätzliche Zeile
    unter dem Betrag oder als eigene Spalte — Umsetzung nach bestehendem
    Tabellen-Layout-Stil).
  - `reminded`: wie `sent` (PDF, Senden-Stub, Stornieren), zusätzlich
    Anzeige `invoice.remindedAt` als Mahndatum.
  - `overdue`: wie `sent`, zusätzlich visuelle "Überfällig"-Markierung
    (bereits über `invoice.isOverdue` aus `InvoiceResource` verfügbar,
    unabhängig vom persistierten Status — siehe `design.md` Context,
    `isOverdue()` ist datumsbasiert; die Markierung muss daher **immer**
    anhand von `invoice.isOverdue === true` erfolgen, nicht anhand des
    `status`-Strings).
  - `cancelled`: nur PDF (kein weiterer Button).
  - Rechnungen mit `invoice.originalInvoiceId !== null` (Stornorechnungen)
    zeigen **keinen** Stornieren-Button, unabhängig vom eigenen Status
    (Non-Goal aus `design.md`: Storno-von-Storno).
  - Alle neuen Aktions-Buttons: nur sichtbar für `!authStore.isCustomer`
    (bestehendes Muster, Zeile 86-87).

  **`invoiceStatusClass()`/`invoiceStatusLabel()`** (Zeile 273-293): neuen
  Eintrag `reminded: 'bg-orange-100 text-orange-800 dark:bg-orange-900
  dark:text-orange-200'` / `reminded: 'Gemahnt'` ergänzen.

  **Neue Funktionen** `deleteInvoice()`, `finalizeInvoice()`,
  `cancelInvoice()` nach dem Vorbild von `markAsPaid()` (Zeile 246-261):
  `confirm()`-Dialog, `try/catch` mit `handleApiError()`, `showSuccess()`,
  `loadInvoices()` danach.
- **Akzeptanzkriterien:**
  - [x] Für jeden Status (`draft`, `sent`, `paid`, `overdue`, `reminded`,
    `cancelled`) werden genau die in der Beschreibung genannten Buttons
    angezeigt (manuell/per Vitest-Komponententest mit gemocktem
    `invoice`-Objekt je Status geprüft).
  - [x] Löschen, Freigeben, Stornieren lösen die korrekten API-Aufrufe
    aus und aktualisieren die Liste danach.
  - [x] Der Senden-Button ist im Status `sent`/`reminded`/`overdue`
    sichtbar, aber deaktiviert (kein Klick-Handler löst einen API-Aufruf
    aus).
  - [x] Zahlungseingangs- und Mahndatum werden korrekt formatiert
    angezeigt, wenn vorhanden, sonst kein Platzhalter-Fehler
    (`formatDate(null)` liefert `'-'`, bestehendes Muster Zeile 263-266).
  - [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne
    Fehler/Warnings durch.

---

## T08: `InvoiceDetailModal.vue` — gleiche Button-/Status-Logik im Detail-Modal

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/InvoiceDetailModal.vue`
- **Abhängigkeiten:** T07 (gleiche Konventionen, kann nach Fertigstellung
  von T07 zügig übertragen werden)
- **Beschreibung:**
  Spiegelt die in T07 beschriebene Button-/Badge-Logik im Detail-Modal:
  `getStatusClass()`/`getStatusLabel()` (Zeile 227-247) um `reminded`
  ergänzen; Buttons-Bereich (Zeile 144-158) um Löschen-, Freigeben-,
  Senden- (deaktiviert) und Stornieren-Buttons erweitern, jeweils mit
  neuen `defineEmits`-Events (`'delete'`, `'finalize'`, `'cancel'`) statt
  direkter API-Aufrufe — das Modal bleibt reines Presentation-Layer
  (bestehendes Muster: `'mark-paid'`-Event Zeile 185, verarbeitet von
  `InvoicesView.vue::markAsPaid()`). `InvoicesView.vue` muss die neuen
  Events auf die in T07 erstellten Funktionen mappen (analog zur
  bestehenden `@mark-paid="markAsPaid"`-Bindung, Zeile 119).
  Zusätzlich: Anzeige der Storno-Referenz, falls vorhanden
  (`invoice.originalInvoiceNumber` bzw.
  `invoice.cancellationInvoiceNumber`) als Info-Zeile im
  "Rechnungsinformationen"-Block (Zeile 40-64).
- **Akzeptanzkriterien:**
  - [x] Detail-Modal zeigt dieselben status-abhängigen Buttons wie die
    Listenansicht (T07), inklusive Deaktivierung des Senden-Buttons.
  - [x] Storno-Referenz (Original ↔ Stornorechnung) wird angezeigt, wenn
    `originalInvoiceNumber` oder `cancellationInvoiceNumber` gesetzt ist.
  - [x] Alle neuen Events werden korrekt von `InvoicesView.vue`
    verarbeitet (Löschen/Freigeben/Stornieren funktionieren identisch,
    ob über Listenzeile oder Detail-Modal ausgelöst).
  - [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne
    Fehler/Warnings durch.

---

## T09: `InvoiceFormModal.vue` — Status-Dropdown entfernen, Payload anpassen

- **Agent:** dev-typescript
- **Dateien:**
  - `frontend/src/components/InvoiceFormModal.vue`
- **Abhängigkeiten:** T03 (Backend akzeptiert `status` nicht mehr aus dem
  Payload — Soft-Dependency, da der Datenvertrag bereits durch
  `design.md` Decision D4 feststeht; die Umsetzung kann parallel zu T03
  starten)
- **Beschreibung:**
  Status-Auswahlfeld (Zeile 57-65) vollständig entfernen (das
  Grid-Layout Zeile 51-66 entsprechend auf ein einspaltiges Feld
  "Fälligkeitsdatum" reduzieren oder mit einem anderen sinnvollen Feld
  auffüllen — Layout-Entscheidung liegt bei `dev-typescript`, solange
  kein Status-Feld mehr angezeigt wird). `form`-Objekt (Zeile 184-193,
  Default Zeile 188, `watch`-Handler Zeile 200-217 insb. Zeile 206,
  `resetForm()` Zeile 284-293 insb. Zeile 289): `status`-Property
  entfernen. `handleSubmit()`-Payload (Zeile 295-327, insb. Zeile 303):
  `status: form.value.status`-Zeile entfernen — weder beim Erstellen
  noch beim Aktualisieren wird `status` mitgeschickt.
- **Akzeptanzkriterien:**
  - [x] Formular zeigt kein Status-Auswahlfeld mehr an.
  - [x] `POST`/`PUT`-Requests an `/api/v1/invoices` enthalten kein
    `status`-Feld im Body mehr.
  - [x] Erstellen einer neuen Rechnung über das Formular funktioniert
    weiterhin (Rechnung landet serverseitig im Status `draft`, siehe
    T03).
  - [x] Bearbeiten einer Entwurfsrechnung über das Formular funktioniert
    weiterhin unverändert für die verbleibenden Felder (Kunde, Datum,
    Fälligkeitsdatum, Positionen, Notizen).
  - [x] `npm run lint`, `npm run test`, `npm run build` laufen ohne
    Fehler/Warnings durch.
