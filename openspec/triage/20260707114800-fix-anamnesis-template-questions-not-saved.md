# Triage: fix-anamnesis-template-questions-not-saved

**Pfad:** standard
**Geschätzter Umfang:** ca. 4–5 Dateien, PHP + Vue.js (beide Stacks betroffen)
**Risiko:** mittel — betrifft Datenintegrität/-anzeige eines bestehenden Produktivfeatures (Anamnese-Vorlagen), aber keine Migration/Schema-Änderung nötig; leichte API-Resource-Erweiterung (kein Breaking Change)
**Klarheit:** mehrdeutig — Statische Codeanalyse zeigt mindestens zwei unterschiedliche, plausible Teilursachen; muss mit dem Trainer/User abgeglichen werden, welches Symptom genau gemeint ist (siehe Rückfragen)

---

## Anforderung (Zusammenfassung)

Ein Trainer meldet: Beim Anlegen einer neuen Anamnesebogen-Vorlage werden
die zugehörigen Fragen nicht gespeichert. Es geht um das bestehende
Trainer-Feature "Anamnese-Vorlagen" (Template + Fragen), nicht um ein
neues Feature.

---

## Ist-Zustand (belegter Code-Befund)

### Backend — Create-Flow (`store()`) sieht strukturell korrekt aus

- `backend/app/Http/Controllers/AnamnesisTemplateController.php` Z. 71–97:
  `store()` erstellt das Template und iteriert danach über
  `$data['questions']`, ruft pro Frage `$template->questions()->create($questionData)` auf
  (Z. 79–90). Das entspricht der vorhandenen `hasMany`-Relation in
  `backend/app/Models/AnamnesisTemplate.php` Z. 69–72.
- `backend/app/Http/Requests/StoreAnamnesisTemplateRequest.php` Z. 24–64:
  Validierung und `validatedSnakeCase()`-Mapping von camelCase (Frontend)
  auf snake_case (DB) sehen korrekt aus (`questionText` → `question_text` usw.).
- `backend/app/Models/AnamnesisQuestion.php` Z. 38–45: `$fillable` enthält
  alle benötigten Felder (`template_id`, `question_text`, `question_type`,
  `options`, `is_required`, `order`).
