# Production Deployment Guide

Dieser Guide beschreibt das Deployment der Hundeschule-Verwaltungsanwendung auf einem Webhoster mit PHP 8.4 und MySQL.

---

## 🚀 Quick Start: Shared Hosting Installation

**NEU**: Für Shared Hosting Umgebungen (z.B. Strato, 1&1, ALL-INKL) steht jetzt ein automatisierter Installations-Prozess zur Verfügung!

### Übersicht

Der automatisierte Shared Hosting Installer besteht aus zwei Komponenten:
1. **Build-Script** (`build-deployment.sh`) - Erstellt ein deployment-fertiges Archiv
2. **Installation-Wizard** (`install.php`) - Web-basierter Installations-Assistent

### Voraussetzungen

- PHP 8.4+ mit benötigten Extensions
- MySQL 8.0+ Datenbank
- FTP oder Web-Dateimanager Zugriff
- Composer und npm (nur für Build-Prozess auf lokalem Rechner)

---

### Schritt 1: Deployment-Paket erstellen

**Auf Ihrem lokalen Entwicklungsrechner:**

```bash
# Build-Script ausführbar machen (einmalig)
chmod +x build-deployment.sh

# Deployment-Paket erstellen
./build-deployment.sh
```

Das Script führt automatisch folgende Schritte aus:
- ✓ Prüft ob Composer und npm verfügbar sind
- ✓ Installiert Backend Production Dependencies
- ✓ Installiert und baut Frontend Assets
- ✓ Kopiert Anwendungsdateien (ohne Development-Files)
- ✓ Generiert .htaccess Dateien für Apache
- ✓ Erstellt tar.gz Archiv mit Zeitstempel

**Ausgabe:**
```
✓ Build Completed Successfully!

Archive:      homocanis-deployment-20260215-143000.tar.gz
Size:         45.2M
Files:        3,847

Next steps:
  1. Upload homocanis-deployment-20260215-143000.tar.gz to your shared hosting server
  2. Extract the archive in your desired directory
  3. Access install.php in your browser to complete installation
```

---

### Schritt 2: Archiv auf Server hochladen

**Via FTP oder Web-Dateimanager:**

1. **Verbinden Sie sich mit Ihrem Shared Hosting**
   - Verwenden Sie FTP (FileZilla, Cyberduck) oder Web-Dateimanager
   
2. **Navigieren Sie zum Zielverzeichnis**
   - Typischerweise: `public_html/` oder `htdocs/`
   - Für Subdomain: `public_html/hundeschule/` oder ähnlich

3. **Laden Sie das tar.gz Archiv hoch**
   - Upload: `homocanis-deployment-YYYYMMDD-HHMMSS.tar.gz`

4. **Entpacken Sie das Archiv**
   ```bash
   # Via SSH (wenn verfügbar)
   tar -xzf homocanis-deployment-YYYYMMDD-HHMMSS.tar.gz
   
   # Oder via Web-Dateimanager "Extract" Funktion
   ```

5. **Löschen Sie das Archiv**
   ```bash
   rm homocanis-deployment-YYYYMMDD-HHMMSS.tar.gz
   ```

---

### Schritt 3: Installation-Wizard ausführen

**Im Browser:**

1. **Öffnen Sie den Installer**
   - Navigieren Sie zu: `https://ihre-domain.de/hundeschule/install.php`
   - Der Wizard startet automatisch

2. **Schritt 1: Willkommen**
   - Lesen Sie die Übersicht
   - Klicken Sie "Start Installation"

3. **Schritt 2: Server-Anforderungen**
   - Der Wizard prüft automatisch:
     - ✓ PHP Version (8.4+)
     - ✓ Alle benötigten PHP Extensions
     - ✓ Empfohlene Extensions
   - Bei kritischen Fehlern: Kontaktieren Sie Ihren Hoster
   - Bei Warnungen: Sie können fortfahren (Features evtl. eingeschränkt)
   - Klicken Sie "Next: Database Configuration"

