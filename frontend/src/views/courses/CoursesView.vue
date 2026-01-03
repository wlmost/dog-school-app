<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4">
        <select v-model="filterStatus" @change="loadCourses" class="input max-w-xs">
          <option :value="null">Alle Kurse</option>
          <option value="active">Aktive Kurse</option>
          <option value="upcoming">Bevorstehende Kurse</option>
          <option value="completed">Abgeschlossene Kurse</option>
        </select>
      </div>
      <button @click="openCreateModal" class="btn btn-primary">
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
            <p class="text-sm text-gray-600">{{ course.description }}</p>
          </div>
          <span :class="courseStatusClass(course.status)" class="px-3 py-1 text-xs font-medium rounded-full whitespace-nowrap">
            {{ courseStatusLabel(course.status) }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p class="text-xs text-gray-500 mb-1">Startdatum</p>
            <p class="text-sm font-medium text-gray-900">{{ formatDate(course.start_date) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Enddatum</p>
            <p class="text-sm font-medium text-gray-900">{{ formatDate(course.end_date) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Teilnehmer</p>
            <p class="text-sm font-medium text-gray-900">{{ course.current_participants || 0 }} / {{ course.max_participants }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Typ</p>
            <p class="text-sm font-medium text-gray-900">{{ getCourseTypeLabel(course.course_type) }}</p>
          </div>
        </div>

        <div class="mb-4">
          <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
            <span>Auslastung</span>
            <span>{{ Math.round((course.current_participants || 0) / course.max_participants * 100) }}%</span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2">
            <div
              class="bg-primary-600 h-2 rounded-full transition-all"
              :style="{ width: `${Math.round((course.current_participants || 0) / course.max_participants * 100)}%` }"
            ></div>
          </div>
        </div>

        <div class="flex space-x-2 pt-4 border-t border-gray-200">
          <button @click="editCourse(course)" class="btn btn-primary flex-1">Bearbeiten</button>
          <button @click="deleteCourse(course)" class="btn bg-red-100 hover:bg-red-200 text-red-700 flex-1">Löschen</button>
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
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'
import CourseFormModal from '@/components/CourseFormModal.vue'

const loading = ref(true)
const filterStatus = ref<string | null>(null)
const courses = ref<any[]>([])
const showFormModal = ref(false)
const selectedCourse = ref<any>(null)

onMounted(async () => {
  try {
    // Placeholder - replace with actual API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    courses.value = [
      {
        id: 1,
        name: 'Welpentraining',
        description: 'Grundlagen für Welpen von 8-16 Wochen',
        startDate: '05.01.2026',
        endDate: '02.03.2026',
        participants: 6,
        maxParticipants: 8,
        trainer: 'Anna Müller',
        status: 'active'
      },
      {
        id: 2,
        name: 'Agility Fortgeschrittene',
        description: 'Hindernislauf für erfahrene Hunde',
        startDate: '10.01.2026',
        endDate: '28.03.2026',
        participants: 8,
        maxParticipants: 10,
        trainer: 'Max Schmidt',
        status: 'active'
      },
      {
        id: 3,
        name: 'Grundgehorsam',
        description: 'Basisbefehle und Leinenführigkeit',
        startDate: '15.02.2026',
        endDate: '15.04.2026',
        participants: 4,
        maxParticipants: 12,
        trainer: 'Anna Müller',
        status: 'upcoming'
      }
    ]
  } catch (error) {
    console.error('Error loading courses:', error)
  } finally {
    loading.value = false
  }
})

async function loadCourses() {
  loading.value = true
  try {
    const params: any = {}
    if (filterStatus.value) {
      params.status = filterStatus.value
    }
    
    const response = await apiClient.get('/api/v1/courses', { params })
    courses.value = response.data.data
  } catch (error) {
    console.error('Error loading courses:', error)
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
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Löschen des Kurses')
  }
}

function courseStatusClass(status: string) {
  const classes = {
    active: 'bg-green-100 text-green-800',
    upcoming: 'bg-blue-100 text-blue-800',
    completed: 'bg-gray-100 text-gray-800'
  }
  return classes[status as keyof typeof classes] || classes.upcoming
}

function courseStatusLabel(status: string) {
  const labels = {
    active: 'Aktiv',
    upcoming: 'Bevorstehend',
    completed: 'Abgeschlossen'
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
</script>
