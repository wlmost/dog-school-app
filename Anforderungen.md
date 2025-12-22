# Verwaltungssoftware für Hundeschulen

## Kernfunktionen
### Kundenverwaltung (CRM)
Dies ist die digitale Akte dervKunden und ihrer Hunde.
| Kriterium | Funktion und Nutzen |
| --------- | ------------------- |
| Digitale Kundenakte | Zentrale Speicherung aller relevanten Daten (Adresse, Kontaktdaten, Kommunikation) und vor allem der Hundeinformationen (Rasse, Alter, Kastrationsstatus, Impfungen, Tierarzt).| 
| Übersichtliche Historie | Auf einen Blick sehen, welche Stunden, Kurse und Termine der Kunde bereits gebucht und abgeschlossen hat. Dies ist essenziell für die Nachverfolgung des Trainingsfortschritts.|
| Einfache Kommunikation | Direkter Versand von E-Mail-Bestätigungen, Zahlungserinnerungen und Kursupdates aus der Software heraus. Idealerweise mit konfigurierbaren Vorlagen. |
| DSGVO-Konformität | Die Software muss die deutschen und europäischen Datenschutzrichtlinien (DSGVO) einhalten, da Sie sensible personenbezogene Daten verwalten.Kunden-LoginEin eigenes Kundenportal, über das Kunden ihre gebuchten Termine, Rechnungen und Mehrfachkarten selbst einsehen können, entlastet Sie enorm.|

### Terminplanung
Der Kern des Geschäfts: dieeffiziente PLanung und Organisation
| Kriterium | Funktion und Nutzen |
| --------- | ------------------- |
| Online-Buchung (24/7) | Kunden können selbstständig Termine (Einzelstunden, Kurse) über Ihre Webseite buchen. Dadurch sparen Sie viele Telefonate und E-Mails. |
| Kalender-Synchronisation | Automatische Synchronisation mit externen Kalendern (z.B. Google Kalender, Outlook), um Terminkollisionen zu vermeiden. |
| Flexible Terminarten | Möglichkeit, Gruppentermine (Kurse), Serientermine (wiederkehrende Gruppen) und Einzelstunden mit unterschiedlichen Regeln zu planen und zu verwalten. |
| Kapazitäts- & Ressourcenmanagement | Definieren Sie die maximale Teilnehmerzahl pro Kurs/Stunde (Mensch & Hund). Optimal: eine automatische Wartelistenfunktion. |
| Mehrfachkarten-/Guthaben-Verwaltung | Automatisches Buchen und Abbuchen von Einheiten bei Nutzung einer 5er- oder 10er-Karte. |

### Anamnesen verwalten
Der wichtigste Punkt 

| Kriterium | Funktion und Nutzen |
| --------- | ------------------- |
| Individualisierbare Formulare | Erstellung von digitalen Anamnesebögen (Erstaufnahme) mit frei definierbaren Fragen (Verhalten, medizinische Vorgeschichte, Haltungsbedingungen), die der Kunde idealerweise vorab online ausfüllen kann.|
| Fortschritts- & Verlaufsdokumentation | Möglichkeit, nach jeder Stunde Notizen, Beobachtungen und Empfehlungen direkt in der Kundenakte zu speichern. |
| Bilder & Dokumente | Hochladen und Speichern von Fotos, Videos oder Tierarztdokumenten direkt in der Hundedatei. |
| Mobiler Zugriff | App- oder mobile Web-Ansicht, um Notizen und Anamnesedaten direkt auf dem Trainingsplatz oder beim Hausbesuch einzusehen und zu aktualisieren. |
| Exportfunktion | Wichtige Dokumente (z.B. Anamnese, Trainingsplan) als PDF exportieren und dem Kunden per Mail zur Verfügung stellen können. |

### Abrechnung & Administration
| Kriterium | Funktion und Nutzen |
| --------- | ------------------- |
| Automatisierte Rechnungsstellung | Rechnungen und Mahnungen werden automatisch nach einer Buchung oder bei Fälligkeit erstellt und versendet. |
| Anbindung an externe Bürosoftware über API | Falls externe Bürosoftware bereits verwendet wird und diese eine API anbietet, Beispiel Papierkram |
| Online-Zahlungsanbindung | Integration von Zahlungsanbietern (z.B. PayPal, Kreditkarte) für eine sofortige Online-Zahlung bei der Buchung. |
| Buchhaltungsschnittstelle | Export der Einnahmen/Rechnungen im gängigen Format (z.B. DATEV) für Ihren Steuerberater, um die Buchhaltung zu erleichtern. |
| Übersichtliche Finanzberichte | Schnelle Statistiken zu Einnahmen, offenen Posten und Auslastung. |

## Entwurf und Konzeption
Rolle: Du bist ein erfahrener Software-Architekt und Full-Stack-Entwickler mit Spezialisierung auf CRM-Systeme für Dienstleister im Tierbereich.

Aufgabe: Erstelle ein detailliertes technisches Konzept und eine Grundstruktur für eine moderne Management-Software für Hundeschulen (Web-App und mobile App).

Anforderungen (Core Features):

Kunden- & Hundeverwaltung (CRM): Verknüpfung von Besitzerdaten mit Hundeprofilen (Rasse, Geburtsdatum, Impfstatus).

Stundenverwaltung & Booking: Ein System für Einzelstunden und Gruppenkurse. Inklusive Verwaltung von 10er-Karten (Guthaben-System) und automatischer Warteliste.

Anamnese-Modul: Ein dynamischer Formular-Editor, mit dem Trainer individuelle Anamnesebögen erstellen können. Kunden sollen diese vorab digital ausfüllen können.

Fortschrittsdokumentation: Tagebuch-Funktion für jeden Hund, um Trainingsnotizen, Fotos und Videos nach jeder Stunde zu speichern.

Abrechnung: Automatisierte PDF-Rechnungsstellung und Integration von Zahlungsanbietern (z.B. Stripe oder PayPal).

Technische Leitplanken:

Fokus auf DSGVO-Konformität (Rollenmanagement, Datenlöschung).

Mobile-First Design, da Trainer oft auf dem Platz arbeiten.

Skalierbare Datenbank-Struktur.

Bitte liefere mir als ersten Schritt:

Ein Entity-Relationship-Diagramm (ERD) in Textform (welche Tabellen brauchen wir?).

Eine Liste der wichtigsten API-Endpunkte.

Einen Vorschlag für den Tech-Stack (Frontend, Backend, Datenbank).

Einen Entwurf für das User-Interface (UI) der Anamnese-Seite.

## Implementation
Nach einer Überprüfung und Freigabe von mir, beginne mit der Entwicklung.
Verwende hierbei TDD und Clean Code.
Erzeuge nach jeder erfolgreich getesteten Funktionalität eine Commit-Nachricht.
Beachte die Instruktionen und Skills.
