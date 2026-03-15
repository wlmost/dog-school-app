# Frontend Manual Testing Guide

Diese Anleitung beschreibt die manuelle Testprozedur für alle Features der Hundeschule-Verwaltungsanwendung.

## Vorbereitung

### Testumgebung starten
```bash
# Docker-Container starten
docker-compose up -d

# Sicherstellen, dass Backend und Frontend laufen
# Backend: http://localhost:8000
# Frontend: http://localhost:5173
# Mailpit: http://localhost:8025
```

### Test-Zugangsdaten

Alle Testbenutzer verwenden das Passwort: `password`

| Rolle | E-Mail | Berechtigungen |
|-------|--------|----------------|
| **Admin** | admin@hundeschule.test | Voller Zugriff auf alle Funktionen |
| **Trainer** | trainer@hundeschule.test | Kurse, Kunden, Hunde, Buchungen verwalten |
| **Kunde** | kunde@hundeschule.test | Eigene Buchungen und Hunde einsehen |

## Test-Checkliste

### 1. Authentifizierung & Benutzer-Rollen ✓

#### 1.1 Login
- [ ] **URL öffnen**: http://localhost:5173
- [ ] **Admin-Login testen**:
  - E-Mail: `admin@hundeschule.test`
  - Passwort: `password`
  - ✅ Login erfolgreich
  - ✅ Weiterleitung zum Dashboard
  - ✅ Admin-Menü sichtbar (Alle Menüpunkte)
  
- [ ] **Logout testen**:
  - ✅ Logout-Button klicken
  - ✅ Weiterleitung zur Login-Seite
  - ✅ Session wurde gelöscht

- [ ] **Ungültige Anmeldedaten**:
  - ✅ Fehlermeldung als Toast erscheint
  - ✅ Kein Login möglich

#### 1.2 Trainer-Rolle
- [ ] Login als Trainer
- [ ] Dashboard zeigt nur zugewiesene Kunden
- [ ] Menü: Kunden, Hunde, Kurse, Buchungen, Trainings-Logs sichtbar
- [ ] Keine Admin-Funktionen (Einstellungen, Systemverwaltung)

#### 1.3 Kunden-Rolle
- [ ] Login als Kunde
- [ ] Dashboard zeigt nur eigene Daten
- [ ] Menü: Nur Dashboard, Meine Buchungen, Meine Hunde
- [ ] Keine Verwaltungsfunktionen

---

### 2. Dashboard ✓

#### 2.1 Admin Dashboard
- [ ] **Statistiken anzeigen**:
  - ✅ Anzahl Kunden
  - ✅ Anzahl Hunde
  - ✅ Aktive Kurse
  - ✅ Offene Buchungen
  
- [ ] **Kommende Trainings**:
  - ✅ Liste der nächsten 5 Trainings
  - ✅ Datum, Uhrzeit, Kurs-Name
  - ✅ Teilnehmerzahl
  
- [ ] **Neueste Aktivitäten**:
  - ✅ Letzte Buchungen
  - ✅ Zeitstempel korrekt

#### 2.2 Dark Mode
- [ ] Toggle-Button in der Kopfzeile
- [ ] Umschalten zwischen Hell/Dunkel
- [ ] Einstellung wird gespeichert (localStorage)
- [ ] Nach Reload noch aktiv

---

### 3. Kundenverwaltung ✓

#### 3.1 Kunden-Liste
- [ ] **Navigation**: Kunden → Alle Kunden
- [ ] **Anzeige**:
  - ✅ Tabelle mit allen Kunden
  - ✅ Name, E-Mail, Telefon, Adresse
  - ✅ Paginierung funktioniert
  
- [ ] **Suche**:
  - ✅ Suchfeld eingeben
  - ✅ Ergebnisse filtern in Echtzeit
  - ✅ Keine Verzögerung

#### 3.2 Kunde erstellen
- [ ] **Button**: "Neuer Kunde"
- [ ] **Modal öffnet sich**:
  - ✅ Formular mit allen Feldern
  - ✅ Pflichtfelder markiert
  
- [ ] **Eingabe**:
  - Vorname: Max
  - Nachname: Mustermann
  - E-Mail: max.mustermann@test.de
  - Telefon: 0123456789
  - Straße: Musterstraße 1
  - PLZ: 12345
  - Stadt: Musterstadt
  - Land: Deutschland
  
