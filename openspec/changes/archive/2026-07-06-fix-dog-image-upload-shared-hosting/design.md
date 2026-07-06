# Design: fix-dog-image-upload-shared-hosting

**Change-ID:** fix-dog-image-upload-shared-hosting
**Datum:** 2026-07-06

---

## 1. Betroffene Dateien (Übersicht)

| Datei | Änderung | Task | DB-Bezug? |
|---|---|---|---|
| `deployment-templates/htaccess/backend-public.htaccess` | `LimitRequestBody` + `php_value`-Fallback ergänzen | T01 | Nein |
| `deployment-templates/htaccess/backend-public.user.ini` (neu) | PHP-Ini-Overrides für PHP-FPM-Hosts (PHP-Core-Mechanismus) | T02 | Nein |
| `deployment-templates/htaccess/backend-public.php.ini` (neu) | PHP-Ini-Overrides als Fallback für CGI/FastCGI-Hosts, bei denen `.user.ini` nicht greift | T02 | Nein |
| `build-deployment.sh`, `build-deployment-docker.sh` | Neue `.user.ini`- **und** `php.ini`-Datei mit ausliefern | T02 | Nein |
| `DEPLOYMENT.md` | Hinweis auf Body-Size-Limits für Betreiber ergänzen | T02 | Nein |
| `frontend/src/components/DogFormModal.vue` | `handleSubmit`: Bild-Upload-Fehler nicht mehr verschlucken | T03 | Nein |
| `backend/public/.htaccess.production` | Optional: entfernen oder in Build-Prozess integrieren | T04 (könnte) | Nein |

**Keine Migration in diesem Change.** `profile_image` existiert bereits als
Spalte (Migration `2026_05_04_100000_add_profile_image_to_dogs_table`,
bereits in `main`) und ist in `Dog::$fillable`
(`backend/app/Models/Dog.php:65`) eingetragen. Alle Änderungen sind reine
Konfigurationsänderungen (Apache/.htaccess, PHP-Ini) und Frontend-Logik —
**unkritisch bzgl. MySQL/Postgres-Portabilität**, da keine SQL- oder
Migrations-Datei berührt wird.

---

## 2. Fix T01/T02: Apache/PHP-Body-Size-Limit für Shared Hosting

### Befund (verifiziert)

- `deployment-templates/htaccess/backend-public.htaccess` (51 Zeilen,
  vollständig gelesen) enthält `mod_negotiation`/`mod_rewrite`-Regeln und
  einen `mod_headers`-Security-Block, aber **keine** Direktive, die die
  Body-Größe begrenzt oder erhöht.
- `deployment-templates/htaccess/backend-root.htaccess` enthält nur
  `Options -Indexes` (1 Zeile) — hier ist kein Body-Size-Limit sinnvoll
  platziert, da diese Datei nur den Ordner `backend/` (Deny-all für
  Source-Dateien) schützt, nicht die tatsächlich ausgeführten PHP-Requests.
- `build-deployment.sh:244` und `build-deployment-docker.sh:340` kopieren
  `backend-public.htaccess` nach `$BUILD_DIR/backend/public/.htaccess`. Das
  ist die Datei, die für alle über
  `deployment-templates/htaccess/root-post-install.htaccess:26`
  (`RewriteRule ^api/(.*)$ backend/public/index.php [L,QSA]`) weitergeleiteten
  API-Requests wirksam wird — inklusive `POST /api/v1/dogs/{id}/upload-image`.
- Laravel-Validierungsregel (unverändert):
  `backend/app/Http/Controllers/Api/DogController.php:185`:
  ```php
  'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
  ```
- Bereits etablierte Konvention aus dem archivierten Nginx-Fix
  (`openspec/specs/dog-image-upload/spec.md:7-9`): Reverse-Proxy-/Server-
  Limit = 2× Laravel-Validierungslimit = **10 MB**, damit Laravel selbst
  bei 5–10 MB noch ein sauberes HTTP-422 mit Validierungsfehler liefern
  kann, statt dass die Infrastrukturebene mit einem undurchsichtigen Fehler
  abbricht.

