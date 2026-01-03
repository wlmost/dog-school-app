<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4">
        <select v-model="filterStatus" @change="loadInvoices" class="input max-w-xs">
          <option :value="null">Alle Rechnungen</option>
          <option value="draft">Entwurf</option>
          <option value="sent">Versendet</option>
          <option value="paid">Bezahlt</option>
          <option value="overdue">Überfällig</option>
          <option value="cancelled">Storniert</option>
        </select>
      </div>
      <button @click="openCreateModal" class="btn btn-primary">
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
            <tr v-else v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50 cursor-pointer" @click="viewInvoice(invoice)">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-mono font-medium text-gray-900">{{ invoice.invoiceNumber }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ invoice.customer?.user?.fullName || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ formatDate(invoice.invoiceDate) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ formatDate(invoice.dueDate) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ formatCurrency(invoice.totalAmount) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="invoiceStatusClass(invoice.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ invoiceStatusLabel(invoice.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2" @click.stop>
                <button @click="downloadPDF(invoice)" class="text-primary-600 hover:text-primary-900">PDF</button>
                <button v-if="invoice.status === 'draft' || invoice.status === 'sent'" @click="markAsPaid(invoice)" class="text-green-600 hover:text-green-900">Bezahlt</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Invoice Form Modal -->
    <InvoiceFormModal 
      :is-open="showFormModal" 
      :invoice="selectedInvoice"
      @close="closeFormModal"
      @saved="handleInvoiceSaved"
    />

    <!-- Invoice Detail Modal -->
    <InvoiceDetailModal
      :is-open="showDetailModal"
      :invoice="selectedInvoice"
      @close="closeDetailModal"
      @download="downloadPDF"
      @mark-paid="markAsPaid"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'
import InvoiceFormModal from '@/components/InvoiceFormModal.vue'
import InvoiceDetailModal from '@/components/InvoiceDetailModal.vue'

const loading = ref(true)
const filterStatus = ref<string | null>(null)
const invoices = ref<any[]>([])
const showFormModal = ref(false)
const showDetailModal = ref(false)
const selectedInvoice = ref<any>(null)

onMounted(() => {
  loadInvoices()
})

async function loadInvoices() {
  loading.value = true
  try {
    const params: any = {}
    if (filterStatus.value) {
      params.status = filterStatus.value
    }
    
    const response = await apiClient.get('/api/v1/invoices', { params })
    invoices.value = response.data.data
  } catch (error) {
    console.error('Error loading invoices:', error)
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  selectedInvoice.value = null
  showFormModal.value = true
}

function viewInvoice(invoice: any) {
  selectedInvoice.value = invoice
  showDetailModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  selectedInvoice.value = null
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedInvoice.value = null
}

async function handleInvoiceSaved() {
  await loadInvoices()
  closeFormModal()
}

async function downloadPDF(invoice: any) {
  try {
    const response = await apiClient.get(`/api/v1/invoices/${invoice.id}/pdf`, {
      responseType: 'blob'
    })
    
    // Create blob link to download
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `Rechnung-${invoice.invoiceNumber}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Herunterladen der PDF')
  }
}

async function markAsPaid(invoice: any) {
  if (!confirm(`Rechnung ${invoice.invoiceNumber} als bezahlt markieren?`)) {
    return
  }

  try {
    await apiClient.post(`/api/v1/invoices/${invoice.id}/mark-paid`)
    await loadInvoices()
    if (showDetailModal.value) {
      closeDetailModal()
    }
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Aktualisieren der Rechnung')
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
