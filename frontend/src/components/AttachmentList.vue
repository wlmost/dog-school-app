<template>
  <div class="attachment-list">
    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
        Anhänge ({{ attachments.length }})
      </h3>
      <div v-if="showFilters" class="flex gap-2">
        <button
          v-for="type in fileTypes"
          :key="type.value"
          @click="filterByType(type.value)"
          :class="[
            'px-3 py-1 text-sm rounded-md transition-colors',
            activeFilter === type.value
              ? 'bg-primary-600 text-white'
              : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
          ]"
        >
          {{ type.label }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-8">
      <svg
        class="animate-spin h-8 w-8 text-primary-600 mx-auto"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path
          class="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        ></path>
      </svg>
      <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Lade Anhänge...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredAttachments.length === 0" class="text-center py-8">
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
      <p class="text-gray-500 dark:text-gray-400">Keine Anhänge vorhanden</p>
    </div>

    <!-- Grid View -->
    <div v-else-if="viewMode === 'grid'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <div
        v-for="attachment in filteredAttachments"
        :key="attachment.id"
        class="group relative bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow overflow-hidden"
      >
        <!-- Image/Video Preview -->
        <div class="aspect-square bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
          <img
            v-if="attachment.fileType === 'image'"
            :src="getPublicUrl(attachment.filePath)"
            :alt="attachment.fileName"
            class="w-full h-full object-cover"
            @error="handleImageError"
          />
          <video
            v-else-if="attachment.fileType === 'video'"
            :src="getPublicUrl(attachment.filePath)"
            class="w-full h-full object-cover"
            preload="metadata"
          />
          <div v-else class="text-gray-400 dark:text-gray-500">
            <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
        </div>

        <!-- Overlay with Actions -->
        <div
          class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-opacity flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100"
        >
          <button
            @click="viewAttachment(attachment)"
            class="p-2 bg-white dark:bg-gray-800 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            title="Anzeigen"
          >
            <svg class="w-5 h-5 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
              />
            </svg>
          </button>
          <button
            @click="downloadAttachment(attachment)"
            class="p-2 bg-white dark:bg-gray-800 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            title="Herunterladen"
          >
            <svg class="w-5 h-5 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
              />
            </svg>
          </button>
          <button
            v-if="canDelete"
            @click="deleteAttachment(attachment)"
            class="p-2 bg-red-600 rounded-full hover:bg-red-700 transition-colors"
            title="Löschen"
          >
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
          </button>
        </div>

        <!-- File Name -->
        <div class="p-2 border-t border-gray-200 dark:border-gray-700">
          <p class="text-xs text-gray-700 dark:text-gray-300 truncate" :title="attachment.fileName">
            {{ attachment.fileName }}
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ formatDate(attachment.uploadedAt) }}
          </p>
        </div>
      </div>
    </div>

    <!-- List View -->
    <div v-else class="space-y-2">
      <div
        v-for="attachment in filteredAttachments"
        :key="attachment.id"
        class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm hover:shadow-md transition-shadow"
      >
        <div class="flex items-center gap-4 flex-1 min-w-0">
          <!-- Icon -->
          <div class="flex-shrink-0">
            <svg
              v-if="attachment.fileType === 'image'"
              class="w-10 h-10 text-blue-500"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                clip-rule="evenodd"
              />
            </svg>
            <svg
              v-else-if="attachment.fileType === 'video'"
              class="w-10 h-10 text-purple-500"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
            </svg>
            <svg
              v-else
              class="w-10 h-10 text-gray-500"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                clip-rule="evenodd"
              />
            </svg>
          </div>

          <!-- File Info -->
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
              {{ attachment.fileName }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ formatDate(attachment.uploadedAt) }}
            </p>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 flex-shrink-0">
          <button
            @click="viewAttachment(attachment)"
            class="p-2 text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg transition-colors"
            title="Anzeigen"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
              />
            </svg>
          </button>
          <button
            @click="downloadAttachment(attachment)"
            class="p-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
            title="Herunterladen"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
              />
            </svg>
          </button>
          <button
            v-if="canDelete"
            @click="deleteAttachment(attachment)"
            class="p-2 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
            title="Löschen"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { TrainingAttachment } from '@/api/trainingAttachments'
import { trainingAttachmentsApi } from '@/api/trainingAttachments'

interface Props {
  attachments: TrainingAttachment[]
  loading?: boolean
  viewMode?: 'grid' | 'list'
  showFilters?: boolean
  canDelete?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  viewMode: 'grid',
  showFilters: true,
  canDelete: true
})

const emit = defineEmits<{
  delete: [attachment: TrainingAttachment]
  view: [attachment: TrainingAttachment]
}>()

const activeFilter = ref<string | null>(null)

const fileTypes = [
  { value: null, label: 'Alle' },
  { value: 'image', label: 'Bilder' },
  { value: 'video', label: 'Videos' },
  { value: 'document', label: 'Dokumente' }
]

const filteredAttachments = computed(() => {
  if (!activeFilter.value) return props.attachments
  return props.attachments.filter(a => a.fileType === activeFilter.value)
})

function filterByType(type: string | null) {
  activeFilter.value = type
}

function getPublicUrl(filePath: string): string {
  return trainingAttachmentsApi.getPublicUrl(filePath)
}

function viewAttachment(attachment: TrainingAttachment) {
  emit('view', attachment)
  // Open in new tab
  window.open(getPublicUrl(attachment.filePath), '_blank')
}

function downloadAttachment(attachment: TrainingAttachment) {
  window.open(attachment.downloadUrl, '_blank')
}

function deleteAttachment(attachment: TrainingAttachment) {
  if (confirm(`Möchten Sie "${attachment.fileName}" wirklich löschen?`)) {
    emit('delete', attachment)
  }
}

function formatDate(dateString: string): string {
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('de-DE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

function handleImageError(event: Event) {
  const img = event.target as HTMLImageElement
  img.style.display = 'none'
}
</script>
