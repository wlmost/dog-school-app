# Triage: Kontaktformular-Benachrichtigungsmail zeigt Platzhalter-Adresse statt Einstellungen

**Status:** Erledigt — behoben in PR [#78](https://github.com/wlmost/dog-school-app/pull/78) (Commit `ccc30aa`), gemerged nach `main` am 2026-08-04.

**Pfad:** trivial
**Geschätzter Umfang:** 1 Datei, PHP (Blade-Template)
**Risiko:** niedrig — reine Anzeige-Korrektur in einem Blade-Template, keine
öffentliche Schnittstelle, kein Datenmodell, keine Migration betroffen.
**Klarheit:** klar — Ursache im Code eindeutig lokalisiert und belegt.

## Anforderung (Zusammenfassung)
Der User meldet, dass in der Benachrichtigungsmail, die beim Absenden des
öffentlichen Kontaktformulars verschickt wird, im Footer weiterhin die
Platzhalter-Adresse "Musterstraße 123" steht statt der in den
Einstellungen (Firmendaten) hinterlegten Adresse.

## Ursache (mit Datei:Zeile belegt)
- `backend/resources/views/layouts/email.blade.php:158` liest den Wert
  über den Settings-Key `company_address`:
  ```
  {{ $settings['company_address'] ?? 'Musterstraße 123' }}<br>
  ```
- Der tatsächliche Settings-Key für die Straße heißt jedoch **`company_street`**,
  nicht `company_address`. Belegt durch:
  - `backend/database/seeders/SettingsSeeder.php:20` — Seed-Eintrag
    `['key' => 'company_street', 'value' => 'Musterstraße 123', ...]`
  - `backend/app/Http/Requests/UpdateSettingsRequest.php:35` — Validierungsregel
    für `company_street`
  - `backend/app/Http/Controllers/Api/SettingsController.php:54` — ebenfalls
    `company_street` als erwarteter Key
- Da der Key `company_address` in der `settings`-Tabelle nie existiert, greift
  in `email.blade.php:158` **immer** der hartkodierte Fallback
  `'Musterstraße 123'` — unabhängig davon, was der User in den Einstellungen
  hinterlegt hat. Das ist exakt das gemeldete Symptom.
- `company_zip` (Zeile 159) und `company_city` (Zeile 159) sind korrekt
  benannt und stimmen mit dem Seeder überein — nur die Straße ist betroffen.
- Der genutzte Mailable `backend/app/Mail/ContactFormMail.php:67-74`
  (`content()`-Methode) lädt korrekt **alle** Settings (`Setting::pluck('value', 'key')`)
  und übergibt sie unverändert als `$settings` an das Layout — der Fehler liegt
  ausschließlich im Template, nicht im Mailable.
- `grep` bestätigt: `company_address` kommt im gesamten `backend/`-Verzeichnis
  nur an dieser einen Stelle vor (`email.blade.php:158`) — kein weiterer
  Bezugspunkt, keine andere Stelle betroffen.
- Wichtiger Nebenbefund: `layouts/email.blade.php` ist das **gemeinsame
  Layout aller Systemmails** (nicht nur Kontaktformular) — der Fix behebt die
  Adressanzeige also für alle darüber versendeten Mails, nicht nur für die
  Kontaktformular-Benachrichtigung. Das vergrößert nicht den Implementierungs-
  Umfang (weiterhin 1 Zeile, 1 Datei), erhöht aber den Nutzen des Fixes.
- Kein bestehender Test deckt `ContactFormMail` oder `layouts/email.blade.php`
  ab (`find backend/tests -iname "*Contact*"` liefert keine Treffer).

## Rückfragen an den User
Keine — Klarheit = klar, Ursache eindeutig belegt.

## Empfohlene nächste Aktion
Kein Architekt/Skeptiker nötig (trivialer Pfad, Edge Case gemäß
`~/.claude/WORKFLOW.md`). Direkt:

1. Feature-Branch anlegen: `git switch -c feature/fix-contact-mail-address`
2. `@dev-php` beauftragen: In `backend/resources/views/layouts/email.blade.php:158`
   den Settings-Key `company_address` durch `company_street` ersetzen.
   Optional: kurzen Feature-Test für `ContactFormMail` ergänzen, der prüft,
   dass die gerenderte Mail den in den Settings hinterlegten `company_street`-Wert
   enthält (kein Platzhalter) — TESTING.md-Konventionen beachten.
3. Pre-Flight: `composer qa` (siehe CLAUDE.md Abschnitt 7.1).
4. Commit (`fix: Kontaktformular-Mail zeigt korrekte Firmenadresse`), PR erstellen.
