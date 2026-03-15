# Production Setup für Shared Hosting

## Ziel-Konfiguration

- **URL**: https://www.leisoft.de/dog-school
- **Verzeichnis-Struktur**: 
  ```
  dog-school/
  ├── backend/
  └── frontend/
  ```
- **Datenbank**: MySQL (gemeinsame DB mit Prefix `ds_`)
- **PHP**: 8.4
- **Webserver**: Apache

---

## 1. Verzeichnis-Struktur vorbereiten

Laden Sie die Dateien wie folgt hoch:
```
/webroot/dog-school/
├── backend/          ← Laravel-Backend
│   ├── app/
│   ├── public/      ← API-Endpunkt
│   └── ...
└── frontend/        ← Vue.js-Frontend
    └── dist/        ← Gebaut mit npm run build
```

---

## 2. Backend-Konfiguration

### 2.1 Environment-Datei erstellen

```bash
cd backend/
cp .env.production .env
```

Bearbeiten Sie `.env`:
```env
APP_URL=https://www.leisoft.de/dog-school
FRONTEND_URL=https://www.leisoft.de/dog-school

# MySQL mit Tabellen-Prefix
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=ihre_datenbank
DB_USERNAME=ihr_user
DB_PASSWORD=ihr_passwort
DB_PREFIX=ds_

# E-Mail
MAIL_FROM_ADDRESS=info@leisoft.de
MAIL_HOST=smtp.ihrer-provider.de
MAIL_PORT=587
MAIL_USERNAME=ihre-email@leisoft.de
MAIL_PASSWORD=ihr-smtp-passwort
```

### 2.2 Installation

```bash
# App Key generieren
php artisan key:generate

# Dependencies installieren
composer install --no-dev --optimize-autoloader

# Datenbank-Tabellen erstellen (mit ds_ Prefix)
php artisan migrate --force

# Storage-Link
php artisan storage:link

# Caches erstellen
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Berechtigungen
chmod -R 755 storage bootstrap/cache
```

---

## 3. Frontend-Konfiguration

### 3.1 API-URL anpassen

Bearbeiten Sie `frontend/src/api/client.ts`:
```typescript
const apiClient = axios.create({
  baseURL: 'https://www.leisoft.de/dog-school/api',
  // ... rest
})
```

### 3.2 Build erstellen

```bash
cd frontend/
npm ci
npm run build
```

Die Dateien landen in `frontend/dist/`

---

## 4. Webserver-Konfiguration

### Option A: .htaccess im Hauptverzeichnis

Erstellen Sie `/webroot/dog-school/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /dog-school/
    
    # API-Requests zum Backend
    RewriteRule ^api/(.*)$ backend/public/index.php [L,QSA]
    
    # Storage-Dateien
    RewriteRule ^storage/(.*)$ backend/public/storage/$1 [L]
    
    # Frontend SPA
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ frontend/dist/index.html [L]
</IfModule>
```

### Option B: Apache Virtual Host

Falls Sie Zugriff haben:
```apache
Alias /dog-school/api /webroot/dog-school/backend/public
Alias /dog-school /webroot/dog-school/frontend/dist

<Directory /webroot/dog-school/backend/public>
    AllowOverride All
    Require all granted
</Directory>

<Directory /webroot/dog-school/frontend/dist>
    AllowOverride All
    Require all granted
    FallbackResource /dog-school/index.html
</Directory>
```

---

## 5. Admin-User erstellen

```bash
cd backend/
php artisan tinker
```

```php
$user = new App\Models\User();
$user->first_name = 'Admin';
$user->last_name = 'User';
$user->email = 'admin@leisoft.de';
$user->password = bcrypt('SicheresPasswort123!');
$user->role = 'admin';
$user->save();
```

---

## 6. Testen

- **Frontend**: https://www.leisoft.de/dog-school
- **API Health**: https://www.leisoft.de/dog-school/api/v1/health
- **Login**: admin@leisoft.de / SicheresPasswort123!

---

## Deployment-Script

Nutzen Sie `deploy.sh` für Updates:

1. Pfad anpassen:
   ```bash
   DEPLOY_PATH="/webroot/dog-school"
   ```

2. Ausführbar machen:
   ```bash
   chmod +x deploy.sh
   ```

3. Ausführen:
   ```bash
   ./deploy.sh
   ```

---

## Wichtige Hinweise

### Datenbank-Tabellen mit Prefix
Alle Tabellen haben den Prefix `ds_`:
- `ds_users`
- `ds_customers`
- `ds_dogs`
- `ds_courses`
- `ds_bookings`
- etc.

### Cron Job (empfohlen)
```cron
* * * * * cd /webroot/dog-school/backend && php artisan schedule:run >> /dev/null 2>&1
```

### E-Mail testen
```bash
php artisan email:test admin@leisoft.de
```

---

## Troubleshooting

**500 Error**: Prüfen Sie `storage/logs/laravel.log`

**API nicht erreichbar**: Stellen Sie sicher, dass mod_rewrite aktiviert ist

**Frontend weiße Seite**: Öffnen Sie Browser-Konsole (F12) und prüfen Sie Fehler

**DB-Verbindung fehlgeschlagen**: Prüfen Sie `.env` Credentials und `DB_PREFIX=ds_`
