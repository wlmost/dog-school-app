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

#### 4. Authentication & Authorization (Commit: TBD)
- [x] **Laravel Sanctum Konfiguration**
  - API-Token-Authentifizierung
  - SPA-Authentifizierung
  - routes/api.php mit v1 Prefix
- [x] **User Management**
  - Registrierung (nur Admin) - AuthController@register
  - Login/Logout - AuthController@login, AuthController@logout
  - Profil-Verwaltung - AuthController@user
  - UserFactory erweitert mit role States
- [x] **Role-Based Access Control (RBAC)**
  - UserPolicy mit Admin/Trainer/Customer Policies
  - Gates für role-basierte Checks (admin, trainer, customer)
  - Helper-Methoden: isAdmin(), isTrainer(), isCustomer()
  - full_name Accessor für User Model
- [x] **Form Request Validation**
  - LoginRequest - E-Mail & Passwort
  - RegisterRequest - Komplett mit Rollen-Validierung
  - UpdateProfileRequest - Profil-Updates
- [x] **Test-Suite für Authentication & Authorization**
  - 11 Authentication Tests (Login, Register, Logout, Profil)
  - 29 Authorization Tests (Gates, Policies, Rollen)
  - 60 Tests gesamt mit 114 Assertions
  - Alle Tests erfolgreich

#### 5. Models & Relationships (Commit: TBD)
- [x] **17 Eloquent Models erstellt**
  - Customer (mit full_address Accessor, scopeWithActiveCredits)
  - Dog (mit age Accessor, SoftDeletes, scopeActive, scopePuppies)
  - Vaccination (mit isDue, scopeOverdue, scopeDueSoon)
  - Course (mit isActive, isFull, scopeActive, scopeOfType)
  - TrainingSession (mit isPast, isFull, available_spots, scopeUpcoming, scopePast)
  - Booking (mit isConfirmed, isCancelled, scopeConfirmed, scopeAttended)
  - CreditPackage
  - CustomerCredit (mit useCredit, isActive, isExpired, scopeActive, scopeExpired)
  - Invoice (mit isPaid, isOverdue, total_paid, remaining_balance, scopeUnpaid, scopeOverdue)
  - InvoiceItem
  - Payment (mit isCompleted, isPending, isFailed, scopeCompleted)
  - AnamnesisTemplate (mit scopeActive)
  - AnamnesisQuestion
  - AnamnesisResponse (mit isCompleted, scopeCompleted)
  - AnamnesisAnswer
  - TrainingLog
  - TrainingAttachment
- [x] **Model Relationships definiert**
  - hasMany, belongsTo, hasOne Beziehungen
  - Customer ↔ User, Dogs, Bookings, Credits, Invoices
  - Dog ↔ Customer, Vaccinations, Bookings, Responses, Logs
  - Course ↔ Trainer, Sessions
  - TrainingSession ↔ Course, Trainer, Bookings, Logs
  - Invoice ↔ Customer, Items, Payments
  - Anamnesis: Templates ↔ Questions ↔ Responses ↔ Answers
- [x] **Accessors, Mutators & Casts**
  - full_address (Customer), age (Dog), available_spots (TrainingSession)
  - total_paid, remaining_balance (Invoice)
  - Date Casts für alle Datums-Felder
  - Boolean Casts, Decimal Casts
- [x] **Query Scopes**
  - 20+ Scopes für häufige Queries (active, upcoming, past, overdue, etc.)
  - Type-Safe Scopes mit Builder Type Hints
- [x] **Business Logic Methoden**
  - isActive, isFull, isPast, isExpired, etc.
  - useCredit mit automatischer Status-Aktualisierung
  - isDue für Impfungen mit 30-Tage-Logik
- [x] **11 Model Factories**
  - Mit States für verschiedene Szenarien (upcoming, past, overdue, etc.)
  - Schema-aligned mit Migrationen
  - CustomerFactory, DogFactory, VaccinationFactory, CourseFactory
  - TrainingSessionFactory, BookingFactory, CreditPackageFactory
  - CustomerCreditFactory, InvoiceFactory, InvoiceItemFactory, PaymentFactory
