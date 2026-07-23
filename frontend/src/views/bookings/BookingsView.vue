<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4">
        <select v-model="filterStatus" @change="onFilterChange" class="input max-w-xs">
          <option :value="null">Alle Buchungen</option>
          <option value="confirmed">Bestätigt</option>
          <option value="pending">Ausstehend</option>
          <option value="cancellation_requested">Stornierung beantragt</option>
          <option value="cancelled">Storniert</option>
          <option value="attended">Teilgenommen</option>
        </select>
      </div>
      <button v-if="isTrainer" @click="openCreateModal" class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Neue Buchung
      </button>
    </div>

    <!-- Bookings Table -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Buchungsnr.</th>
              <th v-if="!isCustomer" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kunde</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Hund</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kurs</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Datum</th>
              <th v-if="isCustomer" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stornierungsfrist</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aktionen</th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                <svg class="animate-spin h-8 w-8 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2">Lade Buchungsdaten...</p>
              </td>
            </tr>
            <tr v-else-if="error">
              <td colspan="7" class="px-6 py-4">
                <div class="rounded-lg p-4" :class="forbidden ? 'bg-yellow-50 border border-yellow-200 text-yellow-800 dark:bg-yellow-900/20 dark:border-yellow-700 dark:text-yellow-300' : 'bg-red-50 border border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300'">
                  <p class="font-medium">{{ error }}</p>
                  <button v-if="!forbidden" @click="loadBookings()" class="mt-2 text-sm underline hover:no-underline">
                    Erneut laden
                  </button>
                </div>
              </td>
            </tr>
            <tr v-else-if="!bookings.length">
              <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                Keine Buchungen gefunden
              </td>
            </tr>
            <tr v-else v-for="booking in bookings" :key="booking.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">#{{ booking.id }}</div>
              </td>
              <td v-if="!isCustomer" class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-gray-100">{{ booking.dog?.customer?.user?.fullName || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-gray-100">{{ booking.dog?.name || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 dark:text-gray-100">{{ booking.trainingSession?.course?.name || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(booking.bookingDate) }}</div>
              </td>
              <!-- Cancellation deadline column (customers only) -->
              <td v-if="isCustomer" class="px-6 py-4 whitespace-nowrap">
                <template v-if="booking.cancellationDeadline && booking.status !== 'cancelled' && booking.status !== 'cancellation_requested'">
                  <div class="text-sm" :class="booking.isCancellationAllowed ? 'text-gray-600 dark:text-gray-400' : 'text-red-600 dark:text-red-400 font-medium'">
                    {{ formatDate(booking.cancellationDeadline) }}
                    <span class="block text-xs">{{ formatTime(booking.cancellationDeadline) }} Uhr</span>
                  </div>
                </template>
                <span v-else class="text-sm text-gray-400 dark:text-gray-500">-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="bookingStatusClass(booking.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ bookingStatusLabel(booking.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <!-- Admin / trainer actions -->
                <template v-if="isTrainer">
                  <button @click="editBooking(booking)" class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300">Bearbeiten</button>
                  <button v-if="booking.status === 'pending'" @click="confirmBooking(booking)" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300">Bestätigen</button>
                  <button
                    v-if="booking.status === 'cancellation_requested'"
                    @click="approveCancellation(booking)"
                    class="text-orange-600 dark:text-orange-400 hover:text-orange-900 dark:hover:text-orange-300"
                  >
                    Stornierung genehmigen
                  </button>
                  <button v-if="booking.status !== 'cancelled' && booking.status !== 'cancellation_requested'" @click="cancelBooking(booking)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">Stornieren</button>
                </template>

                <!-- Customer actions -->
                <template v-else-if="isCustomer">
                  <!-- Already cancelled or cancellation in progress -->
                  <span v-if="booking.status === 'cancelled'" class="text-gray-400 dark:text-gray-500 text-sm">Storniert</span>
                  <span v-else-if="booking.status === 'cancellation_requested'" class="text-orange-500 dark:text-orange-400 text-sm">Stornierung beantragt</span>

                  <!-- Active booking: show cancellation option -->
                  <template v-else-if="booking.status !== 'attended'">
                    <button
                      v-if="booking.isCancellationAllowed"
                      @click="requestCancellation(booking)"
                      class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300"
                    >
                      Stornieren
                    </button>
                    <span v-else class="text-gray-400 dark:text-gray-500 text-xs" :title="'Stornierungsfrist abgelaufen'">
                      Stornierung nicht möglich
                    </span>
                  </template>
                </template>

                <!-- Admin: read-only, no booking actions -->
                <template v-else>
                  <span class="text-gray-400 dark:text-gray-500 text-xs">Leserecht</span>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <PaginationControls
      v-if="!loading"
      :current-page="currentPage"
      :last-page="lastPage"
      :total="total"
      @update:current-page="goToPage"
    />

    <!-- Booking Form Modal (trainer only) -->
    <BookingFormModal
      v-if="isTrainer"
      :is-open="showFormModal"
      :booking="selectedBooking"
      @close="closeFormModal"
      @saved="handleBookingSaved"
    />

    <!-- Cancellation Deadline Expired Modal -->
    <div v-if="showDeadlineModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
        <div class="flex items-center mb-4">
          <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mr-3 shrink-0">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Stornierung nicht möglich</h3>
        </div>
        <p class="text-gray-600 dark:text-gray-400 mb-6">
          Die Stornierungsfrist ist abgelaufen. Eine Stornierung ist nicht mehr möglich.
          Die Kurskosten fallen an.
        </p>
        <div class="flex justify-end">
          <button @click="showDeadlineModal = false" class="btn btn-primary">Verstanden</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import apiClient from '@/api/client'
import BookingFormModal from '@/components/BookingFormModal.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import { handleApiError, showSuccess } from '@/utils/errorHandler'
import { useAuthStore } from '@/stores/auth'
import { usePagination } from '@/composables/usePagination'

const authStore = useAuthStore()
const isCustomer = computed(() => authStore.user?.role === 'customer')
const isTrainer = computed(() => authStore.user?.role === 'trainer')

const loading = ref(true)
const error = ref<string | null>(null)
const forbidden = ref(false)
const filterStatus = ref<string | null>(null)
const bookings = ref<any[]>([])
const showFormModal = ref(false)
const selectedBooking = ref<any>(null)
const showDeadlineModal = ref(false)

const { currentPage, lastPage, total, updateFromMeta, resetPage } = usePagination()

onMounted(() => {
  loadBookings()
})

async function loadBookings() {
  error.value = null
  forbidden.value = false
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filterStatus.value) {
      params.status = filterStatus.value
    }
    
    const response = await apiClient.get('/api/v1/bookings', { params })
    bookings.value = response.data.data
    if (response.data.meta) {
      updateFromMeta(response.data.meta)
    }
  } catch (err) {
    console.error('Error loading bookings:', err)
    const status = (err as { response?: { status?: number } })?.response?.status
    if (status === 403) {
      forbidden.value = true
      error.value = 'Du hast keine Berechtigung, diese Daten zu sehen.'
    } else {
      error.value = 'Beim Laden der Daten ist ein Fehler aufgetreten.'
    }
  } finally {
    loading.value = false
  }
}

