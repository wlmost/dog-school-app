<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogPanel,
  DialogTitle,
} from '@headlessui/vue'
import { isAxiosError } from 'axios'
import apiClient from '@/api/client'
import { handleApiError, showSuccess, showWarning } from '@/utils/errorHandler'

interface Session {
  id: number
  sessionDate: string // 'YYYY-MM-DD'
  startTime: string | null
  endTime: string | null
  location: string | null
  status: string
}

interface CourseRun {
  id: number
  startDate: string
  endDate: string | null
  status: string
  sessions: Session[]
}

interface Dog {
  id: number
  name: string
}

interface Props {
  isOpen: boolean
  courseId: number | undefined
  courseName: string | undefined
}
const props = withDefaults(defineProps<Props>(), {
  courseId: undefined,
  courseName: undefined,
})

const emit = defineEmits<{
  close: []
  booked: []
}>()

// Legacy session state (used when no runs are present)
const sessions = ref<Session[]>([])
// CourseRun state
const runs = ref<CourseRun[]>([])
const selectedRunId = ref<number | null>(null)

const dogs = ref<Dog[]>([])
const customerId = ref<number | null>(null)
const selectedSessionIds = ref<Set<number>>(new Set())
const selectedDogId = ref<number | null>(null)
const notes = ref('')
const loading = ref(false)
const submitting = ref(false)
const loadError = ref<string | null>(null)

/** Sessions to display: from the selected run (CourseRun flow) or standalone sessions (legacy flow). */
const displaySessions = computed<Session[]>(() => {
  if (runs.value.length > 0) {
    const run = runs.value.find((r) => r.id === selectedRunId.value)
    return run?.sessions ?? []
  }
  return sessions.value
})

watch(
  () => props.isOpen,
  async (isOpen) => {
    if (!isOpen) {
      resetForm()
      return
    }
    if (!props.courseId) return
    loading.value = true
    loadError.value = null
    try {
      const [sessionsRes, profileRes, dogsRes, runsRes] = await Promise.all([
        apiClient.get(`/api/v1/courses/${props.courseId}/sessions`),
        apiClient.get('/api/v1/customers/profile'),
        apiClient.get('/api/v1/dogs'),
        apiClient.get(`/api/v1/courses/${props.courseId}/runs`),
      ])

      // CourseRun path: filter to active runs only
      const allRuns: CourseRun[] = runsRes.data.data ?? runsRes.data
      runs.value = allRuns.filter((r: CourseRun) => r.status === 'active')

      if (runs.value.length === 1 && runs.value[0]) {
        // Auto-select the only available run
        selectedRunId.value = runs.value[0].id
      }

      // Legacy path: standalone sessions (used only when no runs exist)
      if (runs.value.length === 0) {
        const allSessions: Session[] = sessionsRes.data.data ?? sessionsRes.data
        sessions.value = allSessions.filter((s) => s.status === 'scheduled')
        selectedSessionIds.value = new Set(sessions.value.map((s) => s.id))
      }

      customerId.value = profileRes.data.data.id

      dogs.value = dogsRes.data.data ?? dogsRes.data
      if (dogs.value.length === 1 && dogs.value[0]) {
        selectedDogId.value = dogs.value[0].id
      }
    } catch (err) {
      loadError.value = 'Fehler beim Laden der Daten.'
      handleApiError(err)
    } finally {
      loading.value = false
    }
  },
)

const canSubmit = computed(() => {
  if (runs.value.length > 0) {
    return selectedRunId.value !== null && selectedDogId.value !== null && customerId.value !== null
  }
  return (
    selectedSessionIds.value.size > 0 &&
    selectedDogId.value !== null &&
    customerId.value !== null
  )
})

function toggleSession(id: number): void {
  const set = new Set(selectedSessionIds.value)
  if (set.has(id)) set.delete(id)
  else set.add(id)
  selectedSessionIds.value = set
}

function extractErrorMessage(err: unknown): string {
  if (isAxiosError(err)) {
    return err.response?.data?.message ?? err.message
  }
  return 'Unbekannter Fehler'
}

