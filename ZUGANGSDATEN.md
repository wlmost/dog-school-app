# Zugangsdaten - Dog School Management System

## 🔐 Test-Benutzer

Die folgenden Zugangsdaten können für Tests und Entwicklung verwendet werden:

### Administrator
- **E-Mail:** `admin@example.com`
- **Passwort:** `password`
- **Berechtigungen:** Vollzugriff auf alle Funktionen

### Trainer
- **E-Mail:** `trainer@example.com`
- **Passwort:** `password`
- **Berechtigungen:** Kurse verwalten, Buchungen sehen, Anamnesen erstellen

### Kunde
- **E-Mail:** `customer@example.com`
- **Passwort:** `password`
- **Berechtigungen:** Eigene Hunde und Buchungen verwalten, Anamnesen ausfüllen

---

## 🌐 Zugriffs-URLs

- **Frontend (Vue.js):** http://localhost:5173
- **Backend API:** http://localhost:8081
- **Mailpit (E-Mail-Testing):** http://localhost:8025
- **PostgreSQL:** localhost:5432
- **Redis:** localhost:6379

---

## 🔧 Datenbank neu seeden

Falls die Datenbank zurückgesetzt werden muss:

```bash
# In der Docker-Umgebung
docker-compose exec php php artisan migrate:fresh --seed
```

Dies wird:
1. Alle Tabellen löschen
2. Alle Migrationen ausführen
3. Test-Benutzer erstellen
4. Anamnese-Templates erstellen

---

## 📝 Notizen

- Alle Passwörter sind `password` (nur für Entwicklung!)
- Für Produktion müssen sichere Passwörter verwendet werden
- Die Datenbank wird beim ersten Start leer sein - `migrate:fresh --seed` ausführen
