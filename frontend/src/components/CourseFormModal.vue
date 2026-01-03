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
                    <textarea v-model="form.description" rows="3" class="input"></textarea>
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
                  <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                  <textarea v-model="form.notes" rows="2" class="input"></textarea>
                </div>

                <!-- Error Message -->
                <div v-if="error" class="rounded-md bg-red-50 p-4">
                  <p class="text-sm text-red-800">{{ error }}</p>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-3 pt-4">
                  <button type="button" @click="closeModal" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Abbrechen
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

const props = defineProps<{
  isOpen: boolean
  course?: any
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const trainers = ref<any[]>([])

onMounted(() => {
  loadTrainers()
})

const form = ref({
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
  duration_minutes: 60
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
      duration_minutes: newCourse.durationMinutes || 60
    }
  } else {
    resetForm()
  }
}, { immediate: true })

function resetForm() {
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
    duration_minutes: 60
  }
}

async function handleSubmit() {
  loading.value = true
  error.value = null

  try {
    const payload = {
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
      status: 'active'
    }

    if (props.course) {
      await apiClient.put(`/api/v1/courses/${props.course.id}`, payload)
    } else {
      await apiClient.post('/api/v1/courses', payload)
    }

    emit('saved')
    closeModal()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Ein Fehler ist aufgetreten'
  } finally {
    loading.value = false
  }
}

function closeModal() {
  resetForm()
  error.value = null
  emit('close')
}
</script>
