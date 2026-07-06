# Review: T03 — `DogFormModal.vue` — Bild-Upload-Fehler nicht mehr verschlucken

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

Keine.

## Sollte (vor Merge erledigen, kann diskutiert werden)

Keine.

## Könnte (optional, Verbesserung)

- **[Lesbarkeit/UX]** `frontend/src/components/DogFormModal.vue:465-472`: Wenn ein Retry des Bild-Uploads (nach vorherigem Fehlschlag) erfolgreich ist, wird `emit('saved'); closeModal()` ausgelöst, aber es gibt **keine** `showSuccess(...)`-Rückmeldung für diesen konkreten Erfolgsfall — der einzige sichtbare Hinweis für den Nutzer ist das Schließen des Modals. Beim ursprünglichen (Erst-)Erfolgspfad kommt der Toast dagegen bereits aus `saveDogRecord()` (Zeile 407/411). Kein funktionaler Fehler (alle Akzeptanzkriterien sind erfüllt, Test 4 in `DogFormModal.test.ts:157-193` deckt genau dieses Verhalten korrekt ab), aber ein optionaler UX-Polish-Punkt: ein zusätzlicher Toast (z. B. "Profilbild hochgeladen") in `uploadDogImage()` bei `return true` (Zeile 439) würde die Rückmeldung beim Retry-Erfolgsfall konsistenter zum Rest der Komponente machen.

## Lob (kurz, was gut gelöst wurde)

- Saubere Aufteilung von `handleSubmit()` in `saveDogRecord()` (`DogFormModal.vue:387-419`) und `uploadDogImage()` (`DogFormModal.vue:421-446`) — Single-Responsibility, direkt testbar, guter Kommentar-Header pro Funktion.
- Die Kernkorrektur des Skeptiker-Befunds wurde korrekt umgesetzt: Bei fehlgeschlagenem Bild-Upload wird **weder** `emit('saved')` **noch** `closeModal()`/`emit('close')` ausgelöst (`DogFormModal.vue:464-472`, `return` direkt nach `uploadDogImage()`) — dadurch bleibt das Modal auch über die kontrollierte `is-open`-Bindung in `DogsView.vue` (nicht Teil dieses Diffs, korrekt unverändert gelassen) tatsächlich offen, nicht nur isoliert in `DogFormModal.vue`.
- `savedDogId` (`DogFormModal.vue:236-246`) verhindert nachweisbar die Doppel-Anlage beim Retry: wird bei jedem Öffnen des Modals (`watch(() => props.isOpen, ...)`, Zeile 298) und in `resetForm()` (Zeile 351) korrekt zurückgesetzt, und nur gesetzt, wenn `saveDogRecord()` erfolgreich zurückkehrt (Zeile 459-460, innerhalb des `try`-Blocks — bei einer Exception wird die Zuweisung nie erreicht).
- `error.value = null` zu Beginn von `handleSubmit()` (Zeile 450) sorgt dafür, dass ein erfolgreicher Retry den alten Fehlerbanner zuverlässig löscht, bevor `closeModal()` den `!error.value`-Guard für `resetForm()` prüft (Zeile 503).
- Test-Suite (`DogFormModal.test.ts`) bildet alle acht Akzeptanzkriterien aus `tasks.md` 1:1 ab, inklusive der beiden am schwersten zu verifizierenden Szenarien (kein zweiter POST bei Retry, Abbrechen-Button ohne Doppel-Anlage) — Tests prüfen sowohl `wrapper.emitted()` als auch die tatsächliche Anzahl der `apiClient.post`-Aufrufe pro Endpunkt, nicht nur oberflächliches Verhalten.
- Der TypeScript-Konflikt (Rollenvorgabe "reines JS" vs. bestehende `lang="ts"`-Konvention der Datei) wurde in `task-T03.notes.md` transparent dokumentiert und nachvollziehbar begründet, statt stillschweigend entschieden oder die Task abgebrochen zu werden.
