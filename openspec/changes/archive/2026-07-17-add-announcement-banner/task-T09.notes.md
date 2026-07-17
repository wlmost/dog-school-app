# Notes T09: Frontend-API-Client + Composable

**Change-ID:** add-announcement-banner
**Agent:** dev-typescript
**Dateien (neu):**
- `frontend/src/api/announcements.ts`
- `frontend/src/api/announcements.test.ts`
- `frontend/src/composables/useAnnouncements.ts`

## Umsetzung

### `frontend/src/api/announcements.ts`

Folgt `frontend/src/api/pricingItems.ts` für `getPublic`/`getAll`/`delete`
(schlanke Funktionen auf einem Objekt-Literal, `response.data.data`-
Unwrapping) und `frontend/src/api/settings.ts:35-64` sowie
`frontend/src/api/trainingAttachments.ts:49-59` für die Multipart-Stellen
(`create`/`update`).

- `interface Announcement` bildet exakt den in `design.md` Abschnitt 5.1/6.1
  verbindlich festgelegten Response-Shape ab (`id`, `title`, `body`,
  `imageUrl: string | null`, `displayDays`, `expiresAt: string | null`,
  `isActive: boolean`, `createdAt: string | null`, `updatedAt: string | null`).
- `interface AnnouncementFormData` (`title`, `body`, `displayDays`,
  `image?: File | null`) — Grundlage für `create()`/`update()`.
- `buildFormData()` (private Hilfsfunktion, DRY zwischen `create`/`update`):
  hängt nur definierte Felder an, damit `update()` mit `Partial<...>` echte
  Teil-Updates senden kann, ohne unberührte Felder mit Leerstrings zu
  überschreiben.
- **`create(data)`:** `apiClient.post('/api/v1/admin/announcements', buildFormData(data), { headers: { 'Content-Type': 'multipart/form-data' } })`.
- **`update(id, data)`:** baut FormData, setzt zusätzlich
  `formData.set('_method', 'PUT')` (bewusst `set()`, nicht `append()` — siehe
  Kommentar in der Datei, identische Begründung wie in `settings.ts:48-50`)
  und sendet ebenfalls per `apiClient.post(...)` **mit** explizitem
  `headers: { 'Content-Type': 'multipart/form-data' }` an
  `/api/v1/admin/announcements/{id}` — **kein** echter HTTP-`PUT`.
- **Beide** Methoden (`create` **und** `update`) setzen den
  `Content-Type`-Header explizit pro Request. Grund (aus der Task-Vorgabe /
  Skeptiker-Korrektur übernommen): `apiClient`
  (`frontend/src/api/client.ts:33-40`) setzt einen Default-Header
  `Content-Type: application/json` auf der Axios-Instanz; ohne expliziten
  Override würde Axios ein `FormData`-Objekt wegen `hasJSONContentType`
  fälschlich zu JSON serialisieren statt als `multipart/form-data` zu senden.
- `getPublic()` → `GET /api/v1/announcements` (öffentlich), `getAll()` →
  `GET /api/v1/admin/announcements` (admin), `delete(id)` →
  `DELETE /api/v1/admin/announcements/{id}`.

### `frontend/src/composables/useAnnouncements.ts`

1:1-Aufbau nach `frontend/src/composables/usePricingItems.ts`: `ref`-State
(`announcements`, `loading`, `error`), fünf async Funktionen
(`loadPublic`, `loadAll`, `createAnnouncement`, `updateAnnouncement`,
`deleteAnnouncement`), jeweils mit try/catch/finally und deutschsprachiger
Fehlermeldung (`error.value = e instanceof Error ? e.message : '...'`).
`createAnnouncement`/`updateAnnouncement`/`deleteAnnouncement` aktualisieren
`announcements.value` lokal (immutabel via `map`/`filter`/Spread), analog zu
`updateItem`/`deleteItem`/`createItem` in `usePricingItems.ts`.