async function handleSubmit(): Promise<void> {
  submitting.value = true

  if (runs.value.length > 0) {
    // ── CourseRun booking path ──────────────────────────────────────────────
    try {
      const res = await apiClient.post(`/api/v1/course-runs/${selectedRunId.value}/book`, {
        customerId: customerId.value,
        dogId: selectedDogId.value,
        notes: notes.value || undefined,
      })
      const skipped: string[] = res.data.skipped ?? []
      if (skipped.length > 0) {
        showWarning('Einige Termine übersprungen', skipped.join('\n'))
      }
      showSuccess('Buchung erfolgreich', 'Kursdurchlauf erfolgreich gebucht.')
      emit('booked')
      resetForm()
      emit('close')
    } catch (err) {
      handleApiError(err)
    } finally {
      submitting.value = false
    }
  } else {
    // ── Legacy per-session booking path ────────────────────────────────────
    let successCount = 0
    const errors: string[] = []
    for (const sessionId of selectedSessionIds.value) {
      try {
        await apiClient.post('/api/v1/bookings', {
          trainingSessionId: sessionId,
          customerId: customerId.value,
          dogId: selectedDogId.value,
          notes: notes.value || undefined,
        })
        successCount++
      } catch (err) {
        errors.push(`Termin ${sessionId}: ${extractErrorMessage(err)}`)
      }
    }
    submitting.value = false
    if (successCount > 0) {
      showSuccess('Buchung erfolgreich', `${successCount} Termin(e) erfolgreich gebucht.`)
      emit('booked')
      resetForm()
      emit('close')
    }
    if (errors.length > 0) {
      showWarning('Einige Termine konnten nicht gebucht werden', errors.join('\n'))
    }
  }
}

function resetForm(): void {
  sessions.value = []
  runs.value = []
  selectedRunId.value = null
  dogs.value = []
  customerId.value = null
  selectedSessionIds.value = new Set()
  selectedDogId.value = null
  notes.value = ''
  loadError.value = null
}

function formatSessionDate(dateStr: string): string {
  const [year, month, day] = dateStr.split('-')
  return `${day}.${month}.${year}`
}

function formatTime(timeStr: string | null): string {
  if (!timeStr) return ''
  return timeStr.slice(0, 5)
}

function formatRunLabel(run: CourseRun): string {
  const start = formatSessionDate(run.startDate)
  if (!run.endDate) return `ab ${start}`
  const end = formatSessionDate(run.endDate)
  return `${start} – ${end}`
}
</script>