4. **Schritt 3: Datenbank-Konfiguration**
   - Geben Sie Ihre MySQL-Zugangsdaten ein:
     - **Host**: `localhost` (Standard bei Shared Hosting)
     - **Port**: `3306` (Standard MySQL Port)
     - **Datenbankname**: Ihr MySQL Datenbankname (z.B. `db123456_homocanis`)
     - **Benutzername**: Ihr MySQL Benutzername
     - **Passwort**: Ihr MySQL Passwort
   - Klicken Sie "Test Connection" - Der Wizard testet die Verbindung
   - Bei Erfolg: Klicken Sie "Next: Application Settings"
   - Bei Fehler: Prüfen Sie Zugangsdaten und versuchen Sie erneut

5. **Schritt 4: Anwendungs-Einstellungen**
   - **Application Name**: `HomoCanis` (oder Ihr gewünschter Name)
   - **Application URL**: Auto-erkannt, ggf. anpassen (z.B. `https://hundeschule.ihre-domain.de`)
     - ⚠️ **Wichtig**: Die URL wird verwendet, um die `.htaccess` Dateien automatisch zu konfigurieren
     - Für Subdomain: `https://hundeschule.ihre-domain.de` → RewriteBase `/`
     - Für Unterverzeichnis: `https://ihre-domain.de/hundeschule` → RewriteBase `/hundeschule`
   - **Environment**: `production` (für Live-Server)
   - **Timezone**: `Europe/Berlin` oder Ihre Zeitzone
   - Klicken Sie "Next: Environment Setup"

6. **Schritt 5: Umgebung einrichten**
   - Der Wizard führt automatisch aus:
     - ✓ Erstellt .env Datei mit Ihren Einstellungen
     - ✓ Generiert APP_KEY (Verschlüsselungsschlüssel)
     - ✓ Konfiguriert .htaccess Dateien basierend auf Application URL
     - ✓ Erstellt benötigte Verzeichnisse (storage/, bootstrap/cache/)
     - ✓ Setzt Berechtigungen (775)
     - ✓ Prüft Schreibrechte
   - Bei Fehlern: "Rollback Installation" möglich
   - Bei Erfolg: Klicken Sie "Next: Database Migration"

7. **Schritt 6: Datenbank-Migration**
   - Der Wizard führt automatisch aus:
     - ✓ Erstellt alle Datenbank-Tabellen
     - ✓ Führt Migrationen aus
     - ✓ Optional: Installiert Demo-Daten (Checkbox aktivieren)
   - Fortschritt wird angezeigt
   - Klicken Sie "Complete Installation"

8. **Schritt 7: Installation abgeschlossen! 🎉**
   - ✓ Storage Symlink erstellt
   - ✓ Lock-Datei erstellt (verhindert erneute Installation)
   - ✓ Installation-Log gespeichert
   
   **WICHTIG - Sicherheit:**
   - Klicken Sie "🗑 Delete Installer Now" um install.php zu löschen
   - Oder löschen Sie es manuell via FTP
   
   **→ Klicken Sie "Go to Application"** um Ihre Anwendung zu öffnen!

---

### Directory Structure (Shared Hosting)

Nach der Installation haben Sie folgende Struktur:

```
public_html/hundeschule/
├── .htaccess                  # Root .htaccess (leitet zu backend/public/)
├── install.php               # Installer (LÖSCHEN nach Installation!)
├── install.lock              # Lock-Datei (verhindert Re-Installation)
├── install.log               # Installations-Log
├── backend/
│   ├── .htaccess             # Schützt Backend-Code (deny all)
│   ├── .env                  # Konfiguration (WICHTIG: nicht öffentlich!)
│   ├── .env.template         # Template für .env
│   ├── app/                  # Laravel Application Code
│   ├── bootstrap/
│   │   └── cache/            # Writable (775)
│   ├── config/               # Laravel Config
│   ├── database/             # Migrations, Seeders
│   ├── public/               # Web Root!
│   │   ├── .htaccess         # Laravel Routing + Security Headers
│   │   ├── index.php         # Application Entry Point
│   │   └── storage -> ../../storage/app/public (Symlink)
│   ├── routes/               # API + Web Routes
│   ├── storage/              # Writable (775)
│   │   ├── .htaccess         # Schützt Storage (deny all)
│   │   ├── app/
│   │   ├── framework/
│   │   └── logs/
│   └── vendor/               # Composer Dependencies
├── frontend/
│   ├── .htaccess             # Schützt Frontend Source (deny all)
│   └── dist/                 # Gebaute Frontend Assets
│       ├── assets/
│       └── index.html
└── LICENSE, README.md
```