### `frontend/src/api/announcements.test.ts`

Struktur/Stil nach `frontend/src/api/settings.test.ts` (Vitest, `vi.mock('@/api/client', ...)`
mit `get`/`post`/`put`/`delete` als `vi.fn()`, `beforeEach` mit
`vi.clearAllMocks()`). 17 Tests, gruppiert je API-Methode:

- `getPublic`/`getAll`: korrekter Endpunkt, korrektes Unwrapping von
  `{ data: [...] }`.
- `create`: sendet via `apiClient.post` an den Admin-Endpunkt, explizite
  Multipart-Header, FormData enthält Textfelder korrekt, **kein**
  `_method`-Feld, optionales `image`-File wird angehängt bzw. weggelassen,
  Rückgabewert wird korrekt zurückgegeben.
- `update`: sendet via `apiClient.post` (nicht `apiClient.put`), korrekte
  URL mit ID, explizite Multipart-Header, `_method=PUT` im FormData,
  Teil-Update sendet nur geänderte Felder, optionales Ersatzbild wird
  angehängt, Rückgabewert wird korrekt zurückgegeben.
- `delete`: `apiClient.delete` mit korrekter URL.

## Verifikation

```bash
cd frontend
npx vitest run src/api/announcements.test.ts   # 1 Testdatei, 17 Tests, alle grün
npx vitest run                                  # volle Suite: 13 Testdateien, 151 Tests, alle grün
npx vue-tsc -b                                  # keine TypeScript-Fehler
npm run build                                   # vue-tsc -b && vite build, kein Fehler/Warning
```

Kein ESLint im Projekt konfiguriert (`frontend/package.json` enthält kein
`lint`-Script — bereits in `verification.md` "Fehlende QA-Scripts"
dokumentiert), daher kein `npm run lint` ausgeführt.

## Akzeptanzkriterien (aus tasks.md T09)

- [x] `announcementsApi.create(...)` **und** `announcementsApi.update(...)`
      senden den Request mit explizitem
      `headers: { 'Content-Type': 'multipart/form-data' }`
- [x] `announcementsApi.update(...)` sendet ein `POST` mit `_method=PUT` im
      `FormData`, **kein** echtes HTTP-`PUT`
- [x] TypeScript-Interface `Announcement` bildet exakt den Response-Shape aus
      `design.md` Abschnitt 5.1 ab
- [x] `npm run test` (Vitest) läuft grün für `announcements.test.ts`
- [x] `npm run build` läuft ohne TypeScript-Fehler (`vue-tsc -b`)

## Offene Punkte / Hinweise für Reviewer

- Keine Abweichung vom in `design.md` Abschnitt 6.1/6.2 vorgeschlagenen Code
  (der dort abgedruckte Code enthielt bereits die vom Skeptiker geforderte
  `Content-Type`-Header-Ergänzung für **beide** Methoden — die in
  `verification.md` "Widerlegt" beschriebene Lücke war bereits vor Beginn
  dieser Task in `design.md` behoben).
- `AnnouncementFormData['image']` ist `File | null | undefined` (optional +
  nullable), damit `update()` mit `Partial<AnnouncementFormData>` sowohl
  "kein neues Bild" (`undefined`, Feld weglassen) als auch potenziell
  "Bild explizit entfernen" (`null`) unterscheiden könnte — Letzteres wird
  in T09 noch **nicht** genutzt (kein "Bild entfernen"-Feature in diesem
  Change, siehe `design.md` Abschnitt 8.1, YAGNI), `buildFormData()` hängt
  bei `null` aktuell kein Feld an (nur bei truthy `File`). Für T11 relevant,
  falls dort später ein "Bild entfernen"-Schalter gebraucht wird.
- Keine Komponenten (`AnnouncementBanner.vue`, `AnnouncementsView.vue`)
  angefasst — das ist T10/T11, außerhalb des Scopes dieser Task.
