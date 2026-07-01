# Proposal: favicon-ico-upload-bug

**Change-ID:** favicon-ico-upload-bug
**Typ:** Bugfix
**Pfad:** klein
**Status:** entwurf

---

## Was wird geändert?

Zwei zusammenhängende Bugs im Bereich Systemeinstellungen werden behoben:

1. **Validation-Bug (Backend):** Der Admin kann kein `.ico`-Favicon hochladen,
   obwohl die UI diese Endung bewirbt. Laravel lehnt ICO-Dateien mit der
   Fehlermeldung "The Favicon field must be an image." ab.

2. **Template-Bug (Frontend):** Nach jedem Speicherfehler (z. B. dem
   Validation-Fehler aus Bug 1) verschwindet das gesamte Einstellungsformular
   und ist erst nach einem Seitenneuladen wieder sichtbar. Nur der
   Preise-Abschnitt bleibt erreichbar.

---

## Warum wird es geändert?

- Der Admin kann eine beworbene Funktion (Favicon-Upload als ICO) nicht nutzen.
- Jeder Speicherfehler macht das Formular unbedienbar — d. h. auch valide
  Eingaben können nach einem Fehler nicht mehr gespeichert werden.
- Beide Bugs entstehen durch unabhängige, lokale Logikfehler und lassen sich
  ohne Schemaänderungen oder neue Abhängigkeiten beheben.

---

## Umfang

| Bereich | Datei | Art |
|---------|-------|-----|
| Backend | `backend/app/Http/Requests/UpdateSettingsRequest.php` | Regelfix (1 Zeile) |
| Backend | `backend/tests/Feature/SettingsValidationTest.php` | Neu (Pest) |
| Frontend | `frontend/src/views/SettingsView.vue` | State-Refactoring |
| Frontend | `frontend/src/views/SettingsView.test.ts` | Neu (Vitest) |

---

## Nicht im Scope

- Kein Schemaumbau, keine Datenbankänderungen.
- Keine Änderungen an anderen Validierungsregeln (Logo bleibt `image`).
- Keine visuellen Redesigns; nur die State-Logik und das Template-Conditional werden angepasst.
