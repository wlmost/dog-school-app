# Review: T06 — Einstellungen, Mail-Vorschau, rechtliche Seiten

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)
(keine)

## Sollte (vor Merge erledigen, kann diskutiert werden)
(keine)

## Könnte (optional, Verbesserung)
- **[Konsistenz]** `frontend/src/views/SettingsView.vue:17,391` (Fehlerbox-Text: `text-red-800 dark:text-red-400`) weicht vom in T02/T05 durchgängig verwendeten Fehlerbox-Muster `text-red-800 dark:text-red-200` ab (z. B. `frontend/src/components/DogFormModal.vue`, `frontend/src/components/anamnesis/AnamnesisFormModal.vue`). Beide Varianten sind laut Recherche im `task-T06.notes.md` bereits vor diesem Change im Projekt vorhanden (`AnnouncementsView.vue` nutzt ebenfalls `dark:text-red-400`) — es handelt sich also nicht um eine neu erfundene Konvention, sondern um die Wahl der einen von zwei bereits koexistierenden Projekt-Konventionen. Kein Blocker, da beide auf `dark:bg-red-900/20` ausreichend Kontrast bieten; für maximale Einheitlichkeit innerhalb dieses Changes wäre `dark:text-red-200` (die in T02/T05 mehrheitlich verwendete Variante) die konsistentere Wahl gewesen.

## Lob
- Bemerkenswert sorgfältige Verifikation: Statt sich auf die `design.md`-Teilbefund-2-Zählwerte für `AgbView.vue`/`DatenschutzView.vue` (23/11, 29/12) zu verlassen, wurde das Zähl-Artefakt (Doppelzählung von `text-gray-300` als Substring in `dark:text-gray-300`) erkannt und aufgelöst; unabhängig per `grep -n 'text-gray-900' ... | grep -v 'dark:text-white'` (und analog für `text-gray-700`/`border-gray-200`) gegen die aktuellen Dateien nachvollzogen — bestätigt: **null** Zeilen ohne `dark:`-Pendant in beiden Dateien, keine Code-Änderung war tatsächlich nötig.
- `EmailPreviewModal.vue:22`: einzige echte Lücke (Schließen-Icon-Ruhezustand) präzise gefunden und mit dem etablierten Sekundär-/Icon-Farbmuster (`PricingModal.vue`) behoben; `dark:prose-invert` (Zeile 79, Rich-Text-Vorschau) korrekt unangetastet gelassen.
- `SettingsView.vue`: 14 Änderungsstellen sauber einzeln mit Datei:Zeile-Beleg und Referenzherkunft dokumentiert (u. a. `EmailTemplateEditor.vue:46`, `AnnouncementsView.vue:22-23`, `ContactView.vue:196-197`); rote/grüne Status-Boxen bewusst über die reine Grau-Heuristik aus `design.md` hinaus mitbehandelt und nachvollziehbar begründet.
- `npm run test` (191/191) und `npm run build` laufen grün und warnungsfrei (unabhängig nachvollzogen).
