# Notes: T10 — Cross-Cutting QA-Durchlauf

## Status

Durchgeführt. Reiner Verifikationstask, keine Produktivcode-Änderungen.
Alle drei Akzeptanzkriterien erfüllt. Ein nicht-blockierender Befund zu
Frontend-ESLint-Warnings dokumentiert (siehe unten), kein kritischer Bug
gefunden.

Ausgeführt auf dem Hauptarbeitsverzeichnis (keine Worktree-Isolation,
Branch `feature/add-invoice-dunning-dashboard`, Commit `d81946a` zu
Beginn des Laufs). Der laufende `docker compose`-Stack (Service `php`,
nicht `app`) mountet `backend/` direkt aus diesem Checkout, `docker
compose exec` testet also tatsächlich den zu prüfenden Code.

## 1. Backend — vier Kommandos einzeln

```
docker compose exec php composer test
  → 883 passed (2751 assertions), 3 skipped
    (2× InvoicePaymentRecorderConcurrencyTest, 1× InvoiceDunningRecorder-
    ConcurrencyTest — korrekt auf SQLite übersprungen, "Benötigt eine
    echte MVCC-Datenbank", siehe PostgreSQL/MySQL-Nachweis unten)

docker compose exec php composer lint
  → PASS, 330 files

docker compose exec php composer stan
  → [OK] No errors, 215/215

docker compose exec php composer compat-check
  → keine Ausgabe, exit 0
```

Alle vier grün.

## 2. Frontend

```
docker compose exec node npx vitest run
  → 26 Testdateien, 343 Tests, alle grün (0 failed)

docker compose exec node npm run build
  → vue-tsc -b && vite build, erfolgreich, keine TypeScript-Fehler,
    647 Module transformiert, DashboardView-Chunk und alle
    Rechnungs-Views enthalten

docker compose exec node npm run lint
  → exit 0 (kein Fehler-Exit), 0 errors, 3221 warnings
```

### Warnings-Vergleich gegen Vorzustand (Akzeptanzkriterium)

Referenz laut vorherigen Task-Notes: `task-T07.notes.md` dokumentiert
für den Stand nach T07 (Commit `4250e07`) einen repo-weiten Bestand von
**"0 errors, 3186 warnings"**. Um den exakten Vorzustand zu
reproduzieren (nicht nur aus den Notes zu zitieren), wurde ein
temporäres `git worktree` auf Commit `4250e07` angelegt und dort
`npm run lint` über einen isolierten `docker run node:20-alpine`-
Container (kein Netzwerk-/Volume-Konflikt mit dem laufenden Stack)
ausgeführt:

```
git worktree add <scratch>/wt-t07 4250e07
docker run --rm -v "<scratch>/wt-t07/frontend:/app" -w /app \
  node:20-alpine sh -c "npm ci --no-audit --no-fund && npm run lint"
  → 0 errors, 3186 warnings   # bestätigt den in T07 dokumentierten Wert exakt
git worktree remove <scratch>/wt-t07 --force
```

**Ergebnis: 3221 − 3186 = 35 zusätzliche Warnings gegenüber dem
Vorzustand vor T08/T09.** Detailanalyse (Skript, siehe unten) zeigt,
dass diese ausschließlich aus drei Dateien stammen, exakt den durch T08
und T09 geänderten/neuen Dateien:

| Datei | vorher (T07) | jetzt | Diff |
|---|---|---|---|
| `frontend/src/components/InvoiceDetailModal.vue` (T08) | 126 | 140 | +14 |
| `frontend/src/views/DashboardView.vue` (T09) | 142 | 162 | +20 |
| `frontend/src/views/DashboardView.test.ts` (T09, neue Datei) | 0 | 1 | +1 |

Regel-Aufschlüsselung der Diffs (alle bereits vor T08/T09 in genau
diesen Dateien vorhandene Regel-Kategorien, keine neu eingeführte
Regel-Verletzung):

- `InvoiceDetailModal.vue`: `@typescript-eslint/no-explicit-any` (+2),
  `vue/attributes-order` (+1), `vue/max-attributes-per-line` (+5),
  `vue/singleline-html-element-content-newline` (+6)
- `DashboardView.vue`: `vue/html-self-closing` (+2),
  `vue/max-attributes-per-line` (+16),
  `vue/singleline-html-element-content-newline` (+2)
- `DashboardView.test.ts`: `@typescript-eslint/no-explicit-any` (+1)

