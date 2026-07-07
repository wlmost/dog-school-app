# Design: fix-anamnesis-template-questions-not-saved

**Change-ID:** fix-anamnesis-template-questions-not-saved
**Datum:** 2026-07-07

---

## 1. Betroffene Dateien (Übersicht)

| Datei | Änderung | Task | DB-Bezug? |
|---|---|---|---|
| `backend/tests/Feature/AnamnesisTemplateApiTest.php` | Verifikationslauf des bestehenden Tests "trainer can create template with questions" (Z. 97-128) | T01 | Lesend (Test gegen DB) |
| `backend/app/Http/Controllers/AnamnesisTemplateController.php` | `index()`: `withCount('questions')` ergänzen | T02 | Eloquent-only, unkritisch |
| `backend/app/Http/Resources/AnamnesisTemplateResource.php` | `questionsCount`-Feld ergänzen | T02 | Nein |
| `backend/app/Http/Controllers/AnamnesisTemplateController.php` | `update()`: Fragen-Synchronisation (ID-Diff) | T03 | Eloquent-only, unkritisch |
| `backend/app/Http/Requests/UpdateAnamnesisTemplateRequest.php` | `questions`-Validierungsregeln + `id`-Feld ergänzen | T03 | Nein |
| `backend/tests/Feature/AnamnesisTemplateApiTest.php` | Neue Testfälle für Update-mit-Fragen (siehe Abschnitt 5) | T03 | Lesend/schreibend (Test gegen DB) |
| `frontend/src/views/anamnesis/AnamnesisView.vue` | `openTemplateModal()`: Vorlage vor Öffnen des Editors per `getById()` nachladen | T04 | — |
| `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue` | Frage-`id` durchs Formular + in den Save-Payload durchreichen | T05 | — |
| `frontend/src/api/anamnesis.ts` | `updateTemplate()`-Payload-Typ um optionales `id`-Feld pro Frage erweitern | T05 | — |

**Keine Migration in diesem Change.** Alle betroffenen Spalten existieren
bereits (`backend/database/migrations/2025_12_22_184952_create_anamnesis_questions_table.php`).
Alle Backend-Änderungen sind reines Eloquent (`with()`, `withCount()`,
`update()`, `create()`, `delete()`, `whereDoesntHave()`) — **kein raw SQL**,
damit automatisch MySQL- und Postgres-portabel gemäß CLAUDE.md Abschnitt 4.2.

---

## 2. T01 — Verifikation des Create-Flows (zuerst, vor T03)

**Reihenfolge-Vorgabe aus Triage:** Bevor der Update-Persistenz-Bug (T03)
angegangen wird, muss ausgeschlossen werden, dass der Create-Flow selbst
noch einen unentdeckten dritten Bug enthält — sonst würde ein Fix auf einer
falschen Annahme aufbauen.

**Vorgehen (kein Produktivcode, reine Verifikation):**

1. In der Docker-Umgebung ausführen:
   ```bash
   docker compose exec app composer test -- --filter=AnamnesisTemplateApiTest
   ```
   (Alternativ: `docker compose exec app php artisan test --filter=AnamnesisTemplateApiTest`.)
2. Falls grün: zusätzlich einmal manuell über die UI (oder `tinker`/`psql`/
   `mysql`-Client) eine Vorlage mit Fragen anlegen und per direktem
   DB-Blick (`SELECT * FROM anamnesis_questions WHERE template_id = ?`)
   bestätigen, dass die Fragen tatsächlich in der DB stehen.
3. Ergebnis (grün/rot, DB-Blick-Befund) in `task-T01.notes.md` dokumentieren.

