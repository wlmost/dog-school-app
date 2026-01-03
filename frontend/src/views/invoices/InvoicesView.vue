<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4">
        <select class="input max-w-xs">
          <option value="">Alle Rechnungen</option>
          <option value="draft">Entwurf</option>
          <option value="sent">Versendet</option>
          <option value="paid">Bezahlt</option>
          <option value="overdue">Überfällig</option>
          <option value="cancelled">Storniert</option>
        </select>
      </div>
      <button class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Neue Rechnung
      </button>
    </div>

    <!-- Invoices Table -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rechnungsnr.</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kunde</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Datum</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fällig am</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Betrag</th>
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
                <p class="mt-2">Lade Rechnungsdaten...</p>
              </td>
            </tr>
            <tr v-else-if="!invoices.length">
              <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                Keine Rechnungen gefunden
              </td>
            </tr>
            <tr v-else v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ invoice.invoiceNumber }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ invoice.customer }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ invoice.invoiceDate }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ invoice.dueDate }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ formatCurrency(invoice.total) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="invoiceStatusClass(invoice.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ invoiceStatusLabel(invoice.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button class="text-primary-600 hover:text-primary-900">PDF</button>
                <button v-if="invoice.status === 'draft'" class="text-green-600 hover:text-green-900">Senden</button>
                <button v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'" class="text-blue-600 hover:text-blue-900">Bezahlt</button>
                <button class="text-gray-600 hover:text-gray-900">Details</button>
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
const invoices = ref<any[]>([])

onMounted(async () => {
  try {
    // Placeholder - replace with actual API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    invoices.value = [
      { id: 1, invoiceNumber: 'INV-2026-001', customer: 'Max Mustermann', invoiceDate: '01.01.2026', dueDate: '15.01.2026', total: 12500, status: 'sent' },
      { id: 2, invoiceNumber: 'INV-2026-002', customer: 'Anna Schmidt', invoiceDate: '02.01.2026', dueDate: '16.01.2026', total: 8000, status: 'paid' },
      { id: 3, invoiceNumber: 'INV-2026-003', customer: 'Peter Weber', invoiceDate: '03.01.2026', dueDate: '17.01.2026', total: 15000, status: 'overdue' }
    ]
  } catch (error) {
    console.error('Error loading invoices:', error)
  } finally {
    loading.value = false
  }
})

function formatCurrency(amount: number) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(amount / 100)
}

function invoiceStatusClass(status: string) {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    sent: 'bg-blue-100 text-blue-800',
    paid: 'bg-green-100 text-green-800',
    overdue: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-800'
  }
  return classes[status as keyof typeof classes] || classes.draft
}

function invoiceStatusLabel(status: string) {
  const labels = {
    draft: 'Entwurf',
    sent: 'Versendet',
    paid: 'Bezahlt',
    overdue: 'Überfällig',
    cancelled: 'Storniert'
  }
  return labels[status as keyof typeof labels] || status
}
</script>