- **Es existiert bereits ein grüner Feature-Test**, der exakt dieses
  Verhalten abdeckt: `backend/tests/Feature/AnamnesisTemplateApiTest.php`
  Z. 97–128 ("trainer can create template with questions") — prüft, dass
  nach `POST /api/v1/anamnesis-templates` mit `questions`-Array die Fragen
  in der DB existieren. **Ungeprüfte Annahme:** Ich konnte den Test lokal
  nicht ausführen (Docker-Daemon in dieser Sitzung nicht erreichbar,
  `docker compose ps` schlug fehl mit "Cannot connect to the Docker
  daemon"). Ob dieser Test aktuell tatsächlich grün ist oder zwischenzeitlich
  bricht, ist **nicht verifiziert** und sollte der erste Schritt des
  Entwickler-Agents sein.

### Frontend — zwei sehr wahrscheinliche Ursachen für den *wahrgenommenen* Datenverlust

1. **`questionsCount` wird nie vom Backend geliefert.**
   `frontend/src/views/anamnesis/AnamnesisView.vue` Z. 152 zeigt
   `{{ template.questionsCount || 0 }} Fragen` in der Vorlagen-Kachel-Liste.
   Aber:
   - `AnamnesisTemplateController::index()` (Z. 29) lädt nur
     `->with(['trainer'])` — **nicht** `questions` und **kein**
     `withCount('questions')`.
   - `AnamnesisTemplateResource::toArray()`
     (`backend/app/Http/Resources/AnamnesisTemplateResource.php` Z. 17–35)
     liefert **kein** `questionsCount`-Feld überhaupt (nur `responsesCount`
     via `whenLoaded`, Z. 29–32).
   - Konsequenz: `template.questionsCount` ist im Frontend immer
     `undefined` → Anzeige fällt durch `|| 0` immer auf **"0 Fragen"**
     zurück, unabhängig davon, wie viele Fragen tatsächlich in der DB
     gespeichert sind. Das erzeugt exakt den Eindruck "Fragen werden nicht
     gespeichert", obwohl sie ggf. korrekt persistiert wurden.

2. **Bearbeiten-Modal zeigt ebenfalls 0 Fragen, weil die Liste keine
   `questions`-Relation mitliefert.**
   `openTemplateModal(template)`
   (`frontend/src/views/anamnesis/AnamnesisView.vue` Z. 344–345) übergibt
   das Listen-Objekt `template` **direkt und ungeprüft** als `template`-Prop
   an `AnamnesisTemplateFormModal`, statt vorher per
   `anamnesisTemplatesApi.getById(id)` die Detailansicht (inkl. `questions`)
   zu laden. In
   `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue`
   Z. 287–303 (`watch(() => props.template, ...)`) wird
   `newTemplate.questions?.map(...) || []` ausgewertet — da `questions` im
   Listenobjekt fehlt, zeigt der Editor beim erneuten Öffnen einer gerade
   erstellten Vorlage **0 Fragen**, obwohl sie in der DB stehen könnten.

### Backend — echter Persistenz-Bug beim **Bearbeiten** (nicht beim Neu-Anlegen)

- `AnamnesisTemplateController::update()`
  (`backend/app/Http/Controllers/AnamnesisTemplateController.php`
  Z. 114–124) ruft nur `$anamnesisTemplate->update($request->validatedSnakeCase())`
  auf und lädt danach `questions` nur zur Anzeige nach — es gibt **keinerlei
  Code-Pfad**, der `questions` aus dem Request synchronisiert (anlegen,
  aktualisieren, löschen).
- `UpdateAnamnesisTemplateRequest::rules()`
  (`backend/app/Http/Requests/UpdateAnamnesisTemplateRequest.php` Z. 24–31)
  validiert `questions` **gar nicht** — das Feld wird beim Update
  komplett ignoriert.
- Das Frontend (`AnamnesisTemplateFormModal.vue` Z. 388–401, `save()`)
  schickt beim Bearbeiten (`isEditMode`) jedoch ein volles
  `questions`-Array mit — das geht beim `PUT` also **stillschweigend
  verloren**. Das ist ein **echter, unabhängig vom Anzeige-Problem
  bestehender Datenverlust-Bug**, falls der Trainer Fragen zu einer
  bestehenden Vorlage hinzufügt/ändert statt eine komplett neue anzulegen.

### Zusammenfassende Bewertung

Es gibt vermutlich **zwei separate, aber zusammenhängende Bugs**:

1. **Reines Anzeigeproblem** beim Neu-Anlegen: Fragen werden vermutlich
   korrekt gespeichert, aber weder die Kachel-Liste (`questionsCount`)
   noch das erneute Öffnen des Bearbeiten-Modals zeigen sie an — der
   Trainer *glaubt*, sie seien nicht gespeichert worden.
2. **Echter Persistenz-Bug** beim Bearbeiten bestehender Vorlagen:
   `questions` wird im `UpdateAnamnesisTemplateRequest`/`update()` komplett
   ignoriert — hier gehen Änderungen tatsächlich verloren.

Welches der beiden (oder beide) der Trainer erlebt hat, lässt sich aus der
Bug-Meldung allein nicht zweifelsfrei ableiten — daher Klarheit:
mehrdeutig.

---

## Betroffene Dateien (Schätzung)

### Backend (PHP)
- `backend/app/Http/Controllers/AnamnesisTemplateController.php` — `index()` um `withCount('questions')`/Eager-Load erweitern, `update()` um Fragen-Synchronisation erweitern
- `backend/app/Http/Resources/AnamnesisTemplateResource.php` — `questionsCount`-Feld ergänzen
- `backend/app/Http/Requests/UpdateAnamnesisTemplateRequest.php` — `questions`-Validierungsregeln ergänzen (analog zu `StoreAnamnesisTemplateRequest`)
- ggf. `backend/tests/Feature/AnamnesisTemplateApiTest.php` — Testfälle für Update-mit-Fragen und für `questionsCount` ergänzen

### Frontend (Vue.js)
- `frontend/src/views/anamnesis/AnamnesisView.vue` — `openTemplateModal()` sollte vor dem Öffnen des Editors die Voll-Details per `getById()` nachladen (statt Listenobjekt direkt zu verwenden)

**Summe: ca. 4–5 Dateien**, beide Stacks (PHP + Vue.js).

---

## Ungeprüfte Referenzen / offene Verifikation

- Ob `AnamnesisTemplateApiTest.php` aktuell grün ist, wurde in dieser
  Triage **nicht** verifiziert (Docker-Daemon nicht erreichbar). Erster
  Schritt des Entwickler-Agents: `composer test -- --filter=AnamnesisTemplateApiTest`
  in der Docker-Umgebung laufen lassen, bevor Code geändert wird.
- Ob der Trainer den Bug beim reinen Neu-Anlegen (Anzeigeproblem) oder
  auch beim späteren Bearbeiten (echter Datenverlust) beobachtet hat, ist
  ungeklärt (siehe Rückfragen).

---

## Rückfragen an den User — beantwortet (2026-07-07)

- **Tritt der Effekt beim Anlegen, beim Bearbeiten oder bei beidem auf?**
  → **Beides.** Der Trainer erlebt den Effekt sowohl beim erstmaligen
  Anlegen als auch beim nachträglichen Bearbeiten einer Vorlage.
- **Wurde die DB direkt geprüft oder nur die UI beobachtet?**
  → **Nur UI beobachtet** (Kachel-Liste bzw. Editor beim erneuten Öffnen).
  Es ist **nicht verifiziert**, ob die Fragen beim Neu-Anlegen tatsächlich
  in der DB fehlen, oder ob es sich (wie im Code-Befund vermutet) rein um
  das Anzeigeproblem handelt.
- Umgebung (Docker/Demo/Produktion) wurde nicht spezifiziert.

**Einordnung:** Die Antwort "beides" ist konsistent mit der ursprünglichen
Vermutung aus dem Code-Befund — sie schließt den echten Bearbeiten-Bug
nicht aus, bestätigt ihn aber auch nicht zweifelsfrei, da beim Neu-Anlegen
nur ein Anzeigeproblem vorliegen könnte, das den Eindruck von Datenverlust
erzeugt. Da eine DB-Verifikation aussteht, sollte der Entwickler-Agent als
**allererster Schritt** `composer test -- --filter=AnamnesisTemplateApiTest`
laufen lassen und den Create-Flow einmal manuell mit direktem DB-Blick
verifizieren, bevor er den vermuteten Update-Bug behebt — beide Ursachen
bleiben aber in Scope, da der Trainer explizit beide Fälle gemeldet hat.

---

## Empfohlene nächste Aktion

`@architect` beauftragen, einen openspec-Change (`fix-anamnesis-template-questions-not-saved`)
zu erstellen, der **beide** vermuteten Ursachen abdeckt (Pfad `standard`
bestätigt):

1. **Anzeigeproblem** (Create + Liste + erneutes Öffnen): `index()` +
   `AnamnesisTemplateResource` (`questionsCount`) + `AnamnesisView.vue`
   (Vorlagen-Details vor Öffnen des Editors nachladen).
2. **Echter Update-Datenverlust**: `update()` + `UpdateAnamnesisTemplateRequest`
   um Fragen-Synchronisation/-Validierung erweitern (analog zu `store()`).
3. **Verifikationsschritt vorab**: bestehenden Feature-Test
   `AnamnesisTemplateApiTest.php` ausführen und den Create-Flow einmal mit
   direktem DB-Blick gegenprüfen, um auszuschließen, dass beim Neu-Anlegen
   selbst noch ein dritter, bisher unentdeckter Bug existiert.

Der Architekt sollte zwei Tasks vorsehen, eine für
`dev-php` (Backend: Resource/Controller/Request, inkl. Update-Fix) und eine
für `dev-javascript` (Frontend: `AnamnesisView.vue` Nachladen der
Vorlagen-Details vor dem Öffnen des Editors), mit einem definierten
API-Vertrag (`questionsCount`-Feld) als Übergabepunkt. Test-Erweiterung
für den Update-Pfad (Fragen ändern + speichern) ist Teil der Backend-Task.

**Kommando für den nächsten Schritt (nach Klärung der Rückfragen):**
```
@architect Erstelle den openspec-Change basierend auf
openspec/triage/20260707114800-fix-anamnesis-template-questions-not-saved.md
```
