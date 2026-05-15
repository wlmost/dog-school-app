# Review: T01 — Nginx `client_max_body_size` setzen

**Datum:** 2026-05-13
**Reviewer:** reviewer-Agent

**Kurzurteil:** Der Patch ist korrekt, minimal und vollständig — er löst das beschriebene Problem präzise.

---

## Prüfergebnisse

### 1. Korrektheit ✅

`client_max_body_size` ist die korrekte nginx-Direktive für Upload-Limits.
Der Wert 10M gegenüber dem Laravel-Limit von 5 MB ist bewusst und richtig:
- Nginx prüft die rohe Request-Größe (inkl. multipart-Overhead, Boundary, Header-Daten)
- Ein 2x-Puffer stellt sicher, dass ein 5-MB-Bild nginx immer passiert und Laravel die
  eigentliche Business-Logik-Prüfung (`max:5120`) durchführen kann
- Platzierung im `server {}`-Block (nicht in einem `location`-Block) ist korrekt:
  Die Direktive erbt in alle location-Blöcke, ohne dass irgendwo ein überschreibender
  Wert gesetzt ist

### 2. Sicherheit ✅

Im Dev-Docker-Stack kein relevantes Risiko. Der theoretische DoS-Vektor
(große Requests → mehr Speicher/Disk) existiert auf einem öffentlich erreichbaren
Produktiv-Server — dieser Stack ist jedoch ausschließlich lokal und nicht exponiert.
10 MB ist außerdem ein sehr konservativer Wert; Standard-Upload-Konfigurationen liegen
oft bei 50–100 MB.

### 3. Vollständigkeit ✅

Kein `location`-Block in der Datei definiert einen eigenen `client_max_body_size`-Wert.
Die Direktive im `server {}`-Block greift daher vollständig für alle Requests,
einschließlich des `location ~ \.php$`-Blocks, der die Upload-Requests verarbeitet.
Kein weiterer Patch-Kandidat notwendig.

### 4. Shared-Hosting-Implikation ✅

Die `tasks.md` hält explizit fest: „Nginx-Limits auf Shared Hosting werden vom Hoster
gesteuert und sind nicht Teil dieses Changes." Der hinzugefügte Kommentar in der Datei
selbst erklärt den Kontext (Dev-Docker-Kontext ist durch Dateipfad implizit klar).
Kein Risiko, dass dieser Fix fälschlich als Shared-Hosting-Konfiguration interpretiert wird.

### 5. Keine anderen Änderungen ✅

Die vollständige Datei wurde gelesen. Der einzige Unterschied gegenüber dem
Originalinhalt ist der eingefügte Kommentar + Direktive auf Zeilen 12–16.
Kein anderer Inhalt verändert, keine Formatierungsänderungen, keine versehentlichen
Löschungen.

---

## Befunde

### Könnte (optional)

- **[Lesbarkeit]** `docker/nginx/conf.d/default.conf:12–15`:
  Der Kommentar erklärt, *warum* der Wert größer als das Laravel-Limit ist, implizit
  aber nicht explizit. Ein zukünftiger Leser könnte sich fragen, warum 10M statt 5M.
  Optional: `# Set to 10M (2x Laravel limit) to account for multipart overhead`.
  Kein Muss, da der bestehende Kommentar ausreichend ist.

---

## Lob

- Der Kommentar erklärt den Zusammenhang zwischen nginx-Limit, HTTP 413, CORS-Bypass
  und Laravel-Middleware präzise — das ist ungewöhnlich gut dokumentiert für eine
  Ein-Zeilen-Konfigurationsänderung.
- Die Platzierung im `server`-Block statt in einem spezifischen `location`-Block ist
  die sauberere Lösung und deckt alle Upload-Pfade ab ohne Redundanz.
- Korrekte Aufarbeitung des HTTP-500-Folgeproblems (fehlende Migration) in den Notes —
  transparente Dokumentation ohne Scope-Creep in T01.

---

## Gesamturteil: `freigegeben`

Der Patch ist korrekt, sicher und vollständig. Keine blockierenden Befunde.
