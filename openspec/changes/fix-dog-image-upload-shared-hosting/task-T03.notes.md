# Notes: T03 — `DogFormModal.vue` — Bild-Upload-Fehler nicht mehr verschlucken

**Agent:** dev-javascript
**Datei:** `frontend/src/components/DogFormModal.vue` (geändert),
`frontend/src/components/DogFormModal.test.ts` (neu)

## Umsetzung

`handleSubmit()` (`frontend/src/components/DogFormModal.vue`) wurde in drei
Funktionen aufgeteilt (SRP, bessere Testbarkeit):

- `saveDogRecord()` — reiner Stammdaten-Save (PUT/POST), unverändert in der
  Logik gegenüber vorher, gibt die Dog-Id zurück.
- `uploadDogImage(dogId)` — reiner Bild-Upload; setzt bei Fehler zusätzlich
  zum bestehenden Toast (`handleApiError`) `error.value` (dauerhafter roter
  Banner, `DogFormModal.vue:166-168`, bereits vorhanden) und gibt
  `false`/`true` zurück.
- `handleSubmit()` orchestriert beide: Stammdaten nur senden, wenn noch
  keine `savedDogId` für diese Modal-Sitzung bekannt ist; danach Bild-Upload
  nur, wenn eine Datei ausgewählt wurde. Schlägt der Bild-Upload fehl, wird
  **kein** `emit('saved')` und **kein** `closeModal()`/`emit('close')`
  ausgelöst (`return` direkt nach `uploadDogImage()` liefert `false`) — exakt
  wie in `design.md`, Abschnitt 3 ("Entscheidung — Lösung (a)") vom
  Architekten nach Skeptiker-Korrektur festgelegt.

### Neuer State: `savedDogId`

Ein neuer `ref<any>(null)` `savedDogId` merkt sich die Id des im aktuellen
Modal-Durchlauf bereits erfolgreich gespeicherten Hundes (Create **und**
Update, nicht nur Create — schadet im Update-Fall nicht, vermeidet dort
sogar ein unnötiges erneutes PUT). Reset erfolgt:

- beim (Wieder-)Öffnen des Modals (bestehender `watch(() => props.isOpen, ...)`),
- in `resetForm()` (läuft u. a. beim erfolgreichen Schließen und beim
  Abbrechen, sofern kein Fehler vorliegt).

Ein erneuter Klick auf "Speichern" nach gescheitertem Bild-Upload
überspringt damit `saveDogRecord()` und ruft nur `uploadDogImage()` erneut
auf — kein zweiter Hund wird angelegt (Akzeptanzkriterium 3 in `tasks.md`).

### Fehlertext

