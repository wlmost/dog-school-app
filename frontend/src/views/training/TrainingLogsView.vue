<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        Trainings-Dokumentation
      </h1>
    </div>

    <!-- Demo TrainingLog for Testing -->
    <div class="card">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        Demo Training Log - Datei-Upload Testen
      </h2>
      
      <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
        <p class="text-sm text-blue-800 dark:text-blue-200">
          <strong>Hinweis:</strong> Dies ist eine Demo-Seite zum Testen des File Upload Systems.
          Upload-Funktionalität für Trainings-Notizen (Bilder, Videos, Dokumente).
        </p>
      </div>

      <!-- File Upload Component -->
      <div class="mb-6">
        <h3 class="text-md font-medium text-gray-900 dark:text-white mb-3">
          Dateien hochladen
        </h3>
        <FileUpload
          :accepted-types="'image/*,video/*,.pdf,.doc,.docx'"
          :max-size-m-b="50"
          :multiple="true"
          @upload="handleUpload"
          @error="handleUploadError"
        />
      </div>

      <!-- Uploaded Attachments List -->
      <div v-if="attachments.length > 0">
        <h3 class="text-md font-medium text-gray-900 dark:text-white mb-3">
          Hochgeladene Dateien ({{ attachments.length }})
        </h3>
        <AttachmentList
          :attachments="attachments"
          :loading="loadingAttachments"
          :view-mode="viewMode"
          :can-delete="true"
          @delete="handleDelete"
          @view="handleView"
        />
      </div>

      <!-- Empty State -->
      <div v-else-if="!loadingAttachments" class="text-center py-8">
        <svg
          class="w-16 h-16 text-gray-400 dark:text-gray-600 mx-auto mb-4"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
          />
        </svg>
        <p class="text-gray-500 dark:text-gray-400">
          Noch keine Dateien hochgeladen
        </p>
      </div>
    </div>

    <!-- View Mode Toggle -->
    <div class="card">
      <h3 class="text-md font-medium text-gray-900 dark:text-white mb-3">
        Anzeigemodus
      </h3>
      <div class="flex gap-2">
        <button
          @click="viewMode = 'grid'"
          :class="[
            'px-4 py-2 rounded-lg transition-colors',
            viewMode === 'grid'
              ? 'bg-primary-600 text-white'
              : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
          ]"
        >
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
          </svg>
        </button>
        <button
          @click="viewMode = 'list'"
          :class="[
            'px-4 py-2 rounded-lg transition-colors',
            viewMode === 'list'
              ? 'bg-primary-600 text-white'
              : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
          ]"
        >
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    </div>

    <!-- API Information -->
    <div class="card bg-gray-50 dark:bg-gray-800">
      <h3 class="text-md font-medium text-gray-900 dark:text-white mb-3">
        API Endpunkte
      </h3>
      <div class="space-y-2 text-sm">
        <div class="flex items-start">
          <span class="inline-block bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 px-2 py-1 rounded font-mono text-xs mr-2">GET</span>
          <code class="text-gray-700 dark:text-gray-300">/api/v1/training-attachments</code>
        </div>
        <div class="flex items-start">
          <span class="inline-block bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 px-2 py-1 rounded font-mono text-xs mr-2">POST</span>
          <code class="text-gray-700 dark:text-gray-300">/api/v1/training-attachments</code>
        </div>
        <div class="flex items-start">
          <span class="inline-block bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300 px-2 py-1 rounded font-mono text-xs mr-2">GET</span>
          <code class="text-gray-700 dark:text-gray-300">/api/v1/training-attachments/{id}/download</code>
        </div>
        <div class="flex items-start">
          <span class="inline-block bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 px-2 py-1 rounded font-mono text-xs mr-2">DELETE</span>
          <code class="text-gray-700 dark:text-gray-300">/api/v1/training-attachments/{id}</code>
        </div>
      </div>
      
      <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded">
        <p class="text-xs text-yellow-800 dark:text-yellow-200">
          <strong>Hinweis:</strong> Für Uploads ist ein TrainingLog erforderlich. Die Demo verwendet eine Mock-ID.
          In Production würde man Attachments zu echten TrainingLog-Einträgen zuordnen.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import FileUpload from '@/components/FileUpload.vue'
import AttachmentList from '@/components/AttachmentList.vue'
import { trainingAttachmentsApi, type TrainingAttachment } from '@/api/trainingAttachments'
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()

const attachments = ref<TrainingAttachment[]>([])
const loadingAttachments = ref(false)
const viewMode = ref<'grid' | 'list'>('grid')

// Mock TrainingLog ID for demo purposes
// In production, this would come from actual TrainingLog data
const MOCK_TRAINING_LOG_ID = 1

onMounted(() => {
  loadAttachments()
})

async function loadAttachments() {
  loadingAttachments.value = true
  try {
    const response = await trainingAttachmentsApi.getAttachments({
      trainingLogId: MOCK_TRAINING_LOG_ID,
      perPage: 100
    })
    attachments.value = response.data || []
  } catch (error: any) {
    console.error('Fehler beim Laden der Anhänge:', error)
    // Don't show error if training log doesn't exist
    if (error.response?.status !== 404) {
      toastStore.showError('Fehler beim Laden der Anhänge')
    }
  } finally {
    loadingAttachments.value = false
  }
}

async function handleUpload(files: File[]) {
  for (const file of files) {
    try {
      toastStore.showInfo(`Uploading ${file.name}...`)
      
      const attachment = await trainingAttachmentsApi.uploadAttachment({
        trainingLogId: MOCK_TRAINING_LOG_ID,
        file
      })
      
      attachments.value.unshift(attachment)
      toastStore.showSuccess(`${file.name} erfolgreich hochgeladen`)
    } catch (error: any) {
      console.error('Upload error:', error)
      
      if (error.response?.status === 404) {
        toastStore.showError(
          'TrainingLog nicht gefunden. Bitte erstellen Sie zuerst einen TrainingLog-Eintrag oder verwenden Sie eine gültige ID.'
        )
      } else if (error.response?.data?.message) {
        toastStore.showError(error.response.data.message)
      } else {
        toastStore.showError(`Fehler beim Hochladen von ${file.name}`)
      }
    }
  }
}

function handleUploadError(message: string) {
  toastStore.showError(message)
}

async function handleDelete(attachment: TrainingAttachment) {
  try {
    await trainingAttachmentsApi.deleteAttachment(attachment.id)
    attachments.value = attachments.value.filter(a => a.id !== attachment.id)
    toastStore.showSuccess('Datei erfolgreich gelöscht')
  } catch (error) {
    console.error('Delete error:', error)
    toastStore.showError('Fehler beim Löschen der Datei')
  }
}

function handleView(attachment: TrainingAttachment) {
  console.log('Viewing attachment:', attachment)
}
</script>
