# Task T01 — Notizen: Favicon-Validierungsregel korrigieren (Backend)

## Was wurde geändert

### `backend/app/Http/Requests/UpdateSettingsRequest.php`

Zeile 45: `image` durch `file` ersetzt.

```
// vorher
'company_favicon' => ['sometimes', 'nullable', 'image', 'max:512', 'mimes:png,ico'],

// nachher
'company_favicon' => ['sometimes', 'nullable', 'file',  'max:512', 'mimes:png,ico'],
```

Laravels `image`-Regel lehnt `image/x-icon` (ICO) ab, weil sie intern nur
`jpeg`, `png`, `gif`, `bmp`, `svg+xml` und `webp` akzeptiert. Die Regel `file`
prüft nur, ob es sich um eine gültige hochgeladene Datei handelt — die
Einschränkung auf PNG und ICO übernimmt danach `mimes:png,ico`.

Pint hat beim Lint-Lauf zusätzlich den FQCN-Import `\Illuminate\Contracts\Validation\ValidationRule`
aus dem PHPDoc-Return-Type in einen echten `use`-Import konvertiert (Style-Fix
`fully_qualified_strict_types`). Keine logische Änderung.

`company_logo` (Zeile 44) bleibt unberührt — dort ist `image` korrekt, weil
PNG/JPG/JPEG/SVG alle von der `image`-Regel erkannt werden.

### `backend/tests/Feature/SettingsValidationTest.php` (neu)

Pest-Tests für die `company_favicon`-Validierungsregel:

| Test | Erwartung |
|------|-----------|
| ICO-Datei (`image/x-icon`, 100 KB) | 200 OK |
| PNG-Datei (Fake-Image) | 200 OK |
| EXE-Datei (`application/x-msdownload`) | 422 Unprocessable |
| Datei > 512 KB (513 KB, ICO MIME) | 422 Unprocessable |
| Kein `company_favicon` im Request | 200 OK (`sometimes`) |
| PNG als `company_logo` (Seiteneffekt-Schutz) | 200 OK |

Groups: `api`, `setting`. Storage wird mit `Storage::fake('public')` gemockt,
damit der Controller kein echtes Dateisystem beschreibt.

## Annahmen

- Der Pfad `tests/Feature/SettingsValidationTest.php` liegt nicht in
  `tests/Feature/Api/`, obwohl es sich um einen API-Test handelt. Das folgt
  dem Muster von `CourseRequestValidationTest.php` (gleiche Ebene, `group api`).
  Die Entscheidung ist konsistent mit dem Bestand.
- `max:512` in Laravel Validation ist in Kilobyte — daher wurde 513 KB als
  Grenzwert für den Überschreitungstest gewählt.

## QA-Ergebnis

- `vendor/bin/pest tests/Feature/SettingsValidationTest.php` — 6/6 grün
- `vendor/bin/pest` (gesamte Suite) — 658/658 grün, keine Regression
- `vendor/bin/pint --test` — beide Dateien sauber (nach Auto-Fix)
