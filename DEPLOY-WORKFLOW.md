# GitHub Actions Deployment Workflow – Usage Guide

Dieses Dokument beschreibt die Einrichtung und Nutzung des automatisierten
Deployment-Workflows (`.github/workflows/deploy.yml`), der den aktuell geprüften
Code auf den Shared-Hosting-Server überträgt und Datenbankmigrationen ausführt.

---

## Inhaltsverzeichnis

1. [Voraussetzungen auf dem Server](#1-voraussetzungen-auf-dem-server)
2. [Einmalige Einrichtung in GitHub](#2-einmalige-einrichtung-in-github)
3. [Automatisches Deployment](#3-automatisches-deployment)
4. [Manuelles Deployment](#4-manuelles-deployment)
5. [Was der Workflow im Detail macht](#5-was-der-workflow-im-detail-macht)
6. [Fehlerbehandlung und Monitoring](#6-fehlerbehandlung-und-monitoring)
7. [Sicherheitshinweise](#7-sicherheitshinweise)
8. [Häufige Fragen](#8-häufige-fragen)

---

## 1. Voraussetzungen auf dem Server

Bevor der Workflow genutzt werden kann, müssen folgende Dinge auf dem
Shared-Hosting-Server einmalig vorbereitet werden:

### 1.1 SSH-Schlüsselpaar erzeugen

Auf dem lokalen Rechner (oder in einer Docker-Shell) ein dediziertes
Ed25519-Schlüsselpaar für das Deployment erstellen:

```bash
ssh-keygen -t ed25519 -C "github-deploy@dog-school-app" -f ~/.ssh/dog-school-deploy
# Passphrase leer lassen (der private Schlüssel liegt in GitHub Secrets)
```

Dies erzeugt:
- `~/.ssh/dog-school-deploy` – **privater** Schlüssel (kommt in GitHub Secrets)
- `~/.ssh/dog-school-deploy.pub` – **öffentlicher** Schlüssel (kommt auf den Server)

### 1.2 Öffentlichen Schlüssel auf dem Server hinterlegen

Den Inhalt von `dog-school-deploy.pub` in die Datei
`~/.ssh/authorized_keys` auf dem Hosting-Account einfügen
(über SSH-Zugang, SFTP oder das Hoster-Control-Panel):

```bash
# Via SSH, wenn bereits ein anderer Zugang existiert:
cat ~/.ssh/dog-school-deploy.pub | ssh user@meinserver.de \
  "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
```

### 1.3 Zielverzeichnis anlegen

Das Verzeichnis, in das der Workflow deployen soll, muss existieren:

```bash
ssh user@meinserver.de "mkdir -p /home/user/public_html"
```

### 1.4 `.env`-Datei auf dem Server anlegen (Erst-Deployment)

Vor dem ersten Deployment muss die `.env`-Datei manuell auf dem Server erstellt
werden (Zugangsdaten dürfen **nicht** über Git verwaltet werden):

```bash
# .env aus der Vorlage kopieren und anpassen
scp backend/.env.example user@meinserver.de:/home/user/public_html/backend/.env
ssh user@meinserver.de "nano /home/user/public_html/backend/.env"
```

Wichtige Werte in der `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=                  # wird automatisch gesetzt, falls leer
APP_URL=https://meinedomain.de

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hundeschule_db
DB_USERNAME=db_user
DB_PASSWORD=sicheres_passwort

MAIL_MAILER=smtp
MAIL_HOST=...
# usw.
```

> **Hinweis:** Die `.env`-Datei wird vom Workflow **niemals überschrieben**.
> Änderungen an der Konfiguration müssen manuell per SSH auf dem Server
> vorgenommen werden.

---

## 2. Einmalige Einrichtung in GitHub

### 2.1 GitHub Environment `production` anlegen

1. Im Repository auf **Settings → Environments** gehen
2. **New environment** → Name: `production` → **Configure environment**
3. Optional: Unter **Required reviewers** Personen eintragen, die jedes
   Deployment freigeben müssen (empfohlen für Produktion)

### 2.2 Secrets anlegen

Im Environment `production` unter **Environment secrets** folgende
Secrets hinzufügen (**Add secret**):

| Secret | Wert | Beschreibung |
|--------|------|--------------|
| `DEPLOY_SSH_KEY` | Inhalt von `~/.ssh/dog-school-deploy` (privater Schlüssel, inkl. `-----BEGIN ...-----`) | SSH-Authentifizierung |
| `DEPLOY_HOST` | `meinserver.de` oder IP-Adresse | Hostname des Servers |
| `DEPLOY_USER` | `u12345` (Hoster-Benutzername) | SSH-Benutzername |
| `DEPLOY_PATH` | `/home/u12345/public_html` | Zielverzeichnis auf dem Server |

> Auf den **+ Add secret**-Button klicken, Namen eingeben, Wert einfügen, speichern.

### 2.3 Optionale Variable für den SSH-Port

Falls der Server einen nicht-standardmäßigen SSH-Port nutzt
(bei vielen Shared Hostern z. B. Port `22222`):

1. Im Environment `production` unter **Environment variables**
2. **Add variable** → Name: `DEPLOY_PORT` → Wert: z. B. `22222`

Ohne diese Variable wird Port `22` verwendet.

---

## 3. Automatisches Deployment

Nach der Einrichtung läuft das Deployment **vollautomatisch**:

```
Push auf main
    └─→ CI – Build & Test (ci.yml)
            └─→ [alle Tests grün] → Deploy to Shared Hosting (deploy.yml)
                    └─→ Anwendung ist live
```

Es ist **keine manuelle Aktion** erforderlich. Der Deployment-Job startet
nur, wenn die CI-Pipeline erfolgreich durchgelaufen ist.

---

## 4. Manuelles Deployment

Ein Deployment kann jederzeit manuell ausgelöst werden – z. B. um einen
Hotfix ohne vorherigen CI-Lauf zu deployen oder um einen früheren Commit
einzuspielen:

1. Im Repository auf **Actions** gehen
2. Links den Workflow **"Deploy to Shared Hosting"** auswählen
3. **Run workflow** klicken
4. Optional: Im Feld **"Branch or commit SHA to deploy"** einen bestimmten
   Branch-Namen oder Commit-SHA eintragen (Standard: `main`)
5. **Run workflow** bestätigen

> Falls ein **Required Reviewer** konfiguriert ist, erscheint der Job zunächst
> im Status *"Waiting"* und muss von der eingetragenen Person freigegeben werden.

---

## 5. Was der Workflow im Detail macht

Der Workflow durchläuft folgende Schritte in Reihenfolge:

| Schritt | Beschreibung |
|---------|-------------|
| **Checkout** | Lädt den exakten Commit, der CI bestanden hat |
| **PHP 8.2 Setup** | Installiert PHP 8.2 (niedrigster gemeinsamer Nenner für Shared Hosting) |
| **Composer (no-dev)** | `composer install --no-dev --optimize-autoloader` – nur Produktionsabhängigkeiten |
| **Node.js 20** | Installiert Node.js 20 für den Frontend-Build |
| **npm ci + build** | `npm run build:deploy` – erzeugt die optimierten Frontend-Assets |
| **Paket zusammenstellen** | Kopiert Backend + Frontend + `.htaccess`-Dateien + `update.php` + `maintenance.html` in ein temporäres Verzeichnis |
| **SSH konfigurieren** | Schreibt den Deploy-Key und trägt den Server-Fingerabdruck in `known_hosts` ein |
| **Wartungsmodus an** | `php artisan down --retry=60` (schlägt beim ersten Deployment fehl – kein Abbruch) |
| **rsync** | Überträgt alle Dateien per `rsync --delete`; `.env` und Benutzerdaten werden **nicht** überschrieben |
| **Ensure public storage symlink exists** | `php artisan storage:link` – legt `backend/public/storage` an, falls er fehlt (idempotent, kein Fehler bei bereits vorhandenem Symlink) |
| **Migrationen** | `php artisan migrate --force` – führt neue Datenbankmigrationen aus |
| **Cache neu aufbauen** | `config:cache` + `view:cache` (kein `route:cache` wegen Closure-Route in `web.php`) |
| **Wartungsmodus aus** | `php artisan up` – läuft **immer**, auch wenn vorherige Schritte fehlgeschlagen sind |
| **Aufräumen** | SSH-Key wird gelöscht |
| **Summary** | Schreibt Commit, Benutzer und Uhrzeit in die Actions-Zusammenfassung |

### Geschützte Verzeichnisse (werden nie überschrieben)

```
backend/.env
backend/storage/app/          ← Hochgeladene Dateien
backend/storage/logs/         ← Logs
backend/storage/framework/sessions/
backend/storage/framework/cache/
backend/storage/framework/views/
backend/public/storage        ← Symlink zu storage/app/public (kein
                                 Nutzerdaten-Verzeichnis, sondern ein
                                 Symlink, der nach dem rsync-Schritt
                                 automatisch sichergestellt wird, siehe
                                 Schritt-Tabelle oben)
```

---

## 6. Fehlerbehandlung und Monitoring

### Deployment-Status prüfen

- **Actions-Tab** im Repository zeigt alle Workflow-Läufe
- Ein grüner Haken ✅ = Deployment erfolgreich
- Ein rotes X ❌ = Fehler in einem Schritt (App ist trotzdem wieder erreichbar,
  da `php artisan up` immer ausgeführt wird)

### Bei einem fehlgeschlagenen Deployment

1. **Actions** → betroffener Lauf → fehlerhaften Schritt aufklappen
2. Logausgabe lesen
3. Je nach Fehler:
   - **rsync-Fehler** → SSH-Verbindung prüfen (Host, User, Key)
   - **Migrations-Fehler** → Migration manuell via SSH prüfen: `php artisan migrate:status`
   - **Cache-Fehler** → `php artisan config:clear` manuell ausführen

### E-Mail-Benachrichtigung

GitHub sendet bei fehlgeschlagenen Workflows automatisch eine E-Mail
an den Commit-Autor. Unter **Settings → Notifications** lässt sich das
individuell anpassen.

---

## 7. Sicherheitshinweise

- Der SSH-Key wird **nur für die Dauer des Jobs** in den Runner geladen und
  danach sofort gelöscht (`if: always()`).
- Alle Secrets sind im GitHub Environment `production` gespeichert und
  niemals im Code oder in Logs sichtbar.
- Der private SSH-Key sollte **ausschließlich** für dieses Deployment genutzt
  werden und auf dem Server nur minimale Rechte haben.
- Das Deployment-Verzeichnis sollte **nicht** direkt im Document-Root liegen,
  sondern eine Ebene darüber (die `.htaccess` regelt den Zugriff korrekt).

---

## 8. Häufige Fragen

**F: Der Workflow startet nicht, obwohl CI grün ist.**  
A: Stelle sicher, dass der Name des CI-Workflows in `deploy.yml` exakt mit dem
`name:`-Feld in `ci.yml` übereinstimmt (`CI – Build & Test`).

---

**F: Ich möchte nicht bei jedem Merge deployen, sondern nur auf Freigabe.**  
A: Unter **Settings → Environments → production → Required reviewers** einfach
deinen GitHub-Benutzernamen eintragen. Der Job wartet dann auf manuelle Freigabe.

---

**F: Wie ändere ich die Server-Zugangsdaten?**  
A: **Settings → Environments → production → Environment secrets** → das jeweilige
Secret anklicken → **Update** → neuen Wert eingeben. Der nächste Deploy-Lauf
verwendet automatisch die neuen Daten.

---

**F: Kann ich auf einen älteren Commit zurückdeployen?**  
A: Ja, über **Actions → Deploy to Shared Hosting → Run workflow** und den
gewünschten Commit-SHA im `ref`-Feld eintragen.

---

**F: Die `.env` auf dem Server enthält falsche Daten – wie aktualisiere ich sie?**  
A: Direkt per SSH auf dem Server bearbeiten:
```bash
ssh user@meinserver.de "nano /home/user/public_html/backend/.env"
```
Anschließend die Caches neu laden:
```bash
ssh user@meinserver.de "cd /home/user/public_html/backend && php artisan config:cache"
```

---

**F: Was passiert beim ersten Deployment (Server ist noch leer)?**  
A: Der Schritt "Wartungsmodus an" schlägt fehl (kein `artisan` vorhanden), aber
der Workflow bricht dadurch **nicht** ab – eine Warnung wird protokolliert,
und alle weiteren Schritte laufen normal durch.