- [ ] **Speichern**:
  - ✅ Toast-Benachrichtigung "Kunde erstellt"
  - ✅ Kunde erscheint in der Liste
  - ✅ Modal schließt automatisch

#### 3.3 Kunde bearbeiten
- [ ] Kunde in Liste auswählen → "Bearbeiten"
- [ ] Daten ändern (z.B. Telefonnummer)
- [ ] Speichern
- [ ] ✅ Toast "Kunde aktualisiert"
- [ ] ✅ Änderungen sichtbar

#### 3.4 Kunde löschen
- [ ] Kunde ohne aktive Buchungen auswählen
- [ ] "Löschen" klicken
- [ ] Bestätigung
- [ ] ✅ Toast "Kunde gelöscht"
- [ ] ✅ Kunde nicht mehr in Liste

---

### 4. Hundeverwaltung ✓

#### 4.1 Hunde-Liste
- [ ] **Navigation**: Hunde → Alle Hunde
- [ ] **Karten-Ansicht**:
  - ✅ Jeder Hund als Karte
  - ✅ Name, Rasse, Besitzer
  - ✅ Geburtsdatum, Chip-Nummer
  
- [ ] **Skeleton Loader**:
  - ✅ Beim ersten Laden sichtbar
  - ✅ Smooth Transition zu Daten

#### 4.2 Hund erstellen
- [ ] Button "Neuer Hund"
- [ ] **Formular ausfüllen**:
  - Besitzer: Max Mustermann auswählen
  - Name: Bello
  - Rasse: Deutscher Schäferhund
  - Geburtsdatum: 01.01.2020
  - Geschlecht: Männlich
  - Gewicht: 30 kg
  - Chip-Nummer: 123456789012345
  - Kastiert: Ja
  
- [ ] Speichern
- [ ] ✅ Toast "Hund erstellt"
- [ ] ✅ Hund in Liste sichtbar

#### 4.3 Validierung
- [ ] Formular ohne Besitzer absenden
- [ ] ✅ Fehlermeldung als Toast
- [ ] Chip-Nummer mit falscher Länge
- [ ] ✅ Validierungsfehler

---

### 5. Kursverwaltung ✓

#### 5.1 Kurse-Liste
- [ ] **Navigation**: Kurse → Alle Kurse
- [ ] **Filter**:
  - ✅ Nach Status (Aktiv, Abgeschlossen)
  - ✅ Nach Trainer
  - ✅ Suche nach Name
  
- [ ] **Anzeige**:
  - ✅ Kurs-Name, Typ, Trainer
  - ✅ Start-/Enddatum
  - ✅ Teilnehmer / Max. Teilnehmer

#### 5.2 Kurs erstellen
- [ ] Als Trainer einloggen
- [ ] "Neuer Kurs" klicken
- [ ] **Formular**:
  - Name: Welpengruppe Januar
  - Beschreibung: Grundgehorsam für Welpen
  - Kurs-Typ: Gruppentraining
  - Max. Teilnehmer: 8
  - Startdatum: 15.01.2026
  - Enddatum: 15.03.2026
  - Preis pro Einheit: 25,00 €
  - Anzahl Einheiten: 10
  
- [ ] Speichern
- [ ] ✅ Toast "Kurs erstellt"

#### 5.3 Trainings-Sessions
- [ ] Kurs öffnen → "Sessions anzeigen"
- [ ] Liste der geplanten Termine
- [ ] Buchungen für jede Session sichtbar

---

### 6. Buchungsverwaltung ✓

#### 6.1 Als Kunde buchen
- [ ] Als Kunde einloggen
- [ ] Navigation: Dashboard oder Buchungen
- [ ] "Neue Buchung"
- [ ] **Auswahl**:
  - Kurs auswählen
  - Training-Session wählen
  - Eigenen Hund auswählen
  
