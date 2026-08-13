## ADDED Requirements

### Requirement: Versand-Dialog bietet App-Mail und manuellen Download an

Für eine Rechnung im Status `sent`, `reminded` oder `overdue` SHALL das
System beim Klick auf "Senden" einen Dialog anzeigen, der abfragt, ob die
Rechnung aus der App heraus per E-Mail versendet werden soll oder ob sie
manuell (per PDF-Download) versendet wird. Der Dialog SHALL beide
Optionen ("Aus der App versenden" und "Manuell versenden") immer
anzeigen, unabhängig davon, ob für den Kunden eine E-Mail-Adresse
hinterlegt ist (User-Gate-1-Entscheidung: kein UI-Zweig für "keine
E-Mail-Adresse", YAGNI — siehe `design.md` Decision D8; die
serverseitige Zustandsprüfung beim tatsächlichen App-Mail-Versand bleibt
als Defense-in-Depth bestehen, siehe Requirement "App-interner
E-Mail-Versand mit PDF-Anhang").

#### Scenario: Versand-Dialog zeigt stets beide Optionen an
- **GIVEN** eine Rechnung im Status `sent`
- **WHEN** ein Admin oder Trainer auf "Senden" klickt
- **THEN** der Dialog zeigt sowohl "Aus der App versenden" als auch
  "Manuell versenden" (PDF-Download) an, unabhängig davon, ob für den
  Kunden eine E-Mail-Adresse hinterlegt ist

#### Scenario: Manueller Versand nutzt den bestehenden PDF-Download
- **GIVEN** der Versand-Dialog ist für eine Rechnung geöffnet
- **WHEN** "Manuell versenden" gewählt wird
- **THEN** dieselbe PDF-Datei wird heruntergeladen, die auch über den
  bestehenden PDF-Button verfügbar ist, ohne dass sich der Status der
  Rechnung ändert

### Requirement: App-interner E-Mail-Versand mit PDF-Anhang

Das System SHALL beim App-internen Versand einer Rechnung im Status
`sent`, `reminded` oder `overdue` eine E-Mail mit der Rechnung als
PDF-Anhang an die hinterlegte Kunden-E-Mail-Adresse verschicken. Der
Status der Rechnung SHALL sich durch diese Aktion nicht ändern.

#### Scenario: Erfolgreicher App-Mail-Versand
- **GIVEN** eine Rechnung im Status `sent` mit einem Kunden, der eine
  E-Mail-Adresse hat
- **WHEN** ein Admin oder Trainer "Aus der App versenden" auslöst
- **THEN** eine E-Mail mit der Rechnung als PDF-Anhang wird an die
  Kunden-E-Mail-Adresse verschickt und die Rechnung bleibt im Status
  `sent`

#### Scenario: Versand für gemahnte oder überfällige Rechnung möglich
- **GIVEN** eine Rechnung im Status `reminded` oder `overdue` mit einem
  Kunden, der eine E-Mail-Adresse hat
- **WHEN** ein Admin oder Trainer "Aus der App versenden" auslöst
- **THEN** eine E-Mail mit der Rechnung als PDF-Anhang wird verschickt

#### Scenario: App-Mail-Versand für nicht-sendbaren Status wird abgelehnt
- **GIVEN** eine Rechnung im Status `draft`, `paid` oder `cancelled`
- **WHEN** der App-interne E-Mail-Versand für diese Rechnung
  angefordert wird
- **THEN** die Anfrage wird abgelehnt und es wird keine E-Mail
  verschickt

#### Scenario: App-Mail-Versand ohne Kunden-E-Mail-Adresse wird abgelehnt
- **GIVEN** eine Rechnung, deren Kunde keine E-Mail-Adresse hinterlegt
  hat
- **WHEN** der App-interne E-Mail-Versand für diese Rechnung
  angefordert wird
- **THEN** die Anfrage wird mit einem erklärenden Hinweis abgelehnt und
  es wird keine E-Mail verschickt

#### Scenario: Fehlgeschlagener Mailversand wird gemeldet, kein Retry
- **GIVEN** der Mail-Versand schlägt technisch fehl (z. B.
  SMTP-Verbindungsfehler)
- **WHEN** ein Admin oder Trainer den App-internen Versand auslöst
- **THEN** die Anfrage wird mit einer Fehlermeldung abgelehnt, die auf
  den manuellen Download als Alternative hinweist, und es findet kein
  automatischer Wiederholungsversuch statt

#### Scenario: Nur Admin/Trainer dürfen den App-Mail-Versand auslösen
- **GIVEN** ein angemeldeter Kunde
- **WHEN** dieser versucht, den App-internen E-Mail-Versand für eine
  Rechnung auszulösen
- **THEN** die Anfrage wird abgelehnt
