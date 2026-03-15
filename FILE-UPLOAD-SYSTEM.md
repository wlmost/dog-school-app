# File Upload System - Dokumentation

## Übersicht

Das File Upload System ermöglicht das Hochladen, Anzeigen und Verwalten von Dateien (Bilder, Videos, Dokumente) für Training Logs.

## Komponenten

### Backend

#### 1. Database Migration
**Datei:** `backend/database/migrations/2025_12_22_185053_create_training_attachments_table.php`

Tabelle `training_attachments`:
- `id` - Primary Key
- `training_log_id` - Foreign Key zu training_logs
- `file_type` - Enum: 'image', 'video', 'document'
- `file_path` - Dateipfad im Storage
- `file_name` - Originaler Dateiname
- `uploaded_at` - Upload-Zeitstempel
- `created_at`, `updated_at`

#### 2. Model
**Datei:** `backend/app/Models/TrainingAttachment.php`

Features:
- Relationship zu TrainingLog
- Helper-Methoden: `isImage()`, `isVideo()`, `isDocument()`
- Scopes: `images()`, `videos()`, `documents()`
- Fillable fields für Mass Assignment

#### 3. Controller
**Datei:** `backend/app/Http/Controllers/Api/TrainingAttachmentController.php`

Endpunkte:
- `GET /api/v1/training-attachments` - Liste aller Attachments (gefiltert)
- `POST /api/v1/training-attachments` - Upload neuer Attachment
- `GET /api/v1/training-attachments/{id}` - Einzelne Attachment anzeigen
- `GET /api/v1/training-attachments/{id}/download` - Attachment herunterladen
- `DELETE /api/v1/training-attachments/{id}` - Attachment löschen

Features:
- Automatische MIME-Type Erkennung
- Eindeutige Dateinamen (timestamp-basiert)
- Rollenbasierte Zugriffskontrolle
- File Storage in `storage/app/public/training-attachments/{trainingLogId}/`

#### 4. Request Validation
**Datei:** `backend/app/Http/Requests/StoreTrainingAttachmentRequest.php`

Validierungsregeln:
- `training_log_id` - Required, muss existieren
- `file` - Required, max 50MB
- Erlaubte MIME-Types: jpg, jpeg, png, gif, webp, mp4, mov, avi, pdf, doc, docx

#### 5. Resource
**Datei:** `backend/app/Http/Resources/TrainingAttachmentResource.php`

JSON Structure:
```json
{
  "id": 1,
  "trainingLogId": 1,
  "fileType": "image",
  "filePath": "training-attachments/1/photo_1234567890.jpg",
  "fileName": "photo.jpg",
  "uploadedAt": "2026-01-24T12:00:00.000000Z",
  "downloadUrl": "http://localhost:8081/api/v1/training-attachments/1/download",
  "createdAt": "2026-01-24T12:00:00.000000Z",
  "updatedAt": "2026-01-24T12:00:00.000000Z"
}
```

#### 6. Policy
**Datei:** `backend/app/Policies/TrainingAttachmentPolicy.php`

Berechtigungen:
- **Admin:** Voller Zugriff auf alle Attachments
- **Trainer:** Kann Attachments hochladen, löschen (nur eigene)
- **Kunde:** Kann nur Attachments ihrer eigenen Hunde sehen

### Frontend

#### 1. API Client
**Datei:** `frontend/src/api/trainingAttachments.ts`

Methoden:
- `getAttachments(filters)` - Liste abrufen
- `getAttachment(id)` - Einzelne Attachment
- `uploadAttachment(data)` - Datei hochladen
- `deleteAttachment(id)` - Datei löschen
- `getDownloadUrl(id)` - Download-URL
- `getPublicUrl(filePath)` - Public URL für Anzeige

#### 2. FileUpload Komponente
**Datei:** `frontend/src/components/FileUpload.vue`

Features:
- Drag & Drop Support
- Multi-File Upload
- File Size Validation (max 50MB)
- File Type Validation
- Preview vor Upload
- Progress Indication
- Error Handling

