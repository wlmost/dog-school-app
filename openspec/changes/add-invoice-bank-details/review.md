# Review: add-invoice-bank-details (Full-Diff T01–T04, `feature/add-invoice-bank-details` vs. `main`)

**Gesamtempfehlung:** ok

Geprüft: `git diff main -- backend frontend` (working tree, noch nicht committet), alle vier `task-T0X.notes.md`, `proposal.md`, `design.md`, `tasks.md`, `specs/invoice-bank-details/spec.md`, `verification.md`, `TESTING.md`. Lokal nachvollzogen: `pint --test`, `phpstan analyse`, `phpcs --standard=PHPCompatibility --runtime-set testVersion 8.2`, gezielte `pest`-Läufe aller 5 neuen/betroffenen Test-Dateien (38 passed), `vue-tsc -b --noEmit`, `eslint`, `vitest run src/views/SettingsView.test.ts` (10 passed).

## Muss (blockiert Abnahme)
Keine.

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Notes-Diskrepanz, behoben]** `frontend/src/views/SettingsView.test.ts`: Der Diff enthält einen neuen `describe`-Block "Bankverbindung-Formularfelder" (5 neue Tests: Sichtbarkeit/Labels, v-model-Bindung, Anzeige geladener Werte, Default-Fallback, Übertragung beim Speichern). `task-T02.notes.md` erwähnte diese Datei ursprünglich nicht (Tests wurden erst später vom Tester-Agenten ergänzt). Inhaltlich waren die Tests korrekt und liefen grün. Nachtrag in `task-T02.notes.md` ergänzt, damit Notes und Diff deckungsgleich sind.

## Könnte (optional, Verbesserung — nicht umgesetzt, bewusst zurückgestellt)

- **[Stil/Konsistenz]** `frontend/src/views/SettingsView.vue`: `company_payment_term_weeks: 2 as number | string` weicht vom Muster des strukturell identischen Feldes `smtp_port: 587` (ohne Typ-Annotation) ab. Die Union-Typisierung ist nicht load-bearing: `loadSettings()`/`saveSettings()` casten ohnehin auf `Record<string, any>`, sodass die explizite Union am Deklarationsort keinen Compile-Zeit-Nutzen hat (`vue-tsc -b` läuft in beiden Varianten fehlerfrei). Optional: auf `2` vereinfachen (analog `smtp_port`) oder kurz kommentieren, warum die Union hier bewusst gesetzt wurde.

## Lob

- Alle drei Blade-Templates (`pdf/invoice.blade.php`, `emails/invoice-created.blade.php`, `emails/payment-reminder.blade.php`) nutzen durchgängig `{{ }}` (Auto-Escaping), keine unnötige `{!! !!}`-Ausgabe für die neuen, admin-gepflegten Bankdaten eingeführt.
- IBAN-/BIC-Regex 1:1 aus `UpdateCustomerRequest.php:61-62` übernommen statt neu erfunden (verifiziert: zeichengenau identisch mit `UpdateSettingsRequest.php:44-45`) — sauberes DRY.
- `SettingsController::determineTypeAndGroup()` fügt den `company_payment_term_weeks => 'integer'`-Fall exakt nach dem bereits etablierten Muster für `company_small_business` ein statt ein neues Muster einzuführen — per Test verifiziert, dass der Wert trotz FormData-String-Übertragung als PHP-`int` landet.
- Non-Goals sauber eingehalten: `company_name`/`company_street`/`company_city`/`company_tax_id` bleiben unverändert hartkodiert; `backend/app/Http/Controllers/Api/SettingsController.php` (totes Duplikat) wurde nachweislich nicht angefasst.
- Kein PHP-8.3/8.4-Feature verwendet; `phpcs --standard=PHPCompatibility --runtime-set testVersion 8.2` liefert keine Verstöße für alle geänderten Backend-Dateien.
- Neue Test-Dateien (`SettingsBankDetailsApiTest.php`, `InvoiceBankDetailsPdfTest.php`, `InvoiceCreatedMailBankDetailsTest.php`, `InvoiceBankDetailsBladeSourceTest.php`, `PaymentReminderEmailTest.php`) folgen `TESTING.md` konsequent: `it()`, passendes `uses()->group(...)` je Pfad, Domain-getrennte Assertion-Stile, `declare(strict_types=1)`, kein `RefreshDatabase` im Unit-Test ohne DB-Bezug.

## Sonstige geprüfte Punkte (ohne Befund)
- Kein DB-/Migrations-Impact (`settings.value` bleibt `text`), also keine MySQL/Postgres-Portabilitätsfrage.
- `UpdateSettingsRequest::authorize()` (Admin-only) unverändert, gilt auch für die neuen Felder.
- Cache-Invalidierung (`Setting::clearCache()` → `Cache::flush()`) unverändert, deckt neue Keys automatisch ab.
- Frontend-Build (`vue-tsc -b`) und Lint laufen ohne neue Fehler/Warnings-Kategorien.

Relevante Pfade: `backend/app/Http/Requests/UpdateSettingsRequest.php`, `backend/database/seeders/SettingsSeeder.php`, `backend/app/Http/Controllers/SettingsController.php`, `backend/resources/views/pdf/invoice.blade.php`, `backend/resources/views/emails/invoice-created.blade.php`, `backend/resources/views/emails/payment-reminder.blade.php`, `frontend/src/views/SettingsView.vue`, `frontend/src/views/SettingsView.test.ts`, `backend/tests/Feature/Api/SettingsBankDetailsApiTest.php`, `backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php`, `backend/tests/Feature/InvoiceCreatedMailBankDetailsTest.php`, `backend/tests/Unit/InvoiceBankDetailsBladeSourceTest.php`, `backend/tests/Feature/PaymentReminderEmailTest.php`, `openspec/changes/add-invoice-bank-details/{proposal,design,tasks,verification}.md`, `openspec/changes/add-invoice-bank-details/task-T0{1,2,3,4}.notes.md`.
