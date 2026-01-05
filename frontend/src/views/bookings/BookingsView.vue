<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4">
        <select v-model="filterStatus" @change="loadBookings" class="input max-w-xs">
          <option :value="null">Alle Buchungen</option>
          <option value="confirmed">Bestätigt</option>
          <option value="pending">Ausstehend</option>
          <option value="cancelled">Storniert</option>
          <option value="attended">Teilgenommen</option>
        </select>
      </div>
      <button @click="openCreateModal" class="btn btn-primary">
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
                <div class="text-sm text-gray-900">{{ booking.dog?.customer?.user?.fullName || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ booking.dog?.name || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ booking.trainingSession?.course?.name || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ formatDate(booking.bookingDate) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="bookingStatusClass(booking.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ bookingStatusLabel(booking.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button @click="editBooking(booking)" class="text-primary-600 hover:text-primary-900">Bearbeiten</button>
                <button v-if="booking.status === 'pending'" @click="confirmBooking(booking)" class="text-green-600 hover:text-green-900">Bestätigen</button>
                <button v-if="booking.status !== 'cancelled'" @click="cancelBooking(booking)" class="text-red-600 hover:text-red-900">Stornieren</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Booking Form Modal -->
    <BookingFormModal 
      :is-open="showFormModal" 
      :booking="selectedBooking"
      @close="closeFormModal"
      @saved="handleBookingSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'
import BookingFormModal from '@/components/BookingFormModal.vue'
import { handleApiError, showSuccess } from '@/utils/errorHandler'

const loading = ref(true)
const filterStatus = ref<string | null>(null)
const bookings = ref<any[]>([])
const showFormModal = ref(false)
const selectedBooking = ref<any>(null)

onMounted(() => {
  loadBookings()
})

async function loadBookings() {
  loading.value = true
  try {
    const params: any = {}
    if (filterStatus.value) {
      params.status = filterStatus.value
    }
    
    const response = await apiClient.get('/api/v1/bookings', { params })
    bookings.value = response.data.data
  } catch (error) {
    console.error('Error loading bookings:', error)
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  selectedBooking.value = null
  showFormModal.value = true
}

function editBooking(booking: any) {
  selectedBooking.value = booking
  showFormModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  selectedBooking.value = null
}

async function handleBookingSaved() {
  await loadBookings()
  closeFormModal()
}

async function confirmBooking(booking: any) {
  if (!confirm(`Buchung #${booking.id} bestätigen?`)) {
    return
  }

  try {
    await apiClient.post(`/api/v1/bookings/${booking.id}/confirm`)
    // Reload the entire list to ensure fresh data
    await loadBookings()
    showSuccess('Buchung bestätigt', 'Die Buchung wurde erfolgreich bestätigt')
  } catch (error) {
    handleApiError(error, 'Fehler beim Bestätigen der Buchung')
  }
}

async function cancelBooking(booking: any) {
  if (!confirm(`Buchung #${booking.id} wirklich stornieren?`)) {
    return
  }

  try {
    await apiClient.post(`/api/v1/bookings/${booking.id}/cancel`)
    // Reload the entire list to ensure fresh data
    await loadBookings()
    showSuccess('Buchung storniert', 'Die Buchung wurde erfolgreich storniert')
  } catch (error) {
    handleApiError(error, 'Fehler beim Stornieren der Buchung')
  }
}

function formatDate(date: string) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}

function bookingStatusClass(status: string) {
  const classes = {
    confirmed: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    cancelled: 'bg-red-100 text-red-800',
    attended: 'bg-blue-100 text-blue-800'
  }
  return classes[status as keyof typeof classes] || classes.pending
}

function bookingStatusLabel(status: string) {
  const labels = {
    confirmed: 'Bestätigt',
    pending: 'Ausstehend',
    cancelled: 'Storniert',
    attended: 'Teilgenommen'
  }
  return labels[status as keyof typeof labels] || status
}
</script>
