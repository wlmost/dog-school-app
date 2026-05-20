<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4 flex-1 max-w-lg">
        <SearchInput
          v-model="searchQuery"
          placeholder="Kurse suchen..."
          class="flex-1"
        />
        <select v-model="filterStatus" @change="loadCourses" class="input max-w-xs">
          <option :value="null">Alle Kurse</option>
          <option value="active">Aktive Kurse</option>
          <option value="planned">Geplante Kurse</option>
          <option value="completed">Abgeschlossene Kurse</option>
          <option value="cancelled">Abgesagte Kurse</option>
        </select>
      </div>
      <button v-if="isTrainerOrAdmin" @click="openCreateModal" class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Neuer Kurs
      </button>
    </div>

    <!-- Courses Grid -->
    <div v-if="loading" class="text-center py-12">
      <svg class="animate-spin h-12 w-12 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="mt-4 text-gray-500">Lade Kursdaten...</p>
    </div>

    <div v-else-if="!courses.length" class="card text-center py-12 text-gray-500">
      Keine Kurse gefunden
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-for="course in courses" :key="course.id" class="card cursor-pointer hover:shadow-lg transition-shadow">
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1">
            <h3 class="text-xl font-semibold text-gray-900 mb-1">{{ course.name }}</h3>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div class="text-sm text-gray-600 course-description" v-html="sanitizeHtml(course.description)"></div>
          </div>
          <span :class="courseStatusClass(course.status)" class="px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap">
            {{ courseStatusLabel(course.status) }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p class="text-xs text-gray-500 mb-1">Startdatum</p>
            <p class="text-sm font-medium text-gray-900">{{ formatDate(course.startDate) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Enddatum</p>
            <p class="text-sm font-medium text-gray-900">{{ formatDate(course.endDate) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Teilnehmer</p>
            <p class="text-sm font-medium text-gray-900">{{ course.currentParticipants || 0 }} / {{ course.maxParticipants }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Typ</p>
            <p class="text-sm font-medium text-gray-900">{{ getCourseTypeLabel(course.courseType) }}</p>
          </div>
          <div class="col-span-2">
            <p class="text-xs text-gray-500 mb-1">Stornierungsfrist</p>
            <p class="text-sm font-medium text-gray-900">{{ course.cancellationDeadlineHours ?? 24 }} Stunden vor Kursbeginn</p>
          </div>
        </div>

        <div class="mb-4">
          <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
            <span>Auslastung</span>
            <span>{{ Math.round((course.currentParticipants || 0) / course.maxParticipants * 100) }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div
              class="bg-primary-600 h-2 rounded-full transition-all"
              :style="{ width: `${Math.round((course.currentParticipants || 0) / course.maxParticipants * 100)}%` }"
            ></div>
          </div>
        </div>

        <div class="flex space-x-2 pt-4 border-t border-gray-200">
          <!-- Trainer: Bearbeiten + Löschen -->
          <template v-if="isTrainerOrAdmin">
            <button @click="editCourse(course)" class="btn btn-primary flex-1">Bearbeiten</button>
            <button @click="deleteCourse(course)" class="btn bg-red-100 hover:bg-red-200 text-red-700 flex-1">Löschen</button>
          </template>
          <!-- Kunde: Buchen oder bereits gebucht -->
          <template v-else-if="isCustomer">
            <span
              v-if="bookedCourseIds.has(course.id)"
              class="inline-flex items-center px-3 py-2 text-sm font-medium text-green-700 bg-green-100 rounded-lg w-full justify-center"
            >
              ✓ Bereits gebucht
            </span>
            <button
              v-else
              @click="openBookingModal(course)"
              class="btn btn-primary flex-1"
            >
              Buchen
            </button>
          </template>
        </div>
      </div>
    </div>

    <!-- Course Form Modal -->
    <CourseFormModal 
      :is-open="showFormModal" 
      :course="selectedCourse"
      @close="closeFormModal"
      @saved="handleCourseSaved"
    />

    <!-- Customer Booking Modal -->
    <CustomerBookingModal
      :is-open="showBookingModal"
      :course-id="selectedCourseForBooking?.id"
      :course-name="selectedCourseForBooking?.name"
      @close="closeBookingModal"
      @booked="onBookingCompleted"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import apiClient from '@/api/client'
import CourseFormModal from '@/components/CourseFormModal.vue'
import CustomerBookingModal from '@/components/CustomerBookingModal.vue'
import SearchInput from '@/components/SearchInput.vue'
import { useAuthStore } from '@/stores/auth'
import { handleApiError, showSuccess } from '@/utils/errorHandler'
import DOMPurify from 'dompurify'

const loading = ref(true)
const filterStatus = ref<string | null>(null)
const searchQuery = ref('')
const courses = ref<any[]>([])
const showFormModal = ref(false)
const selectedCourse = ref<any>(null)

const authStore = useAuthStore()
const isTrainerOrAdmin = computed(() => authStore.isAuthenticated && authStore.isTrainer)
const isCustomer = computed(() => authStore.isAuthenticated && authStore.isCustomer)
const bookedCourseIds = ref<Set<number>>(new Set())
const showBookingModal = ref(false)
const selectedCourseForBooking = ref<any>(null)

onMounted(() => {
  loadCourses()
  if (isCustomer.value) {
    loadOwnBookings()
  }
})

watch(searchQuery, () => {
  loadCourses()
})

async function loadOwnBookings(): Promise<void> {
  try {
    const response = await apiClient.get('/api/v1/bookings')
    const bookings: any[] = response.data.data ?? []
    bookedCourseIds.value = new Set(
      bookings
        .filter((b) => b.status === 'confirmed' || b.status === 'pending')
        .map((b) => b.trainingSession?.course?.id)
        .filter((id): id is number => typeof id === 'number'),
    )
  } catch (err) {
    console.warn('loadOwnBookings fehlgeschlagen', err)
  }
}

function closeBookingModal(): void {
  showBookingModal.value = false
  selectedCourseForBooking.value = null
}

function openBookingModal(course: any): void {
  selectedCourseForBooking.value = course
  showBookingModal.value = true
}

async function onBookingCompleted(): Promise<void> {
  showBookingModal.value = false
  selectedCourseForBooking.value = null
  await loadOwnBookings()
}

async function loadCourses() {
  loading.value = true
  try {
    const params: any = {}
    if (filterStatus.value) {
      params.status = filterStatus.value
    }
    if (searchQuery.value) {
      params.search = searchQuery.value
    }
    
    const response = await apiClient.get('/api/v1/courses', { params })
    courses.value = response.data.data || []
  } catch (error) {
    handleApiError(error, 'Fehler beim Laden der Kurse')
    courses.value = []
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  selectedCourse.value = null
  showFormModal.value = true
}

function editCourse(course: any) {
  selectedCourse.value = course
  showFormModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  selectedCourse.value = null
}

async function handleCourseSaved() {
  await loadCourses()
  closeFormModal()
}

async function deleteCourse(course: any) {
  if (!confirm(`Möchten Sie den Kurs "${course.name}" wirklich löschen?`)) {
    return
  }

  try {
    await apiClient.delete(`/api/v1/courses/${course.id}`)
    await loadCourses()
    showSuccess('Kurs gelöscht', 'Der Kurs wurde erfolgreich gelöscht')
  } catch (error) {
    handleApiError(error, 'Fehler beim Löschen des Kurses')
  }
}

function courseStatusClass(status: string) {
  const classes = {
    active: 'bg-green-100 text-green-800',
    planned: 'bg-blue-100 text-blue-800',
    completed: 'bg-gray-100 text-gray-800',
    cancelled: 'bg-red-100 text-red-800'
  }
  return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800'
}

function courseStatusLabel(status: string) {
  const labels = {
    active: 'Aktiv',
    planned: 'Geplant',
    completed: 'Abgeschlossen',
    cancelled: 'Abgesagt'
  }
  return labels[status as keyof typeof labels] || status
}

function formatDate(date: string) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
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

/** Allowed HTML tags consistent with the backend sanitization allowlist. */
const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre']

function sanitizeHtml(html: string): string {
  if (!html) return ''
  return DOMPurify.sanitize(html, { ALLOWED_TAGS, ALLOWED_ATTR: [] })
}
</script>

<style scoped>
.course-description :deep(p) {
  margin: 0 0 0.25rem 0;
}

.course-description :deep(p:last-child) {
  margin-bottom: 0;
}

.course-description :deep(ul),
.course-description :deep(ol) {
  padding-left: 1.25rem;
  margin: 0.25rem 0;
}

.course-description :deep(ul) {
  list-style-type: disc;
}

.course-description :deep(ol) {
  list-style-type: decimal;
}

.course-description :deep(strong) {
  font-weight: 600;
}

.course-description :deep(em) {
  font-style: italic;
}

.course-description :deep(h2) {
  font-size: 1rem;
  font-weight: 600;
  margin: 0.25rem 0;
}
</style>