**Wichtige Hinweise:**
- Der **Web Root** sollte auf `backend/public/` zeigen
- Die root `.htaccess` leitet automatisch alle Anfragen zu `backend/public/`
- `install.php` MUSS nach erfolgreicher Installation gelöscht werden!
- `.env` enthält sensible Daten und darf NICHT öffentlich zugänglich sein

---

### Troubleshooting (Shared Hosting)

#### Problem: "Installation Locked" obwohl nicht installiert

**Ursache:** Lock-Datei oder .env existiert bereits

**Lösung:**
```bash
# Via FTP oder SSH
rm -f install.lock
rm -f backend/.env

# Oder via Web-Dateimanager löschen
```

Dann Installer erneut aufrufen.

#### Problem: "Permission denied" Fehler bei Verzeichnis-Erstellung

**Ursache:** Webserver hat keine Schreibrechte

**Lösung via FTP:**
```bash
# Setzen Sie Berechtigungen manuell:
chmod 775 backend/storage
chmod 775 backend/storage/app
chmod 775 backend/storage/framework
chmod 775 backend/storage/logs
chmod 775 backend/bootstrap/cache
```

**Lösung via SSH:**
```bash
find backend/storage -type d -exec chmod 775 {} \;
find backend/bootstrap/cache -type d -exec chmod 775 {} \;
```

#### Problem: Datenbank-Verbindung schlägt fehl

**Ursache:** Falsche Zugangsdaten oder Host

**Lösung:**
1. Prüfen Sie Ihre MySQL-Zugangsdaten im Hosting-Control-Panel
2. Host ist oft `localhost`, kann aber auch sein:
   - `mysql.ihre-domain.de`
   - `db123.hosting-provider.de`
3. Stellen Sie sicher, dass die Datenbank existiert
4. Prüfen Sie Benutzername und Passwort (Copy-Paste empfohlen)

#### Problem: 500 Internal Server Error

**Ursache 1:** .htaccess nicht kompatibel

**Lösung:** Prüfen Sie `storage/logs/laravel.log` (falls zugänglich) oder kontaktieren Sie Support

**Ursache 2:** PHP-Version zu alt

**Lösung:** Stellen Sie sicher, dass PHP 8.4+ aktiv ist. Im Hosting-Panel:
- cPanel: "Select PHP Version"
- Plesk: "PHP Settings"
- Prüfen via: `<?php phpinfo(); ?>` in Datei `info.php`

#### Problem: Assets (CSS/JS) werden nicht geladen

**Ursache:** Frontend nicht korrekt gebaut oder deployed

**Lösung:**
1. Prüfen Sie ob `frontend/dist/` Verzeichnis existiert
2. Bauen Sie Frontend lokal neu: `cd frontend && npm run build`
3. Laden Sie `frontend/dist/` erneut hoch

#### Problem: HTTP 403 Forbidden beim Zugriff auf die Hauptseite

**Ursache:** `.htaccess` nicht korrekt konfiguriert für Unterverzeichnis

**Lösung:**
Der Wizard konfiguriert die `.htaccess` automatisch basierend auf der eingegebenen URL. Falls dies fehlschlägt:

**Für Root-Installation** (`https://hundeschule.ihre-domain.de`):
```apache
# In .htaccess (Root)
RewriteBase /

# In backend/public/.htaccess
RewriteBase /
```

**Für Unterverzeichnis** (`https://ihre-domain.de/hundeschule`):
```apache
# In .htaccess (Root)
RewriteBase /hundeschule

# In backend/public/.htaccess  
RewriteBase /hundeschule
```

**Manuelle Anpassung:**
1. Öffnen Sie `.htaccess` im Root-Verzeichnis
2. Suchen Sie die Zeile `RewriteBase /`
3. Ersetzen Sie `/` mit dem Pfad aus Ihrer URL (z.B. `/hundeschule`)
4. Wiederholen Sie für `backend/public/.htaccess`

#### Problem: Installer lässt sich nicht laden

**Ursache:** Datei-Berechtigungen oder PHP nicht verfügbar

**Lösung:**
```bash
# Berechtigungen setzen
chmod 755 install.php

# PHP-Version prüfen (erstellen Sie test.php):
<?php phpinfo(); ?>
```

#### Problem: Migration schlägt fehl

**Ursache:** Unvollständige Datenbank-Rechte

