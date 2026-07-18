# Review: T04 — Trainer & Rechnungen

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)
(keine)

## Sollte (vor Merge erledigen, kann diskutiert werden)
- **[Konsistenz]** `frontend/src/views/trainers/TrainersView.vue:172,175,178,181` (Tabellenzellen E-Mail/Telefon/Stadt/Spezialisierungen) und `frontend/src/views/invoices/InvoicesView.vue:55,67` (Rechnungsnummer, Gesamtbetrag): nutzen `text-gray-900 dark:text-white`. Die strukturell identischen Zellen in den von anderen Tasks desselben Changes bearbeiteten Schwester-Views (`frontend/src/views/customers/CustomersView.vue` Zeilen zu Name/Hunde, `frontend/src/views/bookings/BookingsView.vue` Zeilen zu Buchungsnr./Kunde/Hund/Kurs, `frontend/src/views/courses/CoursesView.vue` Datenwerte) nutzen durchgängig `text-gray-900 dark:text-gray-100` — exakt das in `design.md`/`tasks.md` als D1-"Werte"-Muster benannte Pendant (`text-gray-900 dark:text-gray-100 für Werte`, aus `DefaultLayout.vue`). `dark:text-white` ist selbst nicht falsch (guter Kontrast), aber innerhalb desselben Changes entsteht dadurch eine sichtbare Inkonsistenz zwischen sonst optisch identischen Tabellen (Trainer/Rechnungen wirken im Dark-Mode "heller/kontrastreicher" als Kunden/Buchungen/Kurse). Vorschlag: in `TrainersView.vue`/`InvoicesView.vue` auf `dark:text-gray-100` vereinheitlichen, um dem D1-Werte-Muster zu folgen.
- **[Konsistenz]** `frontend/src/components/TrainerFormModal.vue:133` und `frontend/src/components/InvoiceFormModal.vue:147` ("Abbrechen"-Button: `text-gray-700 dark:text-gray-200`): In den übrigen Tasks (z. B. `frontend/src/components/DogFormModal.vue`, `frontend/src/components/CustomerFormModal.vue`, `frontend/src/components/anamnesis/AnamnesisFormModal.vue`) wird für denselben Sekundär-Button-Klassenstring (`bg-gray-100 hover:bg-gray-200 text-gray-700`) durchgängig `dark:text-gray-300` verwendet, nicht `dark:text-gray-200`. Beide Werte sind auf `dark:bg-gray-700` gut lesbar, aber es handelt sich um eine unbegründete Abweichung von einem in fünf anderen Dateien desselben Changes etablierten Muster (verstößt gegen das D1-Prinzip "keine neuen Konventionen"). Vorschlag: auf `dark:text-gray-300` vereinheitlichen.

## Könnte (optional, Verbesserung)
(keine)

## Lob
- `InvoiceDetailModal.vue` ist besonders sorgfältig: An mehreren Stellen (z. B. Zeile ~46, 50 laut Diff) wird die Textfarbe explizit ergänzt, obwohl vorher implizit über Body-Vererbung bereits korrekt gewesen wäre — bewusst für Robustheit gegen künftige Body-Style-Änderungen, wie in den Notes begründet.
- Status-Mapping-Funktionen (`getStatusClass()` in `InvoiceDetailModal.vue`, `invoiceStatusClass()` in `InvoicesView.vue`) sind sauber als reine Klassenstring-Erweiterung behandelt, keine Signatur-/Logikänderung.
- Gute, mit `grep`-Belegen unterlegte Dokumentation der verwendeten Konventionen in `task-T04.notes.md` (Abschnitt "Angewendete Konventionen").
- `npm run test` (191/191) und `npm run build` laufen grün und warnungsfrei (unabhängig nachvollzogen).
