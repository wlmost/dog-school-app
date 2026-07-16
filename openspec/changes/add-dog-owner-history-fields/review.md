# Review: add-dog-owner-history-fields (T01–T12)

**Gesamtempfehlung:** ok

Der Change ist additiv, klein-teilig und sauber auf die zwölf Tasks
aufgeteilt gearbeitet worden. Alle geprüften Backend-Tests
(`DogApiTest.php`, `DogRegistrationRequestApiTest.php`, 71 Tests) und alle
Frontend-Tests (134 Tests inkl. `DogFormModal.test.ts`, 13 Tests) laufen
grün (lokal via `docker compose exec php ./vendor/bin/pest --no-coverage
--filter "DogApiTest|DogRegistrationRequestApiTest"` bzw. `docker compose
exec node npm run test -- --run` nachvollzogen). `vendor/bin/pint --test`
auf allen zehn geänderten/neuen Backend-Dateien meldet exakt dieselben 7
Style-Findings in 7 Dateien wie auf `main` vor dem Change (per `git stash`
gegengeprüft) — **keine neue Pint-Regression** durch diesen Change, die
Behauptungen in den Task-Notes (T03, T05–T09) sind zutreffend. Keine
verbotenen PHP-8.3/8.4-Konstrukte im Diff gefunden (`grep` gegen die
CLAUDE.md-Abschnitt-4.1-Liste, keine Treffer).

## Muss (blockiert Abnahme)

*(keine)*

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Testbarkeit]** `frontend/src/components/DogFormModal.test.ts:262-273`:
  Die neuen Tests identifizieren die drei Felder über positionale
  Selektoren (`wrapper.findAll('input[type="date"]')[1]`,
  `selects[selects.length - 1]`), weil `DogFormModal.vue` (im Unterschied zu
  `CustomerDogRequestModal.vue`) keine `id`-Attribute für Formularfelder
  hat. Funktioniert aktuell korrekt (verifiziert: 13/13 Tests grün), ist
  aber fragil gegenüber künftigen Template-Umsortierungen (z. B. ein
  weiteres `<select>` oder `type="date"`-Feld vor dem Owner-History-Block
  würde die Tests unbemerkt auf das falsche Element zeigen lassen, ohne
  Compile- oder Linter-Fehler). Vorschlag: mittelfristig `id`-Attribute für
  die drei neuen Felder in `DogFormModal.vue` ergänzen (analog zum bereits
  etablierten Muster in `CustomerDogRequestModal.vue`,
  `frontend/src/components/CustomerDogRequestModal.vue:132-171`) und die
  Tests darauf umstellen — nicht blockierend für diesen Change, da aktuell
  funktional korrekt.

