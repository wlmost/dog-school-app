<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" @close="closeModal" class="relative z-50">
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
            <DialogPanel class="w-full max-w-3xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 mb-4">
                {{ course ? 'Kurs bearbeiten' : 'Neuer Kurs' }}
              </DialogTitle>

              <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Basic Info -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kursname *</label>
                    <input v-model="form.name" type="text" required class="input" />
                  </div>

                  <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trainer *</label>
                    <select v-model="form.trainer_id" required class="input">
                      <option value="">Trainer auswählen...</option>
                      <option v-for="trainer in trainers" :key="trainer.id" :value="trainer.id">
                        {{ trainer.fullName || trainer.email }}
                      </option>
                    </select>
                  </div>

                  <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                    <HtmlEditor v-model="form.description" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kurstyp *</label>
                    <select v-model="form.course_type" required class="input">
                      <option value="">Typ auswählen...</option>
                      <option value="individual">Einzeltraining</option>
                      <option value="group">Gruppentraining</option>
                      <option value="workshop">Workshop</option>
                      <option value="open_group">Offene Gruppe</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Maximale Teilnehmer *</label>
                    <input v-model.number="form.max_participants" type="number" min="1" required class="input" />
                  </div>
                </div>

                <!-- Dates and Times -->
                <div class="pt-4 border-t border-gray-200 grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Startdatum</label>
                    <input v-model="form.start_date" type="date" class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Enddatum</label>
                    <input v-model="form.end_date" type="date" class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Startzeit</label>
                    <input v-model="form.start_time" type="time" class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Endzeit</label>
                    <input v-model="form.end_time" type="time" class="input" />
                  </div>
                </div>

                <!-- Course Sessions (Kurs-Einheiten) -->
                <div class="pt-4 border-t border-gray-200">
                  <h4 class="text-sm font-medium text-gray-700 mb-3">Kurs-Einheiten</h4>

                  <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Einheitenplanung</label>
                    <select v-model="form.sessionsMode" class="input">
                      <option value="">Keine Einheiten jetzt festlegen</option>
                      <option value="manual">Einzeltermine manuell eintragen</option>
                      <option value="recurrence">Terminserie definieren</option>
                    </select>
                  </div>

                  <!-- Manual sessions -->
                  <div v-if="form.sessionsMode === 'manual'" class="space-y-2">
                    <div
                      v-for="(session, index) in form.sessions"
                      :key="index"
                      class="grid grid-cols-4 gap-2 items-center"
                    >
                      <input v-model="session.sessionDate" type="date" class="input" placeholder="Datum" />
                      <input v-model="session.startTime" type="time" class="input" placeholder="Von" />
                      <input v-model="session.endTime" type="time" class="input" placeholder="Bis" />
                      <div class="flex gap-2 items-center">
                        <input v-model="session.location" type="text" class="input" placeholder="Ort (optional)" />
                        <button
                          type="button"
                          @click="form.sessions.splice(index, 1)"
                          class="flex-shrink-0 text-red-500 hover:text-red-700 font-bold text-lg leading-none"
                          aria-label="Termin entfernen"
                        >&times;</button>
                      </div>
                    </div>
                    <button
                      type="button"
                      @click="addSession"
                      class="btn bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm mt-2"
                    >
                      + Termin hinzufügen
                    </button>
                  </div>

                  <!-- Recurrence form -->
                  <CourseRecurrenceForm
                    v-if="form.sessionsMode === 'recurrence'"
                    v-model="form.recurrenceRule"
                  />
                </div>

                <!-- Pricing -->
                <div class="pt-4 border-t border-gray-200 grid grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dauer (Minuten)</label>
                    <input v-model.number="form.duration_minutes" type="number" min="15" step="15" class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Preis pro Einheit (€)</label>
                    <input v-model.number="form.price_per_session" type="number" step="0.01" min="0" class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Anzahl Einheiten</label>
                    <input v-model.number="form.total_sessions" type="number" min="1" class="input" />
                  </div>
                </div>

                <!-- Additional Info -->
                <div class="pt-4 border-t border-gray-200">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Stornierungsfrist (Stunden)</label>
                      <input
                        v-model.number="form.cancellation_deadline_hours"
                        type="number"
                        min="0"
                        max="720"
                        class="input"
                        placeholder="z.B. 24"
                      />
                      <p class="text-xs text-gray-500 mt-1">Bis wie viele Stunden vor Kursbeginn kann storniert werden? (Standard: 24h)</p>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                      <textarea v-model="form.notes" rows="2" class="input"></textarea>
                    </div>
                  </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-3 pt-4">
                  <button type="button" @click="closeModal" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Abbrechen
                  </button>
                  <button type="button" @click="resetForm" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Zurücksetzen
                  </button>
                  <button type="submit" :disabled="loading" class="btn btn-primary disabled:opacity-50">
                    <span v-if="loading">Speichert...</span>
                    <span v-else>{{ course ? 'Aktualisieren' : 'Erstellen' }}</span>
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

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import apiClient from '@/api/client'
import { handleApiError, showSuccess, showWarning } from '@/utils/errorHandler'
import HtmlEditor from '@/components/HtmlEditor.vue'
import CourseRecurrenceForm, { type RecurrenceRule } from '@/components/CourseRecurrenceForm.vue'

