<template>
  <div class="announcements-view">
    <div class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Ankündigungen</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">
          Verwalten Sie die auf der Startseite angezeigten Ankündigungen.
        </p>
      </div>
      <button
        type="button"
        class="btn btn-primary"
        @click="openNewForm"
      >
        Neue Ankündigung
      </button>
    </div>

    <!-- Mutation Error State: reports create/update/delete failures without
         hiding the (still valid, unchanged) list below -- kept outside the
         loading/list v-if chain on purpose. -->
    <div v-if="mutationError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
      <p class="text-red-800 dark:text-red-400">{{ mutationError }}</p>
    </div>

    <!-- Loading State -->
    <div v-if="loading && announcements.length === 0" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Load Error State -->
    <div v-else-if="loadError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
      <p class="text-red-800 dark:text-red-400">{{ loadError }}</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="announcements.length === 0" class="card text-center text-gray-500 dark:text-gray-400">
      Noch keine Ankündigungen vorhanden.
    </div>

    <!-- List -->
    <ul v-else class="space-y-4">
      <li
        v-for="announcement in announcements"
        :key="announcement.id"
        class="card flex flex-col sm:flex-row sm:items-start gap-4"
      >
        <img
          v-if="announcement.imageUrl"
          :src="announcement.imageUrl"
          :alt="announcement.title"
          class="w-24 h-24 object-cover rounded-md flex-shrink-0"
        />

        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
              {{ announcement.title }}
            </h2>
            <span
              class="px-2 py-1 text-xs font-medium rounded-full"
              :class="announcement.isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
            >
              {{ announcement.isActive ? 'Aktiv' : 'Abgelaufen' }}
            </span>
          </div>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ textPreview(announcement.body) }}
          </p>
          <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
            Anzeigedauer: {{ announcement.displayDays }} {{ announcement.displayDays === 1 ? 'Tag' : 'Tage' }}
            &middot; Läuft ab: {{ formatDate(announcement.expiresAt) }}
          </p>
        </div>

        <div class="flex sm:flex-col gap-2 flex-shrink-0">
          <button
            type="button"
            class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium text-sm"
            @click="openEditForm(announcement)"
          >
            Bearbeiten
          </button>
          <button
            type="button"
            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 font-medium text-sm"
            @click="handleDelete(announcement)"
          >
            Löschen
          </button>
        </div>
      </li>
    </ul>

    <!-- Create/Edit Form Modal -->
    <div
      v-if="showForm"
      class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 overflow-y-auto py-10"
      @click.self="closeForm"
    >
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg mx-4 flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white">
            {{ editingAnnouncement ? 'Ankündigung bearbeiten' : 'Neue Ankündigung' }}
          </h2>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
            aria-label="Formular schließen"
            @click="closeForm"
          >
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <form class="p-6 space-y-4" @submit.prevent="handleSubmit">
          <div
            v-if="errors.general"
            class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-md p-3"
          >
            <p class="text-sm text-red-800 dark:text-red-400">{{ errors.general }}</p>
          </div>

          <!-- Title -->
          <div>
            <label for="af-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Titel <span class="text-red-500">*</span>
            </label>
            <input
              id="af-title"
              v-model="form.title"
              type="text"
              maxlength="255"
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': errors.title }"
            />
            <p v-if="errors.title" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ errors.title }}
            </p>
          </div>

          <!-- Body -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Text <span class="text-red-500">*</span>
            </label>
            <HtmlEditor v-model="form.body" />
            <p v-if="errors.body" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ errors.body }}
            </p>
          </div>

          <!-- Image -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Bild
            </label>
            <img
              v-if="!form.image && editingAnnouncement?.imageUrl"
              :src="editingAnnouncement.imageUrl"
              alt="Aktuelles Bild"
              class="w-20 h-20 object-cover rounded-md mb-2"
            />
            <FileUpload
              :multiple="false"
              accepted-types="image/*"
              :auto-upload="true"
              @upload="handleImageUpload"
              @error="handleImageError"
            />
            <p v-if="errors.image" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ errors.image }}
            </p>
          </div>

          <!-- Display Days -->
          <div>
            <label for="af-display-days" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
              Anzeigedauer (Tage) <span class="text-red-500">*</span>
            </label>
            <input
              id="af-display-days"
              v-model.number="form.displayDays"
              type="number"
              min="1"
              max="365"
              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': errors.displayDays }"
            />
            <p v-if="errors.displayDays" class="mt-1 text-sm text-red-600 dark:text-red-400">
              {{ errors.displayDays }}
            </p>
          </div>

          <!-- Actions -->
          <div class="flex justify-end space-x-3 pt-2">
            <button
              type="button"
              :disabled="saving"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
              @click="closeForm"
            >
              Abbrechen
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="saving">Speichern...</span>
              <span v-else>{{ editingAnnouncement ? 'Aktualisieren' : 'Anlegen' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import HtmlEditor from '@/components/HtmlEditor.vue'
import FileUpload from '@/components/FileUpload.vue'
import { useAnnouncements } from '@/composables/useAnnouncements'
import type { Announcement, AnnouncementFormData } from '@/api/announcements'

const {
  announcements,
  loading,
  loadError,
  mutationError,
  loadAll,
  createAnnouncement,
  updateAnnouncement,
  deleteAnnouncement,
} = useAnnouncements()

const showForm = ref(false)
const editingAnnouncement = ref<Announcement | null>(null)
const saving = ref(false)

const form = reactive<AnnouncementFormData>({
  title: '',
  body: '',
  displayDays: 30,
  image: null,
})

const errors = reactive<Record<string, string>>({})

/**
 * Strips HTML tags from the sanitized rich-text body for a plain-text
 * list preview, truncated to keep list rows compact.
 */
function textPreview(html: string, maxLength = 140): string {
  const plainText = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
  return plainText.length > maxLength ? `${plainText.slice(0, maxLength)}…` : plainText
}

function formatDate(date: string | null): string {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}

function resetForm() {
  form.title = ''
  form.body = ''
  form.displayDays = 30
  form.image = null
  Object.keys(errors).forEach((key) => delete errors[key])
}

function openNewForm() {
  editingAnnouncement.value = null
  resetForm()
  showForm.value = true
}

function openEditForm(announcement: Announcement) {
  editingAnnouncement.value = announcement
  form.title = announcement.title
  form.body = announcement.body
  form.displayDays = announcement.displayDays
  form.image = null
  Object.keys(errors).forEach((key) => delete errors[key])
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingAnnouncement.value = null
}

function handleImageUpload(files: File[]) {
  form.image = files[0] ?? null
  delete errors.image
}

function handleImageError(message: string) {
  errors.image = message
}

async function handleSubmit() {
  Object.keys(errors).forEach((key) => delete errors[key])
  if (!form.title.trim()) errors.title = 'Titel ist erforderlich'
  if (!form.body.trim()) errors.body = 'Text ist erforderlich'
  if (
    form.displayDays === null ||
    form.displayDays === undefined ||
    !Number.isInteger(Number(form.displayDays)) ||
    Number(form.displayDays) < 1 ||
    Number(form.displayDays) > 365
  ) {
    errors.displayDays = 'Anzeigedauer muss zwischen 1 und 365 Tagen liegen'
  }
  if (Object.keys(errors).length > 0) return

  saving.value = true
  try {
    if (editingAnnouncement.value) {
      await updateAnnouncement(editingAnnouncement.value.id, { ...form })
    } else {
      await createAnnouncement({ ...form })
    }
    if (mutationError.value) {
      errors.general = mutationError.value
    } else {
      closeForm()
    }
  } finally {
    saving.value = false
  }
}

async function handleDelete(announcement: Announcement) {
  if (!window.confirm(`Ankündigung "${announcement.title}" wirklich löschen?`)) return
  await deleteAnnouncement(announcement.id)
}

onMounted(() => {
  loadAll()
})
</script>
