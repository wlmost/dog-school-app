## Context

- `backend/app/Models/Setting.php:62-73` — `Setting::get(string $key,
  $default = null)`, gecacht über `Cache::remember("setting.{$key}",
  3600, ...)`. Liefert `$default`, wenn der Key nicht existiert oder kein
  Wert gesetzt ist — kein Fehlerfall, keine Exception.
- `backend/database/seeders/SettingsSeeder.php:18-34` — Gruppe `company`
  enthält bereits `company_name` ('Hundeschule Beispiel'),
  `company_street` ('Musterstraße 123'), `company_zip` ('12345'),
  `company_city` ('Musterstadt'), `company_country` ('Deutschland'),
  `company_phone` ('+49 123 456789'), `company_email`
  ('info@hundeschule-beispiel.de'), `company_website`, `company_tax_id`
  ('DE123456789'), `company_registration_number` — alle `type: string`,
  `group: company`. Diese Keys existieren bereits vollständig; dieser
  Change fügt **keine** neuen Keys hinzu, sondern verdrahtet nur die
  beiden PDF-Templates gegen sie.
- `backend/resources/views/pdf/invoice.blade.php:127-138` — bestehender
  `@php`-Block lädt bereits `$isSmallBusiness`, `$bankAccountHolder`,
  `$bankName`, `$bankIban`, `$bankBic`, `$paymentTermWeeks` per direktem
  `\App\Models\Setting::get(...)`-Aufruf (aus dem archivierten Change
  `add-invoice-bank-details`). Dies ist die etablierte
  Referenzimplementierung für dasselbe Muster, das dieser Change auf
  Firmenname/-adresse anwendet.
- `backend/resources/views/pdf/invoice.blade.php:140-154` — Firmenkopf:
  eine Tabelle mit Logo (Zelle 1, `$logoSrc`) und Firmentextblock
  (Zelle 2, Klasse `company-info`, Zeile 148-152). Nur der Inhalt der
  zweiten Zelle (`<h1>`+2×`<p>`) ist hartkodiert.
- `backend/resources/views/pdf/invoice.blade.php:288-292` — Fußzeile:
  `<div class="footer">` mit zwei `<p>`-Zeilen (Firmenname/-adresse,
  USt-IdNr).
- `backend/resources/views/pdf/anamnesis.blade.php:123-129` — Firmenkopf:
  ein einfaches `<div class="company-info">` mit **textidentischem**
  Inhalt (`<h1>`+2×`<p>`) zum Rechnungs-PDF-Kopf, nur die umschließende
  Struktur unterscheidet sich (`div` statt `td` in einer Logo-Tabelle —
  das Anamnese-PDF hat kein Logo).
