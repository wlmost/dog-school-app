# Notes T01: Verifikation des bestehenden Create-Flows (vor jedem Code-Fix)

**Agent:** dev-php
**Datum:** 2026-07-07

## Ergebnis (Kurzfassung)

**Grün / kein Blocker.** Der bestehende Create-Flow (`store()`) funktioniert
wie vom Code erwartet: Fragen landen tatsächlich in der Datenbank. Es gibt
**keinen** dritten, unentdeckten Bug im Create-Pfad. T02 und T03 können wie
geplant fortgesetzt werden.

## 1. Docker-Umgebung

Docker lief bereits (`docker compose ps` zeigte alle Container als
`Up`/`healthy`: `nginx`, `php`, `postgres`, `redis`, `node`, `queue`,
`scheduler`, `mailpit`). Kein `docker compose up -d` nötig.

## 2. Testlauf `AnamnesisTemplateApiTest`

**Hinweis zur Befehlsdiskrepanz (nicht Teil des T01-Scopes, hier nur
dokumentiert, analog zum bereits in `openspec/changes/archive/2026-07-06-fix-dog-image-upload-shared-hosting/task-T01.notes.md`
festgehaltenen Befund):** Der in der Task-Beschreibung und in `CLAUDE.md`
Abschnitt 5 genannte Befehl `docker compose exec app composer test --
--filter=AnamnesisTemplateApiTest` funktioniert in dieser Umgebung nicht
1:1:

- Der PHP-Service in `docker-compose.yml` heißt `php`, nicht `app`
  (`docker-compose.yml:22`, `container_name: dog-school-php`).
- Innerhalb des Containers ist `/var/www/html` (also `backend/`) das
  Arbeitsverzeichnis. Das dort gemountete `backend/composer.json` enthält
  **keine** `test`/`qa`/`lint`/`stan`/`compat-check`-Scripts (im Gegensatz
  zum Root-`composer.json`, das diese Scripts definiert, aber im Container
  gar nicht zum Tragen kommt, da kein Root-`vendor/` existiert). `composer
  test` schlägt dort mit "Command \"test\" is not defined." fehl.

Tatsächlich ausgeführter, äquivalenter Befehl:

```bash
docker compose exec php vendor/bin/pest --filter=AnamnesisTemplateApiTest
```

**Ergebnis: GRÜN.**

```
PASS  Tests\Feature\AnamnesisTemplateApiTest
 ✓ can list anamnesis templates                                         0.28s
 ✓ admin cannot list anamnesis templates                                0.02s
 ✓ can filter templates by trainer                                      0.02s
 ✓ can filter default templates                                         0.02s
 ✓ can search templates by name                                         0.03s
 ✓ trainer can create template without questions                        0.03s
 ✓ trainer can create template with questions                           0.02s
 ✓ customer cannot create template                                      0.03s
 ✓ can view template details with questions                             0.02s
 ✓ admin cannot view anamnesis template                                 0.02s
 ✓ trainer can update own template                                      0.02s
 ✓ trainer cannot update another trainers template                      0.02s
 ✓ admin cannot update template                                         0.02s
 ✓ trainer can delete own template                                      0.02s
 ✓ trainer cannot delete other trainers template                        0.02s
 ✓ admin cannot delete template                                         0.02s
 ✓ cannot delete template with responses                                0.03s
 ✓ customer cannot delete template                                      0.02s
 ✓ admin cannot create anamnesis template                               0.02s
 ✓ can get template questions ordered                                   0.02s
 ✓ validates required fields when creating template                    0.02s
 ✓ validates question types                                             0.02s

Tests:    22 passed (92 assertions)
Duration: 1.04s
```

Insbesondere der in `proposal.md` referenzierte Test "trainer can create
template with questions" (`AnamnesisTemplateApiTest.php:97-128`) ist grün.

## 3. Manueller Create-Flow-Test mit direktem DB-Blick

Da der `/api/v1/*`-Endpunkt `auth:sanctum`-geschützt ist
(`backend/routes/api.php:69` ff., `Route::apiResource('anamnesis-templates',
AnamnesisTemplateController::class)` innerhalb der
`middleware('auth:sanctum')`-Gruppe), wurde ein kleines, einmaliges
Bootstrap-Skript verwendet (kein Dauerhaft-Artefakt, nach dem Test wieder
entfernt), das:

