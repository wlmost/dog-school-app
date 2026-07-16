# Notes: T12 — `CustomerDogRequestModal.vue` — Self-Service-Formular erweitern

**Agent:** dev-typescript
**Datei:** `frontend/src/components/CustomerDogRequestModal.vue` (ausschließlich diese Datei angefasst — `DogFormModal.vue` wurde bewusst NICHT bearbeitet, siehe Task-Vorgabe/T11 parallel)

## Umsetzung

Umgesetzt exakt gemäß `design.md` Abschnitt 8.1 (Zeilenangaben dort bezogen
auf den Stand vor dieser Änderung):

1. **Template** (`frontend/src/components/CustomerDogRequestModal.vue:132-171`):
   neuer Grid-Block "Owner Since & Origin" (Datum `type="date"` +
   Select mit vier Optionen `breeder`/`shelter`/`private`/`unknown`,
   deutsche Labels Züchter/Tierschutz/Privat/unbekannt) direkt nach dem
   bestehenden "Chip Number"-Block, sowie separater Block "Age at
   Acquisition" (Freitext) — beide vor dem bestehenden "Notes"-Block.
   `id`/`for`-Attribute (`dog-owner-since`, `dog-origin`,
   `dog-age-at-acquisition`) folgen dem bestehenden Muster der Komponente
   (jedes Feld hat `id` + `for`, im Unterschied zu `DogFormModal.vue`).
2. **`form`-Ref** (Zeile ~239-250): drei neue camelCase-Keys ergänzt:
   `ownerSince: ''`, `ageAtAcquisition: ''`, `origin: ''` — konsistent mit
   dem bestehenden camelCase-Stil dieser Komponente (kein snake_case, im
   Unterschied zu `DogFormModal.vue`).
3. **`resetForm()`** (Zeile ~262-278): dieselben drei Keys mit Leerstring
   ergänzt.
4. **`handleSubmit()`-Payload** (Zeile ~285-296): drei neue Zeilen ergänzt,
   `|| null`-Pattern analog zum bestehenden `gender`/`chipNumber`-Muster:
   `ownerSince: form.value.ownerSince || null`,
   `ageAtAcquisition: form.value.ageAtAcquisition || null`,
   `origin: form.value.origin || null`.

Keine Änderung an `translateError()` o.ä. — diese Komponente hat keine
solche Funktion; Fehlerbehandlung läuft bereits generisch über
`err.response?.data?.errors` (unverändert).

## Abweichungen von design.md

Keine. Umsetzung 1:1 nach Abschnitt 8.1.

## Tests

Kein neues Test-File angelegt — für `CustomerDogRequestModal.vue` existiert
laut Skeptiker-Verifikation (`verification.md` Zeile 72) und eigener Prüfung
(`ls frontend/src/components/ | grep -i CustomerDogRequest` → nur die
`.vue`-Datei, keine `.test.ts`) aktuell kein Vitest-Test. Das ist laut
Task-Vorgabe explizit **kein** Scope dieses Tasks (YAGNI, siehe `design.md`
Abschnitt 8.1 letzter Absatz).

## QA

- `npm run build` (= `vue-tsc -b && vite build`) — erfolgreich, keine
  TypeScript-Fehler, keine Warnings im Build-Output.
- Kein `npm run lint`-Script im Projekt vorhanden (`frontend/package.json`
  Scripts: `dev`, `build`, `build:deploy`, `preview`, `test`, `test:ui`,
  `test:coverage`, `e2e`, `e2e:ui`) — laut Skeptiker-Verifikation
  vorbestehender Zustand, daher kein Akzeptanzkriterium (siehe
  `verification.md` Zeile 95, `tasks.md` T12 letztes Akzeptanzkriterium).
- `npm run test` nicht separat für diese Komponente relevant, da kein
  Test-File existiert; bestehende Vitest-Suite wurde nicht angefasst und
  ist durch diese Änderung nicht betroffen (andere Komponente als
  `DogFormModal.test.ts`, die von T11 bearbeitet wird).

## Akzeptanzkriterien (aus tasks.md T12)

- [x] Alle drei neuen Felder sind im Formular sichtbar und editierbar
- [x] `resetForm()` setzt alle drei Felder beim erneuten Öffnen des Modals
      zurück
- [x] Der Submit-Payload (`POST /api/v1/dog-registration-requests`) enthält
      `ownerSince`/`ageAtAcquisition`/`origin` mit `null` bei leeren
      Eingaben
- [x] `npm run build` ohne Warnings (`vue-tsc -b`-Teil des Builds); kein
      `lint`-Script vorhanden, daher kein Akzeptanzkriterium
