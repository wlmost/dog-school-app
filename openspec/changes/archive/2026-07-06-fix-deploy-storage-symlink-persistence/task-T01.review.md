# Review: T01 — `.github/workflows/deploy.yml`

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)

Keine.

## Sollte (vor Merge erledigen, kann diskutiert werden)

Keine.

## Könnte (optional, Verbesserung)

- **[Robustheit]** `.github/workflows/deploy.yml:176`: Der neue rsync-Exclude
  `--exclude='backend/public/storage'` hat bewusst keinen abschließenden `/`,
  während benachbarte Storage-Excludes (`backend/storage/app/` etc., Zeile
  175/177-180) einen haben. Das ist inhaltlich korrekt (Symlink vs.
  Verzeichnis, siehe `design.md` Abschnitt 3.1), aber rein optisch springt
  die Zeile aus dem Muster der Nachbarzeilen heraus. Kein Handlungsbedarf,
  nur zur Kenntnis — das Verhalten wurde geprüft und ist richtig.

## Lob (kurz, was gut gelöst wurde)

- Der neue Schritt „Ensure public storage symlink exists"
  (`.github/workflows/deploy.yml:200-205`) übernimmt exakt das SSH-Aufruf-
  Muster (Quoting, Secrets-Referenzen, `$DEPLOY_PORT`) des direkt
  darunterliegenden „Run database migrations"-Schritts
  (`.github/workflows/deploy.yml:210-215`) — Konsistenz mit bestehendem
  Stil ist vollständig gegeben.
- Die bewusste Entscheidung gegen einen Best-Effort-Fallback
  (`|| echo "::warning::..."`) ist nachvollziehbar begründet und durch
  tatsächliches Lesen von
  `backend/vendor/laravel/framework/src/Illuminate/Foundation/Console/StorageLinkCommand.php`
  verifiziert (Aufruf von `handle()` ohne Return, `components->error()` wirft
  keine Exception) — keine bloße Behauptung, sondern belegte Code-Analyse.
  Die Referenz auf `backend/.gitignore:5` (`/public/storage`) stimmt exakt.
- Die Kommentar-Nummerierung der nachfolgenden Schritte (`# 8.` bis `# 12.`
  → `# 9.` bis `# 13.`) ist lückenlos und korrekt fortlaufend — per `git
  diff` gegenkontrolliert, keine Duplikate oder Sprünge.
- `git diff main -- .github/workflows/deploy.yml` enthält ausschließlich die
  drei angekündigten Änderungsblöcke (Exclude-Zeile, neuer Schritt,
  Nummerierungs-Bumps) — keine ungewollten Nebenänderungen an Secrets-
  Referenzen, Trigger- oder Environment-Konfiguration.
- YAML-Syntax wurde verifiziert (`python3 -c "import yaml; ...; print('OK')"`
  während dieses Reviews erneut erfolgreich ausgeführt).
- Keine Secrets im Klartext — durchgehend `${{ secrets.* }}`-Referenzen wie
  im Bestandscode.