<template>
  <TransitionRoot appear :show="props.isOpen" as="template">
    <Dialog as="div" @close="emit('close')" class="relative z-50">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black bg-opacity-25" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel
              class="w-full max-w-lg transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all"
            >
              <DialogTitle
                as="h3"
                class="text-lg font-medium leading-6 text-gray-900 mb-4"
              >
                Kurs buchen — {{ props.courseName }}
              </DialogTitle>

              <!-- Loading -->
              <div v-if="loading" class="py-8 text-center text-gray-500">
                Lade Daten...
              </div>

              <!-- Fehler beim Laden -->
              <div v-else-if="loadError" class="py-4">
                <p class="text-red-600 text-sm mb-4">{{ loadError }}</p>
                <div class="flex justify-end">
                  <button
                    type="button"
                    @click="emit('close')"
                    class="btn bg-gray-100 hover:bg-gray-200 text-gray-700"
                  >
                    Abbrechen
                  </button>
                </div>
              </div>

              <!-- Keine buchbaren Termine (nur im Legacy-Pfad ohne Runs) -->
              <div v-else-if="runs.length === 0 && sessions.length === 0" class="py-4">
                <p class="text-gray-500 text-sm mb-4">
                  Keine buchbaren Termine verfügbar.
                </p>
                <div class="flex justify-end">
                  <button
                    type="button"
                    @click="emit('close')"
                    class="btn bg-gray-100 hover:bg-gray-200 text-gray-700"
                  >
                    Abbrechen
                  </button>
                </div>
              </div>

              <!-- Formular -->
              <form v-else @submit.prevent="handleSubmit" class="space-y-4">

                <!-- ── CourseRun-Pfad ──────────────────────────────────── -->

                <!-- Kursdurchlauf-Auswahl (nur wenn Runs vorhanden) -->
                <div v-if="runs.length > 0">
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Kursdurchlauf *
                  </label>
                  <select v-model="selectedRunId" required class="input">
                    <option :value="null">Durchlauf auswählen...</option>
                    <option v-for="run in runs" :key="run.id" :value="run.id">
                      {{ formatRunLabel(run) }}
                    </option>
                  </select>
                </div>

                <!-- Enthaltene Termine des gewählten Durchlaufs (read-only) -->
                <div v-if="runs.length > 0 && selectedRunId !== null">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Enthaltene Termine
                  </label>
                  <ul class="space-y-1 rounded-lg border border-gray-200 p-3 bg-gray-50">
                    <li v-if="displaySessions.length === 0" class="text-sm text-gray-500">
                      Keine Termine in diesem Durchlauf.
                    </li>
                    <li
                      v-for="session in displaySessions"
                      :key="session.id"
                      class="text-sm text-gray-800"
                    >
                      {{ formatSessionDate(session.sessionDate) }}
                      <span v-if="session.startTime">
                        {{ formatTime(session.startTime) }} – {{ formatTime(session.endTime) }}
                      </span>
                      <span v-if="session.location" class="text-gray-500">
                        · {{ session.location }}
                      </span>
                    </li>
                  </ul>
                </div>

                <!-- ── Legacy-Pfad (keine Runs) ───────────────────────── -->

                <!-- Session-Auswahl -->
                <div v-if="runs.length === 0">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Termine
                  </label>

                  <!-- Einzeltermin: nur Anzeige -->
                  <div
                    v-if="sessions.length === 1"
                    class="text-sm text-gray-800 rounded-lg border border-gray-200 p-3 bg-gray-50"
                  >
                    {{ sessions[0]?.sessionDate ? formatSessionDate(sessions[0].sessionDate) : '' }}
                    <span v-if="sessions[0]?.startTime">
                      {{ formatTime(sessions[0]?.startTime ?? null) }} –
                      {{ formatTime(sessions[0]?.endTime ?? null) }}
                    </span>
                    <span v-if="sessions[0]?.location" class="text-gray-500">
                      · {{ sessions[0]?.location }}
                    </span>
                  </div>

                  <!-- Mehrere Termine: Checkboxen -->
                  <div v-else class="space-y-2">
                    <label
                      v-for="session in sessions"
                      :key="session.id"
                      class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50"
                    >
                      <input
                        type="checkbox"
                        :checked="selectedSessionIds.has(session.id)"
                        @change="toggleSession(session.id)"
                        class="rounded border-gray-300"
                      />
                      <span class="text-sm text-gray-800">
                        {{ formatSessionDate(session.sessionDate) }}
                        <span v-if="session.startTime">
                          {{ formatTime(session.startTime) }} –
                          {{ formatTime(session.endTime) }}
                        </span>
                        <span v-if="session.location" class="text-gray-500">
                          · {{ session.location }}
                        </span>
                      </span>
                    </label>
                  </div>
                </div>

                <!-- Hund-Auswahl -->
                <div>
                  <label
                    for="dog-select"
                    class="block text-sm font-medium text-gray-700 mb-1"
                  >
                    Hund *
                  </label>
                  <div v-if="dogs.length === 0" class="text-sm text-gray-500">
                    Bitte zuerst einen Hund anlegen.
                  </div>
                  <select
                    v-else
                    id="dog-select"
                    v-model="selectedDogId"
                    required
                    class="input"
                  >
                    <option :value="null">Hund auswählen...</option>
                    <option v-for="dog in dogs" :key="dog.id" :value="dog.id">
                      {{ dog.name }}
                    </option>
                  </select>
                </div>

                <!-- Notizen (optional) -->
                <div>
                  <label
                    for="booking-notes"
                    class="block text-sm font-medium text-gray-700 mb-1"
                  >
                    Anmerkungen
                    <span class="text-gray-400">(optional)</span>
                  </label>
                  <textarea
                    id="booking-notes"
                    v-model="notes"
                    maxlength="1000"
                    rows="3"
                    class="input resize-none"
                    placeholder="Besonderheiten, Hinweise..."
                  />
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 justify-end pt-2">
                  <button
                    type="button"
                    @click="emit('close')"
                    class="btn bg-gray-100 hover:bg-gray-200 text-gray-700"
                  >
                    Abbrechen
                  </button>
                  <button
                    type="submit"
                    :disabled="!canSubmit || submitting"
                    class="btn btn-primary"
                  >
                    {{ submitting ? 'Buche...' : 'Buchen' }}
                  </button>
                </div>
              </form>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
