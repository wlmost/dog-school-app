# Verification: fix-dog-image-upload-shared-hosting

**Schritt 0 — `openspec validate`:** `openspec validate fix-dog-image-upload-shared-hosting` → `Change 'fix-dog-image-upload-shared-hosting' is valid` (strukturell ok, inhaltliche Prüfung durchgeführt).

**Gesamtstatus:** nacharbeit-am-design-nötig

Grund: Ein zentraler Baustein von Fix B (T03) ist so wie beschrieben nicht wirksam — siehe kritischer Befund unter "Widerlegt". Fix A (T01/T02) und das Aufräumen (T04) sind solide verifiziert und benötigen nur kleine Korrekturen an Zeilenangaben.

---

## Bestätigt

- `proposal.md:42-48` / `design.md:41-46` / `tasks.md:15-19`: `build-deployment.sh:244` und `build-deployment-docker.sh:340` kopieren exakt `backend-public.htaccess` nach `$BUILD_DIR/backend/public/.htaccess` → bestätigt, `build-deployment.sh:244` und `build-deployment-docker.sh:340` enthalten wortgleich `cp "$template_dir/backend-public.htaccess" "$BUILD_DIR/backend/public/.htaccess" || error_exit "Failed to copy backend/public .htaccess"`.
- `design.md:41-42` / `tasks.md:6`: `deployment-templates/htaccess/backend-public.htaccess` enthält keine `LimitRequestBody`- oder `php_value`-Direktive → bestätigt durch vollständiges Lesen der Datei (51 Zeilen, nur `mod_negotiation`/`mod_rewrite`/`mod_headers`-Blöcke und `Options -Indexes`).
- `design.md:37-40`: `deployment-templates/htaccess/backend-root.htaccess` enthält inhaltlich nur `Options -Indexes` (plus ein Kommentar) — bestätigt, keine Body-Size-Direktive vorhanden.
- `proposal.md:45-48` / `design.md:43-46`: `deployment-templates/htaccess/root-post-install.htaccess:26` enthält `RewriteRule ^api/(.*)$ backend/public/index.php [L,QSA]` — bestätigt, exakt diese Zeile, **kein** `[PT]`-Flag gesetzt (relevant für das in `design.md:135-145` offen gelassene Risiko zur Verzeichnisbaum-Auswertung von `.htaccess` bei internem Rewrite).
- `design.md:47-51` / `tasks.md:26`: `backend/app/Http/Controllers/Api/DogController.php:185` enthält exakt `'image' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],` — bestätigt wortgleich.
- `proposal.md:82-84`: `DogController.php` `uploadImage()` (Zeilen 180-202) ist strukturell korrekt (Autorisierung, Validierung, Storage-Handling, `$dog->update(['profile_image' => $path])`) — bestätigt, Funktion beginnt Zeile 180, endet Zeile 201 (schließende Klammer), Logik entspricht der Beschreibung.
- `proposal.md:85-86`: `DogResource.php:42-44` (`profileImageUrl`) — bestätigt, Zeilen 42-44 enthalten exakt `'profileImageUrl' => $this->profile_image ? Storage::disk('public')->url($this->profile_image) : null,`.
- `proposal.md:87-88`: Anzeige-Komponenten binden `profileImageUrl` per `v-if` — bestätigt in `frontend/src/views/dogs/DogsView.vue:43-44` und `frontend/src/components/DogFormModal.vue:37,41` (Zeilennummern im Detail leicht abweichend von den in `proposal.md` genannten Bereichen `42-48`/`36-52`, inhaltlich aber korrekt).
- `proposal.md:89-91`: Migration `2026_05_04_100000_add_profile_image_to_dogs_table` existiert und ist bereits in `main` — bestätigt, Datei `backend/database/migrations/2026_05_04_100000_add_profile_image_to_dogs_table.php` vorhanden, keine neue Migration in diesem Change nötig.
- `design.md:22`: `profile_image` ist in `Dog::$fillable` eingetragen — bestätigt inhaltlich (`backend/app/Models/Dog.php:65`), **Zeilenangabe `:52` ist jedoch falsch** (siehe "Widerlegt").
- `design.md:162-181` / `tasks.md:133-145` (`handleSubmit`, Zeilen 406-421): Code-Zitat stimmt wortgleich mit `frontend/src/components/DogFormModal.vue:406-421` überein — bestätigt (inkl. `catch (imgErr: any)`-Block, `handleApiError(imgErr, imgError)`, anschließendem `emit('saved'); closeModal()`).
- `design.md:186-188`: `error`-Ref (`ref<string | null>(null)`) existiert bei `DogFormModal.vue:232` und wird im Template per `v-if="error"` gerendert — bestätigt (`error` deklariert Zeile 232; Template-Nutzung vorhanden, roter Banner-Bereich existiert).
- `design.md:189-194`: `closeModal()` (Zeilen 448-453) hat den Guard `if (!error.value) { resetForm() }` — bestätigt wortgleich in `DogFormModal.vue:448-453`.
- `proposal.md:49-55` / `design.md:241-263` / `tasks.md:193-198`: `backend/public/.htaccess.production` existiert, enthält `php_value upload_max_filesize 50M` / `post_max_size 50M` (u. a.) und wird von keinem Build-Skript referenziert — bestätigt: Datei vorhanden (`backend/public/.htaccess.production`, Inhalt exakt wie zitiert), `grep -rn "htaccess.production"` über das gesamte Repo (außer `openspec/changes/.../*.md`) liefert **keinen Treffer** in `build-deployment.sh`, `build-deployment-docker.sh` oder `DEPLOYMENT.md`.
- `design.md:243-244`: Datei wurde in Commit `5a8f185` ("feat: Add production deployment configuration for shared hosting") hinzugefügt — bestätigt via `git log --diff-filter=A -- backend/public/.htaccess.production`.
- Referenz auf archivierten Change: `openspec/changes/archive/2026-05-13-dog-image-upload-bug/design.md:73-87` — bestätigt, Zeilen enthalten exakt die zitierte Aussage ("Keine Nginx-Konfigurationsdateien in `deployment-templates/`... Shared Hosting verwendet Apache...").
- `docker/nginx/conf.d/default.conf`: enthält `client_max_body_size 10M;` — bestätigt inhaltlich, **Zeilenangabe in `proposal.md:25` (`:12`) ist jedoch falsch** (tatsächlich Zeile 15, siehe "Widerlegt").
- `design.md:52-57` / Verweis auf `openspec/specs/dog-image-upload/spec.md:7-9`: aktive Spec verlangt bereits die 2×-Puffer-Konvention (`client_max_body_size 10M` = 2× Laravel-Limit 5 MB) — bestätigt, Zeilen 7-9 der aktiven Spec enthalten exakt diese Aussage.
- `design.md:106-108`: `DEPLOYMENT.md` enthält einen Abschnitt "PHP-FPM optimieren" — bestätigt, exakt bei Zeile 805 (`### 2. PHP-FPM optimieren`), passt zur Angabe "ab Zeile ~805".
- T02, "neu": `deployment-templates/htaccess/backend-public.user.ini` existiert noch nicht (`ls deployment-templates/htaccess/` zeigt 7 bestehende Dateien, keine `.user.ini`-Datei) — Pfad ist plausibel und konfliktfrei, konsistent mit dem bestehenden `.htaccess`-Template-Verzeichnis.
- `design.md:118-124`: Build-Skripte haben tatsächlich Funktionen `copy_htaccess_files()` und `verify_htaccess_files()`, die um eine `.user.ini`-Kopie/Prüfung erweitert werden könnten — bestätigt (`copy_htaccess_files()` bei `build-deployment.sh:227` / `build-deployment-docker.sh:325`; `verify_htaccess_files()` bei `build-deployment.sh:268` / `build-deployment-docker.sh:359`).
- `design.md:276-286` (PHP-8.2-Kompatibilität/DB-Portabilität nicht anwendbar): korrekt eingeordnet — T01/T02/T04 sind reine `.htaccess`/`.user.ini`-Konfigurationsdateien bzw. Build-Skript-Änderungen, kein PHP-Anwendungscode unter `app/`; Abschnitt 4.1/4.2 der `CLAUDE.md` (verbotene PHP-8.3/8.4-Features, SQL-Portabilität) ist tatsächlich nicht einschlägig. T03 ist reines Frontend (Vue), ebenfalls nicht von Abschnitt 4 betroffen.

