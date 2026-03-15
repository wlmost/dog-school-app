<template>
  <div class="space-y-6">
    <!-- Welcome Section -->
    <div class="card">
      <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
        Willkommen, {{ user?.first_name }}! 👋
      </h3>
      <p class="text-gray-600 dark:text-gray-400">
        Hier ist deine Übersicht über die wichtigsten Kennzahlen und bevorstehenden Aktivitäten.
      </p>
    </div>

    <!-- Statistics Grid -->
    <div :class="statsGridClass">
      <!-- Customers Card - nur für Admin und Trainer -->
      <router-link 
        v-if="user?.role !== 'customer'" 
        :to="{ name: 'Customers' }" 
        class="card hover:shadow-lg transition-shadow cursor-pointer"
      >
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Kunden</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ stats.customers }}</p>
          </div>
          <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
            <UsersIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
          </div>
        </div>
      </router-link>

      <router-link :to="{ name: 'Dogs' }" class="card hover:shadow-lg transition-shadow cursor-pointer">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ user?.role === 'customer' ? 'Meine Hunde' : 'Hunde' }}</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ stats.dogs }}</p>
          </div>
          <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
            <component :is="DogIcon" class="text-green-600 dark:text-green-400" />
          </div>
        </div>
      </router-link>

      <router-link :to="{ name: 'Courses' }" class="card hover:shadow-lg transition-shadow cursor-pointer">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ user?.role === 'customer' ? 'Verfügbare Kurse' : 'Kurse' }}</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ stats.courses }}</p>
          </div>
          <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-lg flex items-center justify-center">
            <AcademicCapIcon class="w-6 h-6 text-purple-600 dark:text-purple-400" />
          </div>
        </div>
      </router-link>

      <router-link :to="{ name: 'Bookings' }" class="card hover:shadow-lg transition-shadow cursor-pointer">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ user?.role === 'customer' ? 'Meine Buchungen' : 'Buchungen' }}</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ stats.bookings }}</p>
          </div>
          <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-lg flex items-center justify-center">
            <CalendarIcon class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
          </div>
        </div>
      </router-link>

      <router-link :to="{ name: 'Invoices' }" class="card hover:shadow-lg transition-shadow cursor-pointer">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ user?.role === 'customer' ? 'Meine Rechnungen' : 'Rechnungen' }}</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ stats.invoices }}</p>
          </div>
          <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-lg flex items-center justify-center">
            <DocumentTextIcon class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
          </div>
        </div>
      </router-link>
    </div>

    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Upcoming Sessions -->
      <div class="card">
        <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Bevorstehende Trainingssessions</h4>
        <div class="space-y-3">
          <div v-if="loading" class="text-center py-8">
            <svg class="animate-spin h-8 w-8 text-primary-600 dark:text-primary-400 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Lade Daten...</p>
          </div>
          <div v-else-if="!upcomingSessions.length" class="text-center py-8 text-gray-500 dark:text-gray-400">
            Keine bevorstehenden Sessions
          </div>
          <div v-else v-for="session in upcomingSessions" :key="session.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div>
              <p class="font-medium text-gray-900 dark:text-gray-100">{{ session.course }}</p>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ session.date }} - {{ session.time }}
                <span v-if="user?.role === 'customer' && session.dog"> - {{ session.dog }}</span>
              </p>
            </div>
            <span v-if="user?.role === 'customer' && session.status" :class="bookingStatusClass(session.status)">
              {{ bookingStatusLabel(session.status) }}
            </span>
            <span v-else class="px-3 py-1 text-xs font-medium text-blue-800 dark:text-blue-200 bg-blue-100 dark:bg-blue-900 rounded-full">
              {{ session.participants }} Teilnehmer
            </span>
          </div>
        </div>
      </div>

      <!-- Recent Bookings -->
      <div class="card">
        <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Neueste Buchungen</h4>
        <div class="space-y-3">
          <div v-if="loading" class="text-center py-8">
            <svg class="animate-spin h-8 w-8 text-primary-600 dark:text-primary-400 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-500 dark:text-gray-400 mt-2">Lade Daten...</p>
          </div>
          <div v-else-if="!recentBookings.length" class="text-center py-8 text-gray-500 dark:text-gray-400">
            Keine neuesten Buchungen
          </div>
          <div v-else v-for="booking in recentBookings" :key="booking.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div>
              <p v-if="user?.role === 'customer'" class="font-medium text-gray-900 dark:text-gray-100">{{ booking.dog }} - {{ booking.course }}</p>
              <p v-else class="font-medium text-gray-900 dark:text-gray-100">{{ booking.customer }}</p>
              <p v-if="user?.role === 'customer'" class="text-sm text-gray-600 dark:text-gray-400">{{ booking.date }}</p>
              <p v-else class="text-sm text-gray-600 dark:text-gray-400">{{ booking.dog }} - {{ booking.course }}</p>
            </div>
            <span :class="bookingStatusClass(booking.status)">
              {{ bookingStatusLabel(booking.status) }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, h } from 'vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/api/client'
import {
  UsersIcon,
  AcademicCapIcon,
  CalendarIcon,
  DocumentTextIcon
} from '@heroicons/vue/24/outline'

// Custom Dog Icon component
const DogIcon = () => h('svg', {
  class: 'w-6 h-6',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24',
  xmlns: 'http://www.w3.org/2000/svg'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M7 4a1 1 0 011-1h1a1 1 0 011 1v1a1 1 0 01-1 1H8a1 1 0 01-1-1V4zm8 0a1 1 0 011-1h1a1 1 0 011 1v1a1 1 0 01-1 1h-1a1 1 0 01-1-1V4zM5 8a2 2 0 012-2h10a2 2 0 012 2v2a2 2 0 01-2 2h-.5a.5.5 0 00-.5.5 3 3 0 01-6 0 .5.5 0 00-.5-.5H7a2 2 0 01-2-2V8zm1 7a1 1 0 011-1h1a1 1 0 011 1v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-1zm9 0a1 1 0 011-1h1a1 1 0 011 1v1a1 1 0 01-1 1h-1a1 1 0 01-1-1v-1z'
  })
])

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const loading = ref(true)
const stats = ref({
  customers: 0,
  dogs: 0,
  courses: 0,
  invoices: 0,
  bookings: 0
})

const upcomingSessions = ref<any[]>([])
const recentBookings = ref<any[]>([])

// Computed grid class based on user role
const statsGridClass = computed(() => {
  // Customers see 4 cards (no Customers card)
  if (user.value?.role === 'customer') {
    return 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6'
  }
  // Admin and Trainer see 5 cards
  return 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6'
})

onMounted(async () => {
  try {
    // Load dashboard data from API
    const response = await apiClient.get('/api/v1/dashboard')
    
    stats.value = response.data.stats
    upcomingSessions.value = response.data.upcomingSessions
    recentBookings.value = response.data.recentBookings
  } catch (error) {
    console.error('Error loading dashboard data:', error)
  } finally {
    loading.value = false
  }
})

function bookingStatusClass(status: string) {
  const classes = {
    confirmed: 'px-3 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full',
    pending: 'px-3 py-1 text-xs font-medium text-yellow-800 bg-yellow-100 rounded-full',
    cancelled: 'px-3 py-1 text-xs font-medium text-red-800 bg-red-100 rounded-full'
  }
  return classes[status as keyof typeof classes] || classes.pending
}

function bookingStatusLabel(status: string) {
  const labels = {
    confirmed: 'Bestätigt',
    pending: 'Ausstehend',
    cancelled: 'Storniert'
  }
  return labels[status as keyof typeof labels] || status
}
</script>
