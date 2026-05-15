<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/api/client'
import { handleApiError } from '@/utils/errorHandler'
import CourseSessionList from '@/components/CourseSessionList.vue'
import CourseFormModal from '@/components/CourseFormModal.vue'
import axios from 'axios'

interface CourseTrainer {
  id: number
  firstName: string
  lastName: string
  email?: string
}

interface PublicSession {
  id: number
  sessionDate: string
  startTime: string | null
  endTime: string | null
  location: string | null
  maxParticipants: number | null
  status: string
}

interface Course {
  id: number
  trainerId?: number
  name: string
  description: string | null
  courseType: string
  level: string | null
  price?: number | null
  maxParticipants: number
  startDate: string | null
  endDate: string | null
  status: string
  cancellationDeadlineHours?: number
  isActive?: boolean
  isFull?: boolean
  trainer: CourseTrainer | null
  sessions?: PublicSession[]
}

const route = useRoute()
const authStore = useAuthStore()

const loading = ref(false)
const course = ref<Course | null>(null)
const notFound = ref(false)
const isEditModalOpen = ref(false)

const isTrainerOrAdmin = computed(() => authStore.isAuthenticated && authStore.isTrainer)

const courseId = computed(() => Number(route.params.id))

const statusLabel: Record<string, string> = {
  active: 'Aktiv',
  inactive: 'Inaktiv',
  cancelled: 'Abgesagt',
  completed: 'Abgeschlossen',
  draft: 'Entwurf',
  open: 'Offen',
  full: 'Ausgebucht',
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatSessionDate(dateStr: string): string {
  const [year, month, day] = dateStr.split('-')
  return `${day}.${month}.${year}`
}

function formatSessionTime(timeStr: string | null): string {
  if (!timeStr) return ''
  return timeStr.substring(0, 5)
}

const sessionStatusLabel: Record<string, string> = {
  scheduled: 'Geplant',
  completed: 'Abgeschlossen',
  cancelled: 'Abgesagt',
}

async function loadCourse() {
  loading.value = true
  notFound.value = false
  try {
    const endpoint = isTrainerOrAdmin.value
      ? `/api/v1/courses/${courseId.value}`
      : `/api/v1/public/courses/${courseId.value}`
    const response = await apiClient.get(endpoint)
    course.value = response.data.data
  } catch (err) {
    if (axios.isAxiosError(err) && err.response?.status === 404) {
      notFound.value = true
    } else {
      handleApiError(err, 'Kurs konnte nicht geladen werden')
    }
  } finally {
    loading.value = false
  }
}

async function onCourseSaved() {
  isEditModalOpen.value = false
  await loadCourse()
}

onMounted(() => {
  loadCourse()
})
</script>

<template>
  <div class="container mx-auto px-4 py-8 max-w-5xl">

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16 text-gray-500">
      <svg class="animate-spin h-6 w-6 mr-3 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
      </svg>
      Lade Kurs...
    </div>

    <!-- 404 -->
    <div v-else-if="notFound" class="text-center py-16">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4">Kurs nicht gefunden</h1>
      <p class="text-gray-600 dark:text-gray-400 mb-8">
        Der gesuchte Kurs existiert nicht oder wurde entfernt.
      </p>
      <RouterLink
        to="/"
        class="inline-block btn btn-primary px-6 py-2"
      >
        Zur Startseite
      </RouterLink>
    </div>

    <!-- Course content -->
    <div v-else-if="course">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
              {{ course.name }}
            </h1>
            <span
              class="inline-block text-xs font-medium px-2.5 py-1 rounded-full"
              :class="{
                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': course.status === 'active' || course.status === 'open',
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': course.status === 'draft',
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': course.status === 'cancelled',
                'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': !['active','open','draft','cancelled'].includes(course.status),
              }"
            >
              {{ statusLabel[course.status] ?? course.status }}
            </span>
          </div>

          <!-- Trainer action button -->
          <div v-if="isTrainerOrAdmin">
            <button
              type="button"
              class="btn btn-primary px-5 py-2"
              @click="isEditModalOpen = true"
            >
              Kurs bearbeiten
            </button>
          </div>
        </div>
      </div>

      <!-- Description -->
      <!-- description is server-stored content, sanitized before storage -->
      <div
        v-if="course.description"
        class="prose prose-gray dark:prose-invert max-w-none mb-8 bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-gray-200 dark:border-gray-700"
        v-html="course.description"
      />

      <!-- Meta info grid -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Kurstyp</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ course.courseType }}</p>
        </div>

        <div v-if="course.level" class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Niveau</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ course.level }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Max. Teilnehmer</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ course.maxParticipants }}</p>
        </div>

        <div v-if="course.trainer" class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Trainer</p>
          <p class="font-semibold text-gray-900 dark:text-white">
            {{ course.trainer.firstName }} {{ course.trainer.lastName }}
          </p>
        </div>

        <div v-if="course.startDate" class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Startdatum</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ formatDate(course.startDate) }}</p>
        </div>

        <div v-if="course.endDate" class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Enddatum</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ formatDate(course.endDate) }}</p>
        </div>

        <!-- Price — only visible if present (trainer/admin endpoint) -->
        <div v-if="course.price != null" class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Preis / Einheit</p>
          <p class="font-semibold text-gray-900 dark:text-white">{{ course.price }} €</p>
        </div>
      </div>

      <!-- Sessions section -->
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-gray-200 dark:border-gray-700 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Termine</h2>

        <!-- Trainer/Admin: full editable session list via CourseSessionList -->
        <CourseSessionList v-if="isTrainerOrAdmin" :courseId="course.id" :editable="true" />

        <!-- Guest/Customer: read-only table from embedded course.sessions -->
        <template v-else>
          <div v-if="course.sessions && course.sessions.length > 0" class="overflow-x-auto">
            <table class="min-w-full text-sm text-left">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                  <th class="py-2 pr-4 font-semibold text-gray-600 dark:text-gray-400">Datum</th>
                  <th class="py-2 pr-4 font-semibold text-gray-600 dark:text-gray-400">Uhrzeit</th>
                  <th class="py-2 pr-4 font-semibold text-gray-600 dark:text-gray-400">Ort</th>
                  <th class="py-2 pr-4 font-semibold text-gray-600 dark:text-gray-400">Status</th>
                  <th class="py-2 font-semibold text-gray-600 dark:text-gray-400">Max. Teilnehmer</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="session in course.sessions"
                  :key="session.id"
                  class="border-b border-gray-100 dark:border-gray-700/50 last:border-0"
                >
                  <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ formatSessionDate(session.sessionDate) }}</td>
                  <td class="py-2 pr-4 text-gray-900 dark:text-white">
                    <template v-if="session.startTime">
                      {{ formatSessionTime(session.startTime) }}
                      <template v-if="session.endTime"> – {{ formatSessionTime(session.endTime) }}</template>
                    </template>
                    <template v-else>—</template>
                  </td>
                  <td class="py-2 pr-4 text-gray-900 dark:text-white">{{ session.location ?? '–' }}</td>
                  <td class="py-2 pr-4">
                    <span
                      class="inline-block text-xs font-medium px-2 py-0.5 rounded-full"
                      :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': session.status === 'scheduled',
                        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300': session.status === 'completed',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': session.status === 'cancelled',
                      }"
                    >
                      {{ sessionStatusLabel[session.status] ?? session.status }}
                    </span>
                  </td>
                  <td class="py-2 text-gray-900 dark:text-white">{{ session.maxParticipants ?? '–' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-gray-500 dark:text-gray-400 text-sm">Noch keine Termine geplant.</p>
        </template>
      </div>

      <!-- Booking CTA for non-trainers -->
      <div v-if="!isTrainerOrAdmin" class="bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-700 rounded-lg p-6 text-center">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Interesse an diesem Kurs?</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">
          Melden Sie sich an oder nehmen Sie Kontakt mit uns auf, um einen Platz zu reservieren.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
          <RouterLink to="/contact" class="btn btn-primary px-6 py-2">
            Kontakt aufnehmen
          </RouterLink>
          <RouterLink to="/login" class="btn bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 px-6 py-2">
            Anmelden
          </RouterLink>
        </div>
      </div>

      <!-- CourseFormModal for trainer/admin -->
      <CourseFormModal
        v-if="isTrainerOrAdmin"
        :is-open="isEditModalOpen"
        :course="course"
        @close="isEditModalOpen = false"
        @saved="onCourseSaved"
      />
    </div>

  </div>
</template>
