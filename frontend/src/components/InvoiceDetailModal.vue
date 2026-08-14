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
            <DialogPanel class="w-full max-w-3xl transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4 flex justify-between items-center">
                <span>Rechnungsdetails</span>
                <button @click="$emit('close')" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </DialogTitle>

              <div v-if="invoice" class="space-y-6">
                <!-- Header Info -->
                <div class="grid grid-cols-2 gap-6">
                  <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Rechnungsinformationen</h4>
                    <div class="space-y-2">
                      <div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Rechnungsnummer:</span>
                        <p class="text-base font-mono font-medium text-gray-900 dark:text-gray-100">{{ invoice.invoiceNumber }}</p>
                      </div>
                      <div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Rechnungsdatum:</span>
                        <p class="text-base text-gray-900 dark:text-gray-100">{{ formatDate(invoice.invoiceDate) }}</p>
                      </div>
                      <div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Fälligkeitsdatum:</span>
                        <p class="text-base text-gray-900 dark:text-gray-100">{{ formatDate(invoice.dueDate) }}</p>
                      </div>
                      <div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                        <p>
                          <span :class="getStatusClass(invoice.status)" class="inline-block mt-1 px-2 py-1 text-xs font-medium rounded-full">
                            {{ getStatusLabel(invoice.status) }}
                          </span>
                        </p>
                      </div>
                      <div v-if="invoice.originalInvoiceNumber">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Stornorechnung zu:</span>
                        <p class="text-base text-gray-900 dark:text-gray-100">{{ invoice.originalInvoiceNumber }}</p>
                      </div>
                      <div v-if="invoice.cancellationInvoiceNumber">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Storniert durch:</span>
                        <p class="text-base text-gray-900 dark:text-gray-100">{{ invoice.cancellationInvoiceNumber }}</p>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Kunde</h4>
                    <div class="space-y-2">
                      <div>
                        <p class="text-base font-medium text-gray-900 dark:text-gray-100">{{ invoice.customer?.user?.fullName || '-' }}</p>
                      </div>
                      <div v-if="invoice.customer?.street">
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ invoice.customer.street }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ invoice.customer.postalCode }} {{ invoice.customer.city }}</p>
                      </div>
                      <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ invoice.customer?.user?.email }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Invoice Items -->
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Rechnungspositionen</h4>
                  <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                      <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Beschreibung</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Menge</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Einzelpreis</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Gesamt</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                      <tr v-for="item in invoice.items" :key="item.id">
                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ item.description }}</td>
                        <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ item.quantity }}</td>
                        <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(item.unitPrice) }}</td>
                        <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(item.quantity * item.unitPrice) }}</td>
                      </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-700">
                      <tr>
                        <td colspan="3" class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ isSmallBusiness ? 'Gesamt (netto)' : 'Zwischensumme' }}:</td>
                        <td class="px-4 py-2 text-sm text-right font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(invoice.subtotalAmount) }}</td>
                      </tr>
                      <tr v-if="!isSmallBusiness">
                        <td colspan="3" class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">MwSt (19%):</td>
                        <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-gray-100">{{ formatCurrency(invoice.taxAmount) }}</td>
                      </tr>
                      <tr v-if="isSmallBusiness">
                        <td colspan="4" class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400 text-right italic">
                          Gemäß §19 UStG wird keine Umsatzsteuer berechnet
                        </td>
                      </tr>
                      <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                        <td colspan="3" class="px-4 py-3 text-base text-right font-bold text-gray-900 dark:text-white">Gesamtbetrag:</td>
                        <td class="px-4 py-3 text-base text-right font-bold text-gray-900 dark:text-white">{{ formatCurrency(invoice.totalAmount) }}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>

                <!-- Payments -->
                <div v-if="invoice.payments && invoice.payments.length > 0" class="border-t border-gray-200 dark:border-gray-700 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Zahlungen</h4>
                  <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                    Bezahlt: {{ formatCurrency(invoice.totalPaid) }} von {{ formatCurrency(invoice.totalAmount) }} — Rest: {{ formatCurrency(invoice.remainingBalance) }}
                  </p>
                  <div class="space-y-2">
                    <div v-for="payment in invoice.payments" :key="payment.id" class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                      <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(payment.amount) }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ formatDate(payment.paymentDate) }} - {{ payment.paymentMethod }}</p>
                        <p v-if="payment.notes" class="text-xs text-gray-500 dark:text-gray-400">{{ payment.notes }}</p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Dunnings -->
                <div v-if="invoice.dunnings && invoice.dunnings.length > 0" class="border-t border-gray-200 dark:border-gray-700 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Mahnungen</h4>
                  <div class="space-y-2">
                    <div v-for="dunning in invoice.dunnings" :key="dunning.id" class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                      <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Stufe {{ dunning.level }} — {{ formatCurrency(dunning.feeAmount) }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ formatDate(dunning.dunningDate) }}<span v-if="dunning.feeInvoiceNumber"> - {{ dunning.feeInvoiceNumber }}</span></p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Notes -->
                <div v-if="invoice.notes" class="border-t border-gray-200 dark:border-gray-700 pt-4">
                  <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Notizen</h4>
                  <p class="text-sm text-gray-600 dark:text-gray-400">{{ invoice.notes }}</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                  <button @click="$emit('close')" class="btn bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200">
                    Schließen
                  </button>
                  <button @click="$emit('download', invoice)" class="btn bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white">
                    PDF herunterladen
                  </button>
                  <button v-if="!authStore.isCustomer && invoice.status === 'draft'" @click="$emit('edit', invoice)" class="btn bg-yellow-500 hover:bg-yellow-600 dark:bg-yellow-600 dark:hover:bg-yellow-500 text-white">
                    Bearbeiten
                  </button>
                  <button v-if="canRecordPayment(invoice)" @click="$emit('record-payment', invoice)" class="btn bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-600 text-white">
                    Zahlung erfassen
                  </button>
                  <button v-if="canDelete(invoice)" @click="$emit('delete', invoice)" class="btn bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 text-white">
                    Löschen
                  </button>
                  <button v-if="canFinalize(invoice)" @click="$emit('finalize', invoice)" class="btn bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white">
                    Freigeben
                  </button>
                  <button v-if="canSend(invoice)" @click="$emit('send', invoice)" class="btn bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600 text-white">
                    Senden
                  </button>
                  <button v-if="canCancel(invoice)" @click="$emit('cancel', invoice)" class="btn bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 text-white">
                    Stornieren
                  </button>
                  <button v-if="canRemind(invoice)" @click="$emit('remind', invoice)" class="btn bg-orange-600 hover:bg-orange-700 dark:bg-orange-700 dark:hover:bg-orange-600 text-white">
                    Mahnen
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
import { ref, onMounted } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

