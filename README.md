# Dog School Management Software

Eine moderne, umfassende Verwaltungssoftware für Hundeschulen mit Web-App und mobiler Unterstützung.

## 🎯 Projektziel

Diese Software bietet Hundeschulen eine vollständige digitale Lösung für:
- **Kunden- & Hundeverwaltung (CRM)** - Zentrale Verwaltung aller Kundendaten und Hundeprofile
- **Terminplanung & Buchungssystem** - Online-Buchung, Kalender-Synchronisation, Mehrfachkarten-Verwaltung
- **Anamnese-Management** - Dynamische, anpassbare Anamnesebögen mit ABC-Verhaltensanalyse
- **Fortschritts-Dokumentation** - Trainingsnotizen, Fotos, Videos und Entwicklungsverlauf
- **Abrechnung & Zahlungen** - Automatisierte Rechnungsstellung mit Stripe/PayPal Integration

## 🚀 Tech-Stack

### Backend
- **Laravel 11** (PHP 8.4)
- **PostgreSQL 16** - Relationale Datenbank
- **Redis 7** - Caching, Sessions & Queue-System
- **Laravel Sanctum** - API-Authentifizierung
- **Pest** - Testing Framework

### Frontend
- **Vue 3** mit TypeScript & Composition API
- **Vite** - Build-Tool
- **Pinia** - State Management
- **TailwindCSS** - Styling
- **VeeValidate** - Formular-Validierung

### DevOps & Infrastructure
- **Docker & Docker Compose** - Containerisierung
- **Nginx 1.25** - Webserver
- **Node.js 20** - Frontend Development
- **Mailpit** - E-Mail Testing

## 📊 Implementierungsstatus

### ✅ Abgeschlossen

#### 1. Docker-Infrastruktur (Commit: `6d79f29`)
- [x] Multi-Service Docker Compose Setup
  - PHP 8.4-FPM mit allen Extensions (PostgreSQL, Redis, GD, Xdebug)
  - Nginx mit Laravel-Optimierung
  - PostgreSQL 16 mit Test-Datenbank
  - Redis für Caching & Queues
  - Node.js 20 für Frontend-Development
  - Queue Worker & Scheduler Container
  - Mailpit für E-Mail Testing
- [x] PHP-Konfiguration
  - Custom php.ini mit optimierten Einstellungen
  - Xdebug für Development-Debugging
  - OPcache für Performance
  - Redis-Session-Storage
- [x] Makefile mit Entwickler-Tools
- [x] Umfassende Docker-Dokumentation

#### 2. Laravel-Projekt Initialisierung (Commit: `6d79f29`)
- [x] Laravel 11 Installation
- [x] Environment-Konfiguration für Docker
- [x] Laravel Sanctum Installation
- [x] Pest Testing Framework Setup
- [x] Initiale Migrations ausgeführt

#### 3. Datenbank-Schema & Migrationen (Commit: `bf90f00`)
- [x] **18 vollständige Datenbank-Migrationen**
  - Users (erweitert mit role, Namen, Telefon, Soft Deletes)
  - Customers (Adresse, Notfallkontakt)
  - Dogs (vollständige Hundeinformationen, Soft Deletes)
  - Vaccinations (Impfaufzeichnungen)
  - Courses (Kursverwaltung)
  - Training_Sessions (Einzeltermine)
  - Bookings (Buchungen mit Anwesenheit)
  - Credit_Packages (Mehrfachkarten)
  - Customer_Credits (Guthaben-Verwaltung)
  - Anamnesis_Templates (Vorlagen)
  - Anamnesis_Questions (dynamische Fragen)
  - Anamnesis_Responses (Antworten pro Hund)
  - Anamnesis_Answers (einzelne Antworten)
  - Training_Logs (Fortschrittsdokumentation)
  - Training_Attachments (Medien-Dateien)
  - Invoices (Rechnungen)
  - Invoice_Items (Rechnungspositionen)
  - Payments (Zahlungsverwaltung)
- [x] **Test-Suite für Datenbankstruktur**
  - 18 Tests mit 36 Assertions
  - Alle Tests erfolgreich
- [x] **Best Practices**
  - Foreign Key Constraints mit Cascade
  - Indizes für Performance
  - ENUM für Status-Felder
  - JSON für flexible Daten
  - Strict Type Declarations (PHP 8.4)

