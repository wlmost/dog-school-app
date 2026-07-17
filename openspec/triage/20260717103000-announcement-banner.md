# Triage: Ankündigungs-Bereich (Landingpage + Admin-Verwaltung)

**Pfad:** standard
**Geschätzter Umfang:** ca. 10-14 neue/geänderte Dateien, PHP (Backend) + TypeScript/Vue (Frontend)
**Risiko:** mittel — neues Datenmodell samt Migration, File-Upload auf Shared Hosting, Auth/Rollen-Check für Admin-Formular, aber keine bestehenden öffentlichen Schnittstellen werden gebrochen.
**Klarheit:** mehrdeutig — Kernidee ist klar, aber mehrere Detailfragen zu Verhalten, Darstellung und Mehrfach-Ankündigungen sind offen (siehe unten).

## Anforderung (Zusammenfassung)

Auf der öffentlichen Landingpage (`frontend/src/views/HomeView.vue`, Hero-Section
"Willkommen bei der Hundeschule HomoCanis" in Zeile 4-26, gefolgt von der
Feature-Section "Unsere Leistungen" ab Zeile 29) soll zwischen diesen beiden
Sections ein optionaler Ankündigungsbereich erscheinen, der nur sichtbar ist,
wenn eine aktive Ankündigung existiert. Als Admin soll es ein Formular geben,
um Ankündigungen zu pflegen: Text, Bild-Upload und eine Anzeigedauer in Tagen,
nach deren Ablauf die Ankündigung automatisch nicht mehr angezeigt wird.

## Rückfragen an den User (nur wenn Klarheit = mehrdeutig)

- Kann es **mehrere gleichzeitig aktive** Ankündigungen geben (Liste/Karussell)
  oder immer nur genau eine aktuell sichtbare?
- Wie wird die **Anzeigedauer** technisch verstanden: "X Tage ab Veröffentlichung"
  oder "X Tage ab dem Zeitpunkt, an dem der Admin sie einträgt"? Braucht es
  zusätzlich einen manuellen "jetzt veröffentlichen" / "sofort deaktivieren"-Schalter,
  oder reicht reines Ablaufdatum aus Anzeigedauer?
- Soll der Ablauf **serverseitig** (z. B. per Scheduler/Cron, siehe
  Shared-Hosting-Einschränkung in `CLAUDE.md` Abschnitt 4.3 — kein Daemon,
  nur `schedule:run` per Hoster-Cron) oder rein **anzeigeseitig** berechnet
  werden (Ankündigung bleibt in der DB, wird aber nur ausgeblendet, wenn
  `created_at + Anzeigedauer < now()`)? Das beeinflusst, ob überhaupt ein
  Scheduler-Task nötig ist.
- Ist **ein Bild** pro Ankündigung ausreichend, oder werden mehrere Bilder
  bzw. eine Galerie erwartet?
- Soll der Ankündigungstext **Rich-Text/HTML** unterstützen (z. B. Fett,
  Links) oder reicht Plain-Text mit Zeilenumbrüchen? Das beeinflusst
  Sanitizing-Aufwand (XSS) im Backend.
- Braucht es eine **Historie** früherer Ankündigungen (Liste im Admin-Bereich
  mit "aktiv/abgelaufen"-Status) oder wird die aktuelle Ankündigung beim
  Bearbeiten einfach überschrieben?

## Ungeprüfte Referenzen

