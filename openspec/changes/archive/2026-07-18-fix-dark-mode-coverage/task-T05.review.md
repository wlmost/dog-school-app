# Review: T05 — Anamnese

**Gesamtempfehlung:** ok

## Muss (blockiert Abnahme)
(keine)

## Sollte (vor Merge erledigen, kann diskutiert werden)
(keine)

## Könnte (optional, Verbesserung)
- **[Konsistenz]** `frontend/src/components/anamnesis/AnamnesisFormModal.vue` (Fehlerbox: `dark:bg-red-900/20 dark:border-red-800 dark:text-red-200`) vs. `frontend/src/components/anamnesis/AnamnesisTemplateFormModal.vue` (identisches Muster) — beide konsistent zueinander und zu `DogFormModal.vue`/`CustomerDogRequestModal.vue` aus T02. Kein Handlungsbedarf, nur zur Vollständigkeit erwähnt, da in `task-T05.notes.md` selbst als "bewusste Wahl zwischen zwei bestehenden Mustern" (Alternative: `dark:border-red-700 dark:text-red-400` aus `PricingItemForm.vue`) offen dokumentiert wurde — die getroffene Wahl ist in sich konsistent zum überwiegenden Teil des Changes (siehe T02).

## Lob
- Explizite Ergänzung von `border-gray-200 dark:border-gray-700` an Stellen, wo vorher nur ein unspezifisches `border-b`/`border-t` ohne Farbangabe stand (z. B. `AnamnesisDetailModal.vue`, `AnamnesisFormModal.vue`) — korrekt erkannt, dass Tailwinds implizite Default-Randfarbe (`gray-200`) sonst im Dark-Mode einen kaum sichtbaren hellen Rand hinterlassen hätte. Das ist genau die Art von Detailprüfung, die über die reine `grep`-Heuristik aus `design.md` hinausgeht.
- Saubere Abgrenzung zum offenen Triage-Eintrag (Caching-Bug) dokumentiert und per `git diff`-Grep verifiziert, dass ausschließlich `class`/`:class`-Änderungen sowie zwei reine Klassenstring-Literale in `statusClass()` vorgenommen wurden.
- `AnamnesisView.vue`: Tab-Leiste (`:class`-Array-Bindings) korrekt als Teil des Task-Scopes erkannt, obwohl vom einfachen `grep -L "dark:"`-Scan aus `design.md` nicht separat erfasst — zeigt gutes Verständnis der tatsächlichen Akzeptanzkriterien statt blinder Heuristik-Befolgung.
- `npm run test` (191/191) und `npm run build` laufen grün und warnungsfrei (unabhängig nachvollzogen).
