# Review: T02 — Hunde & Kunden

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)
(keine)

## Sollte (vor Merge erledigen, kann diskutiert werden)
(keine)

## Könnte (optional, Verbesserung)
- **[Konsistenz]** `frontend/src/components/CustomerDetailModal.vue:57,63` u. a. (`<p class="text-base font-medium">`, `<p class="text-base">` ohne explizite Graufarbe): bleiben laut `task-T02.notes.md` bewusst ohne explizite `dark:`-Klasse, weil sie die globale Body-Textfarbe (`main.css:7`, bereits `dark:`-fähig) erben. Das ist korrekt und kein Fehler, macht die Datei aber inkonsistent zu den unmittelbar benachbarten `<span class="text-sm text-gray-600 dark:text-gray-400">`-Elementen, die explizit gesetzt sind. Rein kosmetisch, keine Handlungsnotwendigkeit.

## Lob
- Alle fünf Modals (`DogFormModal.vue`, `CustomerFormModal.vue`, `CustomerDetailModal.vue`, `CustomerBookingModal.vue`, `CustomerDogRequestModal.vue`) folgen durchgängig dem D1-Dialog-Panel-Muster (`bg-white dark:bg-gray-800`, `text-gray-900 dark:text-white`, `text-gray-700 dark:text-gray-300` für Labels) — keine Abweichungen gefunden.
- `getBookingStatusClass()` in `CustomerDetailModal.vue:133-141` wurde sauber als reine Klassenstring-Erweiterung behandelt, keine Funktionssignatur-/Logikänderung.
- `CustomersView.vue`/`DogsView.vue`: Der Diff geht bewusst über die in `design.md` gezählten `text-gray-*`-Lücken hinaus (z. B. rote Lösch-Buttons, Status-Badge) und begründet das nachvollziehbar mit dem breiteren Akzeptanzkriterium sowie 1:1 übernommenen Referenzstellen — gute Nachvollziehbarkeit gegen Halluzination.
- `git diff --numstat` bestätigt für alle sieben Dateien identische Insert-/Delete-Zahlen (164/164) — reine Attribut-Ersetzungen, keine Strukturänderung, unabhängig nachvollzogen.
- `npm run test` (191/191) und `npm run build` laufen grün und warnungsfrei (unabhängig nachvollzogen).
