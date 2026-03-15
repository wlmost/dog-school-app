# E-Mail System - Verwendung & Testing

## Übersicht

Das E-Mail-System ist vollständig event-basiert und sendet automatisch E-Mails bei bestimmten Aktionen.

## Automatischer E-Mail-Versand

### 1. Buchungsbestätigung

**Trigger:** Wenn eine Buchung erstellt oder bestätigt wird

**Events:**
```php
BookingCreated::dispatch($booking);
```

**Controller-Methoden:**
- `POST /api/v1/bookings` (BookingController@store)
- `POST /api/v1/bookings/{id}/confirm` (BookingController@confirm)

**E-Mail-Template:** `booking-confirmation.blade.php`

**Empfänger:** Kunde (Besitzer des gebuchten Hundes)

---

### 2. Rechnung erstellt

**Trigger:** Wenn eine neue Rechnung erstellt wird

**Events:**
```php
InvoiceWasCreated::dispatch($invoice);
```

**Controller-Methoden:**
- `POST /api/v1/invoices` (InvoiceController@store)

**E-Mail-Template:** `invoice-created.blade.php`

**Empfänger:** Kunde der Rechnung

---

### 3. Willkommens-E-Mail

**Trigger:** Wenn ein neuer Benutzer registriert wird

**Events:**
```php
UserRegistered::dispatch($user, $temporaryPassword);
```

**Controller-Methoden:**
- `POST /api/v1/auth/register` (AuthController@register)
- `POST /api/v1/trainers` (TrainerController@store)

**E-Mail-Template:** `welcome.blade.php`

**Empfänger:** Neu registrierter Benutzer

**Besonderheit:** 
- Passwort wird automatisch generiert wenn nicht angegeben
- E-Mail enthält Zugangsdaten (E-Mail + temporäres Passwort)

---

## Queue-System

### Services

Alle E-Mails werden über die Queue versendet:

```yaml
# Queue Worker (docker-compose.yml)
queue:
  command: php artisan queue:work --sleep=3 --tries=3 --timeout=90
  restart: unless-stopped
```

**Features:**
- ✅ Asynchroner E-Mail-Versand (blockiert API-Requests nicht)
- ✅ Automatische Wiederholung bei Fehlern (3 Versuche)
- ✅ Failed Jobs Logging
- ✅ Queue Monitoring

### Queue Commands

```bash
# Queue Status prüfen
docker-compose exec php php artisan queue:monitor

# Failed Jobs anzeigen
docker-compose exec php php artisan queue:failed

# Failed Jobs erneut versuchen
docker-compose exec php php artisan queue:retry all

# Alle Failed Jobs löschen
docker-compose exec php php artisan queue:flush
```

---

## Testing

### 1. Event-Integration testen

```bash
docker-compose exec php php artisan test:events
```

**Ausgabe:**
```
✓ BookingCreated event loaded
✓ InvoiceWasCreated event loaded
✓ UserRegistered event loaded
✓ SendBookingConfirmationEmail listener loaded
✓ SendInvoiceCreatedEmail listener loaded
✓ SendWelcomeEmail listener loaded
✓ BookingCreated has 2 listener(s)
✓ InvoiceWasCreated has 2 listener(s)
✓ UserRegistered has 2 listener(s)
```

---

### 2. Test-E-Mails versenden

```bash
# Einzelne E-Mail-Typen testen
docker-compose exec php php artisan email:test test@example.com --type=welcome
docker-compose exec php php artisan email:test test@example.com --type=booking
docker-compose exec php php artisan email:test test@example.com --type=invoice
docker-compose exec php php artisan email:test test@example.com --type=reminder

# Alle E-Mail-Typen
docker-compose exec php php artisan email:test test@example.com --type=all
```

**Hinweis:** Booking/Invoice E-Mails benötigen vorhandene Daten in der DB.

---

### 3. E-Mails in Mailpit ansehen

**Web-Interface:** http://localhost:8025

**Features:**
- Alle gesendeten E-Mails
- HTML-Vorschau
- Header-Informationen
- Attachments
- Search & Filter

---

### 4. Live-Test via API

#### Benutzer registrieren

```bash
POST http://localhost:8081/api/v1/auth/register
Content-Type: application/json
Authorization: Bearer {admin-token}

{
  "email": "neuer.kunde@test.de",
  "firstName": "Max",
  "lastName": "Mustermann",
  "role": "customer"
}
```

**Ergebnis:**
- ✅ Benutzer wird erstellt
- ✅ Willkommens-E-Mail wird versendet
- ✅ E-Mail enthält temporäres Passwort

**Mailpit prüfen:** http://localhost:8025

---

#### Buchung erstellen

