# Proposal: fix-anamnesis-template-questions-not-saved

**Change-ID:** fix-anamnesis-template-questions-not-saved
**Typ:** Bug-Fix
**Priorität:** mittel-hoch (Datenintegrität + Trainer-Vertrauen in ein Kernfeature)
**Datum:** 2026-07-07
**Umgebung:** nicht spezifiziert durch den Trainer; Code-Befund zeigt keinen
umgebungsspezifischen Unterschied (kein Shared-Hosting-/DB-Treiber-Bezug) —
der Bug tritt identisch in Docker/Demo/Produktion auf.

---

## Problem-Statement

Ein Trainer meldet: Beim Anlegen einer neuen Anamnesebogen-Vorlage
("Anamnese-Vorlagen"-Feature) werden die zugehörigen Fragen nicht
gespeichert. Auf Rückfrage (siehe Triage,
`openspec/triage/20260707114800-fix-anamnesis-template-questions-not-saved.md`,
Abschnitt "Rückfragen … beantwortet") hat der Trainer bestätigt: Der Effekt
tritt **sowohl beim Anlegen als auch beim Bearbeiten** auf, wurde aber **nur
über die UI beobachtet** (Kachel-Liste bzw. erneutes Öffnen des
Bearbeiten-Modals), nicht direkt in der Datenbank verifiziert.

## Root-Cause-Analyse (verifiziert durch Code-Lektüre)

Es handelt sich um **zwei unabhängige, aber zusammenhängende Ursachen**, die
beide zum gemeldeten Symptom "Fragen werden nicht gespeichert" beitragen
können:

### Ursache 1 — Anzeigeproblem (betrifft Create UND Edit, vermutlich kein echter Datenverlust)

- `backend/app/Http/Controllers/AnamnesisTemplateController.php:29`
  (`index()`) lädt nur `->with(['trainer'])` — weder `questions` noch
  `withCount('questions')`.
- `backend/app/Http/Resources/AnamnesisTemplateResource.php:17-34`
  liefert **kein** `questionsCount`-Feld (nur `responsesCount` via
  `whenLoaded`, Zeile 29-32).
- `frontend/src/views/anamnesis/AnamnesisView.vue:152` zeigt
  `{{ template.questionsCount || 0 }} Fragen` — da `questionsCount` vom
  Backend nie geliefert wird, zeigt die Kachel-Liste **immer "0 Fragen"**,
  unabhängig vom tatsächlichen DB-Stand.
- `frontend/src/api/anamnesis.ts:9` deklariert `questionsCount?: number`
  bereits im TypeScript-Interface — das Feld wird im Frontend also bereits
  erwartet, nur vom Backend nie befüllt.
- `openTemplateModal(template)`
  (`frontend/src/views/anamnesis/AnamnesisView.vue:344-345`) übergibt das
  **Listenobjekt** direkt und ungeprüft als `template`-Prop an
  `AnamnesisTemplateFormModal`, statt zuvor per
  `anamnesisTemplatesApi.getById(id)` (bereits vorhanden,
  `frontend/src/api/anamnesis.ts:78-81`) die Detailansicht inkl.
  `questions`-Relation zu laden. In
  `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue:287-303`
  (`watch(() => props.template, …)`) wird `newTemplate.questions?.map(...)
  || []` ausgewertet — da `questions` im Listenobjekt fehlt, zeigt der
  Editor beim erneuten Öffnen einer gerade erstellten Vorlage **0 Fragen**.

### Ursache 2 — echter Persistenz-Bug beim Bearbeiten (nur Edit, echter Datenverlust)

- `AnamnesisTemplateController::update()`
  (`backend/app/Http/Controllers/AnamnesisTemplateController.php:114-124`)
  ruft nur `$anamnesisTemplate->update($request->validatedSnakeCase())`
  auf und lädt `questions` danach nur zur Anzeige nach — es gibt **keinen**
  Code-Pfad, der `questions` aus dem Request synchronisiert.
