# Abnahme: add-invoice-bank-details

**Status:** bereit-für-user-review

## Strukturelle Validität

```
$ openspec validate add-invoice-bank-details --strict
Change 'add-invoice-bank-details' is valid
```

## Vollständigkeit der Tasks

Alle 4 Tasks in `tasks.md` sind vollständig abgehakt (`[x]`), Akzeptanzkriterien
inklusive:

- **T01** — Settings-Backend (`UpdateSettingsRequest.php`, `SettingsSeeder.php`,
  `SettingsController.php`): 5 neue Bankdaten-/Zahlungsziel-Keys, Validierung,
  Seeder-Defaults, Typ-Sonderfall `company_payment_term_weeks => 'integer'`.
- **T02** — Settings-Frontend (`SettingsView.vue`): 5 neue Formularfelder im
  Stammdaten-Bereich, `loadSettings()`/`saveSettings()` unverändert (generische
  Iteration bestätigt).
- **T03** — Rechnungs-PDF + Rechnungs-E-Mail (`pdf/invoice.blade.php`,
  `emails/invoice-created.blade.php`): Platzhalter-IBAN/BIC ersetzt durch
  Settings-Werte, neuer Überweisungstext mit Wochenanzahl zusätzlich zur
  bestehenden Zahlungsziel-Zeile.
- **T04** — Zahlungserinnerung-E-Mail (`emails/payment-reminder.blade.php`):
  dieselben Kontodaten, aber bewusst **ohne** Wochen-Frist-Text (Decision 8),
  bestehender Einleitungssatz unverändert.

Ich habe den Diff (`git diff`, working tree gegen `main`, da noch nicht
committet) für alle acht geänderten Dateien selbst gelesen und gegen `tasks.md`
abgeglichen — Code entspricht in allen Punkten wörtlich der Aufgabenbeschreibung
(exakte Variablennamen, Einfügepositionen, Wortlaut).

## Spec-Konformität

Alle Requirements/Szenarien aus `specs/invoice-bank-details/spec.md` sind
im Diff nachvollziehbar erfüllt:

- **Bankdaten konfigurierbar / optional / IBAN-BIC-Validierung**: bestätigt in
  `UpdateSettingsRequest.php` (`sometimes`+`nullable`+Regex, identisch zu den
  Szenario-Mustern `^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$` / `^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$`).
- **Standard-Zahlungsziel unabhängig vom `due_date`**: `company_payment_term_weeks`
  wird nirgends mit `due_date`/`dueDate`-Logik verknüpft (kein Treffer bei
  `StoreInvoiceRequest.php`/`Invoice`-Model im Diff); Grenzwerte `min:1`/`max:52`
  vorhanden.
- **Settings-Formular pflegt/zeigt die 5 Felder**: `SettingsView.vue`-Diff zeigt
  Label+Input je Feld, `v-model`/`v-model.number` korrekt gebunden.
- **Rechnungs-PDF zeigt Kontodaten statt Platzhalter, plus Überweisungstext
  neben Zahlungsziel-Zeile**: `pdf/invoice.blade.php`-Diff bestätigt exakten
  Wortlaut "Bitte überweisen Sie den Betrag innerhalb von {{ $paymentTermWeeks }}
  Wochen auf folgendes Konto:" nach der unveränderten `Zahlungsziel:`-Zeile.
- **Rechnungs-E-Mail identisch zum PDF**: `emails/invoice-created.blade.php`-Diff
  zeigt denselben Einleitungssatz (settings-basiert) und dieselben vier Werte.
