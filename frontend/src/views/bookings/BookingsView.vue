<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4">
        <select class="input max-w-xs">
          <option value="">Alle Buchungen</option>
          <option value="confirmed">Bestätigt</option>
          <option value="pending">Ausstehend</option>
          <option value="cancelled">Storniert</option>
        </select>
      </div>
      <button class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Neue Buchung
      </button>
    </div>

    <!-- Bookings Table -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buchungsnr.</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kunde</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hund</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kurs</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Datum</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                <svg class="animate-spin h-8 w-8 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2">Lade Buchungsdaten...</p>
              </td>
            </tr>
            <tr v-else-if="!bookings.length">
              <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                Keine Buchungen gefunden
              </td>
            </tr>
            <tr v-else v-for="booking in bookings" :key="booking.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">#{{ booking.id }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ booking.customer }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ booking.dog }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ booking.course }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ booking.date }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="bookingStatusClass(booking.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ bookingStatusLabel(booking.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button class="text-primary-600 hover:text-primary-900">Details</button>
                <button v-if="booking.status === 'pending'" class="text-green-600 hover:text-green-900">Bestätigen</button>
                <button v-if="booking.status !== 'cancelled'" class="text-red-600 hover:text-red-900">Stornieren</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const loading = ref(true)
const bookings = ref<any[]>([])

onMounted(async () => {
  try {
    // Placeholder - replace with actual API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    bookings.value = [
      { id: 1001, customer: 'Max Mustermann', dog: 'Bello', course: 'Welpentraining', date: '05.01.2026', status: 'confirmed' },
      { id: 1002, customer: 'Anna Schmidt', dog: 'Luna', course: 'Agility Fortgeschrittene', date: '06.01.2026', status: 'pending' },
      { id: 1003, customer: 'Peter Weber', dog: 'Rex', course: 'Grundgehorsam', date: '15.02.2026', status: 'confirmed' }
    ]
  } catch (error) {
    console.error('Error loading bookings:', error)
  } finally {
    loading.value = false
  }
})

function bookingStatusClass(status: string) {
  const classes = {
    confirmed: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    cancelled: 'bg-red-100 text-red-800'
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