### Entscheidung: Zwei parallele Mechanismen (T01 + T02)

Shared-Hosting-Anbieter setzen PHP unterschiedlich ein — als Apache-Modul
(`mod_php`) oder als PHP-FPM/CGI hinter Apache. Das ist aus dem Repo nicht
für die Ziel-Umgebung verifizierbar (Server-Konfiguration außerhalb des
Codes, siehe Triage "Ungeprüfte Referenz"). Daher zwei sich ergänzende,
sich gegenseitig nicht störende Mechanismen:

**T01 — `backend-public.htaccess` (wirkt bei Apache + `mod_php`):**

```apache
# Increase request body / upload limits to allow image uploads up to
# Laravel's validation limit (5 MB, see DogController.php:185).
# Apache's own default (no LimitRequestBody) is unlimited, but many shared
# hosting PHP defaults (upload_max_filesize/post_max_size) are far below
# 5 MB. LimitRequestBody is a core Apache directive (always available,
# no IfModule guard needed) and caps the raw request body at the web
# server layer, analogous to nginx's client_max_body_size (see
# docker/nginx/conf.d/default.conf and openspec/specs/dog-image-upload/spec.md).
LimitRequestBody 10485760

# Fallback for hosts running PHP as an Apache module (mod_php). Wrapped in
# IfModule so this is a no-op (not an error) on PHP-FPM/CGI setups, where
# php_value in .htaccess is ignored or would otherwise cause a 500.
<IfModule mod_php.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 12M
</IfModule>
<IfModule mod_php8.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 12M
</IfModule>
```

Platzierung: vor dem bestehenden `<IfModule mod_negotiation.c>`-Block (Zeile 1),
mit Kommentar, analog zur Platzierungskonvention aus dem archivierten
Nginx-Fix (`design.md` des Vorgänger-Changes, Abschnitt 2 "Einfügeort").

`post_max_size` bewusst 2 MB über `upload_max_filesize` gewählt (12M statt
10M) — gängige PHP-Empfehlung, damit der POST-Body neben der Bilddatei noch
Raum für Formular-Overhead (Multipart-Header, weitere Felder) hat. Diese
Faustregel ist allgemeines PHP-Wissen (keine Repo-Quelle) und wird hier als
Design-Entscheidung dokumentiert, nicht als Fakt aus dem Code behauptet.

**T02 — `backend-public.user.ini` + `backend-public.php.ini` (neu, decken
zusammen PHP-FPM- und CGI/FastCGI-Hosts ab):**

