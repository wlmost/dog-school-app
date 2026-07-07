# Verification: fix-anamnesis-template-questions-not-saved

**Gesamtstatus:** ok

`openspec validate fix-anamnesis-template-questions-not-saved` → **valid** (strukturell korrekt).

---

## Bestätigt

### Migration / Datenmodell (Kernrisiko)

- `design.md` Z.123-124 / `proposal.md` Z.76-77: `question_id` in `anamnesis_answers` ist per `constrained('anamnesis_questions')->onDelete('cascade')` definiert → **bestätigt** exakt in `backend/database/migrations/2025_12_22_185016_create_anamnesis_answers_table.php:17`. Ein "alle Fragen löschen und neu anlegen"-Update würde tatsächlich zugehörige `AnamnesisAnswer`-Zeilen per DB-Cascade mitlöschen. Die Risikoeinschätzung des Architekten ist technisch korrekt.
- `backend/database/migrations/2025_12_22_184952_create_anamnesis_questions_table.php` enthält keine `help_text`-Spalte (grep über alle Migrationen liefert 0 Treffer für `help_text`/`helpText`) → bestätigt kein Schema für dieses Feld.
- `AnamnesisQuestion::$fillable` (`backend/app/Models/AnamnesisQuestion.php:38-45`) enthält `template_id, question_text, question_type, options, is_required, order` → deckt alle für T03 benötigten Sync-Felder ab. Bestätigt.
- `AnamnesisQuestion::answers()` (`backend/app/Models/AnamnesisQuestion.php:74-77`, `HasMany` zu `AnamnesisAnswer`) existiert → `whereDoesntHave('answers')`/`$question->answers()->exists()` aus T03/design.md sind gegen eine real existierende Relation geplant. Bestätigt.
- `AnamnesisTemplate::questions()` (`backend/app/Models/AnamnesisTemplate.php:69-72`, `HasMany`, mit `orderBy('order')`) existiert → `$template->questions()->whereKey(...)->update(...)` / `->create(...)` aus dem geplanten `syncQuestions()` sind gegen reale Relationen geplant. Bestätigt.
- `AnamnesisTemplatePolicy::update()` (`backend/app/Policies/AnamnesisTemplatePolicy.php:39-43`) prüft ausschließlich `trainer_id === $user->id` → Autorisierungs-Aussage ("bleibt unverändert korrekt") bestätigt.

### Backend — Controller/Requests/Resources

- `AnamnesisTemplateController::index()` (`backend/app/Http/Controllers/AnamnesisTemplateController.php:25-29`) lädt nur `->with(['trainer'])`, kein `withCount`/`questions` → bestätigt.
- `AnamnesisTemplateController::store()` (`:71-97`) und `StoreAnamnesisTemplateRequest::rules()`/`validatedSnakeCase()` (`backend/app/Http/Requests/StoreAnamnesisTemplateRequest.php:24-64`) — Zeilenbereich exakt bestätigt; Struktur sieht wie behauptet strukturell korrekt aus (Transaction, Fragen-Anlage in Schleife).
- `AnamnesisTemplateController::update()` (`:114-124`) ruft ausschließlich `$anamnesisTemplate->update($request->validatedSnakeCase())` und lädt `questions` nur nach — **kein** Sync-Code vorhanden. Dies ist die zentrale Behauptung (Ursache 2) und wortwörtlich exakt bestätigt:
  ```
  114  public function update(
  ...
  120      $anamnesisTemplate->update($request->validatedSnakeCase());
  121      $anamnesisTemplate->load(['trainer', 'questions']);
  ...
  124  }
  ```
- `UpdateAnamnesisTemplateRequest::rules()` (`backend/app/Http/Requests/UpdateAnamnesisTemplateRequest.php:24-31`) validiert nur `name`, `description`, `isDefault` — **kein** `questions`-Feld. Bestätigt exakt.
- `AnamnesisTemplateResource::toArray()` (`backend/app/Http/Resources/AnamnesisTemplateResource.php:17-34`) liefert kein `questionsCount`, nur `responsesCount` via `whenLoaded` in Zeile 29-32. Bestätigt exakt (Zeilenangabe passt).
- `store()` lädt `questions` nach Transaktion in Zeile 92 (`$template->load(['trainer', 'questions']);`), `show()` lädt in Zeile 106 (`$anamnesisTemplate->load(['trainer', 'questions', 'responses']);`) — beide exakt bestätigt, stützen die Fallback-Begründung in T02.
- Test "trainer can create template with questions" existiert exakt in `backend/tests/Feature/AnamnesisTemplateApiTest.php:97-128`. Bestätigt.
- Test "trainer can update own template" existiert exakt in `backend/tests/Feature/AnamnesisTemplateApiTest.php:172-190`. Bestätigt.
- `AnamnesisAnswerFactory` existiert (`backend/database/factories/AnamnesisAnswerFactory.php`) mit Feldern `response_id`, `question_id`, `answer_value` → geplanter Testfall 4 ("Frage mit AnamnesisAnswer-Factory-Datensatz") ist mit vorhandener Factory umsetzbar. Bestätigt.
- Laravel-Version laut `composer.lock`: `v11.51.0` → `whereKey()`, `whereDoesntHave()`, `abort_if()` sind Standard-APIs dieser Version, keine Kompatibilitätsprobleme zu erwarten.

