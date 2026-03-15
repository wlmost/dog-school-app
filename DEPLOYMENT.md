# Production Deployment Guide

Dieser Guide beschreibt das Deployment der Hundeschule-Verwaltungsanwendung auf einem Webhoster mit PHP 8.4 und MySQL.

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
