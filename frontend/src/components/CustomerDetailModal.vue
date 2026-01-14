<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" @close="$emit('close')" class="relative z-50">
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
              <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 mb-4 flex justify-between items-center">
                <span>Kunden-Details</span>
                <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </DialogTitle>

              <div v-if="customer" class="space-y-6">
                <!-- Customer Info -->
                <div class="grid grid-cols-2 gap-6">
                  <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Persönliche Informationen</h4>
                    <div class="space-y-2">
                      <div>
                        <span class="text-sm text-gray-600">Name:</span>
                        <p class="text-base font-medium">{{ customer.user?.fullName || '-' }}</p>
                      </div>
                      <div>
                        <span class="text-sm text-gray-600">E-Mail:</span>
                        <p class="text-base">{{ customer.user?.email || '-' }}</p>
                      </div>
                      <div>
                        <span class="text-sm text-gray-600">Telefon:</span>
                        <p class="text-base">{{ customer.user?.phone || '-' }}</p>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Adresse</h4>
                    <div v-if="customer.street || customer.city">
                      <p class="text-base">{{ customer.street }}</p>
                      <p class="text-base">{{ customer.postalCode }} {{ customer.city }}</p>
                      <p class="text-base">{{ customer.country }}</p>
                    </div>
                    <p v-else class="text-base text-gray-400">Keine Adresse angegeben</p>
                  </div>
                </div>

                <!-- Dogs -->
                <div v-if="customer.dogs && customer.dogs.length > 0" class="border-t border-gray-200 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 mb-3">Hunde ({{ customer.dogs.length }})</h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div v-for="dog in customer.dogs" :key="dog.id" class="p-3 bg-gray-50 rounded-lg">
                      <p class="font-medium">{{ dog.name }}</p>
                      <p class="text-sm text-gray-600">{{ dog.breed }}</p>
                    </div>
                  </div>
                </div>

                <!-- Bookings -->
                <div v-if="customer.bookings && customer.bookings.length > 0" class="border-t border-gray-200 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 mb-3">Buchungen ({{ customer.bookings.length }})</h4>
                  <div class="space-y-2 max-h-60 overflow-y-auto">
                    <div v-for="booking in customer.bookings" :key="booking.id" class="p-3 bg-gray-50 rounded-lg flex justify-between items-center">
                      <div>
                        <p class="font-medium">{{ booking.trainingSession?.course?.name }}</p>
                        <p class="text-sm text-gray-600">{{ booking.bookingDate }}</p>
                      </div>
                      <span :class="getBookingStatusClass(booking.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                        {{ getBookingStatusLabel(booking.status) }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Notes -->
                <div v-if="customer.notes" class="border-t border-gray-200 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 mb-2">Notizen</h4>
                  <p class="text-sm text-gray-600">{{ customer.notes }}</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                  <button @click="$emit('close')" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Schließen
                  </button>
                  <button @click="$emit('edit', customer)" class="btn btn-primary">
                    Bearbeiten
                  </button>
                </div>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'

defineProps<{
  isOpen: boolean
  customer?: any
}>()

defineEmits<{
  close: []
  edit: [customer: any]
}>()

function getBookingStatusClass(status: string) {
  const classes: Record<string, string> = {
    confirmed: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    cancelled: 'bg-red-100 text-red-800',
    attended: 'bg-blue-100 text-blue-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

function getBookingStatusLabel(status: string) {
  const labels: Record<string, string> = {
    confirmed: 'Bestätigt',
    pending: 'Ausstehend',
    cancelled: 'Storniert',
    attended: 'Teilgenommen'
  }
  return labels[status] || status
}
</script>