```bash
POST http://localhost:8081/api/v1/bookings
Content-Type: application/json
Authorization: Bearer {token}

{
  "customerId": 1,
  "dogId": 1,
  "trainingSessionId": 1,
  "status": "confirmed"
}
```

**Ergebnis:**
- ✅ Buchung wird erstellt
- ✅ Buchungsbestätigung wird versendet
- ✅ E-Mail enthält alle Buchungsdetails

---

#### Rechnung erstellen

```bash
POST http://localhost:8081/api/v1/invoices
Content-Type: application/json
Authorization: Bearer {admin-token}

{
  "customerId": 1,
  "invoiceNumber": "RE-2026-001",
  "issueDate": "2026-01-24",
  "dueDate": "2026-02-24",
  "status": "pending",
  "items": [
    {
      "description": "Welpentraining",
      "quantity": 10,
      "unitPrice": 15.00,
      "taxRate": 19
    }
  ]
}
```

**Ergebnis:**
- ✅ Rechnung wird erstellt
- ✅ Rechnungs-E-Mail wird versendet
- ✅ E-Mail enthält Rechnungsdetails

---

## Schedule-Tasks

Automatische Zahlungserinnerungen:

```bash
# Alle geplanten Tasks anzeigen
docker-compose exec php php artisan schedule:list

# Manuelle Ausführung (Testing)
docker-compose exec php php artisan schedule:run

# Zahlungserinnerungen manuell senden
docker-compose exec php php artisan invoices:send-reminders --dry-run
docker-compose exec php php artisan invoices:send-reminders
```

**Automatische Ausführung:**
- 09:00 Uhr: Zahlungserinnerungen (7+ Tage überfällig)
- 09:15 Uhr: Zahlungserinnerungen (14+ Tage überfällig, nur Werktags)
- 03:00 Uhr: Failed Jobs bereinigen (30+ Tage alt)

---

## Troubleshooting

### E-Mails werden nicht versendet

**1. Queue Worker läuft nicht:**
```bash
docker-compose ps queue
docker-compose logs queue
```

**Lösung:**
```bash
docker-compose restart queue
```

---

**2. Failed Jobs:**
```bash
docker-compose exec php php artisan queue:failed
```

**Lösung:**
```bash
# Einzelnen Job erneut versuchen
docker-compose exec php php artisan queue:retry {id}

# Alle Failed Jobs erneut versuchen
docker-compose exec php php artisan queue:retry all
```

---

**3. Mailpit läuft nicht:**
```bash
docker-compose ps mailpit
```

**Lösung:**
```bash
docker-compose restart mailpit
```

---

**4. Events werden nicht gefeuert:**
```bash
docker-compose exec php php artisan test:events
```

**Lösung:**
- Prüfen ob Events importiert sind
- Cache leeren: `php artisan config:clear`
- Application Cache leeren: `php artisan cache:clear`

---

## Produktions-Setup

### E-Mail-Provider ändern

**Datei:** `backend/.env`

#### Gmail
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

#### Mailgun
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=ihre-domain.de
MAILGUN_SECRET=key-xxxxxxxxxxxxx
MAILGUN_ENDPOINT=api.eu.mailgun.net
MAIL_FROM_ADDRESS="noreply@ihre-domain.de"
MAIL_FROM_NAME="Hundeschule Name"
```

Mehr Details: [EMAIL-CONFIGURATION.md](EMAIL-CONFIGURATION.md)

---

## Architektur

### Event-Flow

```
Controller Action
    ↓
Event::dispatch($model)
    ↓
Listener (ShouldQueue)
    ↓
Queue Job
    ↓
Queue Worker
    ↓
Mail::send()
    ↓
SMTP/Mailgun/etc.
```

### Vorteile

✅ **Lose Kopplung**
- Controller weiß nichts über E-Mails
- Einfach erweiterbar (weitere Listener hinzufügen)

✅ **Asynchron**
- API-Requests werden nicht blockiert
- Bessere Performance

✅ **Fehlerbehandlung**
- Automatische Wiederholung bei Fehlern
- Failed Jobs Logging
- Monitoring möglich

✅ **Testbarkeit**
- Events können gemockt werden
- Unittests ohne echte E-Mails

✅ **Skalierbarkeit**
- Mehrere Queue Worker möglich
- Redis/SQS für große Lasten

---

## Weitere Informationen

- **Dokumentation:** [SESSION-2026-01-24.md](SESSION-2026-01-24.md)
- **E-Mail Konfiguration:** [EMAIL-CONFIGURATION.md](EMAIL-CONFIGURATION.md)
- **Laravel Events:** https://laravel.com/docs/11.x/events
- **Laravel Queues:** https://laravel.com/docs/11.x/queues
- **Laravel Mail:** https://laravel.com/docs/11.x/mail
