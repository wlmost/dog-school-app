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
                <span>Rechnungsdetails</span>
                <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </DialogTitle>

              <div v-if="invoice" class="space-y-6">
                <!-- Header Info -->
                <div class="grid grid-cols-2 gap-6">
                  <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Rechnungsinformationen</h4>
                    <div class="space-y-2">
                      <div>
                        <span class="text-sm text-gray-600">Rechnungsnummer:</span>
                        <p class="text-base font-mono font-medium">{{ invoice.invoice_number }}</p>
                      </div>
                      <div>
                        <span class="text-sm text-gray-600">Rechnungsdatum:</span>
                        <p class="text-base">{{ formatDate(invoice.invoice_date) }}</p>
                      </div>
                      <div>
                        <span class="text-sm text-gray-600">Fälligkeitsdatum:</span>
                        <p class="text-base">{{ formatDate(invoice.due_date) }}</p>
                      </div>
                      <div>
                        <span class="text-sm text-gray-600">Status:</span>
                        <p>
                          <span :class="getStatusClass(invoice.status)" class="inline-block mt-1 px-2 py-1 text-xs font-medium rounded-full">
                            {{ getStatusLabel(invoice.status) }}
                          </span>
                        </p>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h4 class="text-sm font-medium text-gray-500 mb-2">Kunde</h4>
                    <div class="space-y-2">
                      <div>
                        <p class="text-base font-medium">{{ invoice.customer?.user?.full_name || '-' }}</p>
                      </div>
                      <div v-if="invoice.customer?.street">
                        <p class="text-sm text-gray-600">{{ invoice.customer.street }}</p>
                        <p class="text-sm text-gray-600">{{ invoice.customer.postal_code }} {{ invoice.customer.city }}</p>
                      </div>
                      <div>
                        <p class="text-sm text-gray-600">{{ invoice.customer?.user?.email }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Invoice Items -->
                <div class="border-t border-gray-200 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 mb-3">Rechnungspositionen</h4>
                  <table class="min-w-full">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Beschreibung</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Menge</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Einzelpreis</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Gesamt</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                      <tr v-for="item in invoice.items" :key="item.id">
                        <td class="px-4 py-2 text-sm">{{ item.description }}</td>
                        <td class="px-4 py-2 text-sm text-right">{{ item.quantity }}</td>
                        <td class="px-4 py-2 text-sm text-right">{{ formatCurrency(item.unit_price) }}</td>
                        <td class="px-4 py-2 text-sm text-right font-medium">{{ formatCurrency(item.quantity * item.unit_price) }}</td>
                      </tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                      <tr>
                        <td colspan="3" class="px-4 py-2 text-sm text-right font-medium">Zwischensumme:</td>
                        <td class="px-4 py-2 text-sm text-right font-medium">{{ formatCurrency(invoice.subtotal_amount) }}</td>
                      </tr>
                      <tr>
                        <td colspan="3" class="px-4 py-2 text-sm text-right">MwSt (19%):</td>
                        <td class="px-4 py-2 text-sm text-right">{{ formatCurrency(invoice.tax_amount) }}</td>
                      </tr>
                      <tr class="border-t-2 border-gray-300">
                        <td colspan="3" class="px-4 py-3 text-base text-right font-bold">Gesamtbetrag:</td>
                        <td class="px-4 py-3 text-base text-right font-bold">{{ formatCurrency(invoice.total_amount) }}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- Payments -->
                <div v-if="invoice.payments && invoice.payments.length > 0" class="border-t border-gray-200 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 mb-3">Zahlungen</h4>
                  <div class="space-y-2">
                    <div v-for="payment in invoice.payments" :key="payment.id" class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                      <div>
                        <p class="text-sm font-medium">{{ formatCurrency(payment.amount) }}</p>
                        <p class="text-xs text-gray-600">{{ formatDate(payment.payment_date) }} - {{ payment.payment_method }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Notes -->
                <div v-if="invoice.notes" class="border-t border-gray-200 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 mb-2">Notizen</h4>
                  <p class="text-sm text-gray-600">{{ invoice.notes }}</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                  <button @click="$emit('close')" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Schließen
                  </button>
                  <button @click="$emit('download', invoice)" class="btn bg-blue-600 hover:bg-blue-700 text-white">
                    PDF herunterladen
                  </button>
                  <button v-if="invoice.status === 'draft' || invoice.status === 'sent'" @click="$emit('mark-paid', invoice)" class="btn bg-green-600 hover:bg-green-700 text-white">
                    Als bezahlt markieren
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
  invoice?: any
}>()

defineEmits<{
  close: []
  download: [invoice: any]
  'mark-paid': [invoice: any]
}>()

function formatDate(date: string) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}

function formatCurrency(amount: number) {
  if (!amount) return '0,00 €'
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount)
}

function getStatusClass(status: string) {
  const classes: Record<string, string> = {
    'draft': 'bg-gray-100 text-gray-800',
    'sent': 'bg-blue-100 text-blue-800',
    'paid': 'bg-green-100 text-green-800',
    'overdue': 'bg-red-100 text-red-800',
    'cancelled': 'bg-gray-100 text-gray-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

function getStatusLabel(status: string) {
  const labels: Record<string, string> = {
    'draft': 'Entwurf',
    'sent': 'Versendet',
    'paid': 'Bezahlt',
    'overdue': 'Überfällig',
    'cancelled': 'Storniert'
  }
  return labels[status] || status
}
</script>
