# Triage: favicon-ico-upload-bug

**Pfad:** klein
**Geschätzter Umfang:** 2 Dateien, PHP + Vue.js
**Risiko:** niedrig — keine öffentlichen API-Schnittstellen, keine Datenmodell-Änderungen, kein Auth-Einfluss
**Klarheit:** klar — beide Bugs (Validierungsregel + Template-Logikfehler) sind durch Code-Lektüre eindeutig lokalisiert

## Anforderung (Zusammenfassung)

Der Admin kann in den Systemeinstellungen kein Favicon vom Typ `.ico` hochladen, obwohl die UI diese Endung explizit bewirbt. Laravel lehnt `.ico`-Dateien mit "The Favicon field must be an image." ab. Als Nebeneffekt verschwindet nach jedem Speicherfehler das gesamte Einstellungsformular (außer dem Preise-Bereich), sodass keine weiteren Einstellungen vorgenommen werden können, bis die Seite neu geladen wird.

## Wurzelanalyse

### Bug 1 — Laravels `image`-Validierungsregel kennt kein ICO

**Datei:** `backend/app/Http/Requests/UpdateSettingsRequest.php`, Zeile 45

```php
'company_favicon' => ['sometimes', 'nullable', 'image', 'max:512', 'mimes:png,ico'],
```

Laravels `validateImage()` (in `Illuminate/Validation/Concerns/ValidatesAttributes.php:1438`) erlaubt
ausschließlich: `jpg`, `jpeg`, `png`, `gif`, `bmp`, `svg`, `webp`.
ICO (`image/x-icon` / `image/vnd.microsoft.icon`) ist dort nicht gelistet.
Die Regeln `image` und `mimes:ico` widersprechen sich: `mimes:ico` würde ICO erlauben,
aber `image` lehnt es zuvor ab (die Regeln werden der Reihe nach geprüft).

**Fix:** `image` durch `file` ersetzen:
```php
'company_favicon' => ['sometimes', 'nullable', 'file', 'max:512', 'mimes:png,ico'],
```

### Bug 2 — Template-Logik macht Formular nach jedem Fehler unsichtbar

**Datei:** `frontend/src/views/SettingsView.vue`, Zeilen 12–21

Die Template-Struktur ist:
```html
<div v-if="loading">...</div>
<div v-else-if="error">Fehlermeldung</div>   ← ersetzt das Formular
<form v-else>...das gesamte Formular...</form>
```

`saveSettings()` schreibt bei einem 422-Fehler in `error.value` (dieselbe Variable,
die sonst nur Lade-Fehler signalisiert). Sobald `error` gesetzt ist, wechselt
`v-else-if="error"` auf `true` und das Formular (`v-else`) wird vollständig
aus dem DOM entfernt. Der Preise-Abschnitt liegt außerhalb dieser Bedingungsstruktur
(ab Zeile 387) und bleibt daher zugänglich.

**Fix:** Zwei separate Fehlerzustände einführen: `loadError` (steuert Sichtbarkeit des Formulars)
und `saveError` (wird innerhalb des Formulars als Toast/Inline-Fehler dargestellt,
versteckt das Formular nicht).

## Betroffene Dateien

| Datei | Art der Änderung |
|-------|-----------------|
| `backend/app/Http/Requests/UpdateSettingsRequest.php` | `image` → `file` in Zeile 45 |
| `frontend/src/views/SettingsView.vue` | Fehlerstate aufteilen, Template-Bedingung anpassen |

## Empfohlene nächste Aktion

Pfad **klein** — Architect-Schritt kann entfallen, da beide Fixes trivial
lokalisiert und im Umfang klar begrenzt sind. Empfehlung:

1. `@architect` für einen minimalen Change mit zwei Tasks:
   - T01 (`dev-php`): Validierungsregel in `UpdateSettingsRequest` korrigieren + Pest-Test
   - T02 (`dev-javascript`): Fehlerstate in `SettingsView.vue` aufteilen + Vitest-Test
2. Alternativ direkt `@dev-php` + `@dev-javascript` ohne Spec, wenn der User
   den Workflow abkürzen möchte (Trivial-Pfad aus WORKFLOW.md).
