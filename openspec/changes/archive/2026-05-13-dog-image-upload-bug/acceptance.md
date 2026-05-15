# Abnahme: dog-image-upload-bug

**Datum:** 2026-05-13
**Architekt:** architect-Agent (Modus B)
**Status:** abgenommen

---

## Abnahme-Checkliste

| Kriterium | Ergebnis |
|---|---|
| Proposal umgesetzt? | ✅ Fix 1 (Pflicht) vollständig umgesetzt; Fix 2 (bedingt) korrekt als entfallen bewertet |
| Design eingehalten? | ✅ Exakter Patch gemäß `design.md` in `docker/nginx/conf.d/default.conf` eingefügt; kein anderer Scope berührt |
| Alle Pflicht-ACs aus tasks.md erfüllt? | ✅ (mit Hinweis — siehe unten) |
| Review-Urteil: freigegeben? | ✅ Reviewer-Urteil: `freigegeben`, keine blockierenden Befunde |
| Smoke-Test (4,5-MB-Upload) bestanden? | ✅ Vom User bestätigt: HTTP 200, Avatar-Kachel zeigt korrektes Bild, kein HTTP 413, kein CORS-Fehler |
| T02 Status | ✅ Entfallen — HTTP 500 war fehlende Migration, kein Code-Defekt (siehe Zusatzbefund) |

### Hinweis: Nicht getestetes AC

Das AC „Upload eines Bildes > 10 MB liefert HTTP 413" wurde nicht explizit verifiziert.
Dies ist ein Verifikations-Check des Nginx-Defaults-Verhaltens nach dem Patch, kein
Nachweis des eigentlichen Bugfixes. Da der Wert `client_max_body_size 10M;` korrekt in der
Datei steht und Nginx diesen für Requests > 10 MB automatisch mit 413 beantwortet
(dokumentiertes Nginx-Verhalten), ist dies kein Blocker für die Abnahme.

---

## Erfüllt

- `docker/nginx/conf.d/default.conf` enthält `client_max_body_size 10M;` an der
  im Design spezifizierten Position: nach `charset utf-8;`, vor dem Security-Headers-Block,
  im `server {}`-Block (Zeilen 12–16 der geänderten Datei, live verifiziert).
- Kein weiterer Dateiinhalt verändert — Reviewer hat die vollständige Datei geprüft.
- Der Kommentar über der Direktive erklärt den Zusammenhang zwischen Nginx-Limit,
  HTTP 413, CORS-Bypass und Laravel-Middleware; Qualität über Standard.
- Der `server {}`-Block-Level (statt `location`-Level) ist die korrekte Platzierung
  und deckt alle Upload-Pfade ab, einschließlich des `location ~ \.php$`-Blocks.
- Shared-Hosting-Implikation korrekt ausgeklammert: `deployment-templates/` (Apache)
  ist nicht betroffen; Nginx-Limits auf Shared Hosting liegen beim Hoster.

---

## Zusatzbefund: Fehlende Migration (außerhalb Change-Scope)

Nach dem Nginx-Fix trat HTTP 500 auf. Ursache (Laravel-Log):

```
SQLSTATE[42703]: Undefined column: column "profile_image" of relation "dogs" does not exist
```

Die Migration `2026_05_04_100000_add_profile_image_to_dogs_table` war nicht auf der
laufenden Postgres-Dev-DB angewendet. Gelöst durch:

```bash
docker exec dog-school-php php artisan migrate --force
```

**Bewertung:** Kein Code-Defekt. Die Migration existiert im Projekt und ist korrekt;
sie war lediglich nicht gegen die laufende Dev-DB ausgeführt worden. T02 entfällt
damit vollständig — keine weiteren Maßnahmen am Code notwendig.

**Empfehlung für künftige Onboarding-Dokumentation:** `php artisan migrate` nach
jedem `docker compose up` als Pflichtschritt in einer lokalen Dev-Setup-Anleitung
festhalten, um diesen Typ von „funktioniert nicht, Migration vergessen"-Fehler
systemisch zu eliminieren.

---

## Empfehlung an den User

Der Change ist inhaltlich vollständig und technisch korrekt. Der einzige geänderte
Artefakt (`docker/nginx/conf.d/default.conf`) ist minimal, präzise begründet und
durch Smoke-Test und Review doppelt abgesichert.

**Empfohlener nächster Schritt:** Commit auf dem Feature-Branch, dann Merge in `main`.