- **Zahlungserinnerung ohne Wochen-Frist-Text**: `emails/payment-reminder.blade.php`-Diff
  bestätigt, dass der bestehende Einleitungssatz ("Bitte überweisen Sie den
  offenen Betrag unter Angabe der Rechnungsnummer auf folgendes Konto:")
  unverändert blieb — kein neuer Wochen-Satz eingefügt, nur Kontoinhaber/Bank/
  IBAN/BIC ergänzt bzw. ersetzt.
- **Kein Rendering-Fehler bei fehlenden Bankdaten**: alle drei Templates nutzen
  `Setting::get($key, '')` bzw. `$settings['...'] ?? ''`/`?? 2` — verifiziert im
  Diff, zusätzlich durch dedizierte Tests abgedeckt (`InvoiceBankDetailsPdfTest`,
  `InvoiceCreatedMailBankDetailsTest`, `PaymentReminderEmailTest`).
- **Kein hartkodierter Platzhalter mehr im Repo**: eigener Grep
  `grep -rn "DE89 3704 0044 0532 0130 00\|COBADEFFXXX" backend/resources/`
  liefert **keinen** Treffer mehr — alle drei ursprünglich betroffenen
  Templates sind bereinigt.

## Skeptiker-Befunde (verification.md)

Beide Runden abgeschlossen. Runde 1 fand eine echte Scope-Lücke
(`payment-reminder.blade.php` fehlte) und einen Zeilennummer-Fehler in
`design.md` Decision 3 — beide wurden vor Runde 2 korrigiert (T04 ergänzt,
Zeilennummer berichtigt). Runde 2: Status "bereit-für-user-gate", keine neuen
Diskrepanzen. Ich habe die zentralen Runde-2-Behauptungen stichprobenartig im
aktuellen Code nachvollzogen (Einleitungssatz `payment-reminder.blade.php:71`
unverändert, `PaymentReminder::content()` identisches Muster wie
`InvoiceCreated::content()`) — beides bestätigt sich im Diff.

## Review-Befunde

Laut Auftrag: Reviewer-Gesamtempfehlung "ok", keine blockierenden Befunde,
zwei "Sollte"-Hinweise bearbeitet bzw. als unkritisch eingestuft.

**Anmerkung (Dokumentationslücke, kein inhaltlicher Befund):** Im
Change-Verzeichnis existiert keine `task-T0X.review.md`- oder
`review.md`-Datei — anders als in `CLAUDE.md` Abschnitt 8 vorgesehen
(`task-T01.review.md` etc.). Ich kann die Reviewer-Aussage daher nicht anhand
eines eigenen Artefakts nachvollziehen, sondern nur auf Basis der
Zusammenfassung im Auftrag und meiner eigenen unabhängigen Diff-Prüfung
bewerten (siehe oben — keine "Muss"-würdigen Abweichungen von Spec/Tasks
gefunden). Empfehlung: den Reviewer-Bericht nachträglich als Datei ablegen,
bevor archiviert wird, damit die Historie vollständig ist. Dies ist rein
prozessual und blockiert die fachliche Abnahme nicht, da ich Spec-Konformität
und Testergebnisse selbst am Diff verifiziert habe.

## Test-Ergebnisse

`task-report.test-add-invoice-bank-details.md` meldet Status "alle-gruen",
alle Akzeptanzkriterien aus T01–T04 mit konkreten Testfällen abgedeckt,
inklusive der zwei nachträglich geschlossenen Lücken (Frontend-Tests für
T02-Felder, `isOverdue()`-Zweige bei T04).

Ich habe die vollständige QA-Suite selbst in der Docker-Umgebung erneut
ausgeführt (unabhängig vom Bericht) und bestätige:

```
docker compose exec php composer qa
  lint (Pint):          PASS, 297 files
  stan (Larastan):      [OK] No errors
  compat-check (PHPCS): keine Ausgabe = keine Verstöße
  test (Pest):           760 passed (2405 assertions)

docker compose exec node npm run test -- --run
  Test Files: 20 passed (20)
  Tests:      214 passed (214)

docker compose exec node npm run lint
  0 errors, 3045 warnings (alle bereits vor diesem Change bestehend, keine
  neue Warnkategorie durch die geänderten Dateien)

docker compose exec node npm run build
  vue-tsc -b && vite build → erfolgreich, kein TS-/Build-Fehler
```

Hinweis: Der Frontend-Testlauf in Docker schlug initial mit einem
Node/Rollup-Optional-Dependency-Fehler (`Cannot find module
@rollup/rollup-linux-arm64-musl`) fehl — ein reines Host/Container-
Bind-Mount-Artefakt (bereits von T02 in `task-T02.notes.md` als vergleichbares
`esbuild`-Problem dokumentiert), keine Regression durch diesen Change. Nach
`npm install --no-save @rollup/rollup-linux-arm64-musl` liefen alle Läufe wie
oben grün.

Kein MySQL-Portabilitätslauf nötig — dieser Change enthält keine Migration
(`settings.value` ist bereits generisch `text`), bestätigt durch `git status`
(keine neue/geänderte Datei unter `backend/database/migrations/`).

## Erfüllt

- Alle 4 Tasks vollständig, Diff entspricht `tasks.md` und `design.md` exakt.
- Alle Requirements/Szenarien aus `specs/invoice-bank-details/spec.md`
  im Code nachweisbar umgesetzt.
- Keine hartkodierten Platzhalter-Bankdaten mehr im Repository (PDF,
  Rechnungs-E-Mail, Zahlungserinnerung-E-Mail).
- `openspec validate --strict` erfolgreich.
- Backend-QA (`composer qa`: lint, stan, compat-check, 760 Tests) grün.
- Frontend-QA (`npm run lint`, `npm run test` mit 214 Tests, `npm run build`)
  grün.
- Skeptiker-Prozess (2 Runden) abgeschlossen mit Status
  "bereit-für-user-gate", keine offenen Diskrepanzen.

## Offen / Nacharbeit

- **Prozessuale Dokumentationslücke:** Kein `review.md`-Artefakt im
  Change-Verzeichnis, obwohl laut Auftrag ein Full-Diff-Review mit
  Gesamtempfehlung "ok" stattgefunden hat. Kein inhaltliches Risiko (ich habe
  Spec-Konformität selbst am Diff nachvollzogen), aber vor `openspec archive`
  sollte der Reviewer-Bericht als Datei nachgereicht werden, damit
  `openspec/changes/add-invoice-bank-details/` die in `CLAUDE.md` Abschnitt 8
  vorgesehene vollständige Artefakt-Historie hat.
- Alle Änderungen sind noch **nicht committet** (Working Tree auf
  `feature/add-invoice-bank-details`). Vor PR-Erstellung müssen die Dateien
  gemäß Workflow-Schritt 7–10 (Task-Commits) bzw. mindestens gesammelt
  committet werden.

## Empfehlung an den User

Der Change ist inhaltlich vollständig, spec-konform und durch eine
unabhängig wiederholte QA-Suite (Backend + Frontend, 974 Tests insgesamt)
bestätigt grün. Freigabe für User-Gate 2 empfohlen; einzige offene Punkte
sind prozessual (fehlendes `review.md`-Artefakt nachreichen, Commits
nachholen), kein Blocker für die fachliche Abnahme.