### Frontend

- `frontend/src/api/anamnesis.ts:9` deklariert `questionsCount?: number` bereits im `AnamnesisTemplate`-Interface. Bestätigt exakt.
- `anamnesisTemplatesApi.getById()` existiert exakt in `frontend/src/api/anamnesis.ts:78-81`. Bestätigt.
- `AnamnesisView.vue:152` zeigt exakt `{{ template.questionsCount || 0 }} Fragen`. Bestätigt.
- `openTemplateModal()` (`frontend/src/views/anamnesis/AnamnesisView.vue:344-347`) übergibt aktuell `template || null` direkt als `selectedTemplate`, ohne Nachladen — bestätigt exakt, keine Fetch-Logik vorhanden.
- Aufrufstellen `@click="openTemplateModal()"` in Zeile 95 und 141, `@click="openTemplateModal(template)"` in Zeile 156 — alle drei exakt bestätigt.
- `handleApiError` ist in Zeile 208 importiert (`import { handleApiError, showSuccess } from '@/utils/errorHandler'`) — bestätigt exakt, im Component-Scope vorhanden.
- `AnamnesisTemplateFormModal.vue`: `watch(() => props.template, …)`-Block exakt in Zeile 287-303 (inkl. `newTemplate.questions?.map(...) || []` in Zeile 292-298). Bestätigt exakt.
- `Question`-Interface exakt in Zeile 234-240 (`question_text`, `question_type`, `is_required`, `options`, `order` — alle snake_case, lokal zum Formular). Bestätigt.
- `addQuestion()` exakt in Zeile 320-328. Bestätigt.
- `save()`-Payload-Block exakt in Zeile 388-401, die Fragen-Map darin exakt in Zeile 392-400. Bestätigt.
- `frontend/src/api/anamnesis.ts`: `createTemplate()` exakt Zeile 102-116, `updateTemplate()`-Parametertyp exakt Zeile 118-128. Bestätigt.
- Da `updateTemplate()`'s Parametertyp (Z.118-128) aktuell **kein** `id`-Feld pro Frage-Objekt hat und der Payload in `save()` als Objektliteral übergeben wird, würde `vue-tsc` bei Hinzufügen von `id: q.id` ohne Typ-Erweiterung tatsächlich eine "excess property"-Fehlermeldung werfen — die geplante Typ-Erweiterung (T05, Punkt 4) ist technisch notwendig, nicht nur vorsorglich. Bestätigt als korrekte technische Einschätzung.

---

## Widerlegt

- `proposal.md` Z.168 und `design.md` Z.336: `AnamnesisQuestionResource.php:26` (`'helpText' => $this->help_text ?? null`) — **tatsächliche Zeile ist 27**, nicht 26 (siehe `backend/app/Http/Resources/AnamnesisQuestionResource.php:27`). Der inhaltliche Kern der Aussage (Spalte existiert nicht, `??`-Fallback verhindert Fehler, unkritisch) ist jedoch korrekt — reine Off-by-one-Ungenauigkeit in der Zeilenangabe, kein sachlicher Fehler.

- **Abhängigkeitsrichtung T03 ↔ T05:** Die im Prüfauftrag unterstellte Aussage "T05 ist Voraussetzung für T03" ist **so nicht in den Artefakten enthalten und würde, wäre sie behauptet worden, der tatsächlichen `tasks.md`-Deklaration widersprechen**. `tasks.md` legt explizit fest:
  - T03 → `Abhängigkeiten: T01` (Zeile 102) — **nicht** T05.
  - T05 → `Abhängigkeiten: T03, T04` (Zeile 205) — T05 hängt von T03 ab, nicht umgekehrt.

  Das ist auch die technisch korrekte Reihenfolge: T03 (Backend-Sync) muss zuerst existieren und ist über die API bereits unabhängig testbar (Request mit `id`-Feld manuell/Testcode), bevor das Frontend (T05) beginnt, `id` tatsächlich zu senden. `design.md` Abschnitt 6 formuliert lediglich, dass T03s Fix ohne T05 **wirkungslos für den Trainer über die UI** bliebe ("Damit T03s ID-Diff überhaupt funktionieren kann, muss das Frontend … die id … kennen") — das ist eine Aussage über *End-to-End-Wirksamkeit*, keine Aussage über *Implementierungsreihenfolge*, und steht nicht im Widerspruch zur deklarierten Abhängigkeitskette. Die Kette T01→{T02,T03}→T04→T05 (bzw. T03,T04→T05) ist in sich konsistent und azyklisch.

---

## Nicht auffindbar

