# Proposal: course-dates

**Change-ID:** course-dates
**GitHub Issue:** #33
**Status:** bereit für Umsetzung
**Erstellt:** 2026-05-14

---

## Was wird gebaut?

Trainer sollen beim Anlegen und Bearbeiten eines Kurses konkrete Termineinheiten
definieren können — entweder als manuell eingetragene Einzeltermine oder als
automatisch berechnete Terminserie (wöchentlich oder monatlich wiederkehrend).

Die erzeugten Termineinheiten (`TrainingSession`-Datensätze) sind danach einzeln
editierbar, verschiebbar und löschbar. Kunden sehen die Termine auf einer öffentlichen
Kursdetailseite.

Aktuell speichert das System nur `start_date`, `end_date` und `total_sessions` (Ganzzahl)
am Kurs. Individuelle `TrainingSession`-Datensätze in der bereits vorhandenen Tabelle
`training_sessions` werden durch die App-UI noch nicht verwaltet.

---

## User Stories

### US-1: Kurs mit Einzelterminen anlegen
Als Trainer möchte ich beim Anlegen eines Kurses manuell konkrete Termine eingeben
(Datum, Uhrzeit, Ort), damit Kunden sehen, wann genau die Einheiten stattfinden.

### US-2: Kurs mit Terminserie anlegen
Als Trainer möchte ich beim Anlegen eines Kurses eine Wiederholungsregel definieren
(z. B. jeden Montag 10–11 Uhr, 8× ab 03.03.2025), damit die Einzeltermine automatisch
erzeugt werden, ohne jeden Termin manuell einzutragen.

### US-3: Terminserie mit Ausnahmen
Als Trainer möchte ich nach dem Anlegen eines Kurses einzelne Termine aus einer
Serie verschieben, löschen oder ergänzen, damit Sondersituationen (Feiertage,
Ausfälle) abgebildet werden können.

### US-4: Warnhinweis bei Terminänderungen mit Buchungen
Als Trainer möchte ich beim Löschen oder Verschieben eines Termins mit bestehenden
Buchungen einen Hinweis erhalten, damit ich Kunden informieren kann — ohne dass
das System mich blockiert.

### US-5: Öffentliche Kursdetailseite mit Terminen
Als Kunde möchte ich auf der Kursdetailseite alle geplanten Termine sehen,
damit ich weiß, ob der Kurs in meinen Kalender passt, bevor ich buche.

---

## Akzeptanzkriterien (gesamt)

### Backend API

- [ ] `POST /api/v1/courses` akzeptiert optional ein `sessions`-Feld mit Einzelterminen
  oder ein `recurrenceRule`-Feld und erstellt entsprechende `TrainingSession`-Einträge
- [ ] `PUT /api/v1/courses/{course}` verhält sich analog; bestehende Sessions werden
  abgeglichen (neu hinzufügen, löschen, wenn keine Buchungen vorliegen)
- [ ] Bestehende Clients ohne `sessions`/`recurrenceRule`-Felder funktionieren weiterhin
  (Abwärtskompatibilität)
- [ ] `POST /api/v1/courses/{course}/sessions` legt eine einzelne Session für einen
  bestehenden Kurs an
- [ ] `PUT /api/v1/courses/{course}/sessions/{session}` aktualisiert eine Session;
  bei bestehenden Buchungen wird ein `warnings`-Array in der Antwort mitgegeben
- [ ] `DELETE /api/v1/courses/{course}/sessions/{session}` löscht eine Session;
  bei bestehenden Buchungen wird ein `warnings`-Array mitgegeben (kein hartes Blockieren)
- [ ] `GET /api/v1/public/courses/{course}` gibt Kursdetails inkl. Sessions ohne
  Authentifizierung zurück (Rate-Limiting)
- [ ] Rekurrenz-Berechnung unterstützt `weekly` (Wochentag + Uhrzeit + Anzahl)
  und `monthly` (Monatstag + Uhrzeit + Anzahl)

### Frontend

- [ ] `CourseFormModal` zeigt einen Modus-Schalter „Einzeltermine / Terminserie"
- [ ] Im Modus „Einzeltermine": dynamische Liste mit Datum/Zeit/Ort je Eintrag
- [ ] Im Modus „Terminserie": Felder für Typ (wöchentlich/monatlich), Wochentag/Monatstag,
  Start-/Endzeit, Startdatum, Anzahl Einheiten
- [ ] Beim Speichern werden die Session-Daten in der korrekten API-Payload mitgesendet
- [ ] Ein `CourseSessionList`-Komponent zeigt alle Sessions eines Kurses mit
  Datum, Uhrzeit, Status und Teilnehmeranzahl
- [ ] Einzelne Sessions können über UI-Aktionen bearbeitet, gelöscht oder ergänzt werden
- [ ] Beim Löschen/Verschieben eines Terms mit Buchungen erscheint ein Warn-Dialog
  (Trainer muss bestätigen, wird aber nicht blockiert)
- [ ] Neue `CourseDetailView` zeigt Kursdetails + Session-Liste für Trainer (editierbar)
  und Kunden (nur lesend, öffentlich zugänglich)
- [ ] Alle Formulare (CourseFormModal, Inline-Session-Edit, Inline-Session-Add) haben
  drei Aktions-Buttons in der Reihenfolge: **Abbrechen** (schließt ohne Speichern),
  **Zurücksetzen** (setzt Formularwerte auf Ausgangszustand zurück), **Speichern**

---

## Nicht in diesem Change (bewusst ausgeklammert)

- Push-Benachrichtigungen an Kunden bei Terminänderungen (separater Change)
- iCal-Export der Termine
- Buchungs-Interface direkt auf der Kursdetailseite (bestehende Buchungsflow bleibt)
- Rekurrenz-Typen „alle 2 Wochen" oder mehrere Wochentage gleichzeitig