**Lösung:**
- Stellen Sie sicher, dass der MySQL-User CREATE, DROP, ALTER Rechte hat
- Im Control-Panel: MySQL User Permissions prüfen/setzen

#### Problem: Nach Installation "Page not found"

**Ursache:** .htaccess Regeln nicht aktiv

**Lösung:**
1. Prüfen Sie ob Apache `mod_rewrite` aktiviert ist (fragen Sie Ihren Hoster)
2. Testen Sie ob `.htaccess` gelesen wird:
   ```
   # Fügen Sie in .htaccess ein:
   # TEST
   
   # Wenn Sie 500 Error bekommen, wird .htaccess gelesen
   ```

---

### Post-Installation Schritte

Nach erfolgreicher Installation:

1. **Installer löschen**
   ```bash
   rm install.php install.lock install.log
   ```

2. **Erste Schritte**
   - Melden Sie sich beim Admin-Account an
   - Ändern Sie das Admin-Passwort
   - Konfigurieren Sie E-Mail-Einstellungen (in `.env`)

3. **Sicherheit**
   - Prüfen Sie, dass `.env` nicht über Web zugänglich ist
   - Testen Sie: `https://ihre-domain.de/hundeschule/backend/.env` sollte 403 Forbidden zeigen
   - Prüfen Sie, dass `storage/` geschützt ist

4. **Backup einrichten**
   - Richten Sie regelmäßige Datenbank-Backups ein (oft im Hosting-Panel verfügbar)
   - Sichern Sie `.env` Datei lokal

5. **Optional: Demo-Daten entfernen**
   ```bash
   # Falls Sie Demo-Daten installiert haben und diese löschen möchten:
   php artisan migrate:fresh --force
   # ACHTUNG: Löscht ALLE Daten!
   ```

---

### Build-Script Anpassungen

Falls Sie den Build-Prozess anpassen möchten:

**Dateien ausschließen** (in `build-deployment.sh`):
```bash
# Zeile ~120: Fügen Sie weitere Ausschlüsse hinzu
rsync -a --exclude='node_modules' \
         --exclude='.git' \
         --exclude='your-custom-exclusion' \
         backend/ "$BUILD_DIR/backend/"
```

**.htaccess Templates anpassen** (in `deployment-templates/htaccess/`):
```bash
# Bearbeiten Sie die Templates:
deployment-templates/htaccess/root.htaccess
deployment-templates/htaccess/backend-public.htaccess
deployment-templates/htaccess/backend-root.htaccess
deployment-templates/htaccess/storage.htaccess
deployment-templates/htaccess/frontend.htaccess
```

---

## Server-Anforderungen überprüfen

**WICHTIG**: Bevor Sie mit dem Deployment beginnen, überprüfen Sie, ob Ihr Server alle Anforderungen erfüllt.

### Automatische Überprüfung mit requirements-check.php

Die Anwendung enthält ein Validierungsskript, das alle Server-Anforderungen automatisch überprüft.

**So verwenden Sie das Skript:**

1. **Laden Sie die Datei hoch**:
   - Kopieren Sie `backend/requirements-check.php` auf Ihren Server
   - Platzieren Sie es im `backend/`-Verzeichnis

2. **Führen Sie das Skript aus**:
   - Öffnen Sie in Ihrem Browser: `https://ihre-domain.de/requirements-check.php`
   - Das Skript überprüft automatisch:
     - ✓ PHP Version (8.4.x erforderlich)
     - ✓ Alle benötigten PHP-Extensions
     - ✓ Schreibrechte für Storage-Verzeichnisse
     - ✓ MySQL-Datenbank-Konnektivität (optional)

3. **Beheben Sie alle gemeldeten Probleme**:
   - Das Skript zeigt detaillierte Fehlermeldungen und Lösungsvorschläge
   - Rot markierte Einträge MÜSSEN behoben werden
   - Gelb markierte Einträge sind Warnungen (empfohlen)

4. **WICHTIG - Löschen Sie das Skript nach der Überprüfung**:
   ```bash
   rm backend/requirements-check.php
   ```
   Das Skript enthält Systeminformationen und sollte nicht in der Produktivumgebung verbleiben.

**Alternative manuelle Überprüfung:**

Falls Sie das Skript nicht verwenden können, überprüfen Sie die folgenden Anforderungen manuell:

## Systemvoraussetzungen

