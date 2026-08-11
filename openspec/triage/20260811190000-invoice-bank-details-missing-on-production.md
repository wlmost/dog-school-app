# Triage: Kontodaten-Einstellungen fehlen auf Produktion nach Deploy

**Status:** ✅ Gelöst (2026-08-11). Deploy-Lauf `31521261040` erfolgreich
abgeschlossen (`conclusion: success`, 18:37:24 UTC); User hat auf
Produktion verifiziert, dass die Kontodaten-Felder in den Einstellungen
sichtbar und nutzbar sind. Keine weitere Aktion nötig.

**Pfad:** trivial (kein Code-Fix nötig — siehe Begründung unten)
**Geschätzter Umfang:** 0 Dateien Code-Änderung; 1 operative Aktion in GitHub Actions
**Risiko:** niedrig — kein Code wird verändert, keine neue Migration, keine Auth-/Datenmodell-Berührung.
**Klarheit:** klar — Ursache ist zweifelsfrei identifiziert (siehe Rechercheergebnis), keine Rückfrage an den User nötig.

## Anforderung (Zusammenfassung)

Der User meldet, dass nach dem Deploy des zuletzt gemergten Change
`add-invoice-bank-details` (PR #82, Merge-Commit `97a1aa4`) die
Änderungen auf dem produktiven Server nicht sichtbar sind — konkret
fehlt in den Einstellungen die Möglichkeit, als Admin die Kontodaten der
Hundeschule einzugeben.

## Rechercheergebnis

**1. Der Change ist vollständig implementiert und auf `main` gemergt.**
Alle vier Tasks aus `openspec/changes/archive/2026-08-11-add-invoice-bank-details/tasks.md`
sind mit `[x]` abgehakt, inklusive T02 ("Settings-Frontend — neue
Formularfelder"). Verifiziert im aktuellen Code auf `main`:

- `frontend/src/views/SettingsView.vue:166-223` — Eingabefelder für
  `company_bank_account_holder`, `company_bank_name`,
  `company_bank_iban`, `company_bank_bic`, `company_payment_term_weeks`
  sind vorhanden.
- `frontend/src/views/SettingsView.vue:611-615` — dieselben Keys in
  `formData` initialisiert.
- `backend/app/Http/Requests/UpdateSettingsRequest.php:44-48,85-89` —
  Validierungsregeln und Attribut-Labels für alle fünf Felder vorhanden.
- `backend/database/seeders/SettingsSeeder.php:29-33` — Default-Werte für
  alle fünf Keys vorhanden.
- `backend/app/Http/Controllers/SettingsController.php:92` — Typ-Handling
  für `company_payment_term_weeks` als `integer` vorhanden.

→ **Es handelt sich nicht um eine unvollständige Implementierung.** Die
Admin-Einstellungsseite zum Pflegen der Kontodaten war Teil des Scopes
(`openspec/specs/invoice-bank-details/spec.md`, Requirement "Admin kann
die neuen Felder im Settings-Formular pflegen") und wurde gebaut.

**2. Die eigentliche Deploy-Pipeline für diesen Merge-Commit ist noch
nicht erfolgreich durchgelaufen — das erklärt die Beobachtung des Users.**
Recherche via `gh run list --workflow=deploy.yml` und
`gh api repos/wlmost/dog-school-app/actions/runs/<id>`:

- CI (`ci.yml`) auf dem Merge-Commit `97a1aa4` lief erfolgreich durch
  (Run `31521382055`, `conclusion: success`, 2026-08-11T17:59:22Z).
- Der dadurch automatisch getriggerte Deploy-Lauf (Run `31521189371`,
  `event: workflow_run`, `head_sha: 97a1aa48...`) wurde **abgebrochen**
  (`status: completed`, `conclusion: cancelled`) — es wurde noch kein
  einziger Job gestartet (`jobs: []`), d. h. es fand **keine
  rsync-Übertragung, kein Frontend-Deploy, keine Migration** statt.
- Ca. 1 Minute später wurde derselbe Commit manuell erneut angestoßen
  (Run `31521261040`, `event: workflow_dispatch`, User `wlmost`,
  2026-08-11T18:09:32Z). Dieser Lauf steht seit ~2 Stunden
  (Stand Recherche) im Status `waiting`.
- `gh api .../environments/production` bestätigt: Das GitHub Environment
  `production` hat eine `required_reviewers`-Schutzregel mit Reviewer
  `wlmost` (siehe auch `DEPLOY-WORKFLOW.md:100-105`, Abschnitt 2.1 —
  bewusst so konfiguriert).
- `gh api .../runs/31521261040/pending_deployments` bestätigt: Der
  wartende Lauf benötigt eine manuelle Freigabe
  (`current_user_can_approve: true` für `wlmost`) — diese Freigabe ist
  bisher **nicht erteilt** worden.

→ **Root Cause:** Es hat seit dem Merge von PR #82 noch kein
erfolgreicher Produktions-Deploy stattgefunden. Der automatische Lauf
wurde abgebrochen, der danach manuell gestartete Ersatzlauf wartet auf
die vom User selbst zu erteilende Environment-Freigabe (Required
Reviewer). Der produktive Server läuft daher noch auf dem Stand **vor**
`add-invoice-bank-details` — die fehlenden Kontodaten-Felder in den
Einstellungen sind exakt dadurch erklärt, kein Bug im Code.

**Ungeklärt (nicht sicherheitsrelevant, daher kein Blocker):** Warum der
automatische Lauf `31521189371` abgebrochen wurde, lässt sich über die
GitHub-API nicht mit letzter Sicherheit klären (kein `cancelled_by`-Feld
im Run-Objekt abrufbar). Zeitlich passt es dazu, dass der User ihn selbst
abgebrochen und den manuellen Ersatzlauf gestartet hat — das ist eine
Vermutung, keine belegte Tatsache.

## Rückfragen an den User

Keine — die Ursache ist eindeutig durch die GitHub-Actions-API belegt.

## Empfohlene nächste Aktion

**Kein `@architect`- oder `@dev-*`-Aufruf nötig.** Es gibt keinen
Code-Defekt zu beheben. Empfohlene Aktion für den User direkt:

1. Den wartenden Deploy-Lauf freigeben/genehmigen:
   `https://github.com/wlmost/dog-school-app/actions/runs/31521261040`
   → "Review deployments" → `production` auswählen → "Approve and
   deploy". (Alternativ per `gh`: es gibt aktuell keinen dedizierten
   `gh`-Subbefehl für Environment-Approvals; die Freigabe muss über die
   GitHub-Weboberfläche erfolgen.)
2. Nach erfolgreichem Lauf (grüner Haken) verifizieren, dass die
   Kontodaten-Felder unter Einstellungen → Stammdaten auf der
   Produktionsinstanz sichtbar sind.
3. Optional, falls gewünscht: separat klären/dokumentieren, warum der
   automatische Lauf `31521189371` abgebrochen wurde, damit zukünftige
   Merges nicht denselben manuellen Nacharbeits-Schritt brauchen. Das
   wäre — falls tatsächlich ein Prozess-/Workflow-Problem vorliegt und
   nicht nur ein bewusster manueller Abbruch — ein eigener, separater
   trivialer bis kleiner Change (z. B. Anpassung von
   `.github/workflows/deploy.yml` oder der Environment-Konfiguration),
   nicht Teil dieser Triage.