- [x] **Test-Suite für Models**
  - 15 Relationship Tests
  - 14 Scope Tests
  - 19 Business Logic Tests
  - 48 Model-Tests + 60 Auth-Tests = 108 Tests gesamt
  - Alle Tests erfolgreich (177 Assertions)

### 🔄 In Arbeit

_Aktuell keine Tasks in Bearbeitung_

**Nächste geplante Schritte:**
1. Anamnese PDF Template erstellen
2. File Upload System für Training Attachments implementieren
3. Frontend Vue 3 Projekt aufsetzen

### ✅ Abgeschlossen (Fortsetzung)

#### 6. API Controllers & Endpoints (Commit: TBD)
- [x] **11 REST API Controllers implementiert**
  - AuthController (Login, Register, Logout, Password Reset)
  - CustomerController (CRUD + dogs, bookings, invoices, credits)
  - DogController (CRUD + vaccinations, trainingLogs, bookings)
  - BookingController (CRUD + cancel, confirm)
  - CourseController (CRUD + sessions, participants)
  - TrainingSessionController (index, show, bookings, availability)
  - AnamnesisTemplateController (CRUD + questions)
  - AnamnesisResponseController (CRUD + complete)
  - TrainingLogController (CRUD)
  - VaccinationController (CRUD + upcoming, overdue)
  - CreditPackageController (CRUD + available)
  - CustomerCreditController (CRUD + useCredit, active)
  - InvoiceController (CRUD + markAsPaid, overdue, downloadPdf)
  - PaymentController (CRUD + markAsCompleted)
- [x] **18 API Resources** für JSON-Serialisierung
  - User, Customer, Dog, Vaccination, Course, TrainingSession
  - Booking, CreditPackage, CustomerCredit
  - Invoice, InvoiceItem, Payment
  - AnamnesisTemplate, AnamnesisQuestion, AnamnesisResponse, AnamnesisAnswer
  - TrainingLog, TrainingAttachment
- [x] **24 Form Request Validation Classes**
  - Login, Register, UpdateProfile, PasswordReset
  - Store/Update für: Customer, Dog, Vaccination, Course, Booking
  - Store/Update für: CreditPackage, CustomerCredit, Invoice, Payment
  - Store/Update für: AnamnesisTemplate, AnamnesisResponse, TrainingLog
- [x] **14 Authorization Policies**
  - User, Customer, Dog, Vaccination, Booking, Course, TrainingSession
  - CreditPackage, CustomerCredit, Invoice, Payment
  - AnamnesisTemplate, AnamnesisResponse, TrainingLog
- [x] **RESTful API Routes** (alle mit /api/v1 Prefix)
  - Authentication (public + protected)
  - Resource routes für alle Entities
  - Custom endpoints (cancel, confirm, markAsPaid, useCredit, downloadPdf)
  - Filter & Search capabilities
- [x] **API Features**
  - Pagination für alle List-Endpoints
  - Filtering & Sorting
  - Eager Loading für Performance
  - Role-based Access Control
  - Consistent Error Handling
  - Snake_case ↔ camelCase Konvertierung

#### 7. Comprehensive Testing (Commit: TBD)
- [x] **388 Feature Tests** für alle API Endpoints
  - Authentication Tests (11 tests)
  - Authorization Tests (29 tests)
  - Customer API Tests (27 tests)
  - Dog API Tests (29 tests)
  - Booking API Tests (21 tests)
  - Course API Tests (20 tests)
  - Training Session API Tests (12 tests)
  - Vaccination API Tests (19 tests)
  - Credit Package API Tests (16 tests)
  - Customer Credit API Tests (20 tests)
  - Invoice API Tests (21 tests)
  - Payment API Tests (23 tests)
  - Anamnesis Template API Tests (17 tests)
  - Anamnesis Response API Tests (22 tests)
  - Training Log API Tests (32 tests)
  - Model Relationship Tests (15 tests)
  - Model Scope Tests (14 tests)
  - Model Business Logic Tests (19 tests)
  - Database Structure Tests (18 tests)
- [x] **Alle 388 Tests erfolgreich** (1,297 Assertions)