Props:
- `acceptedTypes` - Erlaubte Dateitypen (default: images, videos, documents)
- `maxSizeMB` - Maximale Dateigröße (default: 50MB)
- `multiple` - Mehrere Dateien erlauben (default: false)
- `autoUpload` - Automatisch hochladen (default: false)

Events:
- `upload` - Emittiert Files[] Array
- `error` - Emittiert Fehlermeldung

#### 3. AttachmentList Komponente
**Datei:** `frontend/src/components/AttachmentList.vue`

Features:
- Grid und List View Modes
- Filter nach Dateityp (Alle, Bilder, Videos, Dokumente)
- Preview für Bilder und Videos
- Download-Funktion
- Löschen-Funktion
- Hover-Overlays mit Aktionen
- Responsive Design

Props:
- `attachments` - Array von TrainingAttachment
- `loading` - Loading State (default: false)
- `viewMode` - 'grid' oder 'list' (default: 'grid')
- `showFilters` - Filter anzeigen (default: true)
- `canDelete` - Löschen erlauben (default: true)

Events:
- `delete` - Emittiert TrainingAttachment
- `view` - Emittiert TrainingAttachment

#### 4. Demo View
**Datei:** `frontend/src/views/training/TrainingLogsView.vue`

Demo-Seite zum Testen des File Upload Systems:
- FileUpload Komponente Integration
- AttachmentList Komponente Integration
- API Endpunkte Dokumentation
- View Mode Toggle (Grid/List)

Route: `/app/training-logs`

## Setup & Installation

### 1. Storage Link erstellen

```bash
docker-compose exec php php artisan storage:link
```

Dies erstellt einen symbolischen Link von `public/storage` zu `storage/app/public`.

### 2. Permissions setzen

```bash
docker-compose exec php chmod -R 775 storage
docker-compose exec php chown -R www-data:www-data storage
```

### 3. Demo-Daten erstellen (optional)

```bash
# Via Tinker
docker-compose exec php php artisan tinker

# Im Tinker:
$trainer = User::where('role', 'trainer')->first();
$customer = Customer::first();
$dog = Dog::create([
    'customer_id' => $customer->id,
    'name' => 'Max',
    'breed' => 'Labrador',
    'date_of_birth' => '2020-01-01',
    'gender' => 'male'
]);
$log = TrainingLog::create([
    'dog_id' => $dog->id,
    'trainer_id' => $trainer->id,
    'progress_notes' => 'Demo Training Log'
]);
```

## Verwendung

### Backend API Usage

#### Upload Attachment

```bash
curl -X POST http://localhost:8081/api/v1/training-attachments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "trainingLogId=1" \
  -F "file=@/path/to/image.jpg"
```

#### Get Attachments

```bash
curl http://localhost:8081/api/v1/training-attachments?trainingLogId=1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Delete Attachment

```bash
curl -X DELETE http://localhost:8081/api/v1/training-attachments/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Frontend Integration Example

```vue
<template>
  <div>
    <!-- Upload Component -->
    <FileUpload
      :max-size-m-b="50"
      :multiple="true"
      @upload="handleUpload"
    />

    <!-- Attachments List -->
    <AttachmentList
      :attachments="attachments"
      :loading="loading"
      @delete="handleDelete"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import FileUpload from '@/components/FileUpload.vue'
import AttachmentList from '@/components/AttachmentList.vue'
import { trainingAttachmentsApi } from '@/api/trainingAttachments'

const attachments = ref([])
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  const response = await trainingAttachmentsApi.getAttachments({
    trainingLogId: 1
  })
  attachments.value = response.data
  loading.value = false
})

async function handleUpload(files) {
  for (const file of files) {
    const attachment = await trainingAttachmentsApi.uploadAttachment({
      trainingLogId: 1,
      file
    })
    attachments.value.unshift(attachment)
  }
}

async function handleDelete(attachment) {
  await trainingAttachmentsApi.deleteAttachment(attachment.id)
  attachments.value = attachments.value.filter(a => a.id !== attachment.id)
}
</script>
```

