# T03: `InvoiceController::sendEmail()` — Notes

## Status

Vollständig implementiert. Der ursprünglich beauftragte `dev-php`-Agent
wurde durch einen Host-Sleep während des Schreibens dieser Notes-Datei
unterbrochen (`API Error: Your computer went to sleep mid-response`);
die eigentliche Implementierung und alle Tests waren zu diesem Zeitpunkt
bereits abgeschlossen und die Akzeptanzkriterien in `tasks.md` bereits
abgehakt. Diese Datei wurde nachträglich vom Orchestrator anhand des
tatsächlichen Diffs und eines eigenen QA-Laufs verfasst, nicht vom
ursprünglichen Agenten.

## Umgesetzt

- `backend/routes/api.php`: neue Route
  `POST /invoices/{invoice}/send-email` unterhalb der `pdf`-Route.
- `backend/app/Policies/InvoicePolicy.php`: neue Methode `send()`,
  rollen-only (Admin/Trainer), analog zu `finalize()`.
- `backend/app/Http/Controllers/Api/InvoiceController.php`: neue Methode
  `sendEmail()` — Autorisierung, Status-Whitelist
  (`sent`/`reminded`/`overdue`, sonst 422), E-Mail-Präsenz-Check (sonst
  422), `InvoiceWasSent::dispatch()` mit Try/Catch (Exception → Log +
  HTTP 502 mit Fallback-Hinweis auf manuellen Download). Kein
  Statuswechsel.
- Neue Testdatei `backend/tests/Feature/InvoiceSendEmailTest.php`
  (Gruppe `feature`, `invoice`) deckt alle Akzeptanzkriterien ab: 200 für
  `sent`/`reminded`/`overdue` mit E-Mail, 422 für `draft`/`paid`/
  `cancelled`, 422 für fehlende Kunden-E-Mail, 403 für Kunden-Zugriff,
  unveränderter Status nach erfolgreichem Versand, 502 bei simuliertem
  Mail-Fehler mit Fallback-Hinweis.

## Verifikation (nachträglich durch Orchestrator)

- `docker compose exec php vendor/bin/pest --no-coverage`: **826 Tests
  grün** (2586 Assertions) — vorher (nach T02) 816, Zuwachs passt zu den
  neuen `InvoiceSendEmailTest`-Fällen.
- `composer lint` (Pint, 310 Dateien): grün.
- `composer stan` (PHPStan, 206 Dateien): grün, keine Fehler.
- `composer compat-check` (PHPCompatibility gegen PHP 8.2): grün.
- Hinweis: `composer qa` selbst lief in ein internes Composer-
  Process-Timeout (300s) beim Pest-Lauf — kein Testfehler, sondern reine
  Laufzeit-Konfiguration des `qa`-Composer-Scripts. Die einzelnen Phasen
  wurden deshalb separat verifiziert (siehe oben), alle grün.

## Keine Abweichungen von der Task-Beschreibung

Die Implementierung folgt dem in `tasks.md` vorgegebenen Code-Beispiel
1:1 (Route, Policy, Controller-Methode inkl. Statuskonstante
`SENDABLE_STATUSES`).
