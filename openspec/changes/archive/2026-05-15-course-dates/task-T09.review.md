# Review: T09 — CourseDetailView.vue + Router

**Gesamtempfehlung:** nacharbeit-nötig

---

## Muss (blockiert Abnahme)

### [Korrektheit] `frontend/src/views/CourseDetailView.vue:228`
`CourseSessionList` ruft intern immer `GET /api/v1/courses/{courseId}/sessions` auf — dieser Endpunkt liegt hinter `auth:sanctum` (`backend/routes/api.php:66`). Für nicht-eingeloggte Besucher liefert der Aufruf HTTP 401; die Komponente zeigt dann den `loadError`-Zustand: *"Termine konnten nicht geladen werden."*

Das bricht das Akzeptanzkriterium *„Für nicht eingeloggte User / Kunden ist die Liste nur lesend"*: sie sehen keine Termine, sondern eine Fehlermeldung. Der Befund ist in `openspec/changes/course-dates/verification.md` bereits als Risiko genannt worden, wurde aber nicht aufgelöst.

**Mögliche Lösungsrichtungen (zur Entscheidung durch Entwickler/Architekt):**
- Option A: `CourseSessionList` um ein optionales `sessions`-Prop erweitern; `CourseDetailView` übergibt die im Public-Response eingebetteten Sessions.
- Option B: Die Sessionsanzeige für nicht-eingeloggte User separat implementieren (eigene schreibgeschützte Session-Liste ohne API-Call, basierend auf den in der Kursantwort enthaltenen Sessions).
- Option C: Einen öffentlichen Sessions-Endpunkt anlegen (eigener Route-Task).

---

## Sollte (vor Merge erledigen)

### [Sicherheit] `frontend/src/views/CourseDetailView.vue:157`
`v-html="course.description"` rendert HTML direkt ins DOM. In den Notes steht *"Server-generierter Inhalt aus HtmlEditor — akzeptabel"*. Akzeptabel ist das nur, wenn der Backend-Controller die Beschreibung vor dem Speichern sanitiert (z.B. mit HTMLPurifier oder einem Äquivalent). Eine kurze Nachfrage oder ein Kommentar im Code, der auf die serverseitige Sanitisierung hinweist, würde späteren Fehlern vorbeugen.  
Angriffsszenario: Trainer mit Kurs-Editierrecht speichert `<script>document.cookie…</script>` als Beschreibung → wird bei allen Besuchern der Detailseite ausgeführt.

### [Korrektheit/Spec] CTA für nicht eingeloggte User
Die Spec (`tasks.md:534`) verlangt eine Schaltfläche *„Termin buchen"* die auf den bestehenden Buchungsflow verweist. Die Implementierung zeigt stattdessen „Kontakt aufnehmen" (`/contact`) und „Anmelden" (`/login`). Falls der Buchungsflow existiert, sollte der primäre CTA gemäß Spec *„Termin buchen"* heißen. Falls der Buchungsflow noch nicht erreichbar ist, sollte das in den Notes begründet werden.

---

## Könnte (optional)

### [TypeScript] `frontend/src/views/CourseDetailView.vue:43`
```ts
const courseId = computed(() => Number(route.params.id))
```
`route.params.id` ist typisiert als `string | string[]`. Bei einem unerwarteten Array-Wert liefert `Number([…])` `NaN`; der API-Aufruf landet dann auf `/api/v1/courses/NaN`. Das ist funktional unproblematisch (Backend antwortet 404, wird als `notFound` behandelt), aber ein expliziter Guard wäre robuster:
```ts
const courseId = computed(() => {
  const id = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id
  return Number(id)
})
```

### [Konsistenz/Spec] Router `frontend/src/router/index.ts:42`
Spec (`tasks.md:509`) definiert `name: 'course-detail'` (kebab-case). Die Implementierung verwendet `name: 'CourseDetail'` (PascalCase), was konsistent mit allen anderen Routen in der Datei ist. Sofern nirgendwo `{ name: 'course-detail' }` als Link-Target referenziert wird, ist das kein Funktionsfehler — aber eine Abweichung von der Spec. Empfehlung: Spec und Implementierung angleichen (am einfachsten Spec auf PascalCase korrigieren, da das der bestehenden Konvention entspricht).

---

## Lob

