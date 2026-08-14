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
            <DialogPanel class="w-full max-w-md transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4 flex justify-between items-center">
                <span>Zahlung erfassen</span>
                <button @click="$emit('close')" :disabled="isSubmitting" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-50">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </DialogTitle>

              <div v-if="invoice" class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Rechnung <span class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ invoice.invoiceNumber }}</span>
                  — Restbetrag <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(invoice.remainingBalance) }}</span>
                </p>

                <form @submit.prevent="submit" class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Betrag *</label>
                    <div class="flex gap-2">
                      <input
                        v-model.number="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        class="input"
                      />
                      <button
                        type="button"
                        @click="setFullBalance"
                        :disabled="isSubmitting"
                        class="btn bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 whitespace-nowrap disabled:opacity-50"
                      >
                        Volle Restsumme
                      </button>
                    </div>
                    <p v-if="amountError" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ amountError }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Datum *</label>
                    <input v-model="paymentDate" type="date" :max="today" required class="input" />
                    <p v-if="dateError" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ dateError }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Zahlungsart *</label>
                    <select v-model="paymentMethod" required class="input">
                      <option v-for="option in paymentMethodOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                      </option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Referenz / Notiz</label>
                    <textarea v-model="notes" rows="2" class="input"></textarea>
                  </div>

                  <div class="flex justify-end space-x-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <button
                      type="button"
                      @click="$emit('close')"
                      :disabled="isSubmitting"
                      class="btn bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 disabled:opacity-50"
                    >
                      Abbrechen
                    </button>
                    <button type="submit" :disabled="!isValid || isSubmitting" class="btn btn-primary disabled:opacity-50">
                      <span v-if="isSubmitting">Speichert...</span>
                      <span v-else>Zahlung erfassen</span>
                    </button>
                  </div>
                </form>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'

// Muss exakt den Werten des bestehenden `payment_method`-Enums entsprechen,
// siehe `backend/database/migrations/2025_12_22_185135_create_payments_table.php:14-27`.
const paymentMethodOptions = [
  { value: 'cash', label: 'Bar' },
  { value: 'bank_transfer', label: 'Überweisung' },
  { value: 'paypal', label: 'PayPal' },
  { value: 'stripe', label: 'Stripe' },
  { value: 'credit_card', label: 'Kreditkarte' },
] as const

type PaymentMethod = (typeof paymentMethodOptions)[number]['value']

interface RecordPaymentPayload {
  amount: number
  paymentDate: string
  paymentMethod: PaymentMethod
  notes: string
}

const props = defineProps<{
  isOpen: boolean
  invoice?: any
  isSubmitting: boolean
}>()

const emit = defineEmits<{
  close: []
  'record-payment': [payload: RecordPaymentPayload]
}>()

function todayIsoDate(): string {
  return new Date().toISOString().slice(0, 10)
}

const amount = ref(0)
const paymentDate = ref(todayIsoDate())
const paymentMethod = ref<PaymentMethod>('cash')
const notes = ref('')

const today = computed(() => todayIsoDate())

function resetForm() {
  amount.value = props.invoice?.remainingBalance ?? 0
  paymentDate.value = todayIsoDate()
  paymentMethod.value = 'cash'
  notes.value = ''
}

// Formular neu vorbelegen, sobald der Dialog geöffnet wird oder eine
// andere Rechnung übergeben wird (z. B. Dialog bleibt bei einem
// 422-Fehler offen, aber eine neue Rechnung wird ausgewählt).
watch(
  () => [props.isOpen, props.invoice],
  ([isOpen]) => {
    if (isOpen) {
      resetForm()
    }
  },
  { immediate: true },
)

function setFullBalance() {
  amount.value = props.invoice?.remainingBalance ?? 0
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(value)
}

const amountError = computed(() => {
  const remainingBalance = props.invoice?.remainingBalance ?? 0

  if (!(amount.value > 0)) {
    return 'Der Betrag muss größer als 0 sein.'
  }
  if (amount.value > remainingBalance) {
    return `Der Betrag darf den Restbetrag von ${formatCurrency(remainingBalance)} nicht übersteigen.`
  }
  return null
})

const dateError = computed(() => {
  if (!paymentDate.value) {
    return 'Bitte ein Datum angeben.'
  }
  if (paymentDate.value > today.value) {
    return 'Das Datum darf nicht in der Zukunft liegen.'
  }
  return null
})

const isValid = computed(() => amountError.value === null && dateError.value === null)

function submit() {
  if (!isValid.value) {
    return
  }

  emit('record-payment', {
    amount: amount.value,
    paymentDate: paymentDate.value,
    paymentMethod: paymentMethod.value,
    notes: notes.value,
  })
}
</script>