### 🔄 In Arbeit

_Aktuell keine Tasks in Bearbeitung_

### 📋 Geplant

#### 4. Authentication & Authorization
- [ ] Laravel Sanctum Konfiguration
  - API-Token-Authentifizierung
  - Cookie-basierte SPA-Authentifizierung
  - Token-Refresh-Mechanismus
- [ ] User Management
  - Registrierung (nur Admin)
  - Login/Logout
  - Passwort-Reset
  - E-Mail-Verifizierung
- [ ] Role-Based Access Control (RBAC)
  - Admin-Policy
  - Trainer-Policy
  - Customer-Policy
- [ ] Tests
  - Authentication Flow Tests
  - Authorization Tests
  - Role & Permission Tests

#### 5. Models & Relationships
- [ ] Eloquent Models erstellen
  - User, Customer, Dog, Vaccination
  - Course, TrainingSession, Booking
  - CreditPackage, CustomerCredit
  - AnamnesisTemplate, AnamnesisQuestion, AnamnesisResponse, AnamnesisAnswer
  - TrainingLog, TrainingAttachment
  - Invoice, InvoiceItem, Payment
- [ ] Model Relationships definieren
  - hasMany, belongsTo, belongsToMany
  - Polymorphic Relations wo sinnvoll
- [ ] Accessors, Mutators & Casts
- [ ] Query Scopes
- [ ] Model Events & Observers
- [ ] Factory & Seeder
- [ ] Model Tests

#### 6. API-Endpunkte (RESTful)
- [ ] **Authentication API**
  - POST /api/v1/auth/register
  - POST /api/v1/auth/login
  - POST /api/v1/auth/logout
  - POST /api/v1/auth/refresh
  - POST /api/v1/auth/password/reset
- [ ] **Customer Management API**
  - CRUD für Customers
  - Customer Dogs
  - Customer Bookings
  - Customer Invoices
  - Customer Credits
- [ ] **Dog Management API**
  - CRUD für Dogs
  - Dog Anamnesis
  - Dog Training Logs
  - Dog Vaccinations
- [ ] **Session & Booking API**
  - Verfügbare Sessions
  - Session-Buchung
  - Buchungs-Stornierung
  - Wartelisten-Management
- [ ] **Course Management API**
  - CRUD für Courses
  - Course Sessions
  - Course Participants
- [ ] **Anamnesis API**
  - Template-Management
  - Response-Management
  - Dynamic Form Generation
- [ ] **Training Log API**
  - Log-Erstellung
  - Attachment-Upload
  - Progress-Tracking
- [ ] **Invoice & Payment API**
  - Rechnungserstellung
  - PDF-Generierung
  - Payment Processing (Stripe/PayPal)
- [ ] **API Resources** für konsistente JSON-Responses
- [ ] **Form Requests** für Validierung
- [ ] **API Tests** für alle Endpunkte

#### 7. Frontend (Vue 3 + TypeScript)
- [ ] Vite-Projekt Setup
- [ ] Pinia Store-Konfiguration
- [ ] Vue Router Setup
- [ ] TailwindCSS Integration
- [ ] Komponenten-Bibliothek
  - Layout-Komponenten
  - Form-Komponenten
  - Table-Komponenten
  - Modal-Komponenten
- [ ] **Views/Pages**
  - Login/Register
  - Dashboard
  - Customer Management
  - Dog Management
  - Session Calendar
  - Anamnesis Forms
  - Training Logs
  - Invoice Management
- [ ] **Composables**
  - useAuth
  - useFetch
  - useForm
  - useNotification
- [ ] API-Client mit Axios
- [ ] Frontend Tests (Vitest + Cypress)

#### 8. Features & Integration
- [ ] **Kalender-Integration**
  - Google Calendar Sync
  - Outlook Sync
  - iCal Export
- [ ] **Zahlungs-Integration**
  - Stripe Setup
  - PayPal Setup
  - Webhook-Handling
- [ ] **E-Mail-System**
  - Buchungsbestätigungen
  - Zahlungserinnerungen
  - Newsletter
- [ ] **Datei-Upload & Storage**
  - Lokaler Storage (Development)
  - S3 Storage (Production)
  - Image-Optimierung
