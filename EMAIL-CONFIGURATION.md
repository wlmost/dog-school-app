# E-Mail Konfiguration

## Übersicht

Die Hundeschule-Anwendung unterstützt verschiedene E-Mail-Provider für den Versand von Benachrichtigungen, Rechnungen und Zahlungserinnerungen.

## Entwicklungsumgebung

Standardmäßig wird **Mailpit** verwendet - ein lokaler E-Mail-Testing-Server:

- **Web-Interface**: http://localhost:8025
- Alle E-Mails werden abgefangen und können dort angesehen werden
- Kein echter Versand ins Internet

## Produktions-Konfiguration

### 1. Gmail (Google Mail)

**App-Passwort erstellen:**
1. Google-Konto → Sicherheit
2. "2-Faktor-Authentifizierung" aktivieren
3. "App-Passwörter" erstellen
4. App auswählen: "Mail" / Gerät: "Sonstiges"

**`.env` Konfiguration:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=ihre-email@gmail.com
MAIL_PASSWORD=ihr-app-passwort
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@ihre-domain.de"
MAIL_FROM_NAME="Hundeschule Name"
```

### 2. Microsoft Office365 / Outlook

**`.env` Konfiguration:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=ihre-email@outlook.com
MAIL_PASSWORD=ihr-passwort
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="ihre-email@outlook.com"
MAIL_FROM_NAME="Hundeschule Name"
```

**Hinweis**: Die FROM-Adresse muss mit dem Office365-Konto übereinstimmen.

### 3. Webhoster (z.B. Strato, 1&1, all-inkl.com)

**`.env` Konfiguration:**
```env
MAIL_MAILER=smtp
MAIL_HOST=mail.ihre-domain.de
MAIL_PORT=587
MAIL_USERNAME=noreply@ihre-domain.de
MAIL_PASSWORD=ihr-passwort
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@ihre-domain.de"
MAIL_FROM_NAME="Hundeschule Name"
```

**Wichtig:**
- Host, Port und Verschlüsselung beim Webhoster erfragen
- Manche Webhoster benötigen Port 465 mit SSL statt TLS

### 4. Mailgun (Professioneller E-Mail-Service)

**Vorteile:**
- Hohe Zustellrate
- Detaillierte Statistiken
- Webhook-Support für Bounce-Handling
- 5.000 E-Mails/Monat kostenlos (EU-Region)

**`.env` Konfiguration:**
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=ihre-domain.de
MAILGUN_SECRET=key-xxxxxxxxxxxxx
MAILGUN_ENDPOINT=api.eu.mailgun.net
MAIL_FROM_ADDRESS="noreply@ihre-domain.de"
MAIL_FROM_NAME="Hundeschule Name"
```

**Setup:**
1. Account erstellen: https://www.mailgun.com/
2. Domain verifizieren (DNS-Einträge)
3. API-Key aus Dashboard kopieren

### 5. Amazon SES (AWS Simple Email Service)

**Vorteile:**
- Extrem günstig (€0,10 pro 1.000 E-Mails)
- Hohe Skalierbarkeit
- AWS-Integration

**`.env` Konfiguration:**
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=AKIAXXXXXXXX
AWS_SECRET_ACCESS_KEY=xxxxxxxxxxxxxxx
AWS_DEFAULT_REGION=eu-central-1
MAIL_FROM_ADDRESS="noreply@ihre-domain.de"
MAIL_FROM_NAME="Hundeschule Name"
```

**Setup:**
1. AWS-Account erstellen
2. SES aktivieren (zunächst Sandbox-Modus)
3. E-Mail-Adressen/Domains verifizieren
4. IAM-Benutzer mit SES-Rechten erstellen

## E-Mail Queue

Alle E-Mails werden über die **Queue** (Redis) versendet:

```bash
# Queue Worker starten (bereits in docker-compose.yml)
docker-compose exec php php artisan queue:work

# Queue Status prüfen
docker-compose exec php php artisan queue:monitor

# Fehlgeschlagene Jobs erneut versuchen
docker-compose exec php php artisan queue:retry all
```

## E-Mail Templates

Templates befinden sich in `backend/resources/views/emails/`:
- `booking-confirmation.blade.php` - Buchungsbestätigungen
- `invoice-created.blade.php` - Neue Rechnungen
- `payment-reminder.blade.php` - Zahlungserinnerungen

## Automatische Zahlungserinnerungen

```bash
# Manuell ausführen
docker-compose exec php php artisan invoices:send-reminders

# Mit Options
docker-compose exec php php artisan invoices:send-reminders --days=14 --dry-run

# Cronjob (automatisch täglich)
# Bereits in docker-compose.yml als Scheduler konfiguriert
```

In `backend/app/Console/Kernel.php`:
```php
$schedule->command('invoices:send-reminders --days=7')
    ->dailyAt('09:00');
```

## Testen

```bash
# E-Mail Test senden
docker-compose exec php php artisan tinker
>>> Mail::to('test@example.com')->send(new \App\Mail\TestMail());
```

## Troubleshooting

### E-Mails werden nicht versendet

1. **Queue läuft nicht:**
   ```bash
   docker-compose ps queue
   docker-compose logs queue
   ```

2. **SMTP-Verbindung prüfen:**
   ```bash
   docker-compose exec php php artisan tinker
   >>> config('mail')
   ```

3. **Queue Failed Jobs:**
   ```bash
   docker-compose exec php php artisan queue:failed
   ```

### Gmail: "Less Secure Apps" Fehler

- **Lösung**: App-Passwort verwenden (siehe oben)
- **Nicht empfohlen**: "Unsichere Apps zulassen" aktivieren

### Webhoster: Connection Timeout

- Port 587 → 465 ändern
- `MAIL_ENCRYPTION=tls` → `ssl`
- Firewall-Regeln prüfen

### SPF/DKIM/DMARC einrichten

Für bessere Zustellraten DNS-Records konfigurieren:

**SPF Record** (TXT):
```
v=spf1 include:_spf.google.com include:mailgun.org ~all
```

**DMARC Record** (TXT):
```
v=DMARC1; p=none; rua=mailto:dmarc@ihre-domain.de
```

Bei Mailgun/SES werden DKIM-Keys automatisch bereitgestellt.

## E-Mail Empfang (IMAP) - Optional

Für Kunden-Antworten kann ein IMAP-Paket installiert werden:

```bash
composer require webklex/php-imap
```

Konfiguration in `.env`:
```env
IMAP_HOST=imap.gmail.com
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_USERNAME=ihre-email@gmail.com
IMAP_PASSWORD=ihr-app-passwort
IMAP_VALIDATE_CERT=true
```

**Verwendung:**
```php
use Webklex\IMAP\Facades\Client;

$client = Client::account('default');
$client->connect();

$inbox = $client->getFolder('INBOX');
$messages = $inbox->messages()->unseen()->get();
```

## Empfohlene Konfiguration nach Größe

### Kleine Hundeschule (<100 E-Mails/Monat)
→ **Gmail** oder **Webhoster SMTP**

### Mittelgroße Hundeschule (100-1000 E-Mails/Monat)  
→ **Mailgun** (kostenlos bis 5.000/Monat)

### Große Hundeschule (>1000 E-Mails/Monat)
→ **Amazon SES** (günstigste Option)

## Support

Bei Fragen zur E-Mail-Konfiguration:
1. Logs prüfen: `docker-compose logs php queue`
2. Mailpit Interface: http://localhost:8025
3. Laravel Log: `backend/storage/logs/laravel.log`