`.htaccess`-`php_value`-Direktiven werden von PHP-FPM/CGI-SAPIs ignoriert
(nur `mod_php` wertet sie aus). Da `DEPLOYMENT.md` (Abschnitt "PHP-FPM
optimieren", ab Zeile ~805) PHP-FPM als Referenz-Setup für Produktion
nennt, ist nicht auszuschließen, dass auch Shared-Hosting-Pakete FPM/CGI
verwenden. PHP liest `.user.ini`-Dateien pro Verzeichnis unabhängig von der
SAPI (Standard: `user_ini.filename = .user.ini`, i. d. R. nicht deaktiviert).

> **Nutzer-Rückfrage (2026-07-06):** Der User war sich unsicher, ob der
> konkrete Ziel-Hoster `.user.ini` überhaupt auswertet — der genaue
> PHP-Ausführungsmodus (`mod_php` vs. PHP-FPM vs. klassisches CGI/FastCGI)
> des Shared-Hosting-Pakets ist **nicht aus dem Repo verifizierbar** und dem
> User selbst nicht bekannt. `DEPLOYMENT.md` dokumentiert an keiner Stelle
> explizit, welchen SAPI-Modus der Ziel-Hoster nutzt; der einzige Bezug
> (Abschnitt "PHP-FPM optimieren", Zeile ~805) ist ein allgemeiner
> Performance-Tuning-Hinweis für einen eigenen (Root-/VPS-)Server, keine
> Aussage über das tatsächliche Shared-Hosting-Paket. **Entscheidung des
> Users:** beide Mechanismen nebeneinander ausliefern, da additiv und ohne
> gegenseitige Störung (Begründung siehe unten).

Neue Datei `deployment-templates/htaccess/backend-public.user.ini` (PHP-Core-
Mechanismus, greift bei PHP-FPM und den meisten CGI-Setups, sofern
`user_ini.filename` nicht deaktiviert wurde):

```ini
upload_max_filesize = 10M
post_max_size = 12M
```

**Zusätzlich neue Datei `deployment-templates/htaccess/backend-public.php.ini`**
(Namensanalogie zur bestehenden `.user.ini`-Datei), mit denselben Werten:

```ini
upload_max_filesize = 10M
post_max_size = 12M
```

Hintergrund für diese zweite Datei: Bei einigen Shared-Hosting-Setups, die
PHP klassisch als CGI/FastCGI über einen Wrapper ausführen (verbreitet z. B.
bei cPanel-"MultiPHP INI Editor"/"Custom php.ini"-Funktionen oder
Plesk-CGI-Handlern), wird nicht der PHP-Core-Mechanismus `.user.ini`
ausgewertet, sondern der CGI-Wrapper selbst lädt eine Datei namens `php.ini`
aus demselben Verzeichnis wie das aufgerufene Skript (`backend/public/`)
und reicht deren Werte an die PHP-Instanz durch. Das ist **kein PHP-Core-
Feature** wie `.user.ini` (dafür gibt es keine offizielle PHP-Manual-
Referenz, die für jeden Hoster gilt), sondern eine verbreitete, aber
Hoster-/Panel-spezifische Konvention (allgemeines Shared-Hosting-Wissen,
**keine Repo-Quelle** — analog zur bereits dokumentierten `post_max_size`-
Faustregel in diesem Abschnitt). Da nicht bekannt ist, welcher der beiden
Mechanismen (falls überhaupt einer) beim Ziel-Hoster greift, werden beide
Dateien parallel ausgeliefert.

**Warum das nicht schadet, auch wenn beide oder keiner greifen:**

- **Greift nur `.user.ini`:** `php.ini` liegt ungenutzt im Verzeichnis, wird
  von PHP-FPM/mod_php nicht ausgewertet (nur der jeweilige CGI-Wrapper würde
  das tun, und der ist in diesem Fall nicht aktiv) — kein Effekt, kein
  Schaden.
- **Greift nur `php.ini`:** analog umgekehrt, `.user.ini` bleibt für den
  aktiven SAPI wirkungslos.
- **Greifen beide:** Da beide Dateien **exakt dieselben Werte**
  (`upload_max_filesize = 10M`, `post_max_size = 12M`) enthalten, entsteht
  **kein Wertekonflikt** und keine widersprüchliche Konfiguration — es ist
  unerheblich, welcher Mechanismus zuletzt ausgewertet wird.
- **Greift keiner von beiden** (z. B. Hoster deaktiviert sowohl
  `user_ini.filename` als auch jede Custom-`php.ini`-Auswertung): Der
  Fallback bleibt der in T01 bereits umgesetzte `.htaccess`-`LimitRequestBody`/
  `php_value`-Mechanismus (wirkt unter `mod_php`) sowie — falls auch das
  nicht greift — der in `DEPLOYMENT.md` dokumentierte manuelle
  Post-Deployment-Check (Hoster-Panel bzw. temporäre `phpinfo()`-Seite, siehe
  unten), der diesen Fall für den Betreiber sichtbar macht, statt dass er
  unbemerkt bleibt.
- Beide neuen Dateien sind reine Konfigurationsdateien ohne Code-Ausführung
  (keine PHP-Syntax, kein `<?php`-Tag) — kein Sicherheitsrisiko durch
  versehentliche Ausführung, falls der Webserver `php.ini`/`.user.ini` aus
  Versehen als Skript ausliefert (beide Dateinamen sind keine gängigen
  Apache-Handler-Endungen für PHP-Ausführung).

`build-deployment.sh` und `build-deployment-docker.sh` müssen beide Dateien
zusätzlich zu den bestehenden `.htaccess`-Dateien kopieren:
`deployment-templates/htaccess/backend-public.user.ini` nach
`$BUILD_DIR/backend/public/.user.ini` **und**
`deployment-templates/htaccess/backend-public.php.ini` nach
`$BUILD_DIR/backend/public/php.ini` (analog zum bestehenden
`copy_htaccess_files()`-Pattern, Zeilen 227-264 bzw. 325-355). Die
`verify_htaccess_files()`-Prüfliste (Zeilen 271-286 bzw. 362-372) sollte um
**beide** Dateien erweitert werden, damit ein fehlendes `.user.ini` oder
`php.ini` den Build sichtbar abbricht statt still zu fehlen.

**Hinweis zur Namensgebung (kein Repo-Konflikt, aber Verwechslungspotenzial):**
Im Repo existiert bereits `docker/php/php.ini` (lokale Docker-Dev-Umgebung,
unabhängig von diesem Change, anderes Verzeichnis, anderer Zweck). Die neue
`backend/public/php.ini` im Deployment-Build ist davon getrennt und
überschneidet sich nicht (unterschiedliche Verzeichnisse, keine gemeinsame
Build- oder Laufzeit-Referenz). Trotzdem sollte in `DEPLOYMENT.md` und im
Datei-Kommentar der neuen Vorlage klargestellt werden, dass es sich um zwei
unabhängige Dateien handelt, um künftige Verwechslungen bei der Fehlersuche
zu vermeiden.

`DEPLOYMENT.md` sollte um einen kurzen Hinweis ergänzt werden: Es werden
**beide** Mechanismen ausgeliefert (`.user.ini` für PHP-FPM-Hosts, `php.ini`
für CGI/FastCGI-Hosts mit Custom-php.ini-Unterstützung), da der genaue
PHP-Ausführungsmodus des Ziel-Hosters nicht bekannt ist; beide sind rein
additiv und stören sich nicht gegenseitig. Betreiber sollen nach dem
Deployment prüfen (z. B. über das Hoster-Panel oder eine temporäre
`phpinfo()`-Seite), dass `upload_max_filesize`/`post_max_size` tatsächlich
≥ 10 MB/12 MB wirksam sind — `.user.ini`-Änderungen werden von PHP-FPM
typischerweise erst nach einem Cache-Intervall (`user_ini.cache_ttl`,
Default 300 Sekunden) aktiv, nicht sofort; für `php.ini`-basierte
CGI-Wrapper-Mechanismen ist kein repo-interner Nachweis über ein
vergleichbares Cache-Verhalten möglich (hosterabhängig, im Zweifel nach dem
Deployment einmalig neu testen).

### Offenes Risiko / zu verifizieren (an Skeptiker/Tester)

- **Apache-`.htaccess`-Merge-Reihenfolge bei internem Rewrite:** Die
  `root-post-install.htaccess` leitet `^api/(.*)$` intern per
  `RewriteRule ... backend/public/index.php [L,QSA]` weiter (kein `[PT]`-
  Flag). Ob Apache in dieser Konstellation zuverlässig auch
  `backend/public/.htaccess` (statt nur der Root-`.htaccess`) für
  Verzeichnis-Direktiven wie `LimitRequestBody` auswertet, ist Apache-
  Kernverhalten (Verzeichnisbaum-Merge basiert auf dem aufgelösten
  Dateisystempfad des Ziels), aber **nicht durch einen echten
  Shared-Hosting-Test in diesem Repo verifizierbar**. Empfehlung: nach
  Deployment ein Bild zwischen 2 MB und 8 MB probehalber hochladen (siehe
  Akzeptanzkriterien in `tasks.md`, T01).
- **`AllowOverride`-Kontext für `LimitRequestBody`:** Manche Hoster
  schränken `AllowOverride` ein. Schlägt die Direktive fehl, meldet Apache
  typischerweise HTTP 500 für **alle** Requests unter `backend/public/`
  (nicht nur Uploads) — das wäre sofort sichtbar und einfach durch Entfernen
  der Zeile zu beheben. Kein stiller Fehlerfall.
- **`.user.ini`-Unterstützung:** Setzt voraus, dass der Hoster
  `user_ini.filename` nicht deaktiviert hat. Kein Repo-interner Nachweis
  möglich; Fallback bleibt der `.htaccess`-`php_value`-Mechanismus (T01) für
  `mod_php`-Hosts.
- **`php.ini`-Unterstützung (CGI/FastCGI-Fallback, neu seit User-Feedback
  vom 2026-07-06):** Ob der Ziel-Hoster eine Custom-`php.ini` im
  `backend/public/`-Verzeichnis tatsächlich auswertet, ist eine
  Panel-/Wrapper-spezifische Konvention und **nicht aus dem Repo oder aus
  offizieller PHP-Dokumentation für jeden Hoster verifizierbar** — anders
  als `.user.ini` (PHP-Core-Feature). Schadet aber nicht, falls sie nicht
  greift (siehe Abschnitt "Warum das nicht schadet" oben). Letzter Fallback
  bleibt in jedem Fall T01 (`.htaccess`) sowie der dokumentierte manuelle
  Post-Deployment-Check.

---

## 3. Fix T03: `DogFormModal.vue` — Bild-Upload-Fehler sichtbar machen

> **Korrektur nach Skeptiker-Befund (`verification.md`, Abschnitt
> "Widerlegt", kritischer Punkt):** Die ursprüngliche Fassung dieses
> Abschnitts nahm an, das Modal bleibe offen, solange nur der interne
> `closeModal()`-Aufruf in `DogFormModal.vue` unterbleibt, während
> `emit('saved')` weiterhin feuert. Das ist **widerlegt** — siehe
> "Befund (verifiziert)" unten. Der Lösungsansatz wurde entsprechend
> geändert (Abschnitt "Entscheidung").

### Befund (verifiziert)

`frontend/src/components/DogFormModal.vue:406-421`:

```js
// Upload image if one was selected
if (selectedImageFile.value && savedDog?.id) {
  try {
    const formData = new FormData()
    formData.append('image', selectedImageFile.value)
    await apiClient.post(`/api/v1/dogs/${savedDog.id}/upload-image`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  } catch (imgErr: any) {
    const imgError = imgErr.response?.data?.message || 'Fehler beim Hochladen des Bildes'
    handleApiError(imgErr, imgError)
  }
}

emit('saved')
closeModal()
```

Bereits vorhandene Infrastruktur, die genutzt werden kann (kein Neubau
nötig):

- `error` (Zeile 232, `ref<string | null>(null)`) — wird im Template
  bereits per `v-if="error"` als roter Banner gerendert
  (`DogFormModal.vue:166-168`).
- `closeModal()` (Zeilen 448-453) hat den Guard
  `if (!error.value) { resetForm() }` — **dieser Guard betrifft aber
  ausschließlich `resetForm()`**. `emit('close')` (Zeile 452) wird von
  `closeModal()` **unbedingt**, unabhängig von `error.value`, ausgelöst.

**Kritischer Zusatzbefund (Elternkomponente, durch Skeptiker aufgedeckt
und hier durch eigene Lektüre bestätigt):**

- `frontend/src/views/dogs/DogsView.vue:95-100` bindet `DogFormModal`
  als **kontrollierte Komponente**: `:is-open="showFormModal"` (Prop von
  außen gesetzt), `@close="closeFormModal"`, `@saved="handleDogSaved"`.
- `handleDogSaved` (`DogsView.vue:189-192`) ruft **unbedingt**
  `closeFormModal()` auf, sobald `@saved` feuert:
  ```js
  async function handleDogSaved() {
    await loadDogs()
    closeFormModal()
  }
  ```
- `closeFormModal()` (`DogsView.vue:184-187`) setzt
  `showFormModal.value = false` — das Modal verschwindet über die
  `is-open`-Prop, **unabhängig davon**, ob `DogFormModal.vue` intern
  `closeModal()` aufgerufen hat oder nicht.

Konsequenz: Da das Modal von außen (Prop `is-open`) gesteuert wird, reicht
es **nicht**, den internen `closeModal()`-Aufruf in `DogFormModal.vue` zu
unterdrücken. Jedes der beiden Events (`close` **oder** `saved`) führt in
der Elternkomponente zum selben Ergebnis: `showFormModal.value = false`.
Die ursprünglich geplante Lösung ("`emit('saved')` weiterhin auslösen,
nur `closeModal()` unterdrücken") hätte das Modal also trotzdem schließen
lassen, weil `handleDogSaved` unabhängig vom internen Zustand von
`DogFormModal.vue` reagiert.

### Entscheidung — Lösung (a): `emit('saved')` unterbleibt bei
Bild-Upload-Fehler

Es wird die Variante gewählt, bei der `DogFormModal.vue` beim Bild-Upload-
Fehlerfall **weder `emit('saved')` noch `closeModal()`** aufruft — das
Kind sendet in diesem Fall **kein einziges Signal** an die
Elternkomponente, wodurch `showFormModal` unverändert `true` bleibt und
das Modal unabhängig von `DogsView.vue`s Implementierung offen bleibt.

**Begründung der Wahl gegenüber Alternative (b) (`DogsView.vue` in den
Task-Scope aufnehmen und `handleDogSaved` fehlerabhängig machen):**

1. **Bereits etabliertes Muster im selben Modul:** Der bestehende
   Stammdaten-Fehlerpfad (der äußere `catch`-Block, Zeilen 422-437) macht
   exakt das Gleiche — bei einem Validierungsfehler wird **weder**
   `emit('saved')` **noch** `closeModal()` aufgerufen; das Modal bleibt
   allein dadurch offen, dass die Elternkomponente kein Event empfängt.
   Lösung (a) wendet dasselbe, im Code bereits vorhandene Prinzip
   ("kein Event ⇒ Elternkomponente unternimmt nichts") konsistent auch
   auf den Bild-Upload-Fehlerpfad an. Es wird kein neues Kommunikationsmuster
   zwischen Kind und Eltern eingeführt.
2. **Kleinerer, klarer abgegrenzter Änderungsumfang:** Lösung (b) würde
   den Event-Vertrag zwischen `DogFormModal.vue` und `DogsView.vue`
   erweitern (z. B. `emit('saved', { imageUploadFailed: boolean })`) und
   zusätzlich `handleDogSaved`/`closeFormModal` in `DogsView.vue`
   anpassen. Das bedeutet: zwei Dateien, potenziell zwei Tasks/zwei
   Reviewer-Durchgänge, und einen Vertrag, der bei künftigen Änderungen an
   einer der beiden Seiten leicht auseinanderdriften kann (SOLID:
   Open/Closed wird verletzt, wenn jede neue "Teilerfolg"-Fehlerart im
   Kind eine Vertragsänderung im Eltern-Handler nach sich zieht).
   Lösung (a) bleibt vollständig innerhalb der Verantwortung von
   `DogFormModal.vue` (Single Responsibility: die Komponente entscheidet
   selbst, wann sie "fertig gespeichert" meldet) und ist damit weniger
   fehleranfällig sowie besser wartbar.
3. **Kein Datenverlust, nur verzögerte Listenaktualisierung:** Der
   Stammdaten-Datensatz wurde bereits erfolgreich gespeichert (der erste
   Request war erfolgreich, `savedDog.id` existiert) — nur das Kind-Fenster
   der Liste in `DogsView.vue` wird nicht sofort per `loadDogs()`
   aktualisiert, solange das Modal offen bleibt. Das ist unkritisch: der
   nächste natürliche `loadDogs()`-Aufruf (Suche, Seitenwechsel, erneutes
   Laden der Seite) zeigt den Datensatz korrekt an. Dieser Kompromiss wird
   hier explizit dokumentiert, nicht stillschweigend in Kauf genommen.
4. **User kann explizit "ohne Bild weiterspeichern":** Der bestehende
   "Abbrechen"-Button (`DogFormModal.vue:172-174`, `@click="closeModal"`)
   dient bereits als Ausstiegspunkt aus dem Formular. Klickt der Nutzer
   nach einem gescheiterten Bild-Upload auf "Abbrechen", ruft das
   `closeModal()` auf, welches **immer** `emit('close')` auslöst
   (unabhängig von `error.value`) — die Elternkomponente schließt das
   Modal über `@close="closeFormModal"`. Das ist das bereits vorhandene,
   explizite "ich möchte ohne (aktuelles) Bild weitermachen"-Verhalten;
   kein neuer Button nötig (YAGNI). Die Liste zeigt den gespeicherten Hund
   dann beim nächsten `loadDogs()`-Aufruf (siehe Punkt 3).

**Konkrete Änderungen an `handleSubmit` (Zeilen 406-421):**

1. Im `catch`-Block des Bild-Uploads (aktuell Zeilen 414-417): zusätzlich
   zum bestehenden Toast (`handleApiError`) `error.value` setzen (z. B.
   `"${form.value.name} wurde gespeichert, aber das Profilbild konnte
   nicht hochgeladen werden: ${imgError}"`), damit der bereits vorhandene
   rote Banner den Fehler dauerhaft (nicht nur als vergänglichen Toast)
   anzeigt, **und danach `return`** (kein `emit('saved')`, kein
   `closeModal()` in diesem Ausführungspfad).
2. Nur wenn der Bild-Upload erfolgreich war (oder gar kein Bild
   ausgewählt wurde), werden `emit('saved')` und `closeModal()` wie
   bisher ausgeführt.
3. **Doppel-Anlage-Falle beim Create-Flow vermeiden:** Wenn `props.dog`
   beim Aufruf von `handleSubmit` nicht gesetzt war (Neuanlage), wurde
   durch den ersten (erfolgreichen) Request bereits ein neuer Hund mit
   `savedDog.id` angelegt. Schlägt danach nur der Bild-Upload fehl und der
   Nutzer klickt erneut auf "Speichern" (Modal bleibt ja laut Punkt 1
   offen), darf **kein zweiter Hund** angelegt werden. Empfohlener Ansatz
   (Implementierungsdetail liegt bei `dev-javascript`): den erfolgreich
   gespeicherten/angelegten Hund (`savedDog`) lokal merken (z. B. in einer
   neuen `ref`), sodass ein erneuter Submit-Versuch nach einem
   gescheiterten Bild-Upload **nur** den Bild-Upload wiederholt (PUT/POST
   auf Stammdaten wird übersprungen, wenn bereits ein gespeicherter Hund
   für diese Modal-Instanz bekannt ist). Alternativ: ein dedizierter
   "Bild erneut hochladen"-Button, der direkt den Upload-Request auslöst,
   ohne über `handleSubmit` erneut die Stammdaten zu senden. Bei
   erfolgreichem Retry **muss** dieser Pfad `emit('saved')` und
   `closeModal()` auslösen (analog zu Punkt 2), sonst bleibt das Modal
   auch nach erfolgreichem Bild-Upload fälschlich offen. Beide Ansätze
   sind akzeptabel — entscheidend ist das beobachtbare Verhalten (siehe
   Akzeptanzkriterien in `tasks.md`, T03), nicht die exakte
   Code-Struktur.
4. Fehlermeldungstext soll (wie der bestehende `errorMessage`-Pfad,
   Zeile 434) durch `translateError()` laufen, falls dort eine passende
   Übersetzung existiert — sonst reicht der Rohtext aus
   `imgErr.response?.data?.message`.

### Nicht-Ziel

- Kein Umbau des zweistufigen Save-Flows (Stammdaten zuerst, Bild danach)
  auf einen atomaren Multipart-Request — das wäre ein größerer Umbau
  (Backend-Endpunkt-Änderung) und nicht durch die Anforderung gedeckt
  (YAGNI). Der bestehende zweistufige Ablauf bleibt, nur die
  Fehlerbehandlung wird korrigiert.
- Keine Änderung an `DogsView.vue` bzw. an `handleDogSaved`/
  `closeFormModal` — der gewählte Ansatz (a) löst das Problem
  vollständig innerhalb von `DogFormModal.vue`, ohne den
  Event-Vertrag mit der Elternkomponente zu erweitern.

---

## 4. Nebenfund: `backend/public/.htaccess.production` (toter Code)

`backend/public/.htaccess.production` existiert im Repo (Commit `5a8f185`,
"feat: Add production deployment configuration for shared hosting") und
enthält bereits funktional ähnliche Overrides:

```apache
<IfModule mod_php8.c>
    php_value upload_max_filesize 50M
    php_value post_max_size 50M
    php_value max_execution_time 300
    php_value memory_limit 256M
</IfModule>
```

Diese Datei wird jedoch **von keinem Build-Skript kopiert oder referenziert**
(`grep -n "htaccess.production" build-deployment.sh build-deployment-docker.sh`
liefert keinen Treffer). Sie hat also **keinen Effekt auf reale Deployments**
und ist irreführend für zukünftige Fehlersuche (jemand könnte sie fälschlich
für die aktive Konfiguration halten). Empfehlung: in einem optionalen Task
(T04, "könnte") entweder entfernen oder in den Build-Prozess integrieren
(z. B. als Vorlage für einen künftigen "hoher Traffic"-Modus mit größeren
Limits). Nicht blockierend für dieses Change-Ziel.

---

## 5. Risikobewertung

| Risiko | Schwere | Maßnahme |
|---|---|---|
| `LimitRequestBody`/`php_value` wird vom Hoster nicht erlaubt (`AllowOverride`) | Mittel | Fehler wäre ein sofort sichtbarer HTTP 500 auf allen `/api`-Requests, nicht still — leicht zu erkennen und zu revertieren |
| `.user.ini` wird vom Hoster deaktiviert | Niedrig | Zusätzliches `php.ini` (CGI/FastCGI-Fallback, T02) sowie `.htaccess`-`php_value`-Fallback (T01) decken `mod_php`-Hosts weiterhin ab |
| Weder `.user.ini` noch `php.ini` greifen (Ziel-Hoster nutzt anderen Mechanismus) | Niedrig | `.htaccess`-`php_value`-Fallback (T01) sowie dokumentierter manueller Post-Deployment-Check (`DEPLOYMENT.md`) machen den Fall sichtbar statt still zu bleiben |
| Frontend-Fix ändert Verhalten bei Netzwerkfehlern (z. B. Timeout) unerwartet | Niedrig | Bestehendes Muster ("kein Event ⇒ Elternkomponente schließt nicht") wird wiederverwendet, keine neue Fehlerpfad-Logik erfunden |
| Doppel-Anlage bei Retry im Create-Flow (siehe Abschnitt 3, Punkt 3) | Mittel | Explizit als Akzeptanzkriterium in `tasks.md` T03 aufgenommen |
| Liste in `DogsView.vue` zeigt neu angelegten Hund (ohne Bild) nicht sofort, solange Modal wegen Bild-Upload-Fehler offen bleibt | Niedrig | Bewusster, dokumentierter Kompromiss (Abschnitt 3, Entscheidung Punkt 3); nächster `loadDogs()`-Aufruf (Suche, Seitenwechsel, Reload) zeigt Datensatz korrekt; kein Datenverlust, da Stammdaten bereits gespeichert sind |

### PHP-8.2-Kompatibilität

Dieser Change berührt keine PHP-Anwendungslogik (nur `.htaccess`/`.user.ini`-
Konfigurationsdateien und eine Vue-Komponente). `composer compat-check` ist
nicht betroffen, sollte aber laut Projekt-Pre-Flight trotzdem als Teil der
regulären QA-Suite laufen.

### DB-Portabilität

Kein DB-Zugriff, keine Migration, kein raw SQL in diesem Change — Abschnitt
4.2 der `CLAUDE.md` ist nicht anwendbar.

---

## 6. Nicht-Scope-Abgrenzung

| Thema | Entscheidung |
|---|---|
| Laravel-Validierungsregel `max:5120` erhöhen | Nicht nötig; Wert bleibt (bereits in vorherigem Change entschieden) |
| Atomarer Upload (Stammdaten + Bild in einem Request) | Separater, größerer Change bei Bedarf |
| `training-attachments`-Upload-Flow (`FILE-UPLOAD-SYSTEM.md`) | Anderer Feature-Bereich, nicht Teil dieses Changes |
| Automatisierte Hoster-Panel-Konfiguration | Außerhalb des Repos, nur Dokumentation |