- Keine — alle im Auftrag genannten Bezugspunkte ("Willkommen bei der
  Hundeschule ...", "Unsere Leistungen") wurden in
  `frontend/src/views/HomeView.vue:11` bzw. `:33` verifiziert.

## Codebasis-Befunde (mit Beleg)

- **Landingpage:** `frontend/src/views/HomeView.vue:1-40` — Hero-Section
  endet Zeile 26, Feature-Section "Unsere Leistungen" beginnt Zeile 29. Der
  neue Bereich müsste dazwischen eingefügt werden (conditional `v-if`).
- **Vorbild für admin-pflegbare Inhalte:** `backend/app/Models/Setting.php`
  (Key-Value-Store mit `type`/`group`, Cache-Invalidierung) und
  `backend/app/Http/Controllers/Api/SettingsController.php:1-75` (Validierung,
  `$this->authorize(...)`, Datei-Upload für `email_logo` via
  `mimes:png,jpg,jpeg,svg|max:2048`). Eine Ankündigung ist aber eher ein
  eigenständiges Model mit Lebenszyklus (aktiv/abgelaufen) als ein einzelner
  Setting-Key — spricht für ein eigenes `Announcement`-Model + Migration statt
  Wiederverwendung von `Setting`.
- **Existierendes Upload-Pattern (Bild):** `backend/database/migrations/2026_05_04_100000_add_profile_image_to_dogs_table.php`
  (String-Spalte `profile_image`, nullable) und
  `backend/app/Http/Controllers/Api/DogController.php:192-193`
  (`Storage::disk('public')->exists(...)` / `delete(...)`) — etabliertes
  Muster: Pfad in DB, Datei auf `public`-Disk via `storage:link`
  (siehe `CLAUDE.md` Abschnitt 4.3, Shared-Hosting-Symlink-Hinweis).
- **Frontend-Upload-Komponente:** `frontend/src/components/FileUpload.vue`
  (Dropzone, Multiple-Support, Preview) — wiederverwendbar für den
  Bild-Upload im Admin-Formular, ggf. mit `multiple=false`.
- **Rollen-/Admin-Check Frontend:** `frontend/src/router/index.ts:181-196`
  — Pattern `requiresRole` / `requiresAdmin`, admin hat immer Zugriff. Neue
  Admin-Route für das Ankündigungsformular kann dieses Pattern übernehmen.
- **Kein bestehendes Announcement/Banner-Model gefunden:**
  `backend/app/Models/` enthält kein `Announcement`, `Banner` o. Ä. (siehe
  vollständige Liste oben) — es handelt sich um eine komplett neue Capability.
- **Kein Admin-Views-Verzeichnis, sondern flache Views mit Rollen-Guard:**
  `frontend/src/views/SettingsView.vue` ist das nächstliegende Vorbild für
  eine "Admin bearbeitet globale Inhalte"-Seite.

## Betroffene Bereiche (Schätzung)

- **Backend (`dev-php`):**
  - Migration `create_announcements_table` (Felder mind.: `text`, `image_path`
    nullable, `display_days`/`expires_at`, `is_active` oder berechnet,
    Timestamps) — MySQL/Postgres-kompatibel gemäß Abschnitt 4.2 der `CLAUDE.md`
  - Model `Announcement` (fillable, casts, ggf. Scope `active()`)
  - Policy für Admin-only Schreibzugriff
  - Controller `Api/AnnouncementController` (index/store/update/destroy,
    Datei-Upload wie in `SettingsController`)
  - API-Resource für die öffentliche Ausgabe (kein Admin-only-Feld leaken)
  - Route-Eintrag (public GET aktive Ankündigung, admin-only POST/PUT/DELETE)
- **Frontend (`dev-typescript`):**
  - Öffentliche Anzeige-Komponente (z. B. `AnnouncementBanner.vue`) in
    `HomeView.vue` zwischen Zeile 26 und 29 eingebunden, bedingtes Rendering
  - Admin-Formular-Komponente/View (Text, Bild-Upload via `FileUpload.vue`,
    Zahleneingabe Anzeigedauer), neue Route mit `requiresRole: 'admin'`
  - API-Client-Funktionen in `frontend/src/api/`
  - Vitest-Tests für beide Komponenten

Damit sind mindestens zwei Sprach-Stacks, ein neues Datenmodell mit Migration
und File-Upload sowie ein neuer Auth-geschützter Bereich betroffen — das
übersteigt "klein" (1-3 Dateien). Da die Kernanforderung aber klar umrissen
ist und keine Architektur-Zerlegung in mehrere Teil-Changes nötig erscheint
(kein Eingriff in bestehende Module, kein Multi-Sprachen-Kernkonflikt), wird
**"standard"** statt "groß" empfohlen — vorausgesetzt, die Rückfragen oben
werden vor der Architektur-Phase geklärt, da sie die Migration-Struktur
(z. B. `expires_at` vs. reine `display_days`-Berechnung) direkt beeinflussen.

## Entscheidungen des Users (2026-07-17)

- **Ablauf-Berechnung:** anzeigeseitig (`created_at`/`published_at` +
  `display_days` < `now()`), **kein** Scheduler/Cron-Task nötig.
- **Mehrfach-Ankündigungen:** Es können **mehrere gleichzeitig aktive**
  Ankündigungen existieren (Liste, kein Überschreiben einer einzelnen
  Ankündigung). Frontend zeigt alle aktiven Ankündigungen an (Liste oder
  Karussell — Detail-Layout entscheidet der Architekt/Designer).
- **Bilder:** genau **ein Bild** pro Ankündigung (kein Galerie-Feature).
- **Text-Format:** **Rich-Text (HTML)**. Erfordert einen Rich-Text-Editor
  im Admin-Formular sowie serverseitiges HTML-Sanitizing beim Speichern
  (XSS-Schutz — kein rohes HTML ungefiltert in DB/Ausgabe durchreichen).

Damit entfällt die Rückfrage zu "Historie" nicht automatisch — das Modell
mit mehreren gleichzeitig aktiven Ankündigungen impliziert ohnehin eine
Liste im Admin-Bereich (aktive + abgelaufene Einträge sichtbar), der
Architekt sollte das im `design.md` festlegen (z. B. abgelaufene Einträge
weiterhin auflisten mit Status-Badge, statt sie zu löschen).

## Empfohlene nächste Aktion

Rückfragen oben zuerst mit dem User klären (insb. Ablauf-Berechnung
serverseitig vs. anzeigeseitig, da das die Migration und ob ein
Scheduler-Task nötig ist, direkt bestimmt). Danach:

`@architect Erstelle den openspec-Change basierend auf
openspec/triage/20260717103000-announcement-banner.md` — Modus A, Pfad
"standard", vollständiger Workflow inkl. Skeptiker und User-Spec-Gate.
Der Architekt sollte in `tasks.md` mindestens zwei Tasks anlegen: eine für
`dev-php` (Migration/Model/Controller/Route/Policy) und eine für
`dev-typescript` (öffentliche Anzeige-Komponente + Admin-Formular), mit
klarem Übergabepunkt über den API-Response-Shape der Announcement-Resource.
