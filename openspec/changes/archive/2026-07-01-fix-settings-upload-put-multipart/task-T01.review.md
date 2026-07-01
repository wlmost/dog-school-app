# Review: T01

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

Keine.

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Konsistenz]** `frontend/src/api/settings.test.ts:19,26,34,42,49,60`: Die
  `it(...)`-Beschreibungen sind auf Englisch formuliert
  (`'sends the request via apiClient.post, not apiClient.put'`,
  `'appends a _method=PUT field to the FormData for method override'`, …).
  Bestehende Vitest-Tests im selben Projekt verwenden durchgängig deutsche,
  dritte-Person-Indikativ-Beschreibungen, z. B.
  `frontend/src/views/CourseDetailView.test.ts:128`:
  `it('zeigt den Lade-Spinner während loadCourse läuft', …)` und
  `frontend/src/composables/usePricingItems.test.ts:34`:
  `it('befüllt groups bei Erfolg, setzt loading auf false und error auf null', …)`.
  `TESTING.md` Abschnitt 2.1 schreibt dieses Format zwar mit Pest-Beispielen
  vor, das Prinzip (Deutsch, 3. Person, Verb-first) ist aber im
  Frontend-Testbestand ebenso durchgehend etabliert. Vorschlag: Beschreibungen
  auf Deutsch umformulieren, z. B. `'sendet die Anfrage über apiClient.post,
  nicht apiClient.put'`.

- **[Korrektheit/Robustheit]** `frontend/src/api/settings.ts:37-47`: Das
  Method-Override-Feld wird per `formData.append('_method', 'PUT')` **vor**
  der `forEach`-Schleife gesetzt, die anschließend beliebige Schlüssel aus dem
  übergebenen `settings: Record<string, any>` anhängt. Sollte `settings`
  jemals einen Schlüssel `_method` enthalten (aktuell nicht der Fall, siehe
  `frontend/src/views/SettingsView.vue:625-641` — `settings` wird aus dem
  Vue-`formData`-Ref mit fest kodierten Property-Namen aufgebaut, `_method`
  taucht dort nie auf), würde ein zweiter `_method`-Eintrag im FormData
  landen. Da PHP beim Parsen von `multipart/form-data`-Bodies bei doppelten
  Feldnamen den zuletzt vorkommenden Wert in `$_POST` gewinnen lässt ("last
  wins"), würde ein caller-kontrollierter `_method`-Wert den beabsichtigten
  `'PUT'`-Override stillschweigend überschreiben. Die Funktionssignatur
  (`Record<string, any>`) gibt aber keine Garantie, dass `_method` nie als
  Schlüssel vorkommt — genau diese Art von "kein Fehler, Daten verschwinden
  nur leise" ist der Bug-Klasse, die dieser Change gerade behebt. Vorschlag:
  `_method` per `formData.set('_method', 'PUT')` **nach** der Schleife setzen
  (statt `.append()` davor), damit der Override-Wert unabhängig vom Inhalt
  von `settings` immer gewinnt. Kein Blocker, da beim einzigen aktuellen
  Aufrufer nicht ausnutzbar, aber günstige Absicherung für künftige Aufrufer
  dieser exportierten API-Funktion.

## Könnte (optional, Verbesserung)

- **[Stil]** `frontend/src/api/settings.ts:49-55`: Der ausführliche
  Inline-Kommentar dupliziert einen Teil der Root-Cause-Erklärung aus
  `design.md`. Das ist hier bewusst und sinnvoll (siehe Lob unten), langfristig
  bei Änderungen an der Backend-Logik aber ein zweiter Ort, der synchron
  gehalten werden muss. Keine Handlung nötig, nur zur Kenntnis.
- **[Info, kein Handlungsbedarf in diesem Change]** `frontend/src/api/settings.ts:56-59`:
  Der `Content-Type: multipart/form-data`-Header wird manuell ohne
  `boundary`-Parameter gesetzt. Das ist unverändert aus dem Vorzustand
  übernommen (identisches Muster bereits in
  `frontend/src/api/trainingAttachments.ts:54-58`, dort laut `proposal.md`
  bereits produktiv funktionierend) und nicht Teil dieses Diffs — daher kein
  Befund für T01, nur als Kontext für den Fall künftiger Multipart-Probleme.

## Lob (kurz, was gut gelöst wurde)

- Der Fix ist minimal-invasiv und beschränkt sich exakt auf die zwei in
  `tasks.md`/`design.md` beschriebenen Änderungen (Methode + `_method`-Feld),
  ohne unnötige Refaktorierung oder globale Interceptor-Logik in `client.ts`
  — KISS/YAGNI sauber eingehalten.
- Der Inline-Kommentar (`frontend/src/api/settings.ts:49-55`) macht den
  Zusammenhang (Root Cause + Method-Override-Mechanismus) direkt im Code
  nachvollziehbar, ohne dass man `design.md` konsultieren muss.
- Testabdeckung (`frontend/src/api/settings.test.ts`) deckt alle
  Akzeptanzkriterien aus `tasks.md` 1:1 ab: Methode, Endpunkt+Header,
  `_method`-Feld, Text-Feld, File-Feld, Skip von `null`/`undefined`.
- Backend (`backend/routes/api.php:195`, `SettingsController`,
  `UpdateSettingsRequest`) wurde korrekt unangetastet gelassen — bestätigt:
  `Route::put('/settings', [SettingsController::class, 'update'])` bleibt
  innerhalb der `can:admin`-Middleware-Gruppe unverändert und ist mit dem
  Method-Override-Ansatz kompatibel, da Laravel
  `enableHttpMethodParameterOverride()` vor dem Routing anwendet (bestätigt
  bereits in `verification.md`).
- Keine PHP-Datei im Diff → Abschnitt 4.1/4.2 CLAUDE.md (PHP-8.3/8.4-Verbote,
  SQL-Portabilität) nicht betroffen, nichts zu prüfen.