- [ ] **PDF-Generierung**
  - Rechnungen
  - Anamnese-Reports
  - Trainingspläne
- [ ] **Reporting & Analytics**
  - Umsatz-Reports
  - Auslastungs-Reports
  - Kunden-Statistiken

#### 9. Mobile App (Optional)
- [ ] Capacitor Integration
- [ ] Native Features
  - Kamera für Fotos/Videos
  - Push-Notifications
  - Offline-Modus
- [ ] Mobile UI/UX Optimierung

#### 10. Testing & Quality Assurance
- [ ] Unit Tests (Backend)
- [ ] Feature Tests (Backend)
- [ ] Browser Tests (Laravel Dusk)
- [ ] Component Tests (Frontend)
- [ ] E2E Tests (Cypress)
- [ ] Performance Tests
- [ ] Security Audit

#### 11. Deployment & DevOps
- [ ] CI/CD Pipeline (GitHub Actions)
- [ ] Staging Environment
- [ ] Production Environment
- [ ] Backup-Strategie
- [ ] Monitoring (Laravel Telescope/Horizon)
- [ ] Error Tracking (Sentry)
- [ ] Performance Monitoring

#### 12. Dokumentation
- [ ] API-Dokumentation (OpenAPI/Swagger)
- [ ] Benutzer-Handbuch
- [ ] Admin-Dokumentation
- [ ] Entwickler-Dokumentation
- [ ] Deployment-Guide

## 🏃‍♂️ Schnellstart

### Voraussetzungen
- Docker Desktop
- Git
- Make (optional, aber empfohlen)

### Installation

```bash
# Repository klonen
git clone <repository-url>
cd dog-school-app

# Environment-Datei kopieren
cp .env.example .env

# Docker Container bauen und starten
make install

# Oder manuell:
docker-compose build
docker-compose up -d
docker-compose exec php composer install
docker-compose exec php php artisan key:generate
docker-compose exec php php artisan migrate --seed
```

### Zugriff

- **Backend API**: http://localhost:8081
- **Frontend Dev**: http://localhost:5173
- **Mailpit UI**: http://localhost:8025
- **PostgreSQL**: localhost:5432
- **Redis**: localhost:6379

### Wichtige Befehle

```bash
# Container starten
make up

# Container stoppen
make down

# Shell im PHP Container
make shell

# Migrations ausführen
make migrate

# Tests ausführen
make test

# Logs anzeigen
make logs
```

Siehe [README-Docker.md](README-Docker.md) für detaillierte Docker-Dokumentation.

## 📁 Projektstruktur

```
dog-school-app/
├── backend/                 # Laravel 11 Backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Models/
│   │   └── Policies/
│   ├── database/
│   │   ├── migrations/     # 18 Datenbank-Migrationen
│   │   ├── factories/
│   │   └── seeders/
│   ├── routes/
│   │   ├── api.php
│   │   └── web.php
│   └── tests/
│       ├── Feature/
│       └── Unit/
├── frontend/               # Vue 3 Frontend (geplant)
│   ├── src/
│   │   ├── components/
│   │   ├── composables/
│   │   ├── views/
│   │   ├── stores/
│   │   └── router/
│   └── tests/
├── docker/                 # Docker-Konfiguration
│   ├── nginx/
│   ├── php/
│   └── postgres/
├── docker-compose.yml
├── Makefile
└── README.md
```

## 🔒 Security & DSGVO

- HTTPS-Verschlüsselung (Production)
- CSRF-Protection (Laravel)
- SQL-Injection-Prevention (Eloquent ORM)
- XSS-Protection (Blade Templates)
- Rate Limiting
- Daten-Löschung auf Anfrage
- Export-Funktionalität für personenbezogene Daten
- Rollenbasierte Zugriffskontrolle

## 🤝 Entwicklungs-Standards

- **PSR-12** Coding Style
- **PSR-4** Autoloading
- **Strict Types** (PHP 8.4)
- **Test-Driven Development** (TDD)
- **Clean Code** Prinzipien
- **SOLID** Prinzipien
- **Laravel Best Practices**
- **Vue 3 Best Practices**

## 📝 Lizenz

[Lizenz-Information hier einfügen]

## 👨‍💻 Entwickler

[Entwickler-Information hier einfügen]

## 📞 Support

Bei Fragen oder Problemen bitte ein Issue erstellen.