- `backend/resources/views/pdf/anamnesis.blade.php:270-275` — Fußzeile:
  `<div class="footer">` mit denselben zwei Zeilen wie im Rechnungs-PDF
  plus einer dritten, anamnese-spezifischen Zeile ("Erstellt am:
  {{ now()->format('d.m.Y H:i') }} Uhr").
- `backend/resources/views/layouts/email.blade.php:142-167` — bereits
  etabliertes Fallback-Muster für dieselben Firmenfelder, allerdings über
  ein vom Controller/Mailable übergebenes `$settings`-Array
  (`$settings['company_name'] ?? 'Hundeschule'` usw.), nicht über
  direkte `Setting::get()`-Aufrufe. Dieses Muster passt hier **nicht**
  1:1, weil `InvoiceController::downloadPdf()`
  (`InvoiceController.php:236`) und
  `AnamnesisResponseController::downloadPdf()`
  (`AnamnesisResponseController.php:211`) aktuell kein `$settings`-Array
  an die View übergeben — Einführung eines zweiten Settings-Zugriffsmusters
  in denselben Dateien, die bereits das direkte-`Setting::get()`-Muster
  für Bankdaten nutzen, würde gegen Konsistenz/DRY verstoßen (siehe
  Decision 1).
- `backend/tests/Feature/Pdf/InvoiceBankDetailsPdfTest.php:65-72` — Test
  `it('lässt company_name company_street company_city und
  company_tax_id unverändert hartkodiert', ...)` asserted aktuell exakt
  die Strings, die dieser Change entfernt (`Hundeschule Max Mustermann`,
  `Musterstraße 123`, `12345 Musterstadt`, `USt-IdNr: DE123456789`). Ohne
  Anpassung schlägt dieser Test nach T01 rot fehl. Diese Testdatei liegt
  laut CLAUDE.md Abschnitt 2 unter `backend/tests/Feature/`, also im
  Zuständigkeitsbereich von `dev-php` — keine Grenzüberschreitung in den
  Zuständigkeitsbereich von `tester`.
- `backend/tests/Unit/InvoiceBankDetailsBladeSourceTest.php` — reine
  Dateisystem-Prüfung auf IBAN/BIC-Strings, keine Berührung mit
  Firmenname/-adresse-Strings; kein Anpassungsbedarf.

## Goals / Non-Goals

**Goals:**
- Rechnungs- und Anamnese-PDF zeigen Firmenname, -straße, -PLZ/-ort,
  -telefon, -e-mail (Kopf) sowie Firmenname, -straße, -PLZ/-ort und
  USt-IdNr (Fuß) aus den bestehenden Settings statt hartkodierter
  Platzhalterwerte.
- Fortsetzung des bereits etablierten Musters (direkter
  `\App\Models\Setting::get()`-Aufruf im `@php`-Block des jeweiligen
  Templates), keine Einführung eines zweiten, konkurrierenden Zugriffsmusters.
- Duplikation zwischen den beiden Templates wird für die **textidentischen**
  Teile (Kopf-Textblock, gemeinsame Fuß-Zeilen) über zwei kleine,
  gemeinsame Partials reduziert, ohne die pro Dokumenttyp unterschiedliche
  umgebende Struktur (Logo-Tabelle vs. einfaches `div`, zusätzliche
  "Erstellt am"-Zeile im Anamnese-Fuß) anzutasten.
- Fehlende/leere Settings-Werte führen zu leeren Feldern, nicht zu einem
  PHP-Fehler und nicht zu einer neuen Fantasieadresse.
- Der bestehende Test, der die alte Hartkodierung als Soll-Zustand
  voraussetzt, wird korrigiert, damit `composer qa` grün bleibt.

**Non-Goals:**
- Keine neuen Settings-Keys (`company_website`, `company_registration_number`
  o. Ä. werden **nicht** neu in den PDFs angezeigt — nur die bereits heute
  hartkodiert dargestellten Felder werden durch ihre Settings-Entsprechung
  ersetzt; kein Scope-Creep über die triagierten Befunde hinaus).
- Kein `$settings`-Array-Umbau der Controller (`InvoiceController`,
  `AnamnesisResponseController`) — beide bleiben unverändert.
- Keine Änderung an `layouts/email.blade.php` oder anderen E-Mail-Templates
  — die sind bereits korrekt (siehe Kontext) und nicht Teil der Triage.
- Keine Änderung am bestehenden Bankdaten-Block
  (`invoice.blade.php:128-133`, `.payment-box`) — der ist bereits korrekt
  und bleibt unangetastet.
- Kein generisches "Company"-Value-Object oder Service; die Templates
  bleiben bei der etablierten Blade-`@php`-Lösung (YAGNI — zwei Templates
  rechtfertigen keine neue Abstraktionsschicht in PHP, ein Blade-Partial
  reicht für die vorhandene Duplikation aus).

## Decisions

**1. Direkter `Setting::get()`-Aufruf im `@php`-Block, kein
`$settings`-Array vom Controller.**
Beide Templates nutzen bereits dieses Muster für die Bankdaten
(`invoice.blade.php:128-133`). Ein zweites, konkurrierendes Muster
(Controller übergibt `$settings`-Array, analog zu `email.blade.php`)
würde in denselben Dateien zwei unterschiedliche Zugriffswege auf
dieselbe Datenquelle erzeugen — Verstoß gegen Konsistenz/DRY und
unnötiger Zusatzaufwand (YAGNI), da `Setting::get()` bereits gecacht ist
und keinen Performance-Nachteil gegenüber einem vorab geladenen Array
hat (eine Rechnung/Anamnese-Antwort wird einmal pro Request gerendert,
nicht in einer Schleife über viele Datensätze).

**2. Zwei gemeinsame Blade-Partials statt Duplizierung oder eines
kompletten gemeinsamen Layouts.**
Geprüfte Alternativen:
- *Keine Partials, Duplizierung in beiden Templates* — würde die
  bestehende Duplikation (bereits Ursache dieses Bugs: beide Templates
  hatten denselben hartkodierten String an zwei Stellen) fortschreiben.
  Bei einer künftigen Adress-Änderung müsste wieder an vier Stellen
  synchron geändert werden.
- *Ein gemeinsames Ober-Layout für beide PDFs* (`@extends`) — verworfen:
  die beiden Templates unterscheiden sich strukturell zu stark (Logo-Tabelle
  vs. einfaches Layout, komplett unterschiedliche Inhaltsbereiche
  darunter) für ein sinnvolles gemeinsames Layout; das wäre eine größere
  Restrukturierung ohne Bezug zum eigentlichen Bug (YAGNI/KISS-Verstoß).
- **Gewählt:** zwei kleine, fokussierte Partials
  (`pdf/partials/company-info.blade.php` für den Kopf-Textblock aus
  `<h1>`+2×`<p>`, `pdf/partials/company-footer-lines.blade.php` für die
  zwei gemeinsamen Fuß-`<p>`-Zeilen), die exakt den textidentischen Teil
  kapseln und von der jeweiligen Elternstruktur (`<td>`/`<div>` im Kopf,
  `<div class="footer">` im Fuß) per `@include` eingebunden werden. Jedes
  Partial lädt seine Settings-Werte selbst per `Setting::get()` — kein
  Parameter-Passing nötig, da beide Templates dieselben Keys mit
  denselben Fallbacks benötigen (Single Responsibility: ein Partial, eine
  Aufgabe).

**3. Fallback-Werte: leere Strings statt neuer Platzhalteradressen,
`'Hundeschule'` als einziger nicht-leerer Textfallback für den
Firmennamen.**
Konsistent mit dem bestehenden Bankdaten-Muster
(`Setting::get('company_bank_iban', '')` — leerer String als Default,
kein Fake-Wert) und mit dem bereits etablierten Fallback in
`layouts/email.blade.php:143-159`
(`$settings['company_name'] ?? 'Hundeschule'` als generischer,
nicht-fingierter Name; `$settings['company_street'] ?? 'Musterstraße 123'`
dort zwar noch als Fake-Fallback vorhanden, aber **nicht** Teil dieses
Change — nicht in der Triage erfasst und keine hartkodierte
Dauerdarstellung wie in den PDFs, sondern nur ein Edge-Case-Fallback für
eine ungepflegte Neuinstallation). Für die PDFs gilt: `company_name`
fällt auf `'Hundeschule'` zurück (nie leer, damit der Kopf nicht
komplett leer wirkt), alle übrigen Felder (`company_street`,
`company_zip`, `company_city`, `company_phone`, `company_email`,
`company_tax_id`) fallen auf `''` zurück — ein leeres Feld ist im
Zweifel besser als eine erneut erfundene Adresse, die exakt das ursprüngliche
Problem wiederholen würde.

**4. Umfang der Testkorrektur auf den einen betroffenen Test begrenzt.**
Nur `InvoiceBankDetailsPdfTest.php:65-72` behauptet explizit die alte
Hartkodierung als Soll-Zustand (Kontext). Die übrigen PDF-Tests
(`InvoicePdfTest.php`, `AnamnesisResponsePdfTest.php`) prüfen aktuell nur
HTTP-Status/Header/Content-Type, keine Textinhalte (`expect($response
->getContent())->not()->toBeEmpty()` als einziger Content-Check) — sie
sind vom Fix nicht betroffen und werden nicht angefasst. Neue,
inhaltliche Tests für die Settings-Anzeige (analog zu den bereits
vorhandenen Bankdaten-Tests in derselben Datei, Zeile 28-49) sind Aufgabe
des `tester`-Agenten in Workflow-Schritt 9, nicht Teil dieses
`tasks.md` — die hier korrigierte Task betrifft ausschließlich die
Anpassung der einen **falsch gewordenen** Behauptung, nicht das
Hinzufügen neuer Testabdeckung.

## DB-Portabilität

Keine Migration, kein raw SQL, keine neue Spalte. `Setting::get()` nutzt
ausschließlich Eloquent (`static::where('key', $key)->first()`,
`Setting.php:65`) — MySQL/Postgres-neutral, keine Prüfung durch den
Reviewer im Sinne von CLAUDE.md Abschnitt 4.2 nötig, da keine
Migrations-/Raw-SQL-Änderung stattfindet.

## Risks / Trade-offs

- **Bestandsinstallationen ohne gepflegte Settings zeigen ein
  unvollständiges PDF (leere Felder statt Fake-Adresse).** Akzeptiert —
  entspricht der Anforderung ("keine erfundene Adresse mehr zeigen") und
  dem bereits akzeptierten Verhalten des Bankdaten-Blocks. Kein
  PHP-Fehler, da `Setting::get()` nie wirft (Kontext).
  Akzeptanzkriterium: kein Rendering-Fehler bei fehlenden Keys.
- **Zwei neue Partial-Dateien erhöhen die Dateizahl leicht.** Akzeptiert
  als bewusster DRY-Trade-off (Decision 2) — Alternative (Duplizierung)
  hat bereits einmal zu diesem Bug beigetragen (derselbe hartkodierte
  String an vier statt zwei Stellen hätte das Risiko weiter erhöht).
- **Testkorrektur in `InvoiceBankDetailsPdfTest.php` betrifft eine Datei,
  die ursprünglich von einem anderen, bereits archivierten Change
  angelegt wurde.** Risiko eines Merge-/Verständnis-Konflikts gering, da
  nur der eine namentlich betroffene Test (Zeile 65-72) angepasst wird,
  alle anderen Tests der Datei (Bankdaten-Fokus) bleiben unverändert.
