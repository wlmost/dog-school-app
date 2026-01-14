# Dog School Management - Docker Setup

## Voraussetzungen

- Docker Desktop installiert und läuft
- Docker Compose Version 3.8+
- Git
- Make (optional, aber empfohlen)

## Schnellstart

### 1. Repository klonen
```bash
git clone <repository-url>
cd dog-school-app
```

### 2. Umgebungsvariablen konfigurieren
```bash
cp .env.example .env
```

### 3. Docker Container starten
```bash
# Mit Make (empfohlen)
make install

# Oder manuell
docker-compose build
docker-compose up -d
docker-compose exec php composer install
docker-compose exec php php artisan key:generate
docker-compose exec php php artisan migrate --seed
```

### 4. Anwendung öffnen
- **Backend API**: http://localhost:8080
- **Frontend**: http://localhost:5173
- **Mailpit (E-Mail Testing)**: http://localhost:8025
- **PostgreSQL**: localhost:5432
- **Redis**: localhost:6379

## Docker Services

### PHP-FPM (php)
- PHP 8.3 mit Laravel 11
- Extensions: PostgreSQL, Redis, GD, Zip, Intl, OPcache
- Xdebug für Debugging
- Composer installiert

### Nginx (nginx)
- Nginx 1.25
- Optimiert für Laravel
- Security Headers konfiguriert
- Gzip Compression aktiviert

### PostgreSQL (postgres)
- PostgreSQL 16
- Datenbank: `dog_school`
- Test-Datenbank: `dog_school_test`
- User: `dog_school_user`

### Redis (redis)
- Redis 7
- Für Caching, Sessions und Queues
- AOF Persistence aktiviert

### Node.js (node)
- Node.js 20
- Für Vue 3 Frontend Development
- Vite Dev Server auf Port 5173

### Queue Worker (queue)
- Verarbeitet Laravel Queue Jobs
- Auto-restart bei Fehlern

### Scheduler (scheduler)
- Führt Laravel Scheduled Tasks aus
- Läuft jede Minute

### Mailpit (mailpit)
- E-Mail Testing Tool
- Web UI: http://localhost:8025
- SMTP: localhost:1025

## Häufig verwendete Befehle

### Make Befehle (empfohlen)
```bash
make help              # Alle verfügbaren Befehle anzeigen
make up                # Container starten
make down              # Container stoppen
make restart           # Container neu starten
make logs              # Logs anzeigen
make shell             # Shell im PHP Container öffnen
make composer cmd="install"  # Composer Befehl ausführen
make artisan cmd="migrate"   # Artisan Befehl ausführen
make test              # Tests ausführen
make clean             # Alles aufräumen
```

### Docker Compose Befehle
```bash
# Container verwalten
docker-compose up -d                    # Container im Hintergrund starten
docker-compose down                     # Container stoppen
docker-compose ps                       # Laufende Container anzeigen
docker-compose logs -f [service]        # Logs anzeigen

# In Container ausführen
docker-compose exec php sh              # Shell im PHP Container
docker-compose exec php composer install # Composer install
docker-compose exec php php artisan migrate # Migration ausführen
docker-compose exec postgres psql -U dog_school_user -d dog_school # PostgreSQL CLI
```

### Laravel Artisan Befehle
```bash
# Migrations
docker-compose exec php php artisan migrate
docker-compose exec php php artisan migrate:fresh
docker-compose exec php php artisan migrate:fresh --seed

# Caching
docker-compose exec php php artisan cache:clear
docker-compose exec php php artisan config:clear
docker-compose exec php php artisan route:cache
docker-compose exec php php artisan view:cache

# Testing
docker-compose exec php php artisan test
docker-compose exec php ./vendor/bin/pest

# Queue & Scheduler
docker-compose exec php php artisan queue:work
docker-compose exec php php artisan schedule:run

# Code Generation
docker-compose exec php php artisan make:model ModelName
docker-compose exec php php artisan make:controller ControllerName
docker-compose exec php php artisan make:migration create_table_name
```

## Debugging mit Xdebug

### VS Code Konfiguration
Erstellen Sie `.vscode/launch.json`:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/html": "${workspaceFolder}/backend"
            },
            "log": true
        }
    ]
}
```

### Xdebug aktivieren
Xdebug ist standardmäßig aktiviert. Setzen Sie Breakpoints in VS Code und starten Sie den Debugger.

## Datenbank-Zugriff

### Mit CLI
```bash
# PostgreSQL
docker-compose exec postgres psql -U dog_school_user -d dog_school

# Redis
docker-compose exec redis redis-cli
```

### Mit GUI-Tools
- **Host**: localhost
- **Port**: 5432 (PostgreSQL) / 6379 (Redis)
- **Database**: dog_school
- **User**: dog_school_user
- **Password**: dog_school_password

Empfohlene Tools:
- DBeaver (PostgreSQL)
- TablePlus (PostgreSQL & Redis)
- RedisInsight (Redis)

## Troubleshooting

### Container startet nicht
```bash
# Logs prüfen
docker-compose logs [service-name]

# Container neu bauen
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Permission Errors
```bash
# Berechtigungen im Backend-Ordner setzen
docker-compose exec -u root php chown -R www:www /var/www/html
docker-compose exec php chmod -R 775 storage bootstrap/cache
```

### Datenbank Connection Fehler
```bash
# Prüfen ob PostgreSQL läuft
docker-compose ps postgres

# Health Check
docker-compose exec postgres pg_isready -U dog_school_user

# .env Datei prüfen
DB_HOST=postgres
DB_PORT=5432
```

### Cache-Probleme
```bash
# Alle Caches löschen
make cache-clear

# Oder manuell
docker-compose exec php php artisan cache:clear
docker-compose exec php php artisan config:clear
docker-compose exec php php artisan route:clear
docker-compose exec php php artisan view:clear
```

### Port bereits belegt
Ändern Sie die Ports in `docker-compose.yml`:
```yaml
ports:
  - "8081:80"  # Statt 8080:80
```

## Performance-Optimierung

### Für Development
- OPcache ist aktiviert mit `revalidate_freq=0`
- Xdebug kann deaktiviert werden für bessere Performance

### Für Production
- Xdebug entfernen
- OPcache optimieren
- Assets kompilieren: `npm run build`
- Laravel optimieren: `make optimize`

## Datensicherung

### Datenbank Backup
```bash
# Backup erstellen
docker-compose exec postgres pg_dump -U dog_school_user dog_school > backup.sql

# Backup wiederherstellen
docker-compose exec -T postgres psql -U dog_school_user dog_school < backup.sql
```

### Volume Backup
```bash
# Datenbank Volume
docker run --rm -v dog-school-app_postgres_data:/data -v $(pwd):/backup alpine tar czf /backup/postgres-backup.tar.gz /data

# Redis Volume
docker run --rm -v dog-school-app_redis_data:/data -v $(pwd):/backup alpine tar czf /backup/redis-backup.tar.gz /data
```

## Nützliche Links

- [Laravel Dokumentation](https://laravel.com/docs)
- [Docker Dokumentation](https://docs.docker.com)
- [PostgreSQL Dokumentation](https://www.postgresql.org/docs/)
- [Redis Dokumentation](https://redis.io/documentation)
- [Vue 3 Dokumentation](https://vuejs.org)