- Ob in der **Produktivdatenbank** aktuell bereits `AnamnesisAnswer`-Datensätze existieren, die von T03 betroffen wären, ist aus dem Repository **nicht feststellbar** — dafür gibt es keinen Datenbank-Dump, kein Backup und keinen Zugriff auf die Produktiv-DB im Repo. Es lässt sich nur die *Plausibilität* über Code-Historie einschätzen (siehe Empfehlung/Nebenbefund unten), nicht der tatsächliche Datenbestand.

---

## Neue Elemente (Plausibilität)

- `tasks.md` T03 plant neue private Methode `syncQuestions()` in `AnamnesisTemplateController` — Ort konsistent mit bestehendem `store()`-Muster (bereits vorhandene private Struktur/Transaktions-Konvention in derselben Klasse, `AnamnesisTemplateController.php:79`). Plausibel.
- `tasks.md` T03 plant 6 neue Testfälle in der bestehenden `backend/tests/Feature/AnamnesisTemplateApiTest.php` — Datei existiert bereits mit vergleichbaren Pest-Testfällen im selben Stil (`test('...', function () {...})`, `RefreshDatabase`, `actingAs`). Konsistent mit vorhandenen Konventionen, keine neue Testinfrastruktur nötig.
- `tasks.md` T05 plant Erweiterung des lokalen `Question`-Interfaces (snake_case, Formular-intern) um `id?: number` — dieses Interface ist strukturell getrennt vom camelCase `AnamnesisQuestion`-Interface in `anamnesis.ts`; die Erweiterung an der richtigen (lokalen) Stelle vorzunehmen ist konsistent mit der bestehenden Doppelstruktur (Formular-Modell vs. API-Modell) und verursacht keinen Konflikt.

---

## Zusätzlicher Befund (im Auftrag angefordert: Produktivnutzung des Features)

Der Architekt hat als offene Frage an den Skeptiker weitergegeben, ob aktuell überhaupt schon Daten in `anamnesis_answers` existieren könnten. Code-Evidenz (kein direkter DB-Zugriff, daher s.o. "nicht auffindbar" für den tatsächlichen Datenbestand, aber Wahrscheinlichkeits-Indizien):

- Das Feature ist **vollständig implementiert und aktiv geroutet** seit Dezember 2025:
  - Migrationen vom 2025-12-22 (`create_anamnesis_answers_table.php` u.a.), Commit `bf90f00` "feat: Implement complete database schema with migrations and tests".
  - API-Implementierung Commit `1045fdf` (2025-12-26) "feat(api): implement Anamnesis Management API with comprehensive test coverage".
  - Ein CI-Fix-Commit `4a21500` (2026-04-24) berührt dieselben Tabellen — d. h. das Feature war zu diesem Zeitpunkt (vier Monate nach Einführung) noch in aktiver Wartung/Nutzung.
- Es existiert ein vollständiger, aktiv getesteter Schreibpfad für Antworten: `Route::apiResource('anamnesis-responses', AnamnesisResponseController::class)` (`backend/routes/api.php:148`), inkl. Tests, die tatsächlich `AnamnesisAnswer`-Zeilen anlegen und aktualisieren (`backend/tests/Feature/AnamnesisResponseApiTest.php`, u. a. Zeilen 166-195 "can create response with answers", 286-320 "can update response answers").
- Daraus folgt: Das Feature ist **nicht nur Scaffolding**, sondern ein seit über sechs Monaten (Stand heutiges Datum 2026-07-07) produktiv nutzbares Kernfeature mit vollständigem Schreibpfad für Kundenantworten. Die Architekten-Einschätzung "Trainer könnten bereits Vorlagen mit Kundenantworten haben" ist damit **plausibel und nicht überzogen** — die zusätzliche Schutzlogik in T03 ist als Vorsichtsmaßnahme gerechtfertigt, unabhängig davon, ob im konkreten Fall des meldenden Trainers bereits Antworten vorliegen.

---

## Empfehlung

Alle sicherheitsrelevanten Kernbehauptungen (FK-Cascade-Risiko, Zeilenreferenzen zu Controller/Resource/Request, Modell-Relationen, Testdatei-Referenzen) sind exakt bestätigt. Es gibt nur eine triviale Off-by-one-Zeilenangabe (`AnamnesisQuestionResource.php:26` statt `:27`, inhaltlich korrekt) und eine klärungsbedürftige, aber im Ergebnis unproblematische Formulierung zur T03/T05-Abhängigkeitsrichtung (Design-Text beschreibt Wirksamkeit, `tasks.md` deklariert die technisch korrekte Implementierungsreihenfolge — kein echter Widerspruch). Die Spec ist verlässlich genug, um zu **User-Gate 1** zu gehen. Keine Nacharbeit am Architekten-Entwurf zwingend nötig; die Zeilenangabe in `AnamnesisQuestionResource.php` kann bei Gelegenheit korrigiert werden, ist aber kein Blocker.
