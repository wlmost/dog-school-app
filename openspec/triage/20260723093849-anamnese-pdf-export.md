# Triage: Anamnese PDF-Export

**Pfad:** trivial
**Geschätzter Umfang:** 1 Datei, TypeScript/Vue
**Risiko:** niedrig — Backend vollständig implementiert, keine Schnittstellen-Änderungen notwendig
**Klarheit:** klar — Anforderung ist eindeutig, fast alles bereits implementiert

## Anforderung (Zusammenfassung)
Als Trainer soll es möglich sein, ausgefüllte Anamnesefragebögen als PDF herunterzuladen oder auszudrucken.
Die Anforderung ist faktisch bereits zu ~90 % implementiert: Backend-Endpoint, Blade-Template, API-Layer und ein
PDF-Button in der Listen-Ansicht sind vorhanden. Einzig im Detail-Modal (`AnamnesisDetailModal.vue`) fehlt noch
ein Download-Button, sodass Trainer von der Detail-Ansicht aus kein PDF auslösen können.

## Bestandsaufnahme: Was bereits existiert

### Backend (vollständig implementiert)
| Datei | Status |
|---|---|
| `backend/app/Http/Controllers/AnamnesisResponseController.php` | `downloadPdf()` vorhanden, nutzt `barryvdh/laravel-dompdf` |
| `backend/routes/api.php` (Z. 155) | Route `GET /api/v1/anamnesis-responses/{id}/pdf` registriert |
| `backend/resources/views/pdf/anamnesis.blade.php` | Blade-Template vorhanden |
| `backend/tests/Feature/AnamnesisResponsePdfTest.php` | Vollständige Tests (Auth, Content, Download-Header) vorhanden |

### Frontend (fast vollständig)
| Datei | Status |
|---|---|
| `frontend/src/api/anamnesis.ts` (Z. 194) | `downloadPdf(id)` implementiert |
| `frontend/src/views/anamnesis/AnamnesisView.vue` (Z. 80, 325–336) | PDF-Button in Listen-View + `downloadPdf()`-Funktion vorhanden |
| `frontend/src/components/anamnesis/AnamnesisDetailModal.vue` | **FEHLT:** kein PDF-Button im Footer |

### Vorhandene PDF-Bibliotheken
- `barryvdh/laravel-dompdf: ^3.1` (in `backend/composer.json`) — bereits installiert und in Verwendung

### Bestehende Specs
Kein dedizierter `anamnesis-pdf-export`-Spec vorhanden. Anamnese-Specs:
- `openspec/specs/anamnesis-template-management/spec.md`
- `openspec/specs/anamnesis-answer-question-text/spec.md`
- `openspec/specs/anamnesis-response-display-fields/spec.md`

## Fehlende Implementierung

**Einzige Lücke:** `AnamnesisDetailModal.vue` Footer — PDF-Download-Button ergänzen.

```vue
<\!-- Im Footer der Modal-Komponente, neben "Schließen"-Button -->
<button @click="downloadPdf" class="btn btn-secondary mr-2">PDF herunterladen</button>
```

Die `downloadPdf()`-Logik kann direkt aus `AnamnesisView.vue` (Z. 325–336) übernommen werden
und nutzt `anamnesisResponsesApi.downloadPdf(props.anamnesisResponse.id)`.

## Risiken / Besonderheiten

- **Shared Hosting:** DomPDF läuft rein in PHP (kein Shell-Exec, kein Node) — vollständig shared-hosting-kompatibel ✓
- **PHP 8.2 Kompatibilität:** `barryvdh/laravel-dompdf ^3.1` unterstützt PHP 8.2 ✓
- **MySQL-Kompatibilität:** Nur Eloquent-Abfragen, kein raw SQL ✓
- **Authorization:** Tests zeigen, dass Admin *keinen* Zugriff hat — nur Trainer und zugehörige Kunden. Frontend muss ggf. den Button nur für autorisierte Rollen anzeigen.
- **Drucken-Option:** Die Anforderung erwähnt auch "direkt ausdrucken". Das kann entweder über `window.print()` im Browser-PDF-Viewer (nativ nach Download) oder als separater `print`-Button mit `window.open(url)` umgesetzt werden — kein aufwändiges Eigenbau nötig.

## Empfohlene nächste Aktion

**Direkt von `dev-typescript` umzusetzen (kein Architekt nötig):**

1. `frontend/src/components/anamnesis/AnamnesisDetailModal.vue` — PDF-Download-Button im Footer ergänzen
2. Rolle prüfen: Button nur für Trainer anzeigen (oder für den eigenen Kunden)
3. Ladeindikator während des Downloads optional (analog zu `AnamnesisView.vue`)
4. Kein neuer Spec nötig (Lücke in bestehender Implementierung)

Nach Umsetzung: `npm run lint && npm run test && npm run build` als Pre-Flight.
