# Design: favicon-ico-upload-bug

## Architekturübersicht

Beide Fixes sind unabhängig voneinander und berühren keine gemeinsamen Module.
T01 (Backend) kann parallel zu T02 (Frontend) bearbeitet werden. Es gibt keine
Abhängigkeit zwischen den Tasks.

---

## Bug 1 — Backend: `image`-Regel erlaubt kein ICO

### Analyse

**Datei:** `backend/app/Http/Requests/UpdateSettingsRequest.php`, Zeile 45

Aktuelle Regel:
```php
'company_favicon' => ['sometimes', 'nullable', 'image', 'max:512', 'mimes:png,ico'],
```

Laravels `image`-Validator (`Illuminate\Validation\Concerns\ValidatesAttributes::validateImage`)
prüft den MIME-Typ via `finfo` und erlaubt ausschließlich:
`image/jpeg`, `image/png`, `image/gif`, `image/bmp`, `image/svg+xml`, `image/webp`.

`image/x-icon` und `image/vnd.microsoft.icon` (beide MIME-Typen für ICO) sind
dort nicht aufgeführt. Die danebenstehende Regel `mimes:png,ico` wird nie
erreicht, weil `image` zuerst fehlschlägt.

### Fix

`image` durch `file` ersetzen. `file` prüft nur, dass es sich um eine gültige
hochgeladene Datei handelt — die inhaltliche Einschränkung auf PNG und ICO
übernimmt anschließend `mimes:png,ico`.

```php
// vorher
'company_favicon' => ['sometimes', 'nullable', 'image', 'max:512', 'mimes:png,ico'],

// nachher
'company_favicon' => ['sometimes', 'nullable', 'file',  'max:512', 'mimes:png,ico'],
```

### Kompatibilitätsprüfung

- **PHP-Kompatibilität:** Reiner Laravel-Validator-Regelname — keine PHP-Syntax-
  spezifischen Features. Kompatibel mit PHP 8.2+.
- **DB-Kompatibilität:** keine DB-Änderung.
- **Shared-Hosting:** keine Auswirkung.

### Abgrenzung: `company_logo` bleibt unverändert

`company_logo` hat weiterhin `['sometimes', 'nullable', 'image', 'max:2048', 'mimes:png,jpg,jpeg,svg']`
(Zeile 44). Dort ist `image` korrekt, da PNG/JPG/JPEG/SVG alle von Laravels
`image`-Regel erkannt werden.

---

## Bug 2 — Frontend: Formular verschwindet nach Speicherfehler

### Analyse

**Datei:** `frontend/src/views/SettingsView.vue`

Template-Conditional (Zeilen 11–21):
```html
<div v-if="loading">…</div>
<div v-else-if="error">…</div>   <!-- blockt das Formular -->
<form v-else>…</form>
```

Reactive State (Zeile 557):
```ts
const error = ref<string | null>(null)
```

`loadSettings()` schreibt bei Netzwerk-/API-Fehlern in `error.value`
(Zeile 599). `saveSettings()` schreibt ebenfalls in `error.value` bei einem
422-Fehler (Zeile 643). Da beide dieselbe Ref benutzen, schaltet jeder
Speicherfehler das `v-else-if="error"` auf `true` — das Formular fällt weg.

### Fix: zwei getrennte Refs

Bestehende `error`-Ref wird umbenannt und ihre Nutzung aufgeteilt:

| Alter Name | Neuer Name | Verwendung |
|------------|------------|------------|
| `error` (in `loadSettings()`) | `loadError` | Steuert Sichtbarkeit des Formulars |
| `error` (in `saveSettings()`) | `saveError` | Inline-Fehlermeldung im Formular |

**Template-Anpassungen:**

```html
<!-- Loading State — unverändert -->
<div v-if="loading">…</div>

<!-- Load-Error State — zeigt nur Ladefehler, kein Speicherfehler -->
<div v-else-if="loadError" class="bg-red-50 border border-red-200 …">
  <p class="text-red-800">{{ loadError }}</p>
</div>

<!-- Settings Form — sichtbar sobald kein Ladefehler -->
<form v-else @submit.prevent="saveSettings">
  …
  <!-- Speicherfehler inline im Formular, unterhalb der Buttons -->
  <div v-if="saveError" class="bg-red-50 border border-red-200 …">
    <p class="text-red-800">{{ saveError }}</p>
  </div>
  …
</form>
```

**Script-Anpassungen:**

1. `const error = ref<string | null>(null)` entfernen.
2. `const loadError = ref<string | null>(null)` und
   `const saveError = ref<string | null>(null)` hinzufügen.
3. In `loadSettings()`: alle `error.value`-Zuweisungen auf `loadError.value`.
4. In `saveSettings()`:
   - `error.value = null` am Anfang → `saveError.value = null`
   - Fehler-Catch: `error.value = …` → `saveError.value = …`
5. Template: alle `error`-Referenzen entsprechend aktualisieren.

### Platzierung des `saveError`-Blocks

Der `saveError`-Block wird direkt unterhalb des vorhandenen
`successMessage`-Blocks (Zeilen 379–384) eingefügt. Damit folgt er dem
etablierten Muster (grüne Box für Erfolg, rote Box für Fehler) und bleibt
konsistent mit dem Rest der Seite.

### Kompatibilitätsprüfung

- Rein lokale State-Änderung in einer SFC. Kein API-Vertrag betroffen.
- Keine neuen Abhängigkeiten.
- `npm run build` muss weiter fehlerfrei durchlaufen.

---

## Entwurfsmuster-Bezug

- **Single Responsibility (SOLID-S):** Jede Ref hat eine eindeutige
  Verantwortung — Ladefehlerstatus vs. Speicherfehlerstatus.
- **KISS:** Minimaler Eingriff; keine neue Abstraktion, nur eine saubere
  Benennung vorhandener State-Variablen.
- **DRY:** Kein doppelter Fehleranzeigeblock; das Template-Conditional für
  Ladefehler bleibt ein einziger Block.

---

## Sequenz beim Speicherfehler (nach Fix)

```
User klickt Speichern
  → saveSettings() setzt saveError.value = null
  → API-Call schlägt fehl (z. B. 422)
  → saveError.value = "Fehler beim Speichern…"
  → loadError bleibt null
  → v-else-if="loadError" ist false
  → Formular (v-else) bleibt im DOM
  → saveError-Block unterhalb der Buttons wird sichtbar
```