- **[Konsistenz]** `backend/database/migrations/2026_07_16_120000_add_owner_history_to_dogs_table.php:1-3`
  vs. `backend/database/migrations/2026_07_16_120001_add_owner_history_to_dog_registration_requests_table.php:1-3`:
  T01 verzichtet bewusst auf `declare(strict_types=1);` (Begründung in
  `task-T01.notes.md`: CLAUDE.md Abschnitt 6 fordert das nur für Dateien
  unter `backend/app/`, Migrationen liegen unter `backend/database/`), T02
  setzt es (Begründung in `task-T02.notes.md`: "CLAUDE.md Abschnitt 6 —
  Pflicht für neue PHP-Dateien"). Beide Begründungen sind für sich
  nachvollziehbar und die Bestandsmigrationen sind ohnehin gemischt (32
  ohne, 9 mit `declare(strict_types=1)`, `grep -L` gegen
  `backend/database/migrations/*.php` verifiziert) — daher kein Blocker.
  Aber innerhalb *dieses* Changes, wo T01 und T02 dasselbe Muster auf zwei
  Tabellen anwenden sollen, ist die Abweichung unnötig sichtbar. Vorschlag:
  bei Gelegenheit (z. B. Befund-Schleife) T01 an T02 angleichen, damit
  beide Migrationen dieses Changes identisch aufgebaut sind.

- **[Testbarkeit/Konsistenz]** `backend/tests/Feature/Api/DogApiTest.php:15`
  und `backend/tests/Feature/DogRegistrationRequestApiTest.php:14`: Beide
  Dateien haben keine `uses()->group(...)`-Zeile. TESTING.md Abschnitt 7
  fordert das verbindlich für *neue* Testdateien und Abschnitt 10 listet
  "`uses()->group(` vorhanden" als mechanisch zu prüfenden Punkt bei jedem
  PR mit Test-Änderungen. Da beide Dateien bereits vor diesem Change
  bestanden (kein Neuanlegen), greift die Boy-Scout-Ausnahme aus TESTING.md
  Kopf ("Bestand wird nicht rückwirkend angepasst") — das ist in
  `task-T10.notes.md` Abschnitt "Annahmen" korrekt begründet dokumentiert,
  daher kein "Muss"-Befund. Da beide Dateien in diesem Change aber ohnehin
  erweitert werden ("wer eine alte Test-Datei sowieso anfasst, bringt sie
  bei der Gelegenheit auf den neuen Stand"), wäre das Nachrüsten von
  `uses()->group('api', 'dog')` bzw. `uses()->group('api',
  'dog-registration-request')` eine naheliegende kleine Ergänzung gewesen.

## Könnte (optional, Verbesserung)

- **[Stil]** `backend/app/Http/Resources/DogRegistrationRequestResource.php`:
  T08 hat beim Ausführen von `vendor/bin/pint` die gesamte Datei
  formatieren lassen (Entfernen der `=>`-Spaltenausrichtung im kompletten
  `toArray()`-Array, `use`-Import + `@mixin`-Kurzform), nicht nur die drei
  neuen Zeilen — dokumentiert und begründet in `task-T08.notes.md`. Das
  ist inhaltlich unproblematisch (reine Formatierung, keine
  Funktionsänderung, verifiziert per Pint-Testlauf) und macht die Datei
  jetzt als einzige der zehn geänderten Backend-Dateien Pint-clean. Es
  vergrößert aber den Diff um rein kosmetische Zeilen und weicht vom
  Vorgehen der übrigen Tasks (T03, T05, T06, T07, T09 haben bewusst *kein*
  Auto-Format auf unberührte Zeilen angewendet, um den Diff minimal zu
  halten) ab — für zukünftige Changes wäre ein einheitliches Vorgehen
  (entweder immer oder nie ganze Datei formatieren) wünschenswert, ggf. als
  Ergänzung in CLAUDE.md/TESTING.md festhalten.

- **[Lesbarkeit]** `backend/database/factories/DogFactory.php:34` und
  `backend/database/factories/DogRegistrationRequestFactory.php:37`: Die
  `age_at_acquisition`-Beispielwerte unterscheiden sich leicht zwischen
  beiden Factories (`'ca. 2 Monate'` vs. `'Welpe'` als eines der vier
  Elemente) — funktional irrelevant, rein kosmetisch, kein Handlungsbedarf.

## Lob (kurz, was gut gelöst wurde)

- Durchgängige Konsistenz der Validierungsregeln über alle drei
  FormRequests (`StoreDogRequest.php:47-49`, `UpdateDogRequest.php:63-65`,
  `StoreDogRegistrationRequest.php:45-47`) — identische Regel-Arrays trotz
  paralleler Bearbeitung durch unterschiedliche Agenten, keine Drift.
- Saubere Behandlung des `owner_since`-Date-Cast-Fallstricks in Tests
  (`task-T10.notes.md` "Besonderheit"): DB-Timestamp vs. reiner
  Datumsstring korrekt erkannt und TESTING.md-konform gelöst (`expect()`
  für das gecastete Model-Attribut statt `assertDatabaseHas` mit
  Datumsstring).
- Der bewusst dokumentierte, nicht mitgefixte Altbefund ("`notes` wird bei
  `approve()` nicht übernommen", `task-T09.notes.md`) ist ein gutes
  Beispiel für Scope-Disziplin ohne Informationsverlust.
- Frontend/Backend-Kontrakt (camelCase-Feldnamen, Enum-Werte) wurde von
  T11/T12 korrekt parallel zum noch nicht fertigen Backend nach
  `design.md` umgesetzt, ohne dass nachträglich Anpassungen nötig waren —
  guter Beleg für eine präzise Design-Vorgabe.
- Migrations sind minimal und exakt wie in `design.md`/`tasks.md`
  spezifiziert (`Schema::table()`, kein raw SQL, kein `->after()`), beide
  lokal gegen PostgreSQL und (mangels `docker-compose.mysql.yml`) über
  einen manuell gestarteten MySQL-8.0-Container verifiziert
  (`task-T01.notes.md`, `task-T02.notes.md`) — die fehlende
  `docker-compose.mysql.yml` wurde korrekt als vorbestehende
  Infrastruktur-Lücke erkannt und nicht in Scope gezogen.
