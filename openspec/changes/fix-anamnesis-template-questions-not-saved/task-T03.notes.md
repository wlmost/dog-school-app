# Notes T03: Echter Update-Bug — Fragen-Synchronisation beim Bearbeiten

**Agent:** dev-php
**Datum:** 2026-07-07

## Zusammenfassung

`PUT /api/v1/anamnesis-templates/{id}` synchronisiert Fragen jetzt anhand
einer optionalen `id` im Request-Payload, statt sie beim Update komplett zu
ignorieren (der ursprüngliche Bug: `update()` hat `questions` aus dem
Payload nie ausgewertet, bestehende Fragen blieben unverändert und neue
Fragen wurden nie angelegt). Umgesetzt exakt wie in `design.md`, Abschnitt
4, vorgeschlagen — ID-Diff statt "alle löschen und neu anlegen", um die
`onDelete('cascade')`-FK auf `anamnesis_answers.question_id`
(`backend/database/migrations/2025_12_22_185016_create_anamnesis_answers_table.php:17`)
nicht zu einem versehentlichen Datenverlust bei bereits erfassten Antworten
zu machen.

## Geänderte Dateien

1. `backend/app/Http/Requests/UpdateAnamnesisTemplateRequest.php`
   - `rules()`: `questions`-Regeln analog zu
     `StoreAnamnesisTemplateRequest::rules()` ergänzt (`sometimes`/`array`
     statt `required`, da Updates optional sind), zusätzlich
     `questions.*.id` als `['sometimes', 'integer']`.
   - `validatedSnakeCase()`: neuer Block, der `questions` per
     `array_key_exists('questions', $validated)` (nicht `isset`) prüft —
     damit ist `questions: []` (explizit "alle Fragen entfernen") von
     "Schlüssel fehlt" (bestehende Fragen unangetastet lassen)
     unterscheidbar. Jede Frage wird ins snake_case-Format gemappt; die
     optionale `id` wird nur dann ins gemappte Array übernommen, wenn sie
     im validierten Input vorhanden war (`array_key_exists('id',
     $question)`), damit `unset($questionData['id'])` im Controller
     zuverlässig zwischen "hat id" und "hat keine id" unterscheiden kann.

2. `backend/app/Http/Controllers/AnamnesisTemplateController.php`
   - `update()`: liest `questions` aus `validatedSnakeCase()`, entfernt
     den Schlüssel aus `$data` (damit `$anamnesisTemplate->update($data)`
     nicht versehentlich ein nicht-existentes `questions`-Attribut auf dem
     Template-Model setzt), und ruft — nur falls `$questions !== null`
     (Schlüssel war im Request vorhanden) — die neue private Methode
     `syncQuestions()` auf. Der gesamte Ablauf läuft innerhalb von
     `DB::transaction()`, analog zur bestehenden Konvention in `store()`
     (`AnamnesisTemplateController.php`, ehemals Zeile 79).
   - Neue private Methode `syncQuestions(AnamnesisTemplate $template,
     array $questions): void`:
     - Ermittelt `$existingIds` (alle aktuellen Fragen-IDs der Vorlage) und
       `$incomingIds` (alle im Payload mitgegebenen, nicht-leeren `id`-Werte).
     - `abort_if($unknownIds !== [], 422, ...)`: Jede `id` im Payload, die
       nicht zu `$existingIds` gehört, führt sofort zu HTTP 422 — noch
       bevor irgendeine Frage angefasst wird (kein Teil-Schreiben vor dem
       Abbruch, die Transaktion rollt vollständig zurück).
     - Pro Frage im Payload: mit `id` → `$template->questions()->whereKey($id)->update($questionData)`
       (bestehende Zeile wird aktualisiert, keine neue angelegt); ohne
       `id` → `$template->questions()->create($questionData)` (neue Zeile).
     - Danach: alle bestehenden Fragen, deren `id` nicht mehr im Payload
       vorkommt (`array_diff($existingIds, $incomingIds)`), werden
       gelöscht — aber nur über
       `->whereDoesntHave('answers')->delete()`, sodass Fragen mit
       mindestens einer `AnamnesisAnswer` von der Löschung ausgenommen
       bleiben (Datenschutz vor Kaskaden-Löschung, siehe `design.md`
       Abschnitt 4).
   - Autorisierung unverändert: `AnamnesisTemplatePolicy::update()` prüft
     weiterhin nur die Eigentümerschaft der Vorlage selbst
     (`backend/app/Policies/AnamnesisTemplatePolicy.php:39-43`, nicht
     angefasst); die `id`-Ownership-Prüfung in `syncQuestions()` ist eine
     zusätzliche, feingranularere Schranke auf Fragen-Ebene und kein Ersatz.

3. `backend/tests/Feature/AnamnesisTemplateApiTest.php`
   - Import `App\Models\AnamnesisAnswer` ergänzt.
   - 6 neue Testfälle zwischen "trainer can update own template" und
     "trainer cannot update another trainers template" eingefügt (exakt
     die in `design.md` Abschnitt 5 beschriebenen Szenarien):
     1. `trainer can add new questions when updating template`
     2. `trainer can modify existing question via id when updating template`
     3. `trainer can remove a question by omitting it from the update payload`
     4. `removing a question with existing answers from the update payload does not delete the question or its answers`
        (nutzt `AnamnesisAnswer::factory()->create(['question_id' => ...])`)
     5. `update rejects a question id belonging to a different template`
        (HTTP 422, DB unverändert)
     6. `updating template without the questions key leaves existing questions untouched`
        (Payload enthält nur `name`, kein `questions`-Schlüssel)