## Features

### ✅ Implementiert

- [x] File Upload (Bilder, Videos, Dokumente)
- [x] Drag & Drop Support
- [x] Multi-File Upload
- [x] File Size Validation (50MB max)
- [x] File Type Validation
- [x] Automatic MIME Type Detection
- [x] Unique Filenames
- [x] Grid View für Attachments
- [x] List View für Attachments
- [x] Filter nach Dateityp
- [x] Download Funktion
- [x] Löschen Funktion
- [x] Rollenbasierte Zugriffskontrolle
- [x] Image Preview
- [x] Video Preview
- [x] Responsive Design
- [x] Dark Mode Support
- [x] Error Handling
- [x] Loading States

### 🔄 Mögliche Erweiterungen

- [ ] Image Optimization (automatische Kompression)
- [ ] Thumbnail Generation
- [ ] Video Transcoding
- [ ] Batch Upload
- [ ] Progress Bar für große Dateien
- [ ] Cloud Storage Integration (S3, etc.)
- [ ] File Versioning
- [ ] Bulk Delete
- [ ] Search/Filter in Attachments

## Sicherheit

- **File Size Limit:** 50MB per File
- **File Type Validation:** Server-side MIME-Type Check
- **Authorization:** Policy-based Access Control
- **Unique Filenames:** Verhindert Überschreibungen
- **Sanitization:** Filename wird bereinigt (nur A-Z, 0-9, _, -)
- **Storage Isolation:** Files in separaten Ordnern per TrainingLog

## Troubleshooting

### Problem: "Storage link not found"

**Lösung:**
```bash
docker-compose exec php php artisan storage:link
```

### Problem: "Permission denied" beim Upload

**Lösung:**
```bash
docker-compose exec php chmod -R 775 storage
docker-compose exec php chown -R www-data:www-data storage
```

### Problem: "File size exceeds maximum"

**Ursache:** PHP upload_max_filesize oder post_max_size zu klein

**Lösung:** In `docker/php/php.ini` anpassen:
```ini
upload_max_filesize = 50M
post_max_size = 50M
```

### Problem: "MIME type not allowed"

**Lösung:** In `backend/app/Http/Requests/StoreTrainingAttachmentRequest.php` weitere MIME-Types hinzufügen:
```php
'file' => [
    'required',
    'file',
    'max:51200',
    'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,pdf,doc,docx,WEITERE_TYPES',
],
```

## Testing

### Manual Testing Steps

1. **Login als Trainer**
   - Email: `trainer@hundeschule.test`
   - Password: `password`

2. **Zur Demo-Seite navigieren**
   - URL: `http://localhost:5173/app/training-logs`

3. **Datei hochladen**
   - Drag & Drop oder Click to Upload
   - Wähle Bild, Video oder Dokument
   - Prüfe Upload-Progress
   - Verifiziere Success-Nachricht

4. **Attachments anzeigen**
   - Grid View prüfen
   - List View prüfen
   - Filter testen (Alle, Bilder, Videos, Dokumente)

5. **Download testen**
   - Click auf Download-Icon
   - Datei sollte heruntergeladen werden

6. **Delete testen**
   - Click auf Delete-Icon
   - Bestätigung eingeben
   - Attachment sollte entfernt werden

## Performance

- **Lazy Loading:** Bilder werden on-demand geladen
- **Pagination:** API unterstützt Pagination (default 15 per page)
- **Optimized Queries:** Eager Loading von Relationships
- **Caching:** Browser-Caching für statische Files

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Android)

## Accessibility

- Keyboard Navigation
- Screen Reader Support
- ARIA Labels
- Focus States
- Color Contrast (WCAG AA)

---

**Erstellt:** 24.01.2026  
**Status:** ✅ Vollständig implementiert  
**Version:** 1.0.0
