<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" @close="closeModal" class="relative z-50">
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
            <DialogPanel class="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 mb-4">
                {{ invoice ? 'Rechnung bearbeiten' : 'Neue Rechnung' }}
              </DialogTitle>

              <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Customer & Dates -->
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kunde *</label>
                    <select v-model="form.customer_id" required class="input">
                      <option value="">Kunde auswählen...</option>
                      <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                        {{ customer.user?.full_name }}
                      </option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rechnungsdatum *</label>
                    <input v-model="form.invoice_date" type="date" required class="input" />
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fälligkeitsdatum *</label>
                    <input v-model="form.due_date" type="date" required class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select v-model="form.status" class="input">
                      <option value="draft">Entwurf</option>
                      <option value="sent">Versendet</option>
                      <option value="paid">Bezahlt</option>
                      <option value="cancelled">Storniert</option>
                    </select>
                  </div>
                </div>

                <!-- Invoice Items -->
                <div class="pt-4 border-t border-gray-200">
                  <div class="flex justify-between items-center mb-3">
                    <h4 class="text-sm font-medium text-gray-900">Rechnungspositionen</h4>
                    <button type="button" @click="addItem" class="text-sm text-primary-600 hover:text-primary-700">
                      + Position hinzufügen
                    </button>
                  </div>

                  <div v-for="(item, index) in form.items" :key="index" class="grid grid-cols-12 gap-2 mb-2">
                    <div class="col-span-5">
                      <input v-model="item.description" type="text" placeholder="Beschreibung" required class="input" />
                    </div>
                    <div class="col-span-2">
                      <input v-model.number="item.quantity" type="number" min="1" placeholder="Menge" required class="input" />
                    </div>
                    <div class="col-span-2">
                      <input v-model.number="item.unit_price" type="number" step="0.01" min="0" placeholder="Preis" required class="input" />
                    </div>
                    <div class="col-span-2">
                      <input :value="calculateItemTotal(item)" type="text" readonly class="input bg-gray-50" />
                    </div>
                    <div class="col-span-1 flex items-center justify-center">
                      <button type="button" @click="removeItem(index)" class="text-red-600 hover:text-red-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </div>

                  <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end">
                    <div class="text-right">
                      <div class="flex justify-between gap-8 mb-1">
                        <span class="text-sm text-gray-600">Zwischensumme:</span>
                        <span class="text-sm font-medium">{{ formatCurrency(calculateSubtotal()) }}</span>
                      </div>
                      <div class="flex justify-between gap-8 mb-1">
                        <span class="text-sm text-gray-600">MwSt (19%):</span>
                        <span class="text-sm font-medium">{{ formatCurrency(calculateTax()) }}</span>
                      </div>
                      <div class="flex justify-between gap-8 pt-2 border-t border-gray-200">
                        <span class="text-base font-medium">Gesamt:</span>
                        <span class="text-base font-bold">{{ formatCurrency(calculateTotal()) }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Notes -->
                <div class="pt-4 border-t border-gray-200">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                  <textarea v-model="form.notes" rows="3" class="input"></textarea>
                </div>

                <!-- Error Message -->
                <div v-if="error" class="rounded-md bg-red-50 p-4">
                  <p class="text-sm text-red-800">{{ error }}</p>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-3 pt-4">
                  <button type="button" @click="closeModal" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Abbrechen
                  </button>
                  <button type="submit" :disabled="loading" class="btn btn-primary disabled:opacity-50">
                    <span v-if="loading">Speichert...</span>
                    <span v-else>{{ invoice ? 'Aktualisieren' : 'Erstellen' }}</span>
                  </button>
                </div>
              </form>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import apiClient from '@/api/client'

const props = defineProps<{
  isOpen: boolean
  invoice?: any
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const customers = ref<any[]>([])

const form = ref({
  customer_id: '',
  invoice_date: new Date().toISOString().split('T')[0],
  due_date: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  status: 'draft',
  items: [
    { description: '', quantity: 1, unit_price: 0 }
  ],
  notes: ''
})

onMounted(() => {
  loadCustomers()
})

watch(() => props.invoice, (newInvoice) => {
  if (newInvoice) {
    form.value = {
      customer_id: newInvoice.customer_id,
      invoice_date: newInvoice.invoice_date,
      due_date: newInvoice.due_date,
      status: newInvoice.status,
      items: newInvoice.items?.length > 0 ? newInvoice.items : [{ description: '', quantity: 1, unit_price: 0 }],
      notes: newInvoice.notes || ''
    }
  } else {
    resetForm()
  }
}, { immediate: true })

async function loadCustomers() {
  try {
    const response = await apiClient.get('/api/v1/customers')
    customers.value = response.data.data
  } catch (err) {
    console.error('Error loading customers:', err)
  }
}

function addItem() {
  form.value.items.push({ description: '', quantity: 1, unit_price: 0 })
}

function removeItem(index: number) {
  form.value.items.splice(index, 1)
}

function calculateItemTotal(item: any) {
  return formatCurrency((item.quantity || 0) * (item.unit_price || 0))
}

function calculateSubtotal() {
  return form.value.items.reduce((sum, item) => sum + (item.quantity || 0) * (item.unit_price || 0), 0)
}

function calculateTax() {
  return calculateSubtotal() * 0.19
}

function calculateTotal() {
  return calculateSubtotal() + calculateTax()
}

function formatCurrency(amount: number) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount)
}

function resetForm() {
  form.value = {
    customer_id: '',
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    status: 'draft',
    items: [{ description: '', quantity: 1, unit_price: 0 }],
    notes: ''
  }
}

async function handleSubmit() {
  loading.value = true
  error.value = null

  try {
    const payload = {
      customerId: form.value.customer_id,
      invoiceDate: form.value.invoice_date,
      dueDate: form.value.due_date,
      status: form.value.status,
      items: form.value.items.map(item => ({
        description: item.description,
        quantity: item.quantity,
        unitPrice: item.unit_price
      })),
      notes: form.value.notes
    }

    if (props.invoice) {
      await apiClient.put(`/api/v1/invoices/${props.invoice.id}`, payload)
    } else {
      await apiClient.post('/api/v1/invoices', payload)
    }

    emit('saved')
    closeModal()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Ein Fehler ist aufgetreten'
  } finally {
    loading.value = false
  }
}

function closeModal() {
  resetForm()
  error.value = null
  emit('close')
}
</script>