## Abweichungen vom Design-Vorschlag

Keine. Validierungsregeln, `validatedSnakeCase()`-Logik,
Controller-Struktur und `syncQuestions()` entsprechen 1:1 dem in
`design.md` Abschnitt 4 vorgeschlagenen Code.

## Testergebnisse

Ausgeführt in der Docker-Umgebung (Service `php`, nicht `app` — bereits in
`task-T01.notes.md`/`task-T02.notes.md` dokumentierte Befehlsdiskrepanz
zwischen `CLAUDE.md`/Task-Text und dem tatsächlichen Docker-Setup):

```bash
docker compose exec php vendor/bin/pest --filter=AnamnesisTemplateApiTest
# Tests: 29 passed (116 assertions) — inkl. aller 6 neuen Testfälle, alle grün
# Insbesondere weiterhin grün: "trainer can update own template" (Regressionsschutz)

docker compose exec php vendor/bin/pest
# Tests: 678 passed (2111 assertions) — volle Suite grün, keine Regression
```

**`composer stan` / `composer compat-check`: weiterhin nicht ausführbar**
in diesem Docker-Setup — derselbe, bereits in `task-T01.notes.md` und
`task-T02.notes.md` dokumentierte, vorbestehende Environment-Gap
(`backend/composer.json` im Container enthält keine `stan`/`compat-check`-
Scripts bzw. die zugehörigen Dev-Dependencies `larastan/larastan` und
`phpcompatibility/php-compatibility` sind dort nicht installiert). Kein
Bezug zu T03, keine Behebung im Rahmen dieser Task.

**`vendor/bin/pint --test`** für die drei geänderten Dateien geprüft: Die
gemeldeten Stilverstöße (`fully_qualified_strict_types`, `concat_space`,
`trailing_comma_in_multiline`) betreffen ausschließlich bereits
bestehende Zeilen (z. B. `\App\Http\Resources\AnamnesisQuestionResource::collection()`
in `questions()`, das `.`-Konkatenations-Muster in `index()`/bestehenden
Testzeilen, fehlende Trailing-Commas in bereits vorhandenen
`assertJsonStructure`-Arrays) — dieselbe projektweite, vorbestehende
Pint-Diskrepanz, die bereits in `task-T02.notes.md` dokumentiert wurde.
Mein neu hinzugefügter Code (`syncQuestions()`, die
`validatedSnakeCase()`-Erweiterung, die 6 neuen Testfälle) löst **keine
zusätzlichen** Stilverstöße aus, die nicht schon zuvor in der jeweiligen
Datei vorhanden waren (per Diff-Abgleich verifiziert: die gemeldeten
Zeilen liegen alle außerhalb der von mir neu hinzugefügten Blöcke, mit
einer Ausnahme — die PHPDoc-Ausrichtung von `@param array<...> $questions`
in `syncQuestions()` folgt demselben (Pint-abweichenden) Einzel-Leerzeichen-
Stil wie der Rest der Datei, z. B. der bestehende `@return`-Tag in
`rules()`).

**Manuelle PHP-8.2-Kompatibilitätsprüfung** (Ersatz für den nicht
lauffähigen `compat-check`, siehe `CLAUDE.md` Abschnitt 4.1): Verwendete
Sprachmittel — `array_filter`, `array_column`, `array_diff`,
`array_key_exists`, `abort_if` (Laravel-Helper), `whereDoesntHave`,
`whereKey` — sind alle PHP-8.2- bzw. Laravel-Standard. Kein
`array_find`/`array_any`/`array_all` (8.4), keine Typed Class Constants
oder `#[\Override]` (8.3), keine Property Hooks/Asymmetric Visibility
(8.4), kein `new MyClass()->method()` ohne Klammern. `php -l` gegen alle
drei geänderten PHP-Dateien lief fehlerfrei (`No syntax errors detected`).

## Offene Punkte / Risiken

- Keine inhaltlichen offenen Punkte für T03 — alle Akzeptanzkriterien
  erfüllt.
- Der bereits unter T01/T02 dokumentierte Composer-Script-Gap
  (`stan`/`compat-check` nicht ausführbar im Docker-`php`-Service) bleibt
  ein vorbestehender, unabhängiger Befund außerhalb des T03-Scopes.
- Wie in `design.md` Abschnitt 4 vermerkt: die "beantwortete Frage bleibt
  bei Auslassung erhalten"-Ausnahme weicht bewusst vom reinen
  "Payload-ist-Wahrheit"-Prinzip ab — zugunsten von Datenerhalt. Dies ist
  in Testfall 4 explizit abgedeckt und dokumentiert.
- **Kein Frontend-Bezug in T03:** Damit die neue `id`-basierte
  Synchronisation im echten UI-Betrieb greift, müssen T04 (Vorlage vor
  dem Editor vollständig nachladen) und T05 (Frage-`id` durchs Formular
  und in den Speicher-Payload durchreichen) noch folgen — ohne diese
  würde das Frontend aktuell nie eine `id` mitschicken und jede
  Bearbeitung würde clientseitig wie "alle Fragen sind neu" aussehen
  (siehe `tasks.md`, T05-Beschreibung). Das ist erwartungsgemäß außerhalb
  des T03-Scopes.
