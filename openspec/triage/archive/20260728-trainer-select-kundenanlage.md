# Triage: Trainerauswahl bei Kundenanlage fehlt / keine Vorauswahl

**Pfad:** standard
**Geschätzter Umfang:** ca. 3–6 Dateien, Backend (PHP/Laravel) + Frontend (Vue/TypeScript)
**Risiko:** hoch — die Kernursache ist keine unbeabsichtigte Regression, sondern eine bewusst getestete Autorisierungsgrenze (`can:admin` auf `GET /api/v1/trainers`); eine Änderung berührt eine öffentliche API-Schnittstelle und potenziell die Sichtbarkeit personenbezogener Daten anderer Trainer.
**Klarheit:** mehrdeutig — offene Fragen zu Datenumfang und zur Admin-Teilaussage (siehe unten).

## Anforderung (Zusammenfassung)

Beim Anlegen eines neuen Kunden soll ein Trainer zugewiesen werden können.
Aktuell zeigt die Trainer-Select-Box im Kundenformular nur die Option "Kein
Trainer zugewiesen", obwohl Trainer im System existieren. Zusätzlich soll,
wenn ein eingeloggter Trainer (nicht Admin) selbst einen Kunden anlegt, dieser
Trainer in der Auswahl automatisch vorausgewählt sein.

## Befund im Repo (mit Datei:Zeile belegt)

1. **Frontend — Formular ist bereits korrekt verdrahtet:**
   `frontend/src/components/CustomerFormModal.vue:100-106` rendert die
   Trainer-Select-Box aus `trainers` (Zeile 258), befüllt via `loadTrainers()`
   (Zeile 338-345), aufgerufen bei jedem Öffnen des Modals
   (Zeile 283-285, `watch(() => props.isOpen, ...)`).
   Die Vorauswahl-Logik für Trainer-Nutzer existiert bereits:
   `frontend/src/components/CustomerFormModal.vue:291-294` setzt
   `form.value.trainer_id = currentUser.value.id`, wenn
   `currentUser.value?.role === 'trainer'`.

2. **Root Cause (Backend, bewusst getestet):**
   `backend/routes/api.php:193-196` — die Route `GET /api/v1/trainers` liegt
   in einer `Route::middleware('can:admin')`-Gruppe, ist also **nur für
   Admins** zugänglich. Das Gate ist definiert in
   `backend/app/Providers/AppServiceProvider.php:61-63`
   (`Gate::define('admin', fn ($user) => $user->isAdmin());`).
   Dieses Verhalten ist explizit durch einen Feature-Test abgesichert:
   `backend/tests/Feature/TrainerApiTest.php:56-61`
   ("Trainer-Rolle … erhält 403 beim auflisten von trainern").
   → Wenn ein **Trainer** (nicht Admin) das Kundenformular öffnet, schlägt
   `loadTrainers()` mit 403 fehl. Der Fehler wird in
   `frontend/src/components/CustomerFormModal.vue:342-343` nur
   `console.error`-geloggt und **verschluckt** — `trainers.value` bleibt
   leer. Dadurch fehlt nicht nur die Auswahlliste, sondern auch die
   Vorauswahl läuft ins Leere (der gesetzte `trainer_id`-Wert hat kein
   passendes `<option>`, die Select-Box zeigt "Kein Trainer zugewiesen").
   `UserResource` (`backend/app/Http/Resources/UserResource.php`) liefert
   volle Trainer-Profildaten (E-Mail, Telefon, Adresse, Qualifikationen) —
   das ist vermutlich der Grund, warum die Liste bewusst auf Admin
   beschränkt wurde.

3. **Admin-Fall ungeklärt:** Für echte Admins zeigt
   `backend/tests/Feature/TrainerApiTest.php:17-22`, dass
   `GET /api/v1/trainers` mit `assertOk()` funktioniert — im Code ist kein
   Grund erkennbar, warum ein Admin dieselbe Störung sehen sollte. Die
   User-Beschreibung "als Admin oder Trainer" ist daher teilweise
   **ungeprüfte Referenz** — siehe Rückfrage unten.

4. **Verwandte Fundstelle (nicht Teil der gemeldeten Anforderung, aber
   gleiche Ursache):** `frontend/src/components/CourseFormModal.vue` ruft
   ebenfalls `/api/v1/trainers` auf (Trainer-Zuweisung bei Kursanlage) und
   dürfte für die Rolle "Trainer" densel­ben 403 erleiden. Sollte dem
   Architekten als möglicher gemeinsamer Fix-Ort genannt werden.

## Rückfragen an den User

- Bestätigung: Tritt das Problem tatsächlich auch beim Einloggen als
  **echter Admin** auf (leere Trainerliste), oder wurde die Beschreibung
  primär aus Trainer-Sicht verfasst? Laut Code + bestehendem Feature-Test
  sollte `GET /api/v1/trainers` für Admins funktionieren — falls doch nicht,
  gibt es vermutlich eine zweite, bisher nicht lokalisierte Ursache
  (z. B. leerer Datenbestand in der konkreten Umgebung).
- Datenschutz-Umfang: Soll ein Trainer beim Anlegen eines Kunden die
  **volle** Trainerliste mit allen Profildaten sehen dürfen (aktuelle
  `UserResource`), oder reicht eine reduzierte Liste (nur `id` + Name) für
  die Select-Box? Das beeinflusst, ob die bestehende Route geöffnet wird
  oder ein neuer, schlanker Endpunkt (z. B. `GET /api/v1/trainers/options`)
  entsteht.
- Soll die Vorauswahl-Automatik (Trainer sieht sich selbst vorausgewählt)
  änderbar bleiben (Trainer kann einen anderen Trainer wählen), oder soll
  ein Trainer beim Anlegen eines eigenen Kunden zwingend sich selbst
  zuweisen (Select ggf. deaktiviert)? Aktueller Code erlaubt Änderung.
- Soll `frontend/src/components/CourseFormModal.vue` (gleiche
  Trainer-Endpunkt-Abhängigkeit) im selben Change mit adressiert werden,
  oder ist das bewusst außerhalb des Scopes?

## Empfohlene nächste Aktion

`@architect` (Modus A) erstellt einen openspec-Change (Vorschlag-ID z. B.
`fix-trainer-select-customer-creation`) mit Fokus auf:
- Design-Entscheidung Backend: neue schlanke, Rollen-offene Trainer-Options-
  Route vs. Öffnung der bestehenden `can:admin`-Route für die Rolle
  `trainer` (inkl. Anpassung/Erweiterung von
  `backend/tests/Feature/TrainerApiTest.php`, da dort das 403-Verhalten
  aktuell explizit erwartet wird).
- Fehlerbehandlung im Frontend: `loadTrainers()`
  (`frontend/src/components/CustomerFormModal.vue:338-345`) darf Fehler
  nicht mehr nur verschlucken (User-Feedback bei Ladefehler).
- Task für `dev-php` (Backend-Route/Controller/Tests) und ggf. `dev-typescript`
  (Frontend-Fehlerbehandlung, Verifikation der bereits vorhandenen
  Vorauswahl-Logik).
Da eine bestehende, getestete Autorisierungsgrenze verändert wird, MUSS der
Change durch `@skeptic` geprüft und dem User im User-Gate 1 explizit mit der
Datenschutz-Frage vorgelegt werden, bevor Implementierung beginnt.