1. Laravel bootstrapt (`bootstrap/app.php`),
2. einen temporären Trainer-User per Factory anlegt,
3. via Sanctum ein Personal-Access-Token für diesen User erzeugt,
4. per `Illuminate\Support\Facades\Http` (mit Bearer-Token) einen echten
   `POST` gegen `http://nginx/api/v1/anamnesis-templates` (internes
   Docker-Netzwerk, identischer Pfad wie die echte API) mit einer Vorlage
   und **zwei** Fragen absetzt,
5. die Antwort ausgibt,
6. per `DB::table('anamnesis_questions')->where('template_id', $id)->get()`
   direkt (Eloquent-Query-Builder, kein raw SQL) gegen die DB prüft,
7. die angelegten Test-Daten (Fragen, Vorlage, Trainer, Token) wieder
   löscht, um die Dev-DB nicht zu verschmutzen.

`php artisan tinker --execute="..."` bzw. `php artisan tinker
<datei>.php` wurden zunächst probiert, liefen aber in dieser
Docker/PsySH-Konfiguration nicht nicht-interaktiv durch (die Eingabe wurde
nur echo't, keine Ausführung/Ausgabe der `echo`-Statements sichtbar,
Prozess blieb im interaktiven Prompt hängen). Das direkte Bootstrap-Skript
(`php <skript>.php`, ausgeführt via `docker compose exec php php
/tmp/t01_verify2.php`) war die zuverlässige Alternative und nutzt exakt
denselben Anwendungscode (`bootstrap/app.php`, echte HTTP-Kernel-Pipeline
inkl. Middleware/Policies/Request-Validation) wie ein echter Request.

**Payload (Auszug):**

```json
{
  "name": "T01 Verify Template",
  "description": "Manual verification for T01",
  "isDefault": false,
  "questions": [
    {"questionText": "Wie alt ist der Hund?", "questionType": "text", "isRequired": true, "order": 1},
    {"questionText": "Gibt es Allergien?", "questionType": "text", "isRequired": false, "order": 2}
  ]
}
```

**Ergebnis:**

- HTTP-Status: `201`
- Response-Body enthält `data.questions` mit beiden Fragen
  (`id=37`/`id=38` in diesem Testlauf, `templateId=4`).
- Direkter DB-Blick (`SELECT * FROM anamnesis_questions WHERE template_id =
  4`, äquivalent per `DB::table(...)->get()`) bestätigt: **beide Fragen
  sind tatsächlich in der Tabelle `anamnesis_questions` vorhanden**, mit
  korrektem `template_id`, `question_text`, `question_type` und `order`.
- Nach dem Test wurden die Test-Fragen, die Test-Vorlage sowie der
  Test-Trainer-User inkl. Token wieder gelöscht; per Nachkontrolle
  bestätigt (`AnamnesisTemplate::find(4)` liefert `null`, keine
  verbleibenden `t01-verify-*`-User).

**Erwartungsgemäß fehlt im Response-Body das Feld `questionsCount`** — das
ist exakt die in `proposal.md` (Ursache 1) beschriebene, noch offene Lücke
und wird durch T02 behoben, nicht durch T01.

## 4. Bewertung

- Der `store()`-Pfad speichert Fragen korrekt und vollständig in der DB.
  Kein dritter, unentdeckter Bug im Create-Flow.
- Keine Abweichung von der erwarteten grünen Baseline.
- **T02 und T03 können wie geplant fortgesetzt werden**, kein
  Rücksprache-Bedarf beim Architekten/User.

## Abgrenzung

- Keine Code-Änderung an `AnamnesisTemplateController.php`,
  `AnamnesisTemplateResource.php`, `UpdateAnamnesisTemplateRequest.php`
  oder `AnamnesisTemplateApiTest.php` — reine Verifikation, wie in
  `tasks.md` (T01) vorgegeben.
- Keine Testlücke im bestehenden `AnamnesisTemplateApiTest.php` für den
  Create-Flow selbst festgestellt (das bestehende Testszenario "trainer
  can create template with questions" deckt den Sachverhalt bereits ab und
  ist grün) — daher keine zusätzliche Dokumentation einer Testlücke
  erforderlich.
- Die unter Punkt 2 dokumentierte Composer-Skript-Diskrepanz
  (Root- vs. `backend/composer.json`) ist ein vorbestehender,
  unabhängiger Befund (bereits in
  `openspec/changes/archive/2026-07-06-fix-dog-image-upload-shared-hosting/task-T01.notes.md`
  dokumentiert) und wird hier nur zur Transparenz erneut erwähnt, nicht im
  Rahmen dieser Task behoben.
