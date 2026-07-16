# Notes T11: `DogFormModal.vue` — Admin/Trainer-Formular erweitern

## Umsetzung

Umgesetzt exakt nach `design.md` Abschnitt 7.1.

- `frontend/src/components/DogFormModal.vue`
  - Neuer Grid-Block „Owner History" nach dem bestehenden „Additional Info"-Block
    (vor der Error-Message), analog zum vorgegebenen Muster: Datum „Beim
    Halter seit" (`type="date"`, mit `showPicker()`-Klick-Handler wie beim
    bestehenden `date_of_birth`-Feld), Select „Herkunft"
    (`breeder`/`shelter`/`private`/`unknown`, Leer-Option „Nicht angegeben"),
    Text „Alter bei Einzug" (Placeholder „z.B. ca. 2 Jahre").
  - `form`-Ref: `owner_since`, `age_at_acquisition`, `origin` als neue
    Leerstring-Keys ergänzt (snake_case, konsistent mit den übrigen
    Feldern dieser Komponente wie `date_of_birth`, `chip_number`).
  - `watch(() => props.dog, ...)`: die drei Felder werden aus
    `newDog.ownerSince`, `newDog.ageAtAcquisition`, `newDog.origin`
    übernommen (`|| ''`-Fallback, analog zu `gender`/`notes`).
  - `resetForm()`: dieselben drei Keys mit Leerstring ergänzt.
  - `saveDogRecord()`-Payload: `ownerSince`, `ageAtAcquisition`, `origin`
    im camelCase-API-Payload ergänzt, jeweils `form.value.<key> || null`
    (identisches Pattern zu `gender`/`chipNumber`/`color`).
  - `translateError()`: zusätzlicher Eintrag
    `'The selected origin is invalid' → 'Die ausgewählte Herkunft ist ungültig'`
    ergänzt (optional laut design.md, aus Konsistenz mit dem bestehenden
    `gender`-Übersetzungseintrag aufgenommen).

- `frontend/src/components/DogFormModal.test.ts`
  - Neue `describe`-Gruppe „Übernahme-Historie (Beim Halter seit /
    Herkunft / Alter bei Einzug)" mit 6 Tests:
    - Anzeige aller drei Felder inkl. Prüfung der vier Herkunfts-Optionen
      plus Leer-Option.
    - Vorbefüllung aus `props.dog` beim Bearbeiten eines bestehenden Hundes.
    - Leere Felder beim Anlegen eines neuen Hundes.
    - Payload enthält `null` für alle drei Felder, wenn sie leer bleiben.
    - Payload enthält die korrekt gesetzten Werte, wenn die Felder befüllt
      werden.
    - `resetForm()` (ausgelöst über den Abbrechen-Button) setzt alle drei
      Felder zurück.
  - Da die Komponente keine `id`/`for`-Attribute für diese Felder nutzt
    (im Unterschied zu `CustomerDogRequestModal.vue`, siehe design.md
    Abschnitt 8.1), werden die Felder über strukturelle Selektoren
    identifiziert: zweites `input[type="date"]` (erstes ist
    `date_of_birth`), letztes `<select>` (erstes ist `gender`; das
    `customer_id`-Select wird im Test durch die gemockte
    `customer`-Rolle gar nicht gerendert) sowie
    `input[placeholder="z.B. ca. 2 Jahre"]` für „Alter bei Einzug".

## Abweichungen von design.md

Keine. Template, `form`-Ref, `watch()`, `resetForm()` und
`saveDogRecord()`-Payload entsprechen 1:1 den in `design.md` Abschnitt 7.1
zitierten Code-Blöcken.

## Anmerkung zum Backend-Kontrakt

T11 wurde gegen den in `design.md` Abschnitt 7.1 fixierten API-Kontrakt
entwickelt (camelCase `ownerSince`/`ageAtAcquisition`/`origin` in Request
und Response). Zum Zeitpunkt der Implementierung waren
`backend/database/migrations/2026_07_16_120000_*.php` und
`2026_07_16_120001_*.php` bereits als neue Dateien im Arbeitsverzeichnis
vorhanden (paralleler Backend-Fortschritt), der restliche Backend-Stand
(T03–T10) wurde für diese Task nicht geprüft — laut Abhängigkeitsgraph in
`tasks.md` ist das für T11 nicht erforderlich, sollte aber vor dem finalen
Review verifiziert werden (siehe `tasks.md` „Übergabepunkt Backend →
Frontend").

## Pre-Flight-Checks (CLAUDE.md Abschnitt 7.1)

- `npx vitest run src/components/DogFormModal.test.ts` → 13 Tests grün
  (7 bestehende + 6 neue).
- `npx vitest run` (komplette Frontend-Suite) → 134 Tests grün, 12 Testdateien.
- `npm run build` (`vue-tsc -b && vite build`) → erfolgreich, keine
  TypeScript- oder Vite-Warnings.
- Kein `npm run lint`-Script in `frontend/package.json` vorhanden (siehe
  `proposal.md` „Out of Scope — Fehlende QA-Scripts") — daher nicht
  ausgeführt, konsistent mit T12-Vorgehen.

## Geänderte/neue Dateien

- `frontend/src/components/DogFormModal.vue` (geändert)
- `frontend/src/components/DogFormModal.test.ts` (geändert)
- `openspec/changes/add-dog-owner-history-fields/tasks.md` (T11-Checkboxen
  abgehakt)