### Server-Anforderungen
- **PHP**: 8.4 oder höher
- **Datenbank**: MySQL 8.0+ oder MariaDB 10.5+
- **Webserver**: Apache 2.4+ oder Nginx 1.18+
- **Speicher**: Mindestens 512 MB RAM
- **PHP-Extensions**:
  - OpenSSL
  - PDO
  - Mbstring
  - Tokenizer
  - XML
  - Ctype
  - JSON
  - BCMath
  - GD oder Imagick (für PDF-Generierung)
  - Zip
  - Fileinfo

### Composer
- Composer 2.x wird benötigt

### Node.js (für Frontend-Build)
- Node.js 20.x oder höher
- npm 10.x oder höher

---

## Vorbereitung

### 1. Repository klonen
```bash
git clone <repository-url> hundeschule
cd hundeschule
```

### 2. Backend-Dependencies installieren
```bash
cd backend
composer install --no-dev --optimize-autoloader
```

### 3. Frontend bauen
```bash
cd ../frontend
npm install
npm run build
```

Die gebauten Dateien befinden sich in `frontend/dist/`

---

## Datenbank einrichten

### MySQL-Datenbank erstellen

```sql
CREATE DATABASE hundeschule CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hundeschule_user'@'localhost' IDENTIFIED BY 'SICHERES_PASSWORT';
GRANT ALL PRIVILEGES ON hundeschule.* TO 'hundeschule_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## Backend-Konfiguration

### 1. Umgebungsvariablen einrichten

Kopieren Sie die `.env.production.example` zur `.env`:
```bash
cd backend
cp .env.production.example .env
```

### 2. .env anpassen

Bearbeiten Sie die `.env` und passen Sie folgende Werte an:

```env
APP_NAME="Hundeschule Verwaltung"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://ihre-domain.de

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=hundeschule
DB_USERNAME=hundeschule_user
DB_PASSWORD=SICHERES_PASSWORT

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Mail-Konfiguration (anpassen!)
MAIL_MAILER=smtp
MAIL_HOST=smtp.ihre-domain.de
MAIL_PORT=587
MAIL_USERNAME=noreply@ihre-domain.de
MAIL_PASSWORD=MAIL_PASSWORT
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@ihre-domain.de
MAIL_FROM_NAME="${APP_NAME}"

# Frontend URL
FRONTEND_URL=https://ihre-domain.de
SANCTUM_STATEFUL_DOMAINS=ihre-domain.de
SESSION_DOMAIN=.ihre-domain.de
```

### 3. Application Key generieren
```bash
php artisan key:generate
```

### 4. Storage-Verzeichnisse vorbereiten
```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

### 5. Datenbank migrieren und seeden
```bash
php artisan migrate --force
php artisan db:seed --force
```

### 6. Caches erstellen
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Webserver-Konfiguration

### Apache (.htaccess)

Die Anwendung verwendet bereits `.htaccess` in `backend/public/`. 

**Wichtig**: Das Document Root muss auf `backend/public` zeigen!

Zusätzliche Apache-Konfiguration in VirtualHost:

```apache
<VirtualHost *:443>
    ServerName ihre-domain.de
    DocumentRoot /pfad/zu/hundeschule/backend/public

    <Directory /pfad/zu/hundeschule/backend/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Frontend (statische Dateien)
    Alias /assets /pfad/zu/hundeschule/frontend/dist/assets
    <Directory /pfad/zu/hundeschule/frontend/dist>
        Require all granted
    </Directory>

    # SSL-Konfiguration
    SSLEngine on
    SSLCertificateFile /pfad/zu/cert.pem
    SSLCertificateKeyFile /pfad/zu/key.pem
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name ihre-domain.de;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ihre-domain.de;
    root /pfad/zu/hundeschule/backend/public;

    index index.php index.html;

    # SSL-Konfiguration
    ssl_certificate /pfad/zu/fullchain.pem;
    ssl_certificate_key /pfad/zu/privkey.pem;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Frontend static files
    location /assets {
        alias /pfad/zu/hundeschule/frontend/dist/assets;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Frontend SPA
    location / {
        try_files /pfad/zu/hundeschule/frontend/dist$uri /pfad/zu/hundeschule/frontend/dist/index.html =404;
    }

    # API routes
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Laravel backend
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## Frontend-Deployment

### 1. Umgebungsvariablen für Frontend

Erstellen Sie `frontend/.env.production`:
```env
VITE_API_URL=https://ihre-domain.de/api/v1
```

### 2. Frontend neu bauen mit Production-Variablen
```bash
cd frontend
npm run build
```

### 3. Statische Dateien deployen

Kopieren Sie den Inhalt von `frontend/dist/` in das Webroot oder konfigurieren Sie den Webserver wie oben beschrieben.

---

## Cron-Jobs einrichten

Für Queue-Jobs und geplante Tasks:

```cron
# Laravel Scheduler (läuft jede Minute)
* * * * * cd /pfad/zu/hundeschule/backend && php artisan schedule:run >> /dev/null 2>&1