## Widerlegt

- `design.md:33-34`: "`deployment-templates/htaccess/backend-public.htaccess` (26 Zeilen, vollständig gelesen)" → tatsächlich **51 Zeilen** (`wc -l deployment-templates/htaccess/backend-public.htaccess` = 51). Die inhaltliche Kernaussage (keine Body-Size-Direktive vorhanden) bleibt davon unberührt, aber die Zeilenangabe ist falsch.
- `proposal.md:25`: "`docker/nginx/conf.d/default.conf:12`, `client_max_body_size 10M;`" → die Direktive steht tatsächlich in **Zeile 15**, nicht Zeile 12 (Zeile 12-14 sind Kommentare, Zeile 15 ist `client_max_body_size 10M;`).
- `proposal.md:84` und `design.md:22`: "`profile_image` ist in `Dog::$fillable` (`backend/app/Models/Dog.php:52`) eingetragen" → Zeile 52 enthält tatsächlich `'customer_id',` (erster Eintrag nach `protected $fillable = [` in Zeile 51). Der Eintrag `'profile_image'` steht tatsächlich in **Zeile 65**. Die inhaltliche Aussage (profile_image ist fillable) ist korrekt, die Zeilenangabe falsch.
- `design.md:121-122`: "Die `verify_htaccess_files()`-Prüfliste (Zeilen 271-286 bzw. 362-372)" → die Funktion `verify_htaccess_files()` beginnt tatsächlich bei **Zeile 268** (`build-deployment.sh`) bzw. **Zeile 359** (`build-deployment-docker.sh`), nicht 271/362 (Abweichung von 3 Zeilen, kein "ca."-Hinweis wie bei der ersten Nennung in Zeile 41-42 des design.md).
- **Kritisch — `design.md:204-209` und `tasks.md:151-164` (Kernannahme von Fix B/T03):** Die Behauptung "`emit('saved')` kann weiterhin ausgelöst werden ... aber `closeModal()` darf in diesem Fall nicht aufgerufen werden" impliziert, dass das Modal offen bleibt, solange nur der interne `closeModal()`-Aufruf in `DogFormModal.vue` unterbleibt. Das ist **widerlegt** durch die Elternkomponente:
  - `frontend/src/views/dogs/DogsView.vue:96-100` bindet `DogFormModal` mit `:is-open="showFormModal"` und `@saved="handleDogSaved"`.
  - `handleDogSaved` (`DogsView.vue:189-192`) ruft **unconditional** `closeFormModal()` auf, sobald `@saved` feuert — unabhängig vom internen Fehlerzustand von `DogFormModal.vue`.
  - `closeFormModal()` (`DogsView.vue:184-187`) setzt `showFormModal.value = false`, wodurch das Modal über die `is-open`-Prop unabhängig von `DogFormModal.vue`s eigenem `closeModal()`/`error.value`-Zustand **verschwindet**.
  - Da `emit('saved')` in der geplanten Lösung laut `tasks.md:162-164` ("`emit('saved')` darf weiterhin ausgelöst werden ... nur das Schließen des Modals ist an den Erfolg des Bild-Uploads gekoppelt") explizit weiterhin ausgelöst werden soll, wird das Modal über den Elternkomponenten-Listener **trotzdem geschlossen** — das im Akzeptanzkriterium geforderte Verhalten "Modal bleibt offen" (`tasks.md:168-169`, Spec-Szenarien in `specs/dog-image-upload/spec.md`, Abschnitt "ADDED Requirements") wird mit dem wie in T03 beschriebenen, auf `DogFormModal.vue` beschränkten Änderungsumfang (`tasks.md:127`: **Dateien:** nur `frontend/src/components/DogFormModal.vue`) **nicht erreicht**.
  - Konsequenz: Entweder muss `emit('saved')` beim Bild-Upload-Fehlerfall **unterbleiben** (Verhaltensänderung gegenüber der Beschreibung in `design.md`/`tasks.md`), oder `DogsView.vue`s `handleDogSaved`/`closeFormModal`-Logik muss ebenfalls angepasst werden (zusätzlicher Task/zusätzliche Datei, die aktuell nicht in `tasks.md` vorgesehen ist).

