<template>
  <div class="space-y-6">
    <!-- Welcome Section -->
    <div class="card">
      <h3 class="text-xl font-semibold text-gray-900 mb-2">
        Willkommen, {{ user?.first_name }}! 👋
      </h3>
      <p class="text-gray-600">
        Hier ist eine Übersicht über Ihre Hundeschule
      </p>
    </div>

    <!-- Statistics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Aktive Kunden</p>
            <p class="text-3xl font-bold text-gray-900">{{ stats.customers }}</p>
          </div>
          <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Registrierte Hunde</p>
            <p class="text-3xl font-bold text-gray-900">{{ stats.dogs }}</p>
          </div>
          <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
            <span class="text-2xl">🐕</span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Aktive Kurse</p>
            <p class="text-3xl font-bold text-gray-900">{{ stats.courses }}</p>
          </div>
          <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-gray-600 mb-1">Offene Rechnungen</p>
            <p class="text-3xl font-bold text-gray-900">{{ stats.invoices }}</p>
          </div>
          <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Upcoming Sessions -->
      <div class="card">
        <h4 class="text-lg font-semibold text-gray-900 mb-4">Bevorstehende Trainingssessions</h4>
        <div class="space-y-3">
          <div v-if="loading" class="text-center py-8">
            <svg class="animate-spin h-8 w-8 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-500 mt-2">Lade Daten...</p>
          </div>
          <div v-else-if="!upcomingSessions.length" class="text-center py-8 text-gray-500">
            Keine bevorstehenden Sessions
          </div>
          <div v-else v-for="session in upcomingSessions" :key="session.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div>
              <p class="font-medium text-gray-900">{{ session.course }}</p>
              <p class="text-sm text-gray-600">{{ session.date }} - {{ session.time }}</p>
            </div>
            <span class="px-3 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full">
              {{ session.participants }} Teilnehmer
            </span>
          </div>
        </div>
      </div>

      <!-- Recent Bookings -->
      <div class="card">
        <h4 class="text-lg font-semibold text-gray-900 mb-4">Neueste Buchungen</h4>
        <div class="space-y-3">
          <div v-if="loading" class="text-center py-8">
            <svg class="animate-spin h-8 w-8 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-gray-500 mt-2">Lade Daten...</p>
          </div>
          <div v-else-if="!recentBookings.length" class="text-center py-8 text-gray-500">
            Keine neuesten Buchungen
          </div>
          <div v-else v-for="booking in recentBookings" :key="booking.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div>
              <p class="font-medium text-gray-900">{{ booking.customer }}</p>
              <p class="text-sm text-gray-600">{{ booking.dog }} - {{ booking.course }}</p>
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
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/api/client'

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const loading = ref(true)
const stats = ref({
  customers: 0,
  dogs: 0,
  courses: 0,
  invoices: 0
})

const upcomingSessions = ref<any[]>([])
const recentBookings = ref<any[]>([])

onMounted(async () => {
  try {
    // Load dashboard data from API
    // This is placeholder - replace with actual API calls
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    stats.value = {
      customers: 124,
      dogs: 156,
      courses: 12,
      invoices: 8
    }

    upcomingSessions.value = [
      { id: 1, course: 'Welpentraining', date: '05.01.2026', time: '10:00', participants: 6 },
      { id: 2, course: 'Agility Fortgeschrittene', date: '06.01.2026', time: '14:00', participants: 8 },
      { id: 3, course: 'Grundgehorsam', date: '07.01.2026', time: '16:00', participants: 5 }
    ]

    recentBookings.value = [
      { id: 1, customer: 'Max Mustermann', dog: 'Bello', course: 'Welpentraining', status: 'confirmed' },
      { id: 2, customer: 'Anna Schmidt', dog: 'Luna', course: 'Agility', status: 'pending' },
      { id: 3, customer: 'Peter Weber', dog: 'Rex', course: 'Grundgehorsam', status: 'confirmed' }
    ]
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
