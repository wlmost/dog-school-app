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
            <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 mb-4">
                {{ booking ? 'Buchung bearbeiten' : 'Neue Buchung' }}
              </DialogTitle>

              <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Dog Selection -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Hund *</label>
                  <select v-model="form.dog_id" @change="onDogChange" required class="input">
                    <option value="">Hund auswählen...</option>
                    <option v-for="dog in dogs" :key="dog.id" :value="dog.id">
                      {{ dog.name }} ({{ dog.customer?.user?.full_name }})
                    </option>
                  </select>
                </div>

                <!-- Course Selection -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Kurs *</label>
                  <select v-model="form.course_id" @change="onCourseChange" required class="input">
                    <option value="">Kurs auswählen...</option>
                    <option v-for="course in uniqueCourses" :key="course.id" :value="course.id">
                      {{ translateCourseName(course.name) }} ({{ getCourseTypeLabel(course.courseType) }})
                    </option>
                  </select>
                </div>

                <!-- Training Session Selection -->
                <div v-if="form.course_id">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Trainingstermin *</label>
                  <select v-model="form.training_session_id" required class="input">
                    <option value="">Termin auswählen...</option>
                    <option v-for="session in availableSessions" :key="session.id" :value="session.id">
                      {{ formatDate(session.session_date) }} - {{ session.session_time }}
                    </option>
                  </select>
                </div>

                <!-- Booking Details -->
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Buchungsdatum *</label>
                    <input v-model="form.booking_date" type="date" required class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select v-model="form.status" class="input">
                      <option value="pending">Ausstehend</option>
                      <option value="confirmed">Bestätigt</option>
                      <option value="cancelled">Storniert</option>
                      <option value="attended">Teilgenommen</option>
                    </select>
                  </div>
                </div>

                <!-- Notes -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                  <textarea v-model="form.notes" rows="3" class="input"></textarea>
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
                    <span v-else>{{ booking ? 'Aktualisieren' : 'Erstellen' }}</span>
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
  booking?: any
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const dogs = ref<any[]>([])
const courses = ref<any[]>([])
const uniqueCourses = ref<any[]>([])
const availableSessions = ref<any[]>([])

const form = ref({
  dog_id: '',
  course_id: '',
  training_session_id: '',
  booking_date: new Date().toISOString().split('T')[0],
  status: 'pending',
  notes: ''
})

onMounted(() => {
  loadDogs()
  loadCourses()
})

watch(() => props.booking, (newBooking) => {
  if (newBooking) {
    form.value = {
      dog_id: newBooking.dog_id,
      course_id: newBooking.training_session?.course_id || '',
      training_session_id: newBooking.training_session_id,
      booking_date: newBooking.booking_date,
      status: newBooking.status,
      notes: newBooking.notes || ''
    }
    if (form.value.course_id) {
      onCourseChange()
    }
  } else {
    resetForm()
  }
}, { immediate: true })

async function loadDogs() {
  try {
    const response = await apiClient.get('/api/v1/dogs')
    dogs.value = response.data.data
  } catch (err) {
    console.error('Error loading dogs:', err)
  }
}

async function loadCourses() {
  try {
    const response = await apiClient.get('/api/v1/courses')
    courses.value = response.data.data
    
    // Remove duplicates by course ID
    const seenIds = new Set()
    uniqueCourses.value = courses.value.filter((course: any) => {
      if (seenIds.has(course.id)) {
        return false
      }
      seenIds.add(course.id)
      return true
    })
  } catch (err) {
    console.error('Error loading courses:', err)
  }
}

function onDogChange() {
  // Could filter available sessions based on dog
}

function onCourseChange() {
  const selectedCourse = courses.value.find((c: any) => c.id === form.value.course_id)
  if (selectedCourse && selectedCourse.sessions) {
    availableSessions.value = selectedCourse.sessions
  } else {
    availableSessions.value = []
  }
  // Reset session selection when course changes
  form.value.training_session_id = ''
}

function getCourseTypeLabel(type: string) {
  const labels: Record<string, string> = {
    group: 'Gruppentraining',
    individual: 'Einzeltraining',
    workshop: 'Workshop',
    open_group: 'Offene Gruppe'
  }
  return labels[type] || type
}

function translateCourseName(name: string) {
  const translations: Record<string, string> = {
    'Puppy Training': 'Welpentraining',
    'Basic Obedience': 'Grundgehorsam',
    'Advanced Training': 'Fortgeschrittenentraining',
    'Agility Course': 'Agility-Kurs',
    'Behavioral Therapy': 'Verhaltenstherapie',
    'Tricks Training': 'Tricks-Training',
    'Rally Obedience': 'Rally Obedience'
  }
  return translations[name] || name
}

function formatDate(date: string) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}

function resetForm() {
  form.value = {
    dog_id: '',
    course_id: '',
    training_session_id: '',
    booking_date: new Date().toISOString().split('T')[0],
    status: 'pending',
    notes: ''
  }
  availableSessions.value = []
}

async function handleSubmit() {
  loading.value = true
  error.value = null

  try {
    const payload = {
      dogId: form.value.dog_id,
      trainingSessionId: form.value.training_session_id,
      bookingDate: form.value.booking_date,
      status: form.value.status,
      notes: form.value.notes || null
    }

    if (props.booking) {
      await apiClient.put(`/api/v1/bookings/${props.booking.id}`, payload)
    } else {
      await apiClient.post('/api/v1/bookings', payload)
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
