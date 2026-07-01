# Abnahme: favicon-ico-upload-bug

**Status:** bereit-für-user-review

---

## Erfüllt

### T01 — Backend-Validierungsfix

- `UpdateSettingsRequest.php:46`: Regel `file` ist gesetzt (verifiziert per grep). `company_logo` hat `image` unverändert behalten — kein Seiteneffekt.
- Testdatei liegt in `backend/tests/Feature/Api/SettingsValidationTest.php` (Reviewer-Sollte-Befund: Verschieben in Api/-Unterverzeichnis — erledigt).
- 7 Pest-Tests, alle grün (658/658 Gesamtsuite). Abdeckung: `image/x-icon`, `image/vnd.microsoft.icon` (vom Tester ergänzt), PNG, EXE-Ablehnung, Übergröße, fehlendes Feld, Logo-Seiteneffektschutz.
- Alle 8 Akzeptanzkriterien aus `tasks.md` abgehakt.
- PHP-8.2-Kompatibilität eingehalten; `composer qa` grün (inkl. Pint-Stylefixup für FQCN-Import, keine logische Änderung).
- Reviewer: ok, keine Muss-Befunde.
- Reviewer-Könnte-Befund (vnd.microsoft.icon-Test) wurde vom Tester proaktiv aufgegriffen und umgesetzt.

### T02 — Frontend-State-Refactoring

- `const error` vollständig entfernt (kein verbleibender Verweis im Template oder Script).
- `const loadError` (Zeile 565) und `const saveError` (Zeile 566) korrekt deklariert.
- Template: `v-else-if="loadError"` auf Zeile 16; `v-if="saveError"`-Block auf Zeile 388 innerhalb `<form v-else>` — visuell konsistent mit `successMessage`-Block.
- Beide catch-Zweige befüllen die korrekte Ref (`loadSettings` → `loadError.value`, `saveSettings` → `saveError.value`); Reset jeweils am Funktionsanfang (`loadError.value = null` Z. 576, `saveError.value = null` Z. 620).
- 5 Vitest-Tests: 72/72 Gesamtsuite grün. Szenarien: 422-Fehler lässt Formular im DOM, `saveError`-Meldung erscheint, Reset beim Folgeversuch, 500-Fehler versteckt Formular, `loadError`-Meldung erscheint.
- `npm run build` erfolgreich, keine Warnings.
- Reviewer: ok, keine Muss- oder Sollte-Befunde.

### Spec-Konformität (`specs/settings-favicon-upload/spec.md`)

Alle 8 Spec-Szenarien sind durch Tests oder Code-Inspektion nachgewiesen:
- "ICO-Datei wird hochgeladen" (`image/x-icon` + `image/vnd.microsoft.icon`) — zwei Tests.
- "PNG-Datei wird hochgeladen" — ein Test.
- "Unerlaubter MIME-Typ wird abgelehnt" — EXE-Test (422).
- "Datei zu groß" — 513-KB-Test (422).
- "Kein Favicon gesendet" — `sometimes`-Test.
- "Speicherfehler durch Validierung — Formular bleibt sichtbar" — Vitest-Test 1 + 2.
- "Ladefehler hält Formular ausgeblendet" — Vitest-Test 4 + 5.
- "Speicherfehler-Meldung verschwindet bei erneutem Versuch" — Vitest-Test 3.

---

## Offen / Nacharbeit

Keine offenen Punkte.

---

## Lint-Klarstellung

Das `lint`-Script existiert im Frontend-Projekt nicht (`frontend/package.json`
definiert kein `lint`-Script, kein ESLint konfiguriert). Dies wurde von Tester
und Reviewer bereits dokumentiert. Der de-facto Qualitätscheck ist `npm run build`
(läuft `vue-tsc -b` + Vite), der grün ist. Das Akzeptanzkriterium `npm run lint`
ist für dieses Projekt eine tote Regel — nicht erfüllbar, nicht blockend.

---

## Empfehlung an den User

**Freigabe erteilt.** Der Change ist vollständig und bereit für User-Gate 2.
Beide Bugs korrekt behoben, alle Tests grün (7 Backend, 72 Frontend),
alle Spec-Szenarien abgedeckt, keine offenen Befunde.