**Einschätzung:** Kein neuer *Fehler* (weiterhin `0 errors`, `npm run
lint` verlässt sich weiterhin mit Exit-Code 0 — kein `--max-warnings`
im Projekt konfiguriert, siehe `frontend/package.json`). Die
zusätzlichen 35 Warnings sind ausschließlich Formatierungs-/Stil-Regeln
(Attribut-Reihenfolge, Attribute-pro-Zeile, `any`-Nutzung), die bereits
in denselben Dateien vor T08/T09 in identischer Kategorie vorkamen —
neuer Code folgt also demselben (nicht perfekt lint-sauberen)
Bestandsstil wie der umgebende Code, führt aber keine neue Regel-
Kategorie ein. `task-T08.notes.md`/`task-T09.notes.md` dokumentieren
dies bereits auf Datei-Ebene ("nur bestehende Bestandscode-Warnungen"),
dieser Task bestätigt das jetzt zusätzlich repo-weit mit exakten
Zahlen. **Werte dies nicht als "grün" im Sinne von "keine einzige neue
Warning", sondern als "keine neue Fehlerkategorie, Anzahl steigt linear
mit neuem Code im etablierten Bestandsstil"** — das ist nach meiner
Einschätzung keine Blocker-Abweichung vom Akzeptanzkriterium (kein
CI-Fail, keine neue Lint-Regel verletzt), aber ein dokumentierenswerter
Befund für Reviewer/Architekt. Empfehlung: kein Nacharbeits-Bedarf für
T10 selbst (T10 ändert keinen Produktivcode), aber optional als Hinweis
für künftige Changes, ob eine `--max-warnings`-Schwelle oder
`eslint --fix` auf neu berührten Zeilen sinnvoll wäre (separate
Entscheidung, außerhalb des T10-Scopes).

Analyse-Methode: Python-Skript hat beide `npm run lint`-Rohausgaben
(`t07` vs. aktueller Stand) geparst, pro Datei die Anzahl der
`warning`-Zeilen gezählt und verglichen; anschließend pro Datei die
Regel-Bezeichner (letztes Token je Warnzeile) aggregiert und verglichen.
Keine dauerhaften Artefakte im Repo hinterlassen (nur im Scratchpad).

## 3. MySQL/PostgreSQL-Migrationslauf (wichtigster Teil)

### Befund: `docker-compose.mysql.yml` existiert nicht

Bestätigt (`ls docker-compose*.yml` im Repo-Root zeigt ausschließlich
`docker-compose.yml`) — deckt sich mit der bereits in
`task-T01.notes.md` und `task-T02.notes.md` dokumentierten bekannten
Lücke. Der in `tasks.md` T10 wörtlich vorgeschlagene Befehl `docker
compose -f docker-compose.yml -f docker-compose.mysql.yml up -d` ist
daher **nicht ausführbar**. Wie in T02 vorgemacht: Ausweichen auf einen
direkten, isolierten Ansatz gegen die bereits laufende
`dog-school-postgres`-Instanz statt eine Compose-Datei zu erfinden.
Zusätzlich (über das in T02 erprobte Muster hinaus) wurde für MySQL ein
temporärer `docker run`-Container auf Basis des bereits lokal
vorhandenen `mysql:8.0`-Images aufgesetzt (siehe Abschnitt 3.3) — das
schließt die MySQL-Lücke für diesen QA-Durchlauf, ohne
`docker-compose.mysql.yml` nachzubauen (das bleibt Aufgabe des
separaten, noch offenen `add-db-matrix-ci`-Changes, wie in
`design.md`/`task-T01.notes.md` bereits vermerkt).

### 3.1 PostgreSQL — `migrate:fresh`

Dedizierte Test-DB `dog_school_test` (bereits vorhanden auf
`dog-school-postgres`, aus `add-invoice-payment-entry` T02, laut
`task-T02.notes.md` dieses Changes weiterverwendet):

```
docker compose exec -e DB_CONNECTION=pgsql -e DB_HOST=postgres \
  -e DB_PORT=5432 -e DB_DATABASE=dog_school_test \
  -e DB_USERNAME=dog_school_user -e DB_PASSWORD=dog_school_password \
  php php artisan migrate:fresh --force
```

Ergebnis: alle 49 Migrationen erfolgreich, inklusive
`2026_08_14_140001_add_document_type_to_invoices_table` und
`2026_08_14_140002_add_fee_invoice_id_to_invoice_dunnings_table`.

### 3.2 PostgreSQL — expliziter End-to-End-Backfill-Regressionstest

Ergänzend zum bereits bestehenden Pest-Test
(`DatabaseStructureTest.php`, `'document_type backfill sets
cancellation on pre-existing cancellation invoices'`, läuft
standardmäßig gegen SQLite) wurde derselbe Ablauf **manuell gegen
echtes PostgreSQL** reproduziert, um Grid-Sperren/Constraints der realen
Datenbank statt SQLite zu prüfen:

