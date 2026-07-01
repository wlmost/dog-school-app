# Tasks: fix-settings-upload-put-multipart

## T01: Transportmethode für Settings-Update auf POST + Method-Override umstellen

- **Agent:** dev-javascript
- **Dateien:**
  - `frontend/src/api/settings.ts` (ändern, Zeilen 35-54)
  - `frontend/src/api/settings.test.ts` (neu erstellen)
- **Abhängigkeiten:** keine
- **Beschreibung:**
  In `settingsApi.updateSettings()` wird:
  1. Vor dem Befüllen der Datei-/Text-Felder ein zusätzliches
     `FormData`-Feld `_method` mit Wert `'PUT'` angehängt
     (`formData.append('_method', 'PUT')`).
  2. Der Request von `apiClient.put<SettingsResponse>(...)` auf
     `apiClient.post<SettingsResponse>(...)` umgestellt. Der Endpunkt-Pfad
     (`/api/v1/settings`) und der `Content-Type`-Header
     (`multipart/form-data`) bleiben unverändert.

  Details, Vorher/Nachher-Codeblock und Begründung siehe `design.md`,
  Abschnitt "Lösungsansatz: POST + Method-Override statt echtem PUT".

  Anschließend wird eine neue Vitest-Datei
  `frontend/src/api/settings.test.ts` erstellt, die `@/api/client` mockt
  (Muster: `frontend/src/views/CourseDetailView.test.ts:16-30`) und prüft,
  dass `settingsApi.updateSettings()` tatsächlich `apiClient.post`
  aufruft (nicht `apiClient.put`) und das `_method=PUT`-Feld im `FormData`
  enthalten ist.

- **Akzeptanzkriterien:**
  - [x] `formData.append('_method', 'PUT')` wird vor dem Absenden gesetzt.
  - [x] Der Request wird über `apiClient.post(...)` gesendet, nicht mehr
        über `apiClient.put(...)`.
  - [x] Der Endpunkt bleibt `/api/v1/settings`.
  - [x] Der Header `Content-Type: multipart/form-data` bleibt erhalten.
  - [x] Datei-Felder (`instanceof File`) und Text-Felder werden weiterhin
        korrekt ins `FormData` übernommen (kein Verhaltensunterschied zur
        bisherigen Logik außer Methode + `_method`-Feld).
  - [x] Vitest-Test: `settingsApi.updateSettings({ company_name: 'Test' })`
        ruft `apiClient.post` (nicht `apiClient.put`) mit dem Pfad
        `/api/v1/settings` auf.
  - [x] Vitest-Test: Das an `apiClient.post` übergebene `FormData`-Objekt
        enthält ein Feld `_method` mit Wert `'PUT'`
        (Prüfung z. B. via `formData.get('_method')` auf dem im Mock
        empfangenen Argument).
  - [x] Vitest-Test: Ein `File`-Wert im Argument von `updateSettings()`
        landet unverändert als Datei-Eintrag im `FormData`.
  - [~] `npm run lint` läuft ohne Fehler. (Kein `lint`-Script in
        `frontend/package.json` vorhanden — siehe `task-T01.notes.md`.)
  - [x] `npm run test` läuft ohne Fehler.
  - [x] `npm run build` läuft ohne Warnings oder Fehler.

---

## Hinweis für Reviewer (kein separater Task, siehe `design.md`)

Der Reviewer soll bestätigen, dass `backend/routes/api.php:195`
(`Route::put('/settings', [SettingsController::class, 'update'])`)
unverändert bleibt und mit dem Method-Override-Ansatz kompatibel ist
(Laravel wendet `enableHttpMethodParameterOverride()` vor dem Routing an,
siehe `design.md`). Keine Backend-Datei wird in diesem Change angefasst.
