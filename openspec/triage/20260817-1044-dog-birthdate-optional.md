# Triage: Geburtsdatum bei Hundeanlage optional machen

**Pfad:** trivial
**Geschätzter Umfang:** 1 Datei (Backend, PHP). Kein Frontend-Change nötig (siehe unten).
**Risiko:** niedrig — reine Lockerung einer Validierungsregel (`required` → `nullable`), keine Migration, kein Schnittstellenbruch, kein Datenverlust.
**Klarheit:** klar — eindeutige, einzeilige Anforderung.

## Anforderung (Zusammenfassung)
Beim Anlegen eines neuen Hundes soll das Geburtsdatum (`dateOfBirth`) kein
Pflichtfeld mehr sein. Aktuell verlangt die Backend-Validierung beim direkten
Anlegen eines Hundes (Admin/Trainer-Flow) ein Geburtsdatum; das soll entfallen,
sodass Hunde auch ohne bekanntes Geburtsdatum angelegt werden können.

## Codebasis-Befund (geprüft, nicht geraten)

- **Migration** `backend/database/migrations/2025_12_22_184754_create_dogs_table.php:19`:
  Spalte `date_of_birth` ist bereits `->nullable()`. **Keine Migration nötig.**
- **Model** `backend/app/Models/Dog.php`:
  - `getAgeAttribute()` (Zeile 138–145) ist bereits null-sicher (`if (! $this->date_of_birth) return null;`).
  - `scopePuppies()` (Zeile 158–162) filtert bereits explizit mit `whereNotNull('date_of_birth')`.
  - **Keine Model-Änderung nötig.**
- **Validierung — der eigentliche Fund:**
  - `backend/app/Http/Requests/StoreDogRequest.php:38` — Admin/Trainer-Flow zum direkten
    Anlegen eines Hundes: `'dateOfBirth' => ['required', 'date', 'before:today']`.
    **Das ist die Regel, die geändert werden muss** (→ `['nullable', 'date', 'before:today']`).
  - `backend/app/Http/Requests/UpdateDogRequest.php:54` — beim Update bereits `sometimes` (kein Zwang).
  - `backend/app/Http/Requests/StoreDogRegistrationRequest.php:40` — Kunden-Self-Service-Flow zur
    Hunde-Registrierung hat `dateOfBirth` bereits als `nullable`. Dort ist nichts zu tun.
- **Resource** `backend/app/Http/Resources/DogResource.php:33`: `$this->date_of_birth?->toDateString()`
  ist bereits null-sicher. **Keine Änderung nötig.**
- **Frontend** `frontend/src/components/DogFormModal.vue`:
  - Das Geburtsdatum-Feld (Zeile 109–115, Label "Geburtsdatum") hat **kein** `*`-Pflichtfeld-Kennzeichen
    und **kein** HTML-`required`-Attribut — im Gegensatz zu Besitzer, Name und Rasse (Zeilen 82/87, 97/98,
    102/103, jeweils mit `*` im Label und `required`-Attribut).
  - Das Frontend behandelt das Geburtsdatum **bereits als optional**. Es existiert sogar schon eine
    deutsche Fehlermeldungs-Übersetzung für den Fall, dass das Backend "required" zurückmeldet
    (Zeile 408: `'The date of birth field is required': 'Das Geburtsdatum ist erforderlich'`), die nach
    der Änderung schlicht nicht mehr getriggert wird — kann bestehen bleiben oder optional entfernt werden.
  - **Keine zwingende Frontend-Änderung nötig**, um die Anforderung zu erfüllen. Optional: die nun
    tote Übersetzungszeile 408 aufräumen (kosmetisch, kein Muss).

## Rückfragen an den User
Keine — Anforderung ist eindeutig, Codebasis-Recherche bestätigt einen einzigen, klar lokalisierten
Änderungspunkt.

## Empfohlene nächste Aktion
Kein Architekt nötig (trivial-Pfad). Direkt `dev-php` beauftragen:

> Ändere `backend/app/Http/Requests/StoreDogRequest.php:38` von
> `'dateOfBirth' => ['required', 'date', 'before:today']` zu
> `'dateOfBirth' => ['nullable', 'date', 'before:today']`.
> Passe ggf. zugehörige Feature-Tests unter `backend/tests/Feature/` an, die aktuell erwarten, dass
> das Anlegen eines Hundes ohne `dateOfBirth` einen Validierungsfehler wirft, und ergänze einen Test,
> der bestätigt, dass ein Hund ohne Geburtsdatum erfolgreich angelegt werden kann.

Ablauf gemäß Trivial-Pfad in `~/.claude/WORKFLOW.md` (Edge Case "trivial"):
Feature-Branch `feature/dog-birthdate-optional` anlegen → `dev-php` implementiert →
`composer qa` laufen lassen → Commit `fix: Geburtsdatum bei Hundeanlage optional machen` → PR.
Kein openspec-Change, kein Architekt/Skeptiker nötig.