- `UpdateAnamnesisTemplateRequest::rules()`
  (`backend/app/Http/Requests/UpdateAnamnesisTemplateRequest.php:24-31`)
  validiert `questions` **gar nicht** — das Feld wird komplett ignoriert.
- `AnamnesisTemplateFormModal.vue:388-401` (`save()`) schickt beim
  Bearbeiten (`isEditMode`) jedoch immer ein volles `questions`-Array mit —
  das geht beim `PUT` also **stillschweigend verloren**.

### Neuer Befund während der Architektur-Planung (Risiko, noch nicht in Triage)

Ein naiver Fix von Ursache 2 nach dem Muster von `store()`
(`AnamnesisTemplateController.php:79-90`, "alle Fragen löschen und neu
anlegen") wäre **datenverlust-riskant**:
`backend/database/migrations/2025_12_22_185016_create_anamnesis_answers_table.php:17`
definiert `question_id` als `constrained('anamnesis_questions')->onDelete('cascade')`.
Würde `update()` bestehende Fragen pauschal löschen und neu anlegen, würden
dabei **bereits erfasste Kunden-Antworten (`AnamnesisAnswer`)** auf diese
Fragen per Datenbank-Cascade **unwiderruflich mitgelöscht** — ein neuer,
schwerwiegenderer Datenverlust als der ursprünglich gemeldete. Details und
die gewählte Gegenmaßnahme (ID-basierte Synchronisation mit
Lösch-Schutz für beantwortete Fragen) stehen in `design.md`, Abschnitt 4.

### Als strukturell korrekt verifiziert (kein Fix nötig)

- `store()`-Pfad (`AnamnesisTemplateController.php:71-97`,
  `StoreAnamnesisTemplateRequest.php:24-64`) sieht strukturell korrekt aus
  und ist von einem bestehenden Feature-Test abgedeckt
  (`backend/tests/Feature/AnamnesisTemplateApiTest.php:97-128`, "trainer
  can create template with questions"). **Der aktuelle Grün-Status dieses
  Tests wurde bisher nicht verifiziert** (Docker-Daemon in der Triage-Sitzung
  nicht erreichbar) — das ist der erste Schritt dieses Changes (T01).
- `AnamnesisQuestion::$fillable`
  (`backend/app/Models/AnamnesisQuestion.php:38-45`) enthält alle für den
  Sync benötigten Felder.
- `AnamnesisTemplatePolicy::update()`
  (`backend/app/Policies/AnamnesisTemplatePolicy.php:39-43`) — Autorisierung
  bleibt unverändert korrekt (nur der Vorlagen-Eigentümer-Trainer darf
  bearbeiten).
- Keine Migration/Schema-Änderung nötig: alle betroffenen Spalten
  (`anamnesis_questions.*`) existieren bereits
  (`backend/database/migrations/2025_12_22_184952_create_anamnesis_questions_table.php`).

---

## Ziel

1. Die Kachel-Liste und das erneute Öffnen einer Vorlage zeigen die
   **tatsächliche** Anzahl bzw. den tatsächlichen Inhalt der gespeicherten
   Fragen — unabhängig davon, ob die Vorlage gerade neu angelegt oder schon
   länger bestehend ist.
2. Beim Bearbeiten einer bestehenden Vorlage werden Änderungen an den
   Fragen (hinzufügen, Text/Typ/Optionen ändern, entfernen, Reihenfolge
   ändern) tatsächlich in der Datenbank persistiert.
3. Bereits erfasste Kunden-Antworten (`AnamnesisAnswer`) gehen durch das
   Bearbeiten einer Vorlage **nicht** versehentlich verloren.
4. Der bestehende Create-Flow (`store()`) bleibt nachweislich funktionsfähig
   (Regressionsschutz via T01 + bestehendem Test).

## Proposed Solution

- **T01 (Pflicht, Verifikation zuerst):** Bestehenden Feature-Test
  `AnamnesisTemplateApiTest.php::"trainer can create template with
  questions"` in der Docker-Umgebung laufen lassen und den Create-Flow
  einmal mit direktem DB-Blick verifizieren, **bevor** der Update-Fix
  implementiert wird — schließt aus, dass beim Neu-Anlegen selbst noch ein
  dritter, unentdeckter Bug existiert.
- **T02 (Pflicht, Anzeigeproblem):** `index()` um `withCount('questions')`
  erweitern, `AnamnesisTemplateResource` um `questionsCount` ergänzen
  (Fallback auf geladene `questions`-Relation, falls vorhanden — z. B. nach
  `store()`/`show()`).
- **T03 (Pflicht, echter Update-Bug):** `AnamnesisTemplateController::update()`
  und `UpdateAnamnesisTemplateRequest` um eine ID-basierte
  Fragen-Synchronisation erweitern (Update bestehender, Anlage neuer,
  Löschung entfernter Fragen), mit explizitem Schutz vor Löschung von
  Fragen, die bereits beantwortet wurden (siehe `design.md`, Abschnitt 4).
- **T04 (Pflicht, Frontend Anzeige):** `AnamnesisView.vue`:
  `openTemplateModal()` lädt vor dem Öffnen des Editors die Vorlage per
  `anamnesisTemplatesApi.getById(id)` neu (bereits vorhandene API-Methode).
- **T05 (Pflicht, Frontend Payload):** `AnamnesisTemplateFormModal.vue` (+
  `frontend/src/api/anamnesis.ts`-Typen): Frage-`id` durchs Formular hindurch
  bis in den Speicher-Payload durchreichen, damit das Backend (T03)
  bestehende Fragen anhand ihrer ID erkennen kann, statt sie als neu zu
  behandeln.

## API-Vertrag (Übergabepunkt Backend ↔ Frontend)

- **`questionsCount: number`** — neues, optionales Feld in
  `AnamnesisTemplateResource` (Liste **und** Detail), von T02 geliefert,
  von T04 konsumiert (`frontend/src/api/anamnesis.ts:9` erwartet es
  bereits).
- **`questions[].id?: number`** — beim `PUT
  /api/v1/anamnesis-templates/{id}` optional mitgeschicktes Feld pro
  Frage; vorhanden = bestehende Frage aktualisieren, fehlend = neue Frage
  anlegen. Von T03 validiert/verarbeitet, von T05 im Frontend-Payload
  ergänzt.

## Out of Scope

- Dedizierte Sub-Resource-Endpoints für einzelne Fragen (z. B. `POST/PUT/DELETE
  /anamnesis-templates/{id}/questions/{questionId}`) — nicht angefordert
  (YAGNI); der bestehende Ansatz "ganze Vorlage inkl. Fragen-Array per PUT"
  bleibt architektonisch unverändert.
- Versionierung/Historisierung von Vorlagen-/Fragen-Änderungen — nicht
  angefordert.
- Der vorbestehende `help_text`-Verweis in
  `backend/app/Http/Resources/AnamnesisQuestionResource.php:26`
  (`$this->help_text ?? null`) referenziert eine Spalte, die laut
  `backend/database/migrations/2025_12_22_184952_create_anamnesis_questions_table.php`
  gar nicht existiert. Das ist ein unabhängiger, hier **nicht behobener**
  Altbefund (siehe `design.md`, Abschnitt 6 "Nebenfund") — außerhalb des
  gemeldeten Symptoms und nicht Teil dieses Changes.
- Migrationen/Schema-Änderungen — nicht nötig (siehe oben).

## Referenzen

- Triage: `openspec/triage/20260707114800-fix-anamnesis-template-questions-not-saved.md`
- Keine vorherige aktive Spec für dieses Feature — neue Capability
  `anamnesis-template-management` (siehe `specs/anamnesis-template-management/spec.md`).