1. `migrate:rollback --path=database/migrations/2026_08_14_140001_add_document_type_to_invoices_table.php`
   → Schema-Stand vor der `document_type`-Spalte wiederhergestellt
   (`invoice_dunnings.fee_invoice_id`-Migration bleibt unberührt, da
   andere Datei).
2. Per `php artisan tinker` (Skript aus dem Scratchpad, temporär nach
   `backend/t10_backfill_probe.php` kopiert, nach dem Lauf wieder
   gelöscht — kein Commit) eine Original-Rechnung per
   `Invoice::factory()->create(['status' => 'sent'])` sowie direkt per
   `DB::table('invoices')->insert([...])` eine simulierte
   Stornorechnung mit gesetztem `original_invoice_id`, aber (auf dem
   damaligen Schema-Stand zwangsläufig) ohne `document_type`-Spalte
   angelegt — 1:1 dasselbe Muster wie im bestehenden Pest-Test.
3. `migrate --path=database/migrations/2026_08_14_140001_...` erneut
   ausgeführt.
4. Verifikation: `Invoice::where('original_invoice_id', 1)->first()->document_type`
   → `"cancellation"`.

**Ergebnis: bestätigt.** Backfill-Logik aus T01 funktioniert
unverändert korrekt gegen echtes PostgreSQL, nicht nur gegen SQLite.

Danach `migrate:fresh --force` gegen `dog_school_test` (PostgreSQL) zur
Bereinigung, damit andere Agenten/Läufe einen sauberen Ausgangszustand
vorfinden.

### 3.3 PostgreSQL — vollständige `composer test`-Suite

```
docker compose exec -e DB_CONNECTION=pgsql -e DB_HOST=postgres \
  -e DB_PORT=5432 -e DB_DATABASE=dog_school_test \
  -e DB_USERNAME=dog_school_user -e DB_PASSWORD=dog_school_password \
  php composer test
```

Ergebnis: **886 passed (2765 assertions), 0 skipped, 0 failed.**
Insbesondere:

```
PASS  Tests\Concurrency\Domain\Invoice\InvoiceDunningRecorderConcurrencyTest
✓ it lässt bei zwei nahezu gleichzeitigen mahnungs-triggern für dieselbe rechnung keine doppelte stufe zu   0.41s

PASS  Tests\Concurrency\Domain\Payment\InvoicePaymentRecorderConcurrencyTest
✓ it verliert keine teilzahlung wenn zwei zahlungen nahezu gleichzeitig für dieselbe rechnung erfasst werden   0.40s
✓ it lehnt die zweite von zwei nahezu gleichzeitigen zahlungen ab, wenn deren summe den restbetrag übersteigt  0.39s
```

Beide Concurrency-Tests **tatsächlich ausgeführt** (nicht "skipped" wie
im SQLite-Standardlauf) — explizit im Testoutput geprüft (`grep -i
"skip"` auf der vollständigen Rohausgabe liefert keinen Treffer
außerhalb harmloser Testnamen wie `"…skipped-liste…"`). Damit ist das
in T02 geforderte PostgreSQL-Concurrency-Kriterium für diesen
Cross-Cutting-Durchlauf erneut bestätigt (zusätzlich zur bereits in
`task-T02.notes.md` dokumentierten Erstverifikation).

Danach `migrate:fresh --force` gegen `dog_school_test` zur Bereinigung.

### 3.4 MySQL — zusätzlich durchgeführt (über die AC-Mindestanforderung hinaus)

Da kein `docker-compose.mysql.yml` existiert, aber die Images
`mysql:8.0`/`mysql:8.4` bereits lokal vorhanden waren, wurde ein
temporärer, isolierter MySQL-Container aufgesetzt statt die
MySQL-Lücke nur zu dokumentieren:

```
docker run -d --name t10-mysql-probe \
  --network dog-school-app_dog-school-network \
  -e MYSQL_ROOT_PASSWORD=root_password \
  -e MYSQL_DATABASE=dog_school_test \
  -e MYSQL_USER=dog_school_user \
  -e MYSQL_PASSWORD=dog_school_password \
  mysql:8.0 --default-authentication-plugin=mysql_native_password
# healthcheck via mysqladmin ping, ready nach ca. 20s
```

Danach dieselben drei Schritte wie gegen PostgreSQL (3.1–3.3), jeweils
mit `DB_CONNECTION=mysql -e DB_HOST=t10-mysql-probe -e DB_PORT=3306`:

- `migrate:fresh --force` → alle 49 Migrationen erfolgreich.
- Backfill-Regressionstest (identisches Skript) → `document_type` nach
  erneuter Migration korrekt `"cancellation"`.