#### 8. PDF Generation System (Commit: TBD)
- [x] **DomPDF Integration**
  - barryvdh/laravel-dompdf v3.1.1 installiert
  - PDF-Konfiguration veröffentlicht
- [x] **Invoice PDF Template**
  - Professionelle deutsche Rechnungs-PDF-Vorlage
  - Header mit Geschäftsdaten & Rechnungsmetadaten
  - Kunden-Rechnungsadresse
  - Positionstabelle mit Artikeln
  - Steueraufschlüsselung nach Steuersätzen
  - Status-Indikatoren (Bezahlt, Überfällig, Unbezahlt)
  - Zahlungsinformationen
  - Optionaler Notizen-Bereich
  - DomPDF-kompatibles CSS (einfaches Layout)
- [x] **PDF Controller & Route**
  - downloadPdf() Methode in InvoiceController
  - GET /api/v1/invoices/{invoice}/pdf Endpoint
  - Policy-basierte Autorisierung
- [x] **PDF Tests**
  - 18 umfassende Feature Tests
  - Autorisierungs-Tests (5 tests)
  - PDF-Inhalts-Validierung (9 tests)
  - Technische Aspekte (2 tests)
  - Edge Cases (2 tests)
  - Alle 18 Tests erfolgreich

### 📋 Geplant

#### 9. Erweiterte PDF Features
- [ ] **Anamnese PDF Template**
  - PDF-Template für Anamnese-Antworten
  - Fragen & Antworten formatiert
  - Hunde-Information
- [ ] **Training Plan PDFs**
  - Individueller Trainingsplan pro Hund
  - Fortschritts-Übersichten

#### 10. File Upload System
- [ ] **Training Attachment Upload**
  - TrainingAttachmentController (CRUD)
  - File Upload Validation (MIME-Types, Größe)
  - Storage-Konfiguration (Local/S3)
  - Image Optimization (Intervention Image)
  - Thumbnail-Generierung
- [ ] **File Management**
  - Storage Symlinks
  - Automatic Cleanup für gelöschte Records
  - Download-Endpoint mit Authorization

#### 11. Payment Integration
- [ ] **Stripe Integration**
  - Stripe PHP SDK Installation
  - Payment Intent Creation
  - Webhook-Handling
  - Refund-Processing
- [ ] **PayPal Integration**
  - PayPal SDK Installation
  - PayPal Checkout
  - IPN-Handling

#### 12. E-Mail-System
- [ ] **Mailable Classes**
  - BookingConfirmation
  - PaymentReminder
  - InvoiceCreated
  - OverdueNotice
- [ ] **Mail Templates**
  - Blade Templates mit Branding
  - Inline CSS für E-Mail-Clients
- [ ] **Queue-basierter Versand**
  - Mail Queue Setup
  - Failed Jobs Handling

#### 13. Calendar Integration
- [ ] iCal Export für Bookings
- [ ] Google Calendar Sync
- [ ] Outlook Calendar Integration

#### 14. Reporting & Analytics
- [ ] Umsatz-Reports
- [ ] Auslastungs-Statistiken
- [ ] Kunden-Entwicklung

#### 15. Background Jobs & Queues
- [ ] Automated Invoice Generation
- [ ] Overdue Reminders
- [ ] Vaccination Reminders

#### 16. API Documentation
- [ ] OpenAPI/Swagger Spec
- [ ] API Blueprint
- [ ] Postman Collection

#### 9. Frontend (Vue 3 + TypeScript)
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

#### 17. Mobile App (Optional)
- [ ] Capacitor Integration
- [ ] Native Features
  - Kamera für Fotos/Videos
  - Push-Notifications
  - Offline-Modus
- [ ] Mobile UI/UX Optimierung

#### 18. Deployment & DevOps
- [ ] CI/CD Pipeline (GitHub Actions)
- [ ] Staging Environment
- [ ] Production Environment
- [ ] Backup-Strategie
- [ ] Monitoring (Laravel Telescope/Horizon)
- [ ] Error Tracking (Sentry)
- [ ] Performance Monitoring

#### 19. Dokumentation
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
