<template>
  <div class="file-upload">
    <!-- Dropzone -->
    <div
      @drop.prevent="handleDrop"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      :class="[
        'border-2 border-dashed rounded-lg p-8 text-center transition-colors',
        isDragging
          ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
          : 'border-gray-300 dark:border-gray-600 hover:border-primary-400 dark:hover:border-primary-500'
      ]"
    >
      <input
        ref="fileInput"
        type="file"
        :accept="acceptedTypes"
        :multiple="multiple"
        @change="handleFileSelect"
        class="hidden"
      />

      <div class="flex flex-col items-center gap-3">
        <!-- Icon -->
        <svg
          class="w-12 h-12 text-gray-400 dark:text-gray-500"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
          />
        </svg>

        <!-- Text -->
        <div>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            <button
              type="button"
              @click="openFileDialog"
              class="font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
            >
              Klicken Sie hier
            </button>
            oder ziehen Sie Dateien hierher
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
            {{ acceptLabel }} (max. {{ maxSizeMB }}MB)
          </p>
        </div>
      </div>
    </div>

    <!-- File Preview List -->
    <div v-if="selectedFiles.length > 0" class="mt-4 space-y-2">
      <div
        v-for="(file, index) in selectedFiles"
        :key="index"
        class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
      >
        <div class="flex items-center gap-3 flex-1 min-w-0">
          <!-- File Icon -->
          <div class="flex-shrink-0">
            <svg
              v-if="file.type.startsWith('image/')"
              class="w-8 h-8 text-blue-500"
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
              v-else-if="file.type.startsWith('video/')"
              class="w-8 h-8 text-purple-500"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
            </svg>
            <svg
              v-else
              class="w-8 h-8 text-gray-500"
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
              {{ file.name }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              {{ formatFileSize(file.size) }}
            </p>
          </div>
        </div>

        <!-- Remove Button -->
        <button
          type="button"
          @click="removeFile(index)"
          class="flex-shrink-0 ml-4 text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
        >
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path
              fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"
            />
          </svg>
        </button>
      </div>
    </div>

    <!-- Upload Button -->
    <div v-if="selectedFiles.length > 0 && !autoUpload" class="mt-4">
      <button
        type="button"
        @click="uploadFiles"
        :disabled="isUploading"
        class="btn btn-primary w-full"
      >
        <svg
          v-if="isUploading"
          class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
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
        {{ isUploading ? 'Wird hochgeladen...' : 'Dateien hochladen' }}
      </button>
    </div>

    <!-- Error Message -->
    <div v-if="errorMessage" class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
      <p class="text-sm text-red-600 dark:text-red-400">{{ errorMessage }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

interface Props {
  acceptedTypes?: string
  maxSizeMB?: number
  multiple?: boolean
  autoUpload?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  acceptedTypes: 'image/*,video/*,.pdf,.doc,.docx',
  maxSizeMB: 50,
  multiple: false,
  autoUpload: false
})

const emit = defineEmits<{
  upload: [files: File[]]
  error: [message: string]
}>()

const fileInput = ref<HTMLInputElement>()
const selectedFiles = ref<File[]>([])
const isDragging = ref(false)
const isUploading = ref(false)
const errorMessage = ref('')

const acceptLabel = computed(() => {
  if (props.acceptedTypes === 'image/*') return 'Bilder'
  if (props.acceptedTypes === 'video/*') return 'Videos'
  if (props.acceptedTypes.includes('image') && props.acceptedTypes.includes('video')) {
    return 'Bilder, Videos und Dokumente'
  }
  return 'Alle Dateitypen'
})

function openFileDialog() {
  fileInput.value?.click()
}

function handleFileSelect(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files) {
    addFiles(Array.from(input.files))
  }
}

function handleDrop(event: DragEvent) {
  isDragging.value = false
  if (event.dataTransfer?.files) {
    addFiles(Array.from(event.dataTransfer.files))
  }
}

function addFiles(files: File[]) {
  errorMessage.value = ''

  // Validate file size
  const invalidFiles = files.filter(file => file.size > props.maxSizeMB * 1024 * 1024)
  if (invalidFiles.length > 0) {
    errorMessage.value = `Einige Dateien überschreiten die maximale Größe von ${props.maxSizeMB}MB`
    emit('error', errorMessage.value)
    return
  }

  if (props.multiple) {
    selectedFiles.value = [...selectedFiles.value, ...files]
  } else {
    selectedFiles.value = files.slice(0, 1)
  }

  if (props.autoUpload) {
    uploadFiles()
  }
}

function removeFile(index: number) {
  selectedFiles.value.splice(index, 1)
}

async function uploadFiles() {
  if (selectedFiles.value.length === 0) return

  isUploading.value = true
  errorMessage.value = ''

  try {
    emit('upload', selectedFiles.value)
    selectedFiles.value = []
  } catch (error) {
    errorMessage.value = 'Fehler beim Hochladen der Dateien'
    emit('error', errorMessage.value)
  } finally {
    isUploading.value = false
  }
}

function formatFileSize(bytes: number): string {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}
</script>
