# Review: T01

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

(keine)

## Sollte (vor Merge erledigen, kann diskutiert werden)

- **[Testkonventionen]** `backend/tests/Feature/SettingsValidationTest.php:11`: `uses()->group('api', 'setting')` setzt die korrekte Gruppen-Kombination, aber die Datei liegt in `tests/Feature/` statt `tests/Feature/Api/`. TESTING.md Abschnitt 7.1 ordnet die `api`-Group explizit dem Pfad `tests/Feature/Api/` zu. Da es sich um eine neue Datei handelt (nicht einen bestehend angefassten Test), greift das Boy-Scout-Argument nicht. Die Abweichung in `CourseRequestValidationTest.php` und `AnamnesisResponseApiTest.php` (beide ebenfalls in `tests/Feature/` mit `group('api',...)`) ist ein bekanntes Bestandsproblem, kein Vorbild für neue Tests. TESTING.md ist hier eindeutig: "Diese Datei gewinnt für neue Tests." Vorschlag: Datei nach `backend/tests/Feature/Api/SettingsValidationTest.php` verschieben.

## Könnte (optional, Verbesserung)

- **[Testabdeckung]** `backend/tests/Feature/SettingsValidationTest.php:20`: Der ICO-Akzeptanz-Test verwendet ausschließlich `image/x-icon`. Reale Browser können ICO-Dateien auch als `image/vnd.microsoft.icon` senden (beide MIME-Typen sind RFC-konform). Laravels `mimes:ico` akzeptiert beide, weil Symfonys `MimeTypes` beide für `ico` listet — aber ein expliziter Testfall für `image/vnd.microsoft.icon` würde dieses Verhalten dokumentieren und absichern, falls sich die Symfony-Abbildung ändert.

## Lob

- `image` → `file` ist der kleinstmögliche, semantisch korrekte Fix. `company_logo` (Zeile 45) bleibt unangetastet — kein versehentlicher Seiteneffekt. Die Abgrenzung ist sauber begründet (Logo-Formate liegen alle innerhalb der `image`-Regel, ICO nicht).
- PHP 8.2-Kompatibilität vollständig eingehalten: keine 8.3/8.4-Features. Der FQCN-Import-Fix durch Pint (Zeile 7: `use Illuminate\Contracts\Validation\ValidationRule`) ist ein sinnvoller Stil-Nebeneffekt ohne logische Änderung.
- Testdatei folgt der TESTING.md-Schablone: `declare(strict_types=1)`, `RefreshDatabase`, `beforeEach` mit Factory State (`admin()`), alle `it()`-Bezeichnungen deutsch/lowercase/mit konjugiertem Verb, HTTP-Assertions ausschließlich Laravel-Style (`assertOk()`, `assertUnprocessable()`, `assertJsonValidationErrors()`), keine Debug-Ausgaben.
- `Storage::fake('public')` korrekt eingesetzt — kein Dateisystem-Seiteneffekt in der CI.
- Alle sechs Akzeptanzkriterien aus `tasks.md` sind durch Tests abgedeckt: ICO akzeptiert, PNG akzeptiert, EXE abgelehnt, Übergröße abgelehnt, fehlendes Feld kein Fehler (`sometimes`), Logo-Seiteneffektsicherung.
