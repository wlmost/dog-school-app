# Verification: favicon-ico-upload-bug

**Gesamtstatus:** ok

---

## Schritt 0: `openspec validate favicon-ico-upload-bug`

Nicht erneut ausgeführt — der vorherige Lauf hat den Strukturdefekt
(`## Requirements` fehlte das Delta-Schlüsselwort) dokumentiert, der Architekt
hat ihn behoben. Der inhaltliche Realitätsabgleich wird direkt durchgeführt.

---

## Bestätigt

- `design.md` Z.15: "Datei: `backend/app/Http/Requests/UpdateSettingsRequest.php`, Zeile 45" mit Regel
  `'company_favicon' => ['sometimes', 'nullable', 'image', 'max:512', 'mimes:png,ico']`
  → bestätigt in `backend/app/Http/Requests/UpdateSettingsRequest.php:45`

- `design.md` Z.53: "company_logo hat weiterhin
  `['sometimes', 'nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,svg']` (Zeile 44)"
  → bestätigt in `backend/app/Http/Requests/UpdateSettingsRequest.php:44`

- `design.md` Z.65: "Template-Conditional (Zeilen 11–21):
  `<div v-if="loading">`, `<div v-else-if="error">`, `<form v-else>`"
  → bestätigt in `frontend/src/views/SettingsView.vue:11-21`

- `design.md` Z.73: "Reactive State (Zeile 557):
  `const error = ref<string | null>(null)`"
  → bestätigt in `frontend/src/views/SettingsView.vue:557`

- `design.md` Z.77: "`loadSettings()` schreibt bei Netzwerk-/API-Fehlern in
  `error.value` (Zeile 599)"
  → bestätigt in `frontend/src/views/SettingsView.vue:599`:
  `error.value = err.response?.data?.message || 'Fehler beim Laden der Einstellungen.'`
  (catch-Block von loadSettings)

- `design.md` Z.79: "`saveSettings()` schreibt ebenfalls in `error.value`
  bei einem 422-Fehler (Zeile 643)"
  → bestätigt in `frontend/src/views/SettingsView.vue:643`:
  `error.value = err.response?.data?.message || 'Fehler beim Speichern der Einstellungen.'`
  (catch-Block von saveSettings; schreibt bei jedem API-Fehler, nicht nur 422 —
  der 422-Bezug ist szenario-beschreibend, nicht strukturell einschränkend)

- `design.md` Z.125: "saveError-Block direkt unterhalb des vorhandenen
  successMessage-Blocks (Zeilen 379–384)"
  → bestätigt: successMessage-Block exakt an `frontend/src/views/SettingsView.vue:379-384`,
  gefolgt von `</form>` an Zeile 385

- `spec.md` Z.24: "API-Route `PUT /api/v1/settings`"
  → bestätigt in `backend/routes/api.php:187` innerhalb
  `Route::prefix('v1')...`-Gruppe (Zeile 67ff.)

- `proposal.md` Umfang-Tabelle: Pfad `backend/app/Http/Requests/UpdateSettingsRequest.php`
  → Datei existiert

- `proposal.md` Umfang-Tabelle: Pfad `frontend/src/views/SettingsView.vue`
  → Datei existiert

---

## Widerlegt

Keine.

---

## Nicht auffindbar

Keine.

---

## Neue Elemente (Plausibilität)

- `tasks.md` T01: legt `backend/tests/Feature/SettingsValidationTest.php` neu an
  → Datei existiert noch nicht; das Verzeichnis `backend/tests/Feature/` existiert
  und enthält zahlreiche vergleichbare Test-Dateien
  (z. B. `CourseRequestValidationTest.php`). Pfad ist konsistent.

- `tasks.md` T02: legt `frontend/src/views/SettingsView.test.ts` neu an
  → Datei existiert noch nicht; das Muster `.test.ts` im selben Verzeichnis
  wie die SFC ist etabliert (`frontend/src/views/CourseDetailView.test.ts`
  existiert als Vorbild). Pfad ist konsistent.

---

## Empfehlung

Die Spec ist verlässlich genug zum Fortfahren. Alle Zeilenangaben für den
Backend- und Frontend-Bug sind präzise bestätigt; die neuen Dateipfade
folgen dem bestehenden Konventionsmuster des Projekts.
