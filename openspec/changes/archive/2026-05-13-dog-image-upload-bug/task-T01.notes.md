# Task T01 Notes: Nginx `client_max_body_size` setzen

**Datum:** 2026-05-13
**Agent:** dev-php

---

## Geänderte Datei

- `docker/nginx/conf.d/default.conf` — Patch eingefügt nach `charset utf-8;` (ursprünglich Zeile 10), vor dem `# Security headers`-Block (ursprünglich Zeile 12).
- Die Direktive `client_max_body_size 10M;` und der erklärende Kommentar befinden sich nun auf den Zeilen 12–17 der geänderten Datei.

## Akzeptanzkriterien — automatisch prüfbar

- [x] `docker/nginx/conf.d/default.conf` enthält `client_max_body_size 10M;` im `server {}`-Block, nach `charset utf-8;`, vor dem Security-Headers-Block.
- [x] Kein anderer Inhalt der Datei verändert (nur der exakte Patch eingefügt, kein weiterer Inhalt berührt).

## Akzeptanzkriterien — manueller Smoke-Test erforderlich

- [x] Upload eines ~4,5-MB-Bildes via `DogFormModal.vue` liefert HTTP 200 — kein HTTP 413, kein CORS-Fehler mehr. **Vom User bestätigt: Avatar-Kachel zeigt korrektes Bild.**
- [ ] Upload eines Bildes > 10 MB liefert HTTP 413 (Nginx greift korrekt). Nicht getestet.

## Zusatzbefund: HTTP 500 nach T01-Fix

Nach dem Nginx-Fix trat HTTP 500 auf (nicht mehr HTTP 413). Ursache laut Laravel-Log:
```
SQLSTATE[42703]: Undefined column: column "profile_image" of relation "dogs" does not exist
```
Die Migration `2026_05_04_100000_add_profile_image_to_dogs_table` war nicht auf der laufenden
Postgres-DB angewendet. Behoben durch `docker exec dog-school-php php artisan migrate --force`.
Kein Code-Defekt — T02 entfällt.