- Die duale Endpunkt-Logik (authenticated vs. public) ist klar und korrekt in `loadCourse()` umgesetzt.
- Die Preis-Bedingung `course.price != null` fängt sowohl `null` als auch `undefined` korrekt ab — Gäste sehen den Preis-Block nie.
- 404-Handling via `axios.isAxiosError + err.response?.status === 404` ist idiomatisch und trennt sauber zwischen "nicht gefunden" und anderen Fehlern.
- Router-Integration (PublicLayout-Children, `requiresAuth: false`) ist korrekt; der Navigation Guard greift dank `to.meta.requiresAuth !== false` nicht für diese Route.
- `CourseFormModal` wird nur für Trainer/Admin in den DOM gerendert (`v-if="isTrainerOrAdmin"`), kein unnötiges Mounting für öffentliche Nutzer.

---

## Re-Review (nach Fix vom 15.05.2026)

**Status: APPROVED**

---

### MUSS-Befund aus erstem Review: BEHOBEN ✓

Der Sessions-Bereich wurde korrekt in zwei Rendering-Pfade aufgetrennt:

- **Trainer/Admin** (`v-if="isTrainerOrAdmin"`, Zeile 237): `<CourseSessionList :courseId="course.id" :editable="true" />` — unverändert, hat Auth-Kontext.
- **Gäste** (`v-else`, Zeile 239–285): Read-only-Tabelle aus `course.sessions`, direkt aus dem `publicShow`-Response eingebettet. **Kein weiterer API-Call**, kein 401.

Der Pfad ist vollständig korrekt: Kein Gast-Rendering-Zweig berührt `CourseSessionList`. Der ursprüngliche Befund ist vollständig aufgelöst.

---

### TypeScript-Qualität der neuen Typen: in Ordnung

- **`PublicSession`-Interface** (`CourseDetailView.vue:17–25`): Alle Felder korrekt getypt. `sessionDate: string` (nie null — korrekt, da Backend immer ein Datum liefert), nullable Felder als `string | null` bzw. `number | null` deklariert.
- **`Course.sessions?: PublicSession[]`** (Zeile 45): Optional (`?`) — korrekt, da der Trainer-Endpunkt das Feld nicht enthält; `v-else`-Zweig greift ausschließlich für Gäste, die den Public-Endpunkt nutzen.
- **`formatSessionDate(dateStr: string)`** (Zeile 71): Nimmt `string` — passt zum Interface. Timezone-sicheres String-Split statt `new Date()` ist bewusst und korrekt.
- **`formatSessionTime(timeStr: string | null)`** (Zeile 76): Nimmt `string | null`, gibt `string` zurück — korrekt. Im Template wird sie nur nach `v-if="session.startTime"` aufgerufen; TypeScript-Typfehler entstehen nicht, da die Funktion den Null-Fall selbst behandelt.

---

### Neue Probleme durch den Fix: keine

Der Fix ist isoliert. `CourseSessionList.vue` wurde nicht verändert; bestehende Tests bleiben unberührt.

Kleiner redaktioneller Hinweis (kein Befund, da bereits im ersten Review nicht erwähnt): `isTrainerOrAdmin` (Zeile 52) prüft nur `authStore.isTrainer`, nicht explizit Admins. Wenn Admins keine `isTrainer`-Flag tragen, würden sie den Gast-Pfad sehen. Das ist eine pre-existierende Eigenschaft der Auth-Store-Logik, nicht durch diesen Fix eingeführt.

---

### Offene Punkte aus dem ersten Review

| Befund | Prio | Status |
|--------|------|--------|
| `v-html`-Kommentar zur serverseitigen Sanitisierung | Sollte | **Adressiert** — Kommentar an Zeile 159 ergänzt |
| CTA „Termin buchen" vs. „Kontakt aufnehmen" | Sollte | **Noch offen** — kein Buchungsflow implementiert; bleibt als Sollte bestehen bis Flow verfügbar |
| `courseId`-NaN-Guard | Könnte | Nicht geändert — akzeptabel |
| Router-Name kebab-case/PascalCase | Könnte | Nicht geändert — akzeptabel |

---

### Fazit

Der MUSS-Befund ist vollständig und korrekt behoben. Die neu eingeführten Typen und Hilfsfunktionen sind solide. **Keine neuen Blocker.** Task ist abnahmefähig.