function goToPage(page: number): void {
  currentPage.value = page
  loadBookings()
}

function onFilterChange(): void {
  resetPage()
  loadBookings()
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
    await loadBookings()
    showSuccess('Buchung storniert', 'Die Buchung wurde erfolgreich storniert')
  } catch (error) {
    handleApiError(error, 'Fehler beim Stornieren der Buchung')
  }
}

async function requestCancellation(booking: any) {
  if (!confirm(`Stornierungsanfrage für Buchung #${booking.id} stellen? Der Trainer wird informiert.`)) {
    return
  }

  try {
    await apiClient.post(`/api/v1/bookings/${booking.id}/cancel`)
    await loadBookings()
    showSuccess('Stornierungsanfrage gesendet', 'Ihre Stornierungsanfrage wurde dem Trainer weitergeleitet.')
  } catch (error: any) {
    if (error?.response?.data?.deadlineExpired) {
      showDeadlineModal.value = true
    } else {
      handleApiError(error, 'Fehler beim Stornieren der Buchung')
    }
  }
}

async function approveCancellation(booking: any) {
  if (!confirm(`Stornierungsanfrage für Buchung #${booking.id} genehmigen?`)) {
    return
  }

  try {
    await apiClient.post(`/api/v1/bookings/${booking.id}/approve-cancellation`)
    await loadBookings()
    showSuccess('Stornierung genehmigt', 'Die Stornierung wurde genehmigt und der Kunde per E-Mail informiert.')
  } catch (error) {
    handleApiError(error, 'Fehler beim Genehmigen der Stornierung')
  }
}

function formatDate(date: string) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}

function formatTime(date: string) {
  if (!date) return ''
  return new Date(date).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
}

function bookingStatusClass(status: string) {
  const classes: Record<string, string> = {
    confirmed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    attended: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    cancellation_requested: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
  }
  return classes[status] || classes.pending
}

function bookingStatusLabel(status: string) {
  const labels: Record<string, string> = {
    confirmed: 'Bestätigt',
    pending: 'Ausstehend',
    cancelled: 'Storniert',
    attended: 'Teilgenommen',
    cancellation_requested: 'Stornierung beantragt',
  }
  return labels[status] || status
}
</script>
