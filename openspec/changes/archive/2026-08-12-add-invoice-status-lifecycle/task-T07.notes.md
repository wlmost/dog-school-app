# T07 — `InvoicesView.vue`: Buttons, Badges, Zahlungs-/Mahndatum pro Status

## Umgesetzte Datei

- `frontend/src/views/invoices/InvoicesView.vue`
- Neu: `frontend/src/views/invoices/InvoicesView.test.ts` (Vitest-Komponententests,
  Stilvorbild `frontend/src/views/courses/CoursesView.test.ts`)

## Umsetzung

- **Status-Filter** (`InvoicesView.vue:12`): `<option value="reminded">Gemahnt</option>`
  zwischen `overdue` und `cancelled` ergänzt.
- **Status-/Badge-Zelle** (`InvoicesView.vue:80-95`): Statusbadge, zusätzliches
  "Überfällig"-Badge (**ausschließlich** anhand `invoice.isOverdue === true`,
  nicht anhand `invoice.status`, wie in `design.md` Context/Decision D3
  gefordert), sowie bedingte Anzeige `Bezahlt am {{ paidDate }}` (`status ===
  'paid'`) bzw. `Gemahnt am {{ remindedAt }}` (`status === 'reminded'`) —
  beides über das bestehende `formatDate()` (liefert `'-'` bei `null`, kein
  Sonderfall nötig).
- **Aktionen-Spalte** (`InvoicesView.vue:96-103`): status-abhängige Buttons
  über neue Helper-Funktionen `canEdit()`, `canDelete()`, `canFinalize()`,
  `canSend()`, `canCancel()` (`InvoicesView.vue:218-238`), alle mit
  `!authStore.isCustomer`-Gate:
  - `draft`: PDF, Bearbeiten, Löschen, Freigeben.
  - `sent`/`reminded`/`overdue`: PDF, Senden (disabled,
    `title="Versand-Dialog folgt in einem späteren Update"`), Stornieren.
  - `paid`: PDF, Stornieren.
  - `cancelled`: nur PDF.
  - Stornorechnungen (`invoice.originalInvoiceId` gesetzt): `canCancel()`
    liefert `false` unabhängig vom Status (`!invoice.originalInvoiceId`-Check).
- **Neue Funktionen** `deleteInvoice()`, `finalizeInvoice()`, `cancelInvoice()`
  (`InvoicesView.vue:308-357`), 1:1 nach dem `markAsPaid()`-Muster:
  `confirm()` → `try/catch` mit `apiClient`-Aufruf → `loadInvoices()` →
  `showSuccess()`/`handleApiError()`.
- **`invoiceStatusClass()`/`invoiceStatusLabel()`**: `reminded` ergänzt
  (`bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200` /
  `'Gemahnt'`), exakt wie in `tasks.md` vorgeschlagen.

## Abweichung von der wörtlichen Task-Beschreibung — "Bezahlt"-Button entfernt

Der bestehende "Bezahlt"-Button (`markAsPaid()`) war vorher für
`draft`/`sent` sichtbar. Die Task-Beschreibung listet für **jeden** Status
(`draft`, `sent`, `paid`, `reminded`, `overdue`, `cancelled`) explizit alle
anzuzeigenden Buttons auf — "Bezahlt" taucht in **keiner** dieser Listen auf,
obwohl "PDF" und "Bearbeiten" dort ausdrücklich mit `(bestehend)`
gekennzeichnet sind, um bestehende Buttons von neuen abzugrenzen. `design.md`
Impact-Abschnitt listet ebenfalls exakt "Entwurf
(PDF/Bearbeiten/Löschen/Freigeben)" und "Offen (PDF/Senden/Stornieren)" ohne
"Bezahlt". Das Akzeptanzkriterium fordert zudem wörtlich "**genau** die in
der Beschreibung genannten Buttons".

**Entscheidung:** Ich habe den "Bezahlt"-Button aus der Aktionen-Spalte
entfernt (weder für `draft` noch für `sent`/`reminded`/`overdue`), um die
Akzeptanzkriterien buchstabengetreu zu erfüllen. Die Funktion `markAsPaid()`
selbst bleibt **unverändert im Skript** erhalten, da `InvoiceDetailModal.vue`
(nicht Teil von T07, wird in T08 angepasst) sie weiterhin über
`@mark-paid="markAsPaid"` (`InvoicesView.vue:134`) auslöst — dieses Binding
darf ich nicht entfernen, ohne das Detail-Modal zu brechen.