- `migrate:fresh --force` (Reset) → `composer test` vollständig:
  **886 passed (2765 assertions), 0 skipped, 0 failed**, beide
  Concurrency-Tests (`InvoiceDunningRecorderConcurrencyTest`,
  `InvoicePaymentRecorderConcurrencyTest`) liefen tatsächlich (nicht
  übersprungen) und grün — MySQLs `SELECT ... FOR UPDATE`
  (`lockForUpdate()`) verhält sich hier wie erwartet äquivalent zu
  PostgreSQL.

Container danach entfernt (`docker rm -f t10-mysql-probe`), kein
dauerhaftes Artefakt im Repo oder in `docker-compose.yml` hinterlassen.

**Damit ist die in `tasks.md` als "falls ohne unverhältnismäßigen
Aufwand machbar" formulierte MySQL-Zusatzprüfung tatsächlich
durchgeführt worden**, nicht nur als bekannte Lücke dokumentiert — der
Aufwand war mit einem einzelnen `docker run` plus Healthcheck-Warteschleife
gering, da die Images bereits lokal vorhanden waren.

**Weiterhin offene, bewusst nicht in T10 behobene Lücke:** Es existiert
weiterhin keine dauerhafte `docker-compose.mysql.yml` bzw. keine
CI-Matrix gegen MySQL (siehe CLAUDE.md Abschnitt 4.2 "Migrations-Test").
Das bleibt wie in `task-T01.notes.md`/`task-T02.notes.md` bereits
vermerkt Aufgabe des eigenständigen, noch nicht begonnenen
`add-db-matrix-ci`-Changes — T10 hat das ad-hoc für diesen einen
QA-Durchlauf umgangen, ohne die strukturelle Lücke zu schließen.

## 4. Kritische Funde

**Keine.** Insbesondere: der in `task-T01.notes.md` als "wichtiger
Befund" dokumentierte offene Punkt ("`InvoiceController::cancel()`
setzt `document_type` auf neuen Stornorechnungen nicht") wurde
zwischenzeitlich behoben — verifiziert:
`backend/app/Http/Controllers/Api/InvoiceController.php:352` setzt
`'document_type' => 'cancellation'` in
`createCancellationInvoiceWithRetry()`. Kein Nachzieh-Bedarf mehr.

Der einzige dokumentierte Befund ist die oben beschriebene, nicht-
blockierende Zunahme von 35 ESLint-Warnings (Abschnitt 2) — kein Bug,
kein neuer Fehler, keine neue Regel-Kategorie, konsistent mit bereits
in T08/T09 dokumentiertem Bestandsstil.

## 5. Umgebungsbereinigung

Nach Abschluss aller Läufe:

- `dog_school_test` (PostgreSQL) via `migrate:fresh --force` auf
  sauberen Stand zurückgesetzt.
- Temporärer `t10-mysql-probe`-Container entfernt.
- Temporäres `git worktree wt-t07` entfernt (`git worktree remove
  --force`), `git worktree list` zeigt danach nur noch das
  Hauptverzeichnis.
- Keine temporären PHP-Skripte im Repo verblieben (`git status
  --short` zeigt nach dem Lauf ausschließlich die bereits vor T10
  vorhandenen, nicht von T10 verursachten untracked Dateien
  `.claude/settings.json` und `Anforderung-Rechnungsworkflow.txt`).

## Zusammenfassung für Architekt (Modus B) / Reviewer / Tester

- Backend: alle vier QA-Kommandos grün, SQLite-Suite 883/883 (3 korrekt
  übersprungen), keine PHP-8.3/8.4-Kompatibilitätsverstöße.
- Frontend: Vitest 343/343 grün, Build fehlerfrei, Lint ohne Fehler
  (nur Warnings, siehe dokumentierter, nicht-blockierender
  Warnings-Zuwachs).
- DB-Portabilität: Migration inkl. `document_type`-Backfill (T01) und
  beide Concurrency-Tests (T02, `InvoiceDunningRecorderConcurrencyTest`;
  `add-invoice-payment-entry` T02, `InvoicePaymentRecorderConcurrencyTest`)
  jeweils vollständig grün gegen **sowohl PostgreSQL als auch MySQL**
  verifiziert — über die AC-Mindestanforderung (nur PostgreSQL) hinaus.
- Bekannte, außerhalb des T10-Scopes liegende strukturelle Lücke:
  `docker-compose.mysql.yml`/CI-Matrix fehlt weiterhin (Thema für
  `add-db-matrix-ci`).
- Keine kritischen Bugs gefunden. Change ist aus QA-Sicht bereit für
  Reviewer/Tester (nächster Workflow-Schritt).