`error.value` beim Bild-Upload-Fehlerfall:
`"Der Hund wurde gespeichert, aber das Profilbild konnte nicht hochgeladen
werden: ${imgError}. Bitte erneut versuchen."` — analog zum in `tasks.md`
vorgeschlagenen Wortlaut. Keine `translateError()`-Anwendung, da
`imgErr.response?.data?.message` hier meist bereits eine spezifische
Backend-Meldung ist (z. B. Validierungsfehler zur Bildgröße) und
`translateError()` nur für die bekannten Stammdaten-Validierungstexte
Übersetzungen enthält (siehe `translateError()`-Dictionary,
`DogFormModal.vue:346-355`) — für Bild-Fehlermeldungen gäbe es dort ohnehin
keinen Treffer, der Rohtext würde unverändert durchgereicht. Das entspricht
der in `design.md:410-413` als akzeptabel beschriebenen Variante ("sonst
reicht der Rohtext").

### `DogsView.vue`

**Nicht verändert**, wie von `tasks.md`/`design.md` gefordert. Der Fix löst
das Problem vollständig durch Unterdrücken beider Events (`saved`, `close`)
in `DogFormModal.vue` selbst.

## TypeScript-Hinweis (Abweichung von der Rollenvorgabe "reines JS")

`DogFormModal.vue` verwendet bereits vor dieser Änderung durchgängig
`<script setup lang="ts">` mit TS-Typannotationen (`ref<string | null>`,
`catch (err: any)` etc.). Das ist **projektweite Konvention**: 49 von 52
`.vue`-Dateien unter `frontend/src` nutzen `lang="ts"`, und
`frontend/package.json` `"build"`-Skript ruft explizit `vue-tsc -b && vite
build` auf — das gesamte Frontend ist also durchgehend typisiert und der
Build ist ohne `vue-tsc`-Lauf gar nicht vollständig.

Das steht im Konflikt mit der generischen Rollenvorgabe "reines JS, kein
TypeScript — falls die Task TS verlangt, brich ab und verweise auf
`dev-typescript`". `CLAUDE.md` (die laut Systemprompt alle Default-Verhalten
überschreibt) ordnet jedoch explizit **alle** Vue-SFCs unter
`frontend/src/**/*.vue` dem `dev-javascript`-Agenten zu und verbietet
`dev-typescript` für dieses Projekt ausdrücklich ("Nicht in diesem Projekt
verwenden: `dev-go`, `dev-typescript`"). Der Architekt hat T03 zudem explizit
mit `Agent: dev-javascript` für exakt diese Datei angelegt.

**Entscheidung:** Task fortgeführt statt abgebrochen, da: (1) es sich um eine
kleine Logikänderung in einer bereits bestehenden, projektweit
TS-typisierten Datei handelt (keine Neuanlage von TS-Code/-Dateien), (2)
`CLAUDE.md` für dieses Projekt keinen `dev-typescript`-Agenten vorsieht und
ein Abbruch die Task unbearbeitbar gemacht hätte, (3) das Beibehalten der
bestehenden Typannotationen (statt sie zu entfernen) Konsistenz mit den
übrigen 48 SFCs wahrt und einen unnötigen, risikoreichen Stilbruch
vermeidet. Diese Abweichung wird hier transparent dokumentiert, wie von den
Anti-Halluzinations-Regeln gefordert ("bei Unsicherheit: in Notes
dokumentieren, nicht erfinden").

## Tests

Neue Datei `frontend/src/components/DogFormModal.test.ts` (7 Tests, an den
Stil von `CustomerBookingModal.test.ts` angelehnt: HeadlessUI-Stubs,
`vi.mock` für `@/api/client`, `@/stores/auth`, `@/utils/errorHandler`).
Rollen-Mock nutzt `role: 'customer'`, damit kein zusätzliches Mocking von
`GET /api/v1/customers` nötig ist (Kundenrolle zeigt das Besitzerfeld als
Read-only-Text statt Dropdown).

Abgedeckte Szenarien (1:1 auf die Akzeptanzkriterien in `tasks.md` gemappt):

1. Bild-Upload schlägt fehl → weder `saved` noch `close` werden emittiert.
2. Bild-Upload schlägt fehl → dauerhafter Fehlerbanner-Text im DOM (nicht
   nur `handleApiError`/Toast-Aufruf).
3. Retry nach gescheitertem Bild-Upload → genau **ein** `POST
   /api/v1/dogs`, aber zwei `POST .../upload-image`-Aufrufe (kein
   doppelter Hund).
4. Erfolgreicher Retry → `saved` und `close` werden (erst beim zweiten
   Versuch) emittiert, weiterhin nur ein `POST /api/v1/dogs`.
5. Abbrechen-Button nach gescheitertem Bild-Upload → `close` wird emittiert,
   weiterhin nur ein `POST /api/v1/dogs`.
6. Regression: kein Bild ausgewählt → `saved`/`close` wie bisher, kein
   `upload-image`-Request.
7. Regression: Bild-Upload erfolgreich → `saved`/`close` wie bisher.

## Pre-Flight-Checks (Docker, `dog-school-node`-Service)

```
docker compose exec node sh -c "cd /var/www/html/frontend && npx vitest run src/components/DogFormModal.test.ts"
→ 7 passed

docker compose exec node sh -c "cd /var/www/html/frontend && npm run test -- run"
→ 12 Testdateien, 128 Tests, alle grün (inkl. der 7 neuen)

docker compose exec node sh -c "cd /var/www/html/frontend && npm run build"
→ vue-tsc -b && vite build erfolgreich, keine Type-Errors, kein Build-Fehler,
  keine Warnungen (Chunk-Größen wie gehabt, kein "large chunk"-Hinweis)
```

**`npm run lint` existiert nicht** in `frontend/package.json` (Skripte:
`dev`, `build`, `build:deploy`, `preview`, `test`, `test:ui`,
`test:coverage`, `e2e`, `e2e:ui` — kein `lint`). Es gibt auch keine
ESLint-Konfiguration im `frontend/`-Verzeichnis (`.eslintrc*` /
`eslint.config*` nicht vorhanden). Das ist eine vorbestehende Lücke im
Projekt, nicht durch diesen Task verursacht oder behebbar — nicht Teil des
T03-Scopes. Dokumentiert hier, damit Reviewer/Tester das nicht als eigenen
Fehler dieses Tasks werten.

## Akzeptanzkriterien-Abgleich (`tasks.md`, T03)

- [x] Modal bleibt bei gescheitertem Bild-Upload offen, dauerhafter
      Fehlerbanner sichtbar (Test 1/2).
- [x] Weder `emit('saved')` noch `emit('close')`/`closeModal()` bei
      gescheitertem Bild-Upload (Test 1, `wrapper.emitted()`).
- [x] Kein zweiter Hund-Datensatz bei erneutem "Speichern" nach
      gescheitertem Bild-Upload (Test 3).
- [x] Retry des Bild-Uploads ohne Modal-Neuöffnung möglich; bei Erfolg
      `saved` + Schließen (Test 4).
- [x] Abbrechen-Button schließt Modal nach gescheitertem Bild-Upload ohne
      zweiten Hund (Test 5).
- [x] Regression: erfolgreicher Bild-Upload → Modal schließt, Toast, `saved`
      (Test 7).
- [x] Regression: kein Bild ausgewählt → unverändertes Verhalten (Test 6).
- [x] `npm run test` und `npm run build` laufen fehlerfrei;
      `npm run lint` existiert im Projekt nicht (siehe oben).
