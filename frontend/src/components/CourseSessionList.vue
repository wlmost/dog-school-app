<template>
  <div>
    <!-- Loading state -->
    <div v-if="loading" class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
      Lade Termine...
    </div>

    <!-- Error state -->
    <div v-else-if="loadError" class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400">
      Termine konnten nicht geladen werden.
    </div>

    <div v-else>
      <!-- Session table -->
      <table v-if="sessions.length > 0" class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-gray-600 dark:text-gray-400">
            <th class="py-2 pr-4 font-medium">Datum</th>
            <th class="py-2 pr-4 font-medium">Uhrzeit</th>
            <th class="py-2 pr-4 font-medium">Ort</th>
            <th class="py-2 pr-4 font-medium">Status</th>
            <th class="py-2 pr-4 font-medium">Teilnehmer</th>
            <th v-if="editable" class="py-2"></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="session in sessions" :key="session.id">
            <!-- Inline edit form row -->
            <tr v-if="editable && editingId === session.id" class="border-b border-gray-100 dark:border-gray-700/50">
              <td :colspan="editable ? 6 : 5" class="py-3">
                <div class="grid grid-cols-4 gap-2 items-center">
                  <input v-model="editForm.sessionDate" type="date" class="input" />
                  <input v-model="editForm.startTime" type="time" class="input" />
                  <input v-model="editForm.endTime" type="time" class="input" />
                  <input v-model="editForm.location" type="text" class="input" placeholder="Ort (optional)" />
                </div>
                <div class="flex gap-2 mt-2">
                  <button
                    type="button"
                    @click="cancelEdit"
                    class="btn bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm"
                  >
                    Abbrechen
                  </button>
                  <button
                    type="button"
                    @click="resetEdit(session)"
                    class="btn bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm"
                  >
                    Zurücksetzen
                  </button>
                  <button
                    type="button"
                    :disabled="savingId === session.id"
                    @click="saveEdit(session)"
                    class="btn btn-primary text-sm disabled:opacity-50"
                  >
                    <span v-if="savingId === session.id">Speichert...</span>
                    <span v-else>Speichern</span>
                  </button>
                </div>
              </td>
            </tr>

            <!-- Normal display row -->
            <tr v-else class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="py-2 pr-4">{{ formatDate(session.sessionDate) }}</td>
              <td class="py-2 pr-4">{{ formatTime(session.startTime, session.endTime) }}</td>
              <td class="py-2 pr-4">{{ session.location || '–' }}</td>
              <td class="py-2 pr-4">{{ session.status }}</td>
              <td class="py-2 pr-4">{{ formatParticipants(session) }}</td>
              <td v-if="editable" class="py-2">
                <div class="flex gap-2">
                  <button
                    type="button"
                    @click="startEdit(session)"
                    class="btn bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs"
                  >
                    Bearbeiten
                  </button>
                  <button
                    type="button"
                    :disabled="deletingId === session.id"
                    @click="deleteSession(session)"
                    class="btn bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300 text-xs disabled:opacity-50"
                  >
                    <span v-if="deletingId === session.id">Löscht...</span>
                    <span v-else>Löschen</span>
                  </button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>

      <p v-else class="text-gray-500 dark:text-gray-400 text-sm py-2">Keine Termine vorhanden.</p>

      <!-- Add inline form -->
      <div v-if="editable && isAdding" class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
        <div class="grid grid-cols-4 gap-2 items-center">
          <input v-model="addForm.sessionDate" type="date" class="input" />
          <input v-model="addForm.startTime" type="time" class="input" />
          <input v-model="addForm.endTime" type="time" class="input" />
          <input v-model="addForm.location" type="text" class="input" placeholder="Ort (optional)" />
        </div>
        <div class="flex gap-2 mt-2">
          <button
            type="button"
            @click="isAdding = false"
            class="btn bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm"
          >
            Abbrechen
          </button>
          <button
            type="button"
            @click="resetAddForm"
            class="btn bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm"
          >
            Zurücksetzen
          </button>
          <button
            type="button"
            :disabled="adding"
            @click="saveNewSession"
            class="btn btn-primary text-sm disabled:opacity-50"
          >
            <span v-if="adding">Speichert...</span>
            <span v-else>Speichern</span>
          </button>
        </div>
      </div>

      <!-- Add button -->
      <button
        v-if="editable && !isAdding"
        type="button"
        @click="isAdding = true"
        class="btn bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm mt-4"
      >
        + Termin hinzufügen
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'
import { handleApiError, showWarning } from '@/utils/errorHandler'

interface Booking {
  id: number
}

interface Session {
  id: number
  courseId: number
  trainerId: number
  sessionDate: string
  startTime: string | null
  endTime: string | null
  duration: number | null
  maxParticipants: number | null
  location: string | null
  status: string
  notes: string | null
  isPast: boolean
  isFull: boolean
  availableSpots: number | null
  bookings: Booking[]
}

interface SessionForm {
  sessionDate: string
  startTime: string
  endTime: string
  location: string
}