interface SessionRow {
  sessionDate: string
  startTime: string
  endTime: string
  location: string
}

const props = defineProps<{
  isOpen: boolean
  course?: any
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const loading = ref(false)
const trainers = ref<any[]>([])

onMounted(() => {
  loadTrainers()
})

const form = ref<{
  trainer_id: string
  name: string
  description: string
  course_type: string
  max_participants: number
  start_date: string
  end_date: string
  start_time: string
  end_time: string
  price_per_session: number
  total_sessions: number
  duration_minutes: number
  cancellation_deadline_hours: number
  notes: string
  sessionsMode: '' | 'manual' | 'recurrence'
  sessions: SessionRow[]
  recurrenceRule: RecurrenceRule | null
}>({
  trainer_id: '',
  name: '',
  description: '',
  course_type: '',
  max_participants: 8,
  start_date: '',
  end_date: '',
  start_time: '',
  end_time: '',
  price_per_session: 25,
  total_sessions: 8,
  duration_minutes: 60,
  cancellation_deadline_hours: 24,
  notes: '',
  sessionsMode: '',
  sessions: [],
  recurrenceRule: null
})

async function loadTrainers() {
  try {
    const response = await apiClient.get('/api/v1/trainers')
    trainers.value = response.data.data
  } catch (err) {
    console.error('Error loading trainers:', err)
  }
}

watch(() => props.course, (newCourse) => {
  if (newCourse) {
    form.value = {
      trainer_id: newCourse.trainerId || '',
      name: newCourse.name,
      description: newCourse.description || '',
      course_type: newCourse.courseType,
      max_participants: newCourse.maxParticipants,
      start_date: newCourse.startDate || '',
      end_date: newCourse.endDate || '',
      start_time: '',
      end_time: '',
      price_per_session: newCourse.pricePerSession || 25,
      total_sessions: newCourse.totalSessions || 8,
      duration_minutes: newCourse.durationMinutes || 60,
      cancellation_deadline_hours: newCourse.cancellationDeadlineHours ?? 24,
      notes: newCourse.notes || '',
      sessionsMode: '',
      sessions: [],
      recurrenceRule: null
    }
  } else {
    resetForm()
  }
}, { immediate: true })

function resetForm() {
  if (props.course) {
    form.value = {
      trainer_id: props.course.trainerId || '',
      name: props.course.name,
      description: props.course.description || '',
      course_type: props.course.courseType,
      max_participants: props.course.maxParticipants,
      start_date: props.course.startDate || '',
      end_date: props.course.endDate || '',
      start_time: '',
      end_time: '',
      price_per_session: props.course.pricePerSession || 25,
      total_sessions: props.course.totalSessions || 8,
      duration_minutes: props.course.durationMinutes || 60,
      cancellation_deadline_hours: props.course.cancellationDeadlineHours ?? 24,
      notes: props.course.notes || '',
      sessionsMode: '',
      sessions: [],
      recurrenceRule: null
    }
  } else {
    form.value = {
      trainer_id: '',
      name: '',
      description: '',
      course_type: '',
      max_participants: 8,
      start_date: '',
      end_date: '',
      start_time: '',
      end_time: '',
      price_per_session: 25,
      total_sessions: 8,
      duration_minutes: 60,
      cancellation_deadline_hours: 24,
      notes: '',
      sessionsMode: '',
      sessions: [],
      recurrenceRule: null
    }
  }
}

function addSession() {
  form.value.sessions.push({
    sessionDate: '',
    startTime: '',
    endTime: '',
    location: ''
  })
}

async function handleSubmit() {
  loading.value = true

  try {
    const basePayload = {
      trainerId: form.value.trainer_id,
      name: form.value.name,
      description: form.value.description || null,
      courseType: form.value.course_type,
      maxParticipants: form.value.max_participants,
      durationMinutes: form.value.duration_minutes,
      pricePerSession: form.value.price_per_session,
      totalSessions: form.value.total_sessions,
      startDate: form.value.start_date || null,
      endDate: form.value.end_date || null,
      cancellationDeadlineHours: form.value.cancellation_deadline_hours,
      status: 'active'
    }

    const payload: Record<string, unknown> = { ...basePayload }

    if (form.value.sessionsMode === 'manual') {
      payload.sessionsMode = 'manual'
      payload.sessions = form.value.sessions
    } else if (form.value.sessionsMode === 'recurrence') {
      payload.sessionsMode = 'recurrence'
      payload.recurrenceRule = form.value.recurrenceRule
    }

    let response
    if (props.course) {
      response = await apiClient.put(`/api/v1/courses/${props.course.id}`, payload)
      showSuccess('Kurs aktualisiert', 'Der Kurs wurde erfolgreich aktualisiert')
    } else {
      response = await apiClient.post('/api/v1/courses', payload)
      showSuccess('Kurs erstellt', 'Der Kurs wurde erfolgreich erstellt')
    }

    if (response.data.meta?.warnings?.length) {
      showWarning(
        'Hinweis',
        `${response.data.meta.warnings.length} Session(s) konnten wegen bestehender Buchungen nicht verändert werden.`
      )
    }

    emit('saved')
    closeModal()
  } catch (err) {
    handleApiError(err, 'Fehler beim Speichern des Kurses')
  } finally {
    loading.value = false
  }
}

function closeModal() {
  resetForm()
  emit('close')
}
</script>