## Nicht auffindbar

- Keine Behauptung konnte nicht verifiziert werden — alle in `proposal.md`, `design.md` und `tasks.md` genannten Dateien, Funktionen und Codezitate existieren an den referenzierten (ggf. leicht abweichenden) Stellen.

## Neue Elemente (Plausibilität)

- `tasks.md` T02: legt `deployment-templates/htaccess/backend-public.user.ini` an → Verzeichnis `deployment-templates/htaccess/` existiert bereits mit 7 verwandten Template-Dateien (`backend-public.htaccess`, `backend-root.htaccess`, `frontend.htaccess`, `frontend-dist.htaccess`, `root.htaccess`, `root-post-install.htaccess`, `storage.htaccess`); neuer Dateiname ist konsistent mit dem bestehenden `backend-public.*`-Namensschema, keine Konflikte.

## Empfehlung

Fix A (T01/T02, Apache/PHP-Body-Size-Limits) ist solide recherchiert und mit nur kleineren Zeilenangabe-Ungenauigkeiten belegt — hier reicht eine kurze Korrektur der Zeilenverweise vor der Umsetzung, kein inhaltlicher Nacharbeitsbedarf. T04 (Aufräumen der toten `.htaccess.production`) ist vollständig bestätigt und unkritisch.

**T03 (Fix B) muss der Architekt überarbeiten**, bevor User-Gate 1 passiert werden sollte: Die Design-Annahme, dass das Weglassen des internen `closeModal()`-Aufrufs bei gleichzeitigem `emit('saved')` das Modal offen hält, ist durch `DogsView.vue:189-192` (`handleDogSaved` → unconditional `closeFormModal()`) widerlegt. Entweder muss das Emit-Verhalten bei Bild-Upload-Fehlern geändert werden (kein `emit('saved')` in diesem Fall), oder `DogsView.vue` muss als zusätzliche Datei/Task in den Scope von T03 aufgenommen werden, damit `handleDogSaved` den Fehlerfall respektiert. Ohne diese Korrektur wird das zentrale Akzeptanzkriterium "Modal bleibt offen bei fehlgeschlagenem Bild-Upload" nicht erfüllt.