const props = withDefaults(defineProps<{
  courseId: number
  editable?: boolean
}>(), { editable: false })

const loading = ref(false)
const loadError = ref(false)
const sessions = ref<Session[]>([])
const editingId = ref<number | null>(null)
const savingId = ref<number | null>(null)
const deletingId = ref<number | null>(null)
const isAdding = ref(false)
const adding = ref(false)

const editForm = ref<SessionForm>({
  sessionDate: '',
  startTime: '',
  endTime: '',
  location: ''
})

const addForm = ref<SessionForm>({
  sessionDate: '',
  startTime: '',
  endTime: '',
  location: ''
})

onMounted(async () => {
  await loadSessions()
})

async function loadSessions(): Promise<void> {
  loading.value = true
  loadError.value = false
  try {
    const response = await apiClient.get(`/api/v1/courses/${props.courseId}/sessions`)
    sessions.value = response.data.data
  } catch (err) {
    loadError.value = true
    handleApiError(err, 'Termine konnten nicht geladen werden')
  } finally {
    loading.value = false
  }
}

function formatDate(dateStr: string): string {
  if (!dateStr) return '–'
  const [year, month, day] = dateStr.split('-')
  return `${day}.${month}.${year}`
}

function formatTime(startTime: string | null, endTime: string | null): string {
  if (!startTime && !endTime) return '–'
  const start = startTime ? startTime.substring(0, 5) : ''
  const end = endTime ? endTime.substring(0, 5) : ''
  if (start && end) return `${start}–${end}`
  return start || end
}

function formatParticipants(session: Session): string {
  const booked = session.bookings.length
  if (session.maxParticipants != null) {
    return `${booked} / ${session.maxParticipants}`
  }
  if (session.availableSpots != null) {
    return `${booked} (${session.availableSpots} frei)`
  }
  return String(booked)
}

function toTimeInputValue(timeStr: string | null): string {
  if (!timeStr) return ''
  return timeStr.substring(0, 5)
}

function startEdit(session: Session): void {
  editingId.value = session.id
  editForm.value = {
    sessionDate: session.sessionDate,
    startTime: toTimeInputValue(session.startTime),
    endTime: toTimeInputValue(session.endTime),
    location: session.location || ''
  }
}

function cancelEdit(): void {
  editingId.value = null
}

function resetEdit(session: Session): void {
  editForm.value = {
    sessionDate: session.sessionDate,
    startTime: toTimeInputValue(session.startTime),
    endTime: toTimeInputValue(session.endTime),
    location: session.location || ''
  }
}

async function saveEdit(session: Session): Promise<void> {
  savingId.value = session.id
  try {
    const response = await apiClient.put(
      `/api/v1/courses/${props.courseId}/sessions/${session.id}`,
      {
        sessionDate: editForm.value.sessionDate,
        startTime: editForm.value.startTime || null,
        endTime: editForm.value.endTime || null,
        location: editForm.value.location || null
      }
    )
    const updated: Session = response.data.data
    const index = sessions.value.findIndex(s => s.id === session.id)
    if (index !== -1) {
      sessions.value[index] = updated
    }
    if ((response.data.meta?.warnings?.length ?? 0) > 0) {
      showWarning('Hinweis', response.data.meta.warnings.join(' '))
    }
    editingId.value = null
  } catch (err) {
    handleApiError(err, 'Termin konnte nicht gespeichert werden')
  } finally {
    savingId.value = null
  }
}

async function deleteSession(session: Session): Promise<void> {
  if (session.bookings.length > 0) {
    const confirmed = window.confirm(
      `Diese Einheit hat ${session.bookings.length} Buchung(en). Wirklich löschen? Die Buchungen werden ebenfalls gelöscht.`
    )
    if (!confirmed) return
  }
  deletingId.value = session.id
  try {
    await apiClient.delete(`/api/v1/courses/${props.courseId}/sessions/${session.id}`)
    sessions.value = sessions.value.filter(s => s.id !== session.id)
  } catch (err) {
    handleApiError(err, 'Termin konnte nicht gelöscht werden')
  } finally {
    deletingId.value = null
  }
}

function resetAddForm(): void {
  addForm.value = {
    sessionDate: '',
    startTime: '',
    endTime: '',
    location: ''
  }
}

async function saveNewSession(): Promise<void> {
  adding.value = true
  try {
    const response = await apiClient.post(
      `/api/v1/courses/${props.courseId}/sessions`,
      {
        sessionDate: addForm.value.sessionDate,
        startTime: addForm.value.startTime || null,
        endTime: addForm.value.endTime || null,
        location: addForm.value.location || null
      }
    )
    const newSession: Session = response.data.data
    sessions.value.push(newSession)
    if ((response.data.meta?.warnings?.length ?? 0) > 0) {
      showWarning('Hinweis', response.data.meta.warnings.join(' '))
    }
    isAdding.value = false
    resetAddForm()
  } catch (err) {
    handleApiError(err, 'Termin konnte nicht hinzugefügt werden')
  } finally {
    adding.value = false
  }
}
</script>