**Spannung mit Bestandscode:** `backend/app/Policies/InvoicePolicy.php:109-121`
(`markAsPaid()`-Docblock) argumentiert explizit, dass "Bezahlt" *nur* für
`sent`/`overdue`/`reminded` sinnvoll ist (nicht für `draft`) — das würde für
eine differenziertere Lösung sprechen (Button behalten, aber nur für
`sent`/`reminded`/`overdue` zeigen). Da die Task-Beschreibung und
`design.md` aber beide **konsistent und exhaustiv** keinen "Bezahlt"-Button
für irgendeinen Status vorsehen, und das Akzeptanzkriterium das Wort "genau"
verwendet, habe ich mich für die buchstabengetreue Umsetzung entschieden.

**Bitte im Review explizit bestätigen:** Ist die vollständige Entfernung des
"Bezahlt"-Buttons aus `InvoicesView.vue` gewollt (Zahlungseingang wird erst
mit `add-invoice-payment-entry` wieder UI-seitig zugänglich), oder war das
eine Lücke in der Task-Beschreibung und der Button sollte für
`sent`/`reminded`/`overdue` erhalten bleiben? Der Backend-Endpunkt
(`POST /invoices/{id}/mark-paid`) ist von T06 unverändert nutzbar — nur der
Trigger aus der Listenansicht fehlt jetzt.

## Tests

Neue Datei `frontend/src/views/invoices/InvoicesView.test.ts`, 16 Tests
(gemocktes `invoice`-Fixture je Status, Stilvorbild `CoursesView.test.ts`):

- Status-Filter enthält "Gemahnt".
- `draft`: korrekte Buttons, `deleteInvoice()`/`finalizeInvoice()` lösen
  `DELETE`/`POST .../finalize` aus und laden neu; Abbruch bei
  `confirm() === false`.
- `sent`: korrekte Buttons, Senden ist `disabled` mit korrektem `title` und
  löst keinen API-Aufruf aus; `cancelInvoice()` löst `POST .../cancel` aus
  und lädt neu; Fehlerfall ruft `handleApiError()`.
- `paid`: PDF/Stornieren, Anzeige `Bezahlt am ...`.
- `reminded`: wie `sent`, Anzeige `Gemahnt am ...`.
- Überfällig-Markierung ausschließlich anhand `isOverdue` (unabhängig vom
  `status`-String), je ein Test für `true`/`false`.
- `cancelled`: nur PDF.
- Stornorechnung (`originalInvoiceId` gesetzt): kein Stornieren-Button.
- Kunden-Ansicht: nur PDF sichtbar.

`window.confirm` ist unter `happy-dom` (Vitest-Testumgebung, siehe
`vitest.config.ts:9`) nicht implementiert — `vi.spyOn(window, 'confirm')`
schlägt daher fehl ("can only spy on a function. Received undefined").
Verwendet wird stattdessen `vi.stubGlobal('confirm', vi.fn()...)` plus
`vi.unstubAllGlobals()` in `beforeEach`.

`formatDate()`-Assertions vergleichen gegen
`new Date(...).toLocaleDateString('de-DE')` statt eines hartkodierten
Strings, da die Docker-Node-Umgebung (`node:20-alpine`, kleine ICU-Daten)
Tage/Monate ohne führende Null ausgibt (`5.8.2026` statt `05.08.2026`) —
damit ist der Test unabhängig von der ICU-Konfiguration der Laufzeitumgebung.

## Pre-Flight-Checks (in Docker, `dog-school-node`-Container)

```
docker compose exec node sh -c "npm run test -- run"    # 21 Testdateien, 230 Tests, alle grün
docker compose exec node sh -c "npm run lint"            # 0 Fehler, 3066 Warnings (Baseline unverändert
                                                           # in Art/Kategorie: no-explicit-any,
                                                           # vue/max-attributes-per-line etc. — keine
                                                           # neuen Warning-Kategorien durch T07 eingeführt)
docker compose exec node sh -c "npm run build"            # vue-tsc -b + vite build erfolgreich, keine
                                                           # TS-Fehler
```

Lokales `npm run test`/`npm run build` außerhalb Docker schlägt auf diesem
Host fehl (`esbuild`-Binary ist `@esbuild/linux-arm64`, Host ist
`darwin-arm64` — `node_modules` stammt aus dem Docker-Volume). Alle Checks
daher wie in `CLAUDE.md` Abschnitt 7.1 gefordert innerhalb des
Node-Containers ausgeführt.

## Nicht angefasst (bewusst außerhalb T07)

- `frontend/src/components/InvoiceDetailModal.vue` — T08.
- `frontend/e2e/invoices.spec.ts` — nicht in den T07-Dateien gelistet, Playwright
  ist nicht Teil der in CLAUDE.md 7.1 geforderten Pre-Flight-Checks
  (`npm run lint`/`test`/`build`).