- [ ] Buchen
- [ ] ✅ Buchungsbestätigung als Toast
- [ ] ✅ E-Mail in Mailpit prüfen (http://localhost:8025)

#### 6.2 Buchung bestätigen (Trainer)
- [ ] Als Trainer einloggen
- [ ] Buchungen → Ausstehende Buchungen
- [ ] Buchung auswählen → "Bestätigen"
- [ ] ✅ Status ändert sich zu "Bestätigt"
- [ ] ✅ Toast-Benachrichtigung

#### 6.3 Buchung stornieren
- [ ] Buchung auswählen → "Stornieren"
- [ ] Bestätigung
- [ ] ✅ Status "Storniert"
- [ ] ✅ Toast-Benachrichtigung

---

### 7. Rechnungsverwaltung ✓

#### 7.1 Rechnung erstellen
- [ ] Als Trainer einloggen
- [ ] Rechnungen → "Neue Rechnung"
- [ ] **Formular**:
  - Kunde: Max Mustermann
  - Rechnungsdatum: Heute
  - Fälligkeitsdatum: +30 Tage
  - Position hinzufügen:
    - Beschreibung: Welpengruppe Januar
    - Menge: 10
    - Einzelpreis: 25,00 €
  
- [ ] Speichern
- [ ] ✅ Rechnung erstellt
- [ ] ✅ Rechnungsnummer generiert
- [ ] ✅ E-Mail gesendet (Mailpit prüfen)

#### 7.2 Rechnung als PDF
- [ ] Rechnung auswählen → "PDF herunterladen"
- [ ] ✅ PDF öffnet sich
- [ ] ✅ Alle Daten korrekt:
  - Rechnungsnummer
  - Kundendaten
  - Positionen mit Preisen
  - Summe, MwSt., Gesamt
  - Firmen-Daten aus Einstellungen

#### 7.3 Zahlung verbuchen
- [ ] Rechnung auswählen → "Als bezahlt markieren"
- [ ] ✅ Status ändert zu "Bezahlt"
- [ ] ✅ Toast-Benachrichtigung

---

### 8. Anamnese-System ✓

#### 8.1 Template erstellen
- [ ] Als Trainer einloggen
- [ ] Anamnese → Templates
- [ ] "Neues Template"
- [ ] **Formular**:
  - Name: Standard Erstanamnese
  - Beschreibung: Für alle neuen Hunde
  - Frage hinzufügen:
    - Text: "Wie alt ist Ihr Hund?"
    - Typ: Text
    - Pflichtfeld: Ja
  - Weitere Fragen:
    - "Hat Ihr Hund Vorerkrankungen?" (Textarea)
    - "Verträgt sich Ihr Hund mit anderen Hunden?" (Radio: Ja/Nein)
    
- [ ] Speichern
- [ ] ✅ Template erstellt

#### 8.2 Anamnese ausfüllen
- [ ] Als Kunde einloggen
- [ ] Anamnese → Neue Anamnese
- [ ] Hund auswählen
- [ ] Template auswählen
- [ ] Fragen beantworten
- [ ] Absenden
- [ ] ✅ Antworten gespeichert

#### 8.3 Anamnese-PDF
- [ ] Als Trainer: Anamnese auswählen
- [ ] "PDF herunterladen"
- [ ] ✅ PDF mit allen Fragen und Antworten

---

### 9. E-Mail-System ✓

#### 9.1 Willkommens-E-Mail
- [ ] Neuen Benutzer erstellen (als Admin)
- [ ] Mailpit öffnen: http://localhost:8025
- [ ] ✅ Willkommens-E-Mail vorhanden
- [ ] ✅ Enthält Zugangsdaten
- [ ] ✅ Link zum Login
- [ ] ✅ Firmen-Logo und Daten

#### 9.2 Buchungsbestätigung
- [ ] Neue Buchung erstellen
- [ ] Mailpit prüfen
- [ ] ✅ Bestätigungs-E-Mail
- [ ] ✅ Kurs-Details
- [ ] ✅ Datum, Uhrzeit, Hund

#### 9.3 Rechnung per E-Mail
- [ ] Rechnung erstellen
- [ ] Mailpit prüfen
- [ ] ✅ Rechnungs-E-Mail
- [ ] ✅ Betrag, Fälligkeit
- [ ] ✅ Zahlungshinweise

#### 9.4 Zahlungserinnerung
- [ ] Terminal: `docker-compose exec php php artisan email:test wlmost@gmx.de`
- [ ] ✅ Alle 4 Test-E-Mails gesendet
- [ ] Inbox prüfen (wlmost@gmx.de)
- [ ] ✅ E-Mails erhalten

---

### 10. Error Handling & UX ✓

#### 10.1 Toast-Benachrichtigungen
- [ ] **Erfolg**: Grün, Häkchen-Icon
  - ✅ Automatisches Schließen nach 5s
  - ✅ Manuelles Schließen möglich
  
- [ ] **Fehler**: Rot, X-Icon
  - ✅ Benutzerfreundliche Meldung
  - ✅ Keine technischen Details
  
- [ ] **Warnung**: Gelb, Warn-Icon
  - ✅ Hinweise sichtbar

- [ ] **Info**: Blau, Info-Icon

#### 10.2 Formular-Validierung
- [ ] Leeres Formular absenden
- [ ] ✅ Toast mit Validierungsfehler
- [ ] ✅ Keine Alert-Dialoge mehr
- [ ] ✅ Fehler klar beschrieben

#### 10.3 Loading States
- [ ] Seite neu laden
- [ ] ✅ Skeleton Loader während Ladezeit
- [ ] ✅ Smooth Transition
- [ ] Formular absenden
- [ ] ✅ Button disabled während Speichern
- [ ] ✅ Loading-Indikator sichtbar

#### 10.4 Dark Mode
- [ ] Toggle in Kopfzeile
- [ ] ✅ Alle Seiten in Dark Mode prüfen
- [ ] ✅ Lesbarkeit gegeben
- [ ] ✅ Kontraste ausreichend
- [ ] ✅ Icons sichtbar
- [ ] Browser-Reload
- [ ] ✅ Dark Mode bleibt aktiv

---

### 11. Responsive Design (Optional)

#### 11.1 Desktop (1920x1080)
- [ ] Alle Funktionen zugänglich
- [ ] Layout nutzt Bildschirm gut aus

#### 11.2 Tablet (768x1024)
- [ ] Menü angepasst
- [ ] Tabellen scrollbar
- [ ] Formulare nutzbar

#### 11.3 Mobile (375x667)
- [ ] Burger-Menü
- [ ] Cards statt Tabellen
- [ ] Touch-friendly Buttons

---

### 12. Performance & Stabilität

#### 12.1 Ladezeiten
- [ ] Dashboard: < 1s
- [ ] Listen-Seiten: < 1s
- [ ] Formular-Submit: < 500ms
- [ ] PDF-Download: < 2s

#### 12.2 Netzwerk-Fehler
- [ ] Backend stoppen: `docker-compose stop php`
- [ ] Aktion im Frontend ausführen
- [ ] ✅ Toast "Netzwerkfehler"
- [ ] ✅ Keine Crash
- [ ] Backend starten: `docker-compose start php`
- [ ] ✅ Funktionalität wiederhergestellt

#### 12.3 Mehrere Tabs
- [ ] Anwendung in 2 Tabs öffnen
- [ ] In Tab 1 Daten ändern
- [ ] Tab 2 aktualisieren
- [ ] ✅ Änderungen sichtbar

---

## Browser-Kompatibilität

### Getestet in:
- [ ] Chrome/Chromium (empfohlen)
- [ ] Firefox
- [ ] Edge
- [ ] Safari (macOS)

---

## Bekannte Einschränkungen

1. **Test-Datenbank**: Tests verwenden eine separate Test-Datenbank, die von der Entwicklungs-DB isoliert ist
2. **E-Mail**: In der Entwicklung werden E-Mails nur an Mailpit gesendet, nicht an echte Adressen
3. **Zahlungen**: Keine echte Zahlungsintegration, nur Simulation
4. **Datei-Upload**: Begrenzt auf 50MB pro Datei

---

## Fehler melden

Bei Fehlern bitte folgende Informationen sammeln:

1. **Was wurde gemacht?** (Schritte zum Reproduzieren)
2. **Was wurde erwartet?**
3. **Was ist passiert?**
4. **Browser & Version**
5. **Screenshot** (falls visuelles Problem)
6. **Console-Log** (F12 → Console)
7. **Netzwerk-Tab** (F12 → Network) bei API-Fehlern

---

## Test-Abschluss

Nach Abschluss aller Tests:

- [ ] Alle Checkboxen abgehakt
- [ ] Keine kritischen Fehler gefunden
- [ ] Performance akzeptabel
- [ ] UX zufriedenstellend
- [ ] E-Mails funktionieren
- [ ] Dark Mode funktioniert
- [ ] Alle Rollen getestet

**Datum**: _______________  
**Tester**: _______________  
**Unterschrift**: _______________