defineProps<{
  isOpen: boolean
  invoice?: any
}>()

defineEmits<{
  close: []
  download: [invoice: any]
  edit: [invoice: any]
  'record-payment': [invoice: any]
  delete: [invoice: any]
  finalize: [invoice: any]
  cancel: [invoice: any]
  send: [invoice: any]
  remind: [invoice: any]
}>()

const isSmallBusiness = ref(false)

// Statuswerte, für die der (deaktivierte) Senden-Button angezeigt wird.
// Siehe InvoicesView.vue für die identische Logik/Kommentierung
// (design.md Decision D3).
const SENDABLE_STATUSES = ['sent', 'reminded', 'overdue']

// Statuswerte, für die der Stornieren-Button angezeigt wird. Muss exakt
// InvoicePolicy::cancel() spiegeln, die `overdue` bewusst NICHT zulässt.
// Siehe InvoicesView.vue für die identische Logik/Kommentierung.
const CANCELLABLE_STATUSES = ['sent', 'reminded', 'paid']

// Statuswerte, für die eine Zahlung erfasst werden darf. Muss
// `PaymentController::store()`s `PAYABLE_STATUSES`-Konstante spiegeln
// (siehe design.md Decision D3) — plus die Zusatzbedingung, dass noch ein
// Restbetrag offen ist (`remainingBalance > 0`). Lokal dupliziert nach dem
// in InvoicesView.vue etablierten Muster (bewusste Nicht-Konsolidierung,
// siehe design.md Context zu T07).
const PAYABLE_STATUSES = ['sent', 'reminded', 'overdue']

// Statuswerte, für die eine Mahnung ausgelöst werden darf. Muss
// `InvoiceDunningRecorder`s Eligibility-Prüfung spiegeln (siehe
// design.md Decision D3). Lokal dupliziert nach dem in InvoicesView.vue
// etablierten Muster (bewusste Nicht-Konsolidierung, siehe design.md
// Context zu T07/T08).
const REMINDABLE_STATUSES = ['sent', 'reminded', 'overdue']

function canDelete(invoice: any): boolean {
  return !authStore.isCustomer && invoice.status === 'draft'
}

function canFinalize(invoice: any): boolean {
  return !authStore.isCustomer && invoice.status === 'draft'
}

function canRecordPayment(invoice: any): boolean {
  return !authStore.isCustomer
    && PAYABLE_STATUSES.includes(invoice.status)
    && invoice.remainingBalance > 0
}

function canSend(invoice: any): boolean {
  return !authStore.isCustomer && SENDABLE_STATUSES.includes(invoice.status)
}

function canCancel(invoice: any): boolean {
  return !authStore.isCustomer
    && CANCELLABLE_STATUSES.includes(invoice.status)
    && !invoice.originalInvoiceId
}

function canRemind(invoice: any): boolean {
  return !authStore.isCustomer
    && REMINDABLE_STATUSES.includes(invoice.status)
    && !invoice.originalInvoiceId
    && invoice.nextDunningLevel !== null
}

onMounted(() => {
  loadSettings()
})

async function loadSettings() {
  try {
    const response = await apiClient.get('/api/v1/settings')
    const allSettings = [
      ...(response.data.data.company || []),
      ...(response.data.data.email || []),
      ...(response.data.data.general || []),
    ]
    const smallBusinessSetting = allSettings.find((s: any) => s.key === 'company_small_business')
    
    // Robuste Boolean-Konvertierung
    if (smallBusinessSetting) {
      const value = smallBusinessSetting.value
      isSmallBusiness.value = value === true || value === 'true' || value === 1 || value === '1'
    } else {
      isSmallBusiness.value = false
    }
  } catch (err) {
    console.error('Error loading settings:', err)
    isSmallBusiness.value = false
  }
}

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
    'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'sent': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'paid': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'overdue': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    'reminded': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    'cancelled': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
  }
  return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

function getStatusLabel(status: string) {
  const labels: Record<string, string> = {
    'draft': 'Entwurf',
    'sent': 'Versendet',
    'paid': 'Bezahlt',
    'overdue': 'Überfällig',
    'reminded': 'Gemahnt',
    'cancelled': 'Storniert'
  }
  return labels[status] || status
}
</script>