# Queue Worker (optional, falls database queue verwendet wird)
# Besser: Supervisor verwenden (siehe unten)
```

### Supervisor für Queue Worker (empfohlen)

`/etc/supervisor/conf.d/hundeschule-worker.conf`:
```ini
[program:hundeschule-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /pfad/zu/hundeschule/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/pfad/zu/hundeschule/backend/storage/logs/worker.log
stopwaitsecs=3600
```

Aktivieren:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start hundeschule-worker:*
```

---

## Sicherheit

### 1. Verzeichnisrechte
```bash
# Besitzer auf Webserver-User setzen
sudo chown -R www-data:www-data /pfad/zu/hundeschule

# Rechte setzen
sudo find /pfad/zu/hundeschule -type f -exec chmod 644 {} \;
sudo find /pfad/zu/hundeschule -type d -exec chmod 755 {} \;

# Storage und Cache beschreibbar
sudo chmod -R 775 /pfad/zu/hundeschule/backend/storage
sudo chmod -R 775 /pfad/zu/hundeschule/backend/bootstrap/cache
```

### 2. Sensible Dateien schützen
```bash
# .env nicht öffentlich zugänglich
chmod 600 backend/.env

# Git-Verzeichnis schützen
echo "Deny from all" > .git/.htaccess
```

### 3. Firewall
```bash
# Nur HTTP/HTTPS erlauben
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 4. SSL/TLS (Let's Encrypt)
```bash
sudo apt install certbot python3-certbot-apache  # für Apache
# oder
sudo apt install certbot python3-certbot-nginx   # für Nginx

sudo certbot --apache -d ihre-domain.de
# oder
sudo certbot --nginx -d ihre-domain.de
```

---

## Performance-Optimierung

### 1. OPcache aktivieren

`/etc/php/8.4/fpm/conf.d/10-opcache.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### 2. PHP-FPM optimieren

`/etc/php/8.4/fpm/pool.d/www.conf`:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

### 3. Database Query Caching
Bereits in Laravel konfiguriert via Cache-Driver.

### 4. Asset Compression
Nginx/Apache sollten gzip/brotli Kompression aktiviert haben.

---

## Monitoring & Logging

### 1. Laravel Logs
```bash
tail -f /pfad/zu/hundeschule/backend/storage/logs/laravel.log
```

### 2. Error Reporting
In Production sollte `APP_DEBUG=false` sein. Fehler werden geloggt, aber nicht angezeigt.

### 3. Monitoring Tools (optional)
- Laravel Telescope (nur Development)
- Laravel Horizon (für Redis Queue)
- Sentry für Error Tracking
- New Relic oder DataDog für APM

---

## Backup-Strategie

### 1. Datenbank-Backup (täglich)
```bash
#!/bin/bash
# backup-db.sh
mysqldump -u hundeschule_user -p'PASSWORT' hundeschule | gzip > /backup/hundeschule-$(date +%Y%m%d).sql.gz

# Alte Backups löschen (älter als 30 Tage)
find /backup -name "hundeschule-*.sql.gz" -mtime +30 -delete
```

Cronjob:
```cron
0 2 * * * /pfad/zu/backup-db.sh
```

### 2. Dateien-Backup
```bash
# Storage-Verzeichnis sichern
tar -czf /backup/storage-$(date +%Y%m%d).tar.gz /pfad/zu/hundeschule/backend/storage/app
```

### 3. Offsite-Backup
- Regelmäßig Backups auf externen Server kopieren
- Cloud-Backup (AWS S3, DigitalOcean Spaces, etc.)

---

## Deployment-Workflow

### Manuelle Deployment-Schritte

1. **Code aktualisieren**:
```bash
cd /pfad/zu/hundeschule
git pull origin master
```

2. **Backend aktualisieren**:
```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. **Frontend neu bauen**:
```bash
cd ../frontend
npm install --production
npm run build
```

4. **Services neu starten**:
```bash
sudo supervisorctl restart hundeschule-worker:*
sudo systemctl restart php8.4-fpm
```

### Automatisiertes Deployment (Optional)

Erstellen Sie ein Deployment-Script `deploy.sh`:
```bash
#!/bin/bash
set -e

echo "Starting deployment..."

# Maintenance Mode
php artisan down

# Update code
git pull origin master

# Backend
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
cd ../frontend
npm ci --production
npm run build

# Restart services
sudo supervisorctl restart hundeschule-worker:*
sudo systemctl restart php8.4-fpm

# Exit maintenance mode
cd ../backend
php artisan up

echo "Deployment completed!"
```

---

## Troubleshooting

### Problem: 500 Internal Server Error
- Prüfen: `storage/logs/laravel.log`
- Rechte: `chmod -R 775 storage bootstrap/cache`
- APP_KEY generiert? `php artisan key:generate`

### Problem: Datenbank-Verbindung fehlgeschlagen
- `.env` DB-Credentials prüfen
- MySQL läuft? `sudo systemctl status mysql`
- Firewall? User-Rechte?

### Problem: Queue-Jobs werden nicht verarbeitet
- Supervisor läuft? `sudo supervisorctl status`
- Queue-Tabelle existiert? `php artisan queue:table` dann `php artisan migrate`

### Problem: E-Mails werden nicht versendet
- SMTP-Credentials in `.env` prüfen
- Queue läuft? (Mails werden gequeued)
- Logs: `storage/logs/laravel.log`

### Problem: Frontend zeigt alte Version
- Browser-Cache leeren
- Hard Refresh (Ctrl+Shift+R)
- Frontend neu bauen

---

## Checkliste vor Go-Live

- [ ] SSL-Zertifikat installiert und gültig
- [ ] `.env` mit Production-Werten konfiguriert
- [ ] `APP_DEBUG=false` gesetzt
- [ ] `APP_ENV=production` gesetzt
- [ ] Datenbank migriert und geseeded
- [ ] Storage-Link erstellt
- [ ] Caches generiert (config, route, view)
- [ ] Cron-Jobs eingerichtet
- [ ] Queue-Worker läuft (Supervisor)
- [ ] Verzeichnisrechte korrekt (775/644)
- [ ] Firewall konfiguriert
- [ ] Backup-Strategie implementiert
- [ ] Monitoring eingerichtet
- [ ] E-Mail-Versand getestet
- [ ] Frontend gebaut und deployed
- [ ] Alle Funktionen manuell getestet
- [ ] Performance-Tests durchgeführt
- [ ] Dokumentation für Kunde erstellt

---

## Support & Wartung

### Regelmäßige Wartungsaufgaben

**Täglich**:
- Log-Dateien prüfen
- Backups verifizieren

**Wöchentlich**:
- System-Updates prüfen
- Disk Space prüfen

**Monatlich**:
- Dependencies updaten (Composer, npm)
- Security-Audit durchführen
- Performance-Analyse

### Updates einspielen

1. In Staging-Umgebung testen
2. Backup erstellen
3. Maintenance Mode aktivieren
4. Update durchführen
5. Tests ausführen
6. Maintenance Mode deaktivieren

---

## Kontakt & Hilfe

Bei Problemen oder Fragen:
- Dokumentation: Siehe README.md
- Logs prüfen: `storage/logs/laravel.log`
- Laravel Dokumentation: https://laravel.com/docs/11.x
- Vue.js Dokumentation: https://vuejs.org/

---

## Anhang

### Nützliche Artisan-Befehle

```bash
# Cache leeren
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Maintenance Mode
php artisan down
php artisan up

# Queue
php artisan queue:work
php artisan queue:restart
php artisan queue:failed

# Datenbank
php artisan migrate:status
php artisan migrate:rollback
php artisan db:seed

# Tests
php artisan test
```

### Environment-spezifische Konfiguration

Die Anwendung erkennt automatisch die Umgebung via `APP_ENV`:
- `local` - Entwicklung (Docker)
- `production` - Live-Server
- `staging` - Test-Server (optional)