**Wenn der Test entgegen der Erwartung rot ist:** Das ist ein Scope-Change
gegenüber diesem Proposal (ein dritter Bug im Create-Flow selbst). In dem
Fall: Befund in `task-T01.notes.md` dokumentieren und **vor** Fortsetzung
mit T02/T03 den User/Architekten informieren (Spec-Anpassung nötig,
Rückkehr zu Workflow-Schritt 3 gemäß `~/.claude/WORKFLOW.md`, Abschnitt
"Was tun, wenn die Spec nach Implementierungsstart noch geändert werden
muss?"). Kein bestehender Code darf ungeplant "mitgefixt" werden.

---

## 3. T02 — Anzeigeproblem: `questionsCount`

### Backend

`AnamnesisTemplateController::index()` (Zeile 29):

```php
$query = AnamnesisTemplate::query()->with(['trainer'])->withCount('questions');
```

`AnamnesisTemplateResource::toArray()` (Zeile 17-34): `questionsCount`
ergänzen, mit Fallback auf eine bereits geladene `questions`-Relation
(wichtig für `store()`/`show()`, die `questions` volltständig laden statt
`withCount` zu nutzen, siehe `AnamnesisTemplateController.php:92`
bzw. `:106` — dort bleibt `questions_count` sonst `null`, obwohl die Anzahl
aus der geladenen Collection trivial ableitbar ist):

```php
'questionsCount' => $this->resolveQuestionsCount(),
```

mit einer privaten Hilfsmethode in der Resource:

```php
private function resolveQuestionsCount(): ?int
{
    if ($this->questions_count !== null) {
        return (int) $this->questions_count;
    }

    if ($this->relationLoaded('questions')) {
        return $this->questions->count();
    }

    return null;
}
```

**Begründung für die Fallback-Logik statt reinem `withCount`:** `store()`
(Zeile 92) und `show()` (Zeile 106) laden bereits die volle
`questions`-Relation (für die verschachtelte `questions`-Ausgabe im
Response). Ein zusätzlicher `withCount('questions')`-Aufruf dort wäre
redundant (N+1-Vermeidung: die Anzahl steckt schon in der geladenen
Collection) — die Fallback-Methode vermeidet unnötige zusätzliche Queries
und hält sich an DRY.

### Frontend

`frontend/src/api/anamnesis.ts:9` deklariert `questionsCount?: number`
bereits — **keine Typ-Änderung nötig**, das Feld wird nur endlich befüllt.
`AnamnesisView.vue:152` (`{{ template.questionsCount || 0 }} Fragen`)
funktioniert dann korrekt ohne Änderung an dieser Zeile.

---

## 4. T03 — Echter Update-Bug: ID-basierte Fragen-Synchronisation

### Entscheidung: ID-Diff statt "alle löschen und neu anlegen"

Ein naiver Fix nach dem Muster von `store()` (alle bestehenden Fragen der
Vorlage löschen, dann aus dem Request-Array neu anlegen) wäre einfacher zu
implementieren, hat aber eine **Datenintegritäts-Konsequenz**: die
FK-Definition in
`backend/database/migrations/2025_12_22_185016_create_anamnesis_answers_table.php:17`
(`$table->foreignId('question_id')->constrained('anamnesis_questions')->onDelete('cascade')`)
würde beim Löschen einer Frage automatisch **alle bereits erfassten
`AnamnesisAnswer`-Datensätze** zu dieser Frage mitlöschen — auch wenn der
Trainer nur den Text einer anderen Frage in derselben Vorlage geändert hat
und die betroffene Frage inhaltlich unverändert im Payload enthalten war.
Das würde einen **neuen, schwerwiegenderen** Datenverlust erzeugen als der
ursprünglich gemeldete Bug.

**Gewählte Lösung:** Fragen werden anhand einer optionalen `id` im
Request-Payload identifiziert:

- Frage **mit** `id` → bestehende Frage wird per `update()` aktualisiert.
- Frage **ohne** `id` → neue Frage wird angelegt.
- Bestehende Frage, deren `id` **nicht** mehr im Payload vorkommt → wird
  gelöscht, **außer** sie hat bereits Antworten
  (`$question->answers()->exists()` bzw. `whereDoesntHave('answers')` als
  Query-Guard) — dann bleibt sie unverändert erhalten. Diese Ausnahme wird
  in `task-T03.notes.md` und im Test explizit dokumentiert, da sie vom
  reinen "Payload ist Wahrheit"-Prinzip abweicht — bewusste Entscheidung
  zugunsten von Datenerhalt.
- `id`-Werte, die im Payload auftauchen, aber **nicht** zu den aktuell
  existierenden Fragen dieser Vorlage gehören (z. B. `id` einer Frage einer
  anderen Vorlage), führen zu **HTTP 422** — kein stillschweigendes
  Ignorieren, kein Cross-Template-Zugriff über die `id`.
- Fehlt der Schlüssel `questions` im Request komplett (nicht `[]`, sondern
  gar nicht gesendet), bleiben bestehende Fragen **unangetastet** — das
  erlaubt zukünftigen API-Konsumenten, nur `name`/`description` zu ändern,
  ohne Fragen zu berühren. Wird `questions: []` explizit gesendet (Trainer
  entfernt alle Fragen in der UI), werden alle unbeantworteten Fragen
  gelöscht (beantwortete bleiben, s. o.).

### `UpdateAnamnesisTemplateRequest::rules()` (Ergänzung, PHP-8.2-konform)

```php
'questions' => ['sometimes', 'array'],
'questions.*.id' => ['sometimes', 'integer'],
'questions.*.questionText' => ['required_with:questions', 'string'],
'questions.*.questionType' => ['required_with:questions', 'string', 'in:text,textarea,select,multiselect,checkbox,radio,file'],
'questions.*.options' => ['nullable', 'array'],
'questions.*.options.*' => ['string'],
'questions.*.isRequired' => ['boolean'],
'questions.*.order' => ['integer', 'min:0'],
```

`validatedSnakeCase()` analog zu `StoreAnamnesisTemplateRequest`
(`backend/app/Http/Requests/StoreAnamnesisTemplateRequest.php:45-64`)
erweitern, zusätzlich `id` durchreichen, falls vorhanden — `array_key_exists`
für `questions` verwenden (nicht `isset`), damit `questions: []`
von "Schlüssel fehlt" unterscheidbar bleibt.

### `AnamnesisTemplateController::update()` (neue Struktur)

```php
public function update(
    UpdateAnamnesisTemplateRequest $request,
    AnamnesisTemplate $anamnesisTemplate
): AnamnesisTemplateResource {
    $this->authorize('update', $anamnesisTemplate);

    $data = $request->validatedSnakeCase();
    $questions = $data['questions'] ?? null;
    unset($data['questions']);

    DB::transaction(function () use ($anamnesisTemplate, $data, $questions) {
        $anamnesisTemplate->update($data);

        if ($questions !== null) {
            $this->syncQuestions($anamnesisTemplate, $questions);
        }
    });

    $anamnesisTemplate->load(['trainer', 'questions']);

    return new AnamnesisTemplateResource($anamnesisTemplate);
}

private function syncQuestions(AnamnesisTemplate $template, array $questions): void
{
    $existingIds = $template->questions()->pluck('id')->all();
    $incomingIds = array_values(array_filter(array_column($questions, 'id')));

    $unknownIds = array_diff($incomingIds, $existingIds);
    abort_if($unknownIds !== [], 422, 'One or more question ids do not belong to this template.');

    foreach ($questions as $questionData) {
        $id = $questionData['id'] ?? null;
        unset($questionData['id']);

        if ($id) {
            $template->questions()->whereKey($id)->update($questionData);
        } else {
            $template->questions()->create($questionData);
        }
    }

    $toDelete = array_diff($existingIds, $incomingIds);
    if ($toDelete !== []) {
        $template->questions()
            ->whereKey($toDelete)
            ->whereDoesntHave('answers')
            ->delete();
    }
}
```

Verwendete Sprachmittel (`array_filter`, `array_column`, `array_diff`,
`abort_if`, `whereDoesntHave`) sind alle PHP-8.2- bzw. Laravel-Standard —
**keine** 8.3/8.4-Features (kein `array_find`/`array_any`, keine Typed
Class Constants, kein `#[\Override]`).

### Autorisierung bleibt unverändert

`AnamnesisTemplatePolicy::update()`
(`backend/app/Policies/AnamnesisTemplatePolicy.php:39-43`) prüft weiterhin
nur Trainer-Eigentümerschaft der **Vorlage** — die zusätzliche
`id`-Ownership-Prüfung in `syncQuestions()` ist eine **Ergänzung** auf
Fragen-Ebene (verhindert, dass ein Trainer über die `questions[].id` eines
PUT-Requests indirekt eine Frage einer fremden Vorlage referenziert), kein
Ersatz für die bestehende Policy.

---

## 5. Testabdeckung (Teil von T03)

Neue Testfälle in `backend/tests/Feature/AnamnesisTemplateApiTest.php`
(Beschreibung, keine Implementierung — Aufgabe des Entwickler-Agenten):

1. "trainer can add new questions when updating template" — Vorlage ohne
   Fragen, Update mit 2 neuen Fragen (ohne `id`) → beide in DB.
2. "trainer can modify existing question via id when updating template" —
   bestehende Frage per `id` im Payload mit geändertem `question_text` →
   DB-Wert aktualisiert, **keine** neue Zeile angelegt (Zeilenzahl bleibt
   gleich).
3. "trainer can remove a question by omitting it from the update payload" —
   2 bestehende Fragen, Payload enthält nur 1 (per `id`) → die
   ausgelassene wird gelöscht.
4. "removing a question with existing answers from the update payload does
   not delete the question or its answers" — Frage mit
   `AnamnesisAnswer`-Factory-Datensatz, Payload lässt sie aus → Frage und
   Antwort bleiben in der DB bestehen (Datenintegritäts-Schutz aus
   Abschnitt 4).
5. "update rejects a question id belonging to a different template" —
   `id` einer Frage aus einer zweiten, fremden Vorlage im Payload → HTTP
   422, keine Datenänderung.
6. "updating template without the questions key leaves existing questions
   untouched" — Update-Payload enthält nur `name`, kein `questions`-Schlüssel
   → Fragen unverändert (Regressionsschutz für Teil-Updates).

---

## 6. T04/T05 — Frontend

### T04: `AnamnesisView.vue` — Vollständige Vorlage vor dem Editor laden

`openTemplateModal()` (Zeile 344-347) wird `async` und lädt bei
vorhandenem `template`-Argument die Detailansicht nach, bevor der Modal
geöffnet wird:

```ts
async function openTemplateModal(template?: AnamnesisTemplate) {
  if (template) {
    try {
      selectedTemplate.value = await anamnesisTemplatesApi.getById(template.id)
    } catch (error) {
      handleApiError(error, 'Fehler beim Laden der Vorlagendetails')
      return
    }
  } else {
    selectedTemplate.value = null
  }
  showTemplateModal.value = true
}
```

`anamnesisTemplatesApi.getById()` existiert bereits
(`frontend/src/api/anamnesis.ts:78-81`) und ruft `GET
/api/v1/anamnesis-templates/{id}` auf, dessen Controller-Methode `show()`
(`AnamnesisTemplateController.php:102-109`) bereits `questions` eager lädt
— **keine Backend-Änderung für T04 nötig**, reiner Frontend-Fix. Die
Aufrufstellen (`@click="openTemplateModal(template)"` Zeile 156,
`@click="openTemplateModal()"` Zeile 95/141) benötigen keine Änderung, da
Vue `async`-Handler in `@click` transparent unterstützt.

### T05: `AnamnesisTemplateFormModal.vue` + `anamnesis.ts` — Frage-`id` durchreichen

Damit T03s ID-Diff überhaupt funktionieren kann, muss das Frontend beim
Bearbeiten die `id` einer bestehenden Frage kennen und beim Speichern
zurückschicken:

1. `Question`-Interface (Zeile 234-240) um `id?: number` erweitern.
2. Im `watch(() => props.template, …)`-Block (Zeile 287-303): `id: q.id`
   mit ins gemappte Objekt aufnehmen.
3. In `save()` (Zeile 392-400): `id: q.id` in jedes Payload-Objekt
   aufnehmen. Für neu über `addQuestion()` (Zeile 320-328) hinzugefügte
   Fragen ist `q.id` `undefined` — `JSON.stringify`/Axios lassen
   `undefined`-Properties beim Serialisieren automatisch weg, das Backend
   sieht in diesem Fall also korrekt **keinen** `id`-Schlüssel (= "neue
   Frage anlegen").
4. `frontend/src/api/anamnesis.ts`: `updateTemplate()`-Parameter-Typ
   (Zeile 118-128) um optionales `id?: number` pro Frage-Objekt erweitern,
   damit der TypeScript-Compiler (`vue-tsc -b` als Teil von `npm run
   build`) das durchgereichte Feld nicht als Typfehler markiert.

**Kein Einfluss auf `createTemplate()`** (Zeile 102-116) — dort gibt es
naturgemäß nie eine `id` (neue Vorlage), das Interface bleibt dort
unverändert.

---

## 7. Risiken / Nebenfunde

- **Nebenfund (kein Fix in diesem Change):**
  `backend/app/Http/Resources/AnamnesisQuestionResource.php:26`
  (`'helpText' => $this->help_text ?? null`) referenziert eine Spalte, die
  laut
  `backend/database/migrations/2025_12_22_184952_create_anamnesis_questions_table.php`
  nicht existiert. Das führt aktuell nicht zu einem Fehler (Laravels
  Eloquent-`__get`-Magie liefert für unbekannte Attribute `null`, der
  `??`-Fallback greift also unauffällig), ist aber ein latenter Altbefund
  außerhalb des gemeldeten Symptoms — nicht Teil dieses Changes (siehe
  `proposal.md`, "Out of Scope").
- **Risiko:** T03 vergrößert den Diff-Umfang gegenüber der ursprünglichen
  Triage-Schätzung ("ca. 4-5 Dateien"), weil die Answer-Cascade-Problematik
  erst während der Architektur-Planung entdeckt wurde (nicht in der
  Triage-Datei erwähnt). Der Skeptiker sollte diesen Mehraufwand explizit
  gegen die Anforderung prüfen (Realitätsabgleich: ist der zusätzliche
  Schutzmechanismus wirklich nötig, oder gibt es aktuell noch gar keine
  produktiven `AnamnesisAnswer`-Datensätze, die betroffen sein könnten?)
  — letzteres würde die Dringlichkeit senken, aber nicht die Korrektheit
  des Fixes in Frage stellen, da das Feature aktiv nutzbar ist und Trainer
  bereits Vorlagen mit Kundenantworten haben könnten.
- **Kein Risiko für Shared-Hosting/PHP-8.2-Kompatibilität:** alle
  verwendeten PHP-Konstrukte sind 8.2-kompatibel (siehe Abschnitt 4,
  letzter Absatz). `composer compat-check` sollte dennoch als Teil der
  Pre-Flight-Checks laufen.
