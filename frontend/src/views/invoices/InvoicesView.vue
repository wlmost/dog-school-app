<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4">
        <select v-model="filterStatus" @change="onFilterChange" class="input max-w-xs">
          <option :value="null">Alle Rechnungen</option>
          <option value="draft">Entwurf</option>
          <option value="sent">Versendet</option>
          <option value="paid">Bezahlt</option>
          <option value="overdue">Überfällig</option>
          <option value="reminded">Gemahnt</option>
          <option value="cancelled">Storniert</option>
        </select>
      </div>
      <button v-if="!authStore.isCustomer" @click="openCreateModal" class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Neue Rechnung
      </button>
    </div>

    <!-- Invoices Table -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rechnungsnr.</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kunde</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Datum</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fällig am</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Betrag</th>
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
                <p class="mt-2">Lade Rechnungsdaten...</p>
              </td>
            </tr>
            <tr v-else-if="error">
              <td colspan="7" class="px-6 py-4">
                <div class="rounded-lg p-4" :class="forbidden ? 'bg-yellow-50 border border-yellow-200 text-yellow-800 dark:bg-yellow-900/20 dark:border-yellow-700 dark:text-yellow-300' : 'bg-red-50 border border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300'">
                  <p class="font-medium">{{ error }}</p>
                  <button v-if="!forbidden" @click="loadInvoices()" class="mt-2 text-sm underline hover:no-underline">
                    Erneut laden
                  </button>
                </div>
              </td>
            </tr>
            <tr v-else-if="!invoices.length">
              <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                Keine Rechnungen gefunden
              </td>
            </tr>
            <tr v-else v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" @click="viewInvoice(invoice)">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-mono font-medium text-gray-900 dark:text-gray-100">{{ invoice.invoiceNumber }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ invoice.customer?.user?.fullName || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(invoice.invoiceDate) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ formatDate(invoice.dueDate) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(invoice.totalAmount) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex flex-col items-start gap-1">
                  <span :class="invoiceStatusClass(invoice.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                    {{ invoiceStatusLabel(invoice.status) }}
                  </span>
                  <span v-if="invoice.isOverdue" class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                    Überfällig
                  </span>
                  <span v-if="invoice.status === 'paid'" class="text-xs text-gray-500 dark:text-gray-400">
                    Bezahlt am {{ formatDate(invoice.paidDate) }}
                  </span>
                  <span v-if="invoice.status === 'reminded'" class="text-xs text-gray-500 dark:text-gray-400">
                    Gemahnt am {{ formatDate(invoice.remindedAt) }}
                  </span>
                  <span v-if="invoice.totalPaid > 0 && invoice.status !== 'paid'" class="text-xs text-gray-500 dark:text-gray-400">
                    {{ formatCurrency(invoice.totalPaid) }} von {{ formatCurrency(invoice.totalAmount) }} bezahlt
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2" @click.stop>
                <button @click="downloadPDF(invoice)" class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300">PDF</button>
                <button v-if="canEdit(invoice)" @click="editInvoice(invoice)" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-900 dark:hover:text-yellow-300">Bearbeiten</button>
                <button v-if="canDelete(invoice)" @click="deleteInvoice(invoice)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">Löschen</button>
                <button v-if="canFinalize(invoice)" @click="finalizeInvoice(invoice)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Freigeben</button>
                <button v-if="canSend(invoice)" @click="openSendDialog(invoice)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Senden</button>
                <button v-if="canRecordPayment(invoice)" @click="openPaymentDialog(invoice)" class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300">Zahlung erfassen</button>
                <button v-if="canCancel(invoice)" @click="cancelInvoice(invoice)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">Stornieren</button>
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
      @edit="editFromDetail"
      @delete="deleteInvoice"
      @finalize="finalizeInvoice"
      @cancel="cancelInvoice"
      @send="openSendDialog"
      @record-payment="openPaymentDialog"
    />

    <!-- Invoice Send Dialog -->
    <InvoiceSendDialog
      :is-open="showSendDialog"
      :invoice="sendDialogInvoice"
      @close="closeSendDialog"
      @download="downloadPDF"
      @send-email="sendInvoiceEmail"
    />

    <!-- Invoice Payment Dialog -->
    <InvoicePaymentDialog
      :is-open="showPaymentDialog"
      :invoice="paymentDialogInvoice"
      :is-submitting="isRecordingPayment"
      @close="closePaymentDialog"
      @record-payment="(payload) => recordPayment(paymentDialogInvoice, payload)"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'
import InvoiceFormModal from '@/components/InvoiceFormModal.vue'
import InvoiceDetailModal from '@/components/InvoiceDetailModal.vue'
import InvoiceSendDialog from '@/components/InvoiceSendDialog.vue'
import InvoicePaymentDialog from '@/components/InvoicePaymentDialog.vue'
import PaginationControls from '@/components/PaginationControls.vue'
import { handleApiError, showSuccess } from '@/utils/errorHandler'
import { useAuthStore } from '@/stores/auth'
import { usePagination } from '@/composables/usePagination'

const authStore = useAuthStore()

const loading = ref(true)
const error = ref<string | null>(null)
const forbidden = ref(false)
const filterStatus = ref<string | null>(null)
const invoices = ref<any[]>([])
const showFormModal = ref(false)
const showDetailModal = ref(false)
const showSendDialog = ref(false)
const showPaymentDialog = ref(false)
const isRecordingPayment = ref(false)
const selectedInvoice = ref<any>(null)
// Eigener Ref statt Mitbenutzung von `selectedInvoice`: Der Send-Dialog kann
// laut design.md Decision D8 über dem weiterhin geöffneten Detail-Modal
// geöffnet werden. Würden sich beide Modals `selectedInvoice` teilen, würde
// `closeSendDialog()` es auf `null` setzen und damit das im Hintergrund noch
// offene Detail-Modal leerräumen (Reviewer-Befund, siehe review.md).
const sendDialogInvoice = ref<any>(null)
// Eigener Ref, gleiche Begründung wie `sendDialogInvoice` (design.md
// Decision D6): der Zahlungsdialog kann über dem weiterhin geöffneten
// Detail-Modal geöffnet werden, ein geteilter `selectedInvoice`-Ref würde
// beim Schließen des Zahlungsdialogs das Detail-Modal leerräumen.
const paymentDialogInvoice = ref<any>(null)

const { currentPage, lastPage, total, updateFromMeta, resetPage } = usePagination()

onMounted(() => {
  loadInvoices()
})

async function loadInvoices() {
  error.value = null
  forbidden.value = false
  loading.value = true
  try {
    const params: any = { page: currentPage.value }
    if (filterStatus.value) {
      params.status = filterStatus.value
    }
    
    const response = await apiClient.get('/api/v1/invoices', { params })
    invoices.value = response.data.data
    if (response.data.meta) {
      updateFromMeta(response.data.meta)
    }
  } catch (err) {
    console.error('Error loading invoices:', err)
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
  loadInvoices()
}

function onFilterChange(): void {
  resetPage()
  loadInvoices()
}

function openCreateModal() {
  selectedInvoice.value = null
  showFormModal.value = true
}

// Statuswerte, für die der (deaktivierte) Senden-Button angezeigt wird.
// Der persistierte Status `overdue` ist dabei nur der Vollständigkeit
// halber enthalten (siehe design.md Decision D3) — die tatsächliche
// "Überfällig"-Markierung erfolgt separat und ausschließlich anhand von
// `invoice.isOverdue`.
const SENDABLE_STATUSES = ['sent', 'reminded', 'overdue']

// Statuswerte, für die der Stornieren-Button angezeigt wird. Muss exakt
// InvoicePolicy::cancel() spiegeln (backend/app/Policies/InvoicePolicy.php),
// die `overdue` bewusst NICHT zulässt: `overdue` wird laut design.md
// Decision D3 nirgends mehr aktiv als Status geschrieben, ist also für
// neue Rechnungen kein erreichbarer Wert. Eine überfällige Rechnung trägt
// weiterhin den Status `sent`/`reminded` (`invoice.isOverdue` wird separat
// zur Anzeigezeit berechnet), sodass diese Liste den Storno-Button für
// überfällige Rechnungen bereits korrekt anzeigt — `overdue` hier
// aufzunehmen würde nur zu einem serverseitigen 403 führen.
const CANCELLABLE_STATUSES = ['sent', 'reminded', 'paid']

// Statuswerte, für die eine Zahlung erfasst werden darf. Muss
// `PaymentController::store()`s `PAYABLE_STATUSES`-Konstante spiegeln
// (backend/app/Http/Controllers/Api/PaymentController.php, siehe design.md
// Decision D3) — plus die Zusatzbedingung, dass noch ein Restbetrag offen
// ist (`remainingBalance > 0`).
const PAYABLE_STATUSES = ['sent', 'reminded', 'overdue']

function canEdit(invoice: any): boolean {
  return !authStore.isCustomer && invoice.status === 'draft'
}

function canDelete(invoice: any): boolean {
  return !authStore.isCustomer && invoice.status === 'draft'
}

function canFinalize(invoice: any): boolean {
  return !authStore.isCustomer && invoice.status === 'draft'
}

function canSend(invoice: any): boolean {
  return !authStore.isCustomer && SENDABLE_STATUSES.includes(invoice.status)
}

function canCancel(invoice: any): boolean {
  return !authStore.isCustomer
    && CANCELLABLE_STATUSES.includes(invoice.status)
    && !invoice.originalInvoiceId
}

function canRecordPayment(invoice: any): boolean {
  return !authStore.isCustomer
    && PAYABLE_STATUSES.includes(invoice.status)
    && invoice.remainingBalance > 0
}

function editInvoice(invoice: any) {
  selectedInvoice.value = invoice
  showFormModal.value = true
}

function editFromDetail(invoice: any) {
  closeDetailModal()
  editInvoice(invoice)
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

function openSendDialog(invoice: any) {
  sendDialogInvoice.value = invoice
  showSendDialog.value = true
}

function closeSendDialog() {
  showSendDialog.value = false
  sendDialogInvoice.value = null
}

function openPaymentDialog(invoice: any) {
  paymentDialogInvoice.value = invoice
  showPaymentDialog.value = true
}

function closePaymentDialog() {
  showPaymentDialog.value = false
  paymentDialogInvoice.value = null
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
    showSuccess('PDF heruntergeladen', 'Die Rechnung wurde erfolgreich heruntergeladen')
  } catch (error) {
    handleApiError(error, 'Fehler beim Herunterladen der PDF')
  }
}

interface RecordPaymentPayload {
  amount: number
  paymentDate: string
  paymentMethod: string
  notes: string
}

async function recordPayment(invoice: any, payload: RecordPaymentPayload) {
  isRecordingPayment.value = true
  try {
    await apiClient.post('/api/v1/payments', {
      invoiceId: invoice.id,
      amount: payload.amount,
      paymentDate: payload.paymentDate,
      paymentMethod: payload.paymentMethod,
      notes: payload.notes || undefined,
      status: 'completed',
    })
    await loadInvoices()
    closePaymentDialog()
    if (showDetailModal.value) {
      closeDetailModal()
    }
    showSuccess('Zahlung erfasst', 'Die Zahlung wurde erfolgreich erfasst')
  } catch (error) {
    handleApiError(error, 'Fehler beim Erfassen der Zahlung')
    // Dialog bleibt bewusst offen (auch bei 422 wegen Überzahlung/ungültigem
    // Status), damit der Betrag korrigiert werden kann — analog zum
    // Send-Dialog-Fehlerverhalten (design.md Decision D6).
  } finally {
    isRecordingPayment.value = false
  }
}

async function deleteInvoice(invoice: any) {
  if (!confirm('Diesen Rechnungsentwurf unwiderruflich löschen?')) {
    return
  }

  try {
    await apiClient.delete(`/api/v1/invoices/${invoice.id}`)
    await loadInvoices()
    if (showDetailModal.value) {
      closeDetailModal()
    }
    showSuccess('Rechnung gelöscht', 'Der Entwurf wurde gelöscht')
  } catch (error) {
    handleApiError(error, 'Fehler beim Löschen der Rechnung')
  }
}

async function finalizeInvoice(invoice: any) {
  if (!confirm('Rechnung freigeben? Es wird eine fortlaufende Rechnungsnummer vergeben, danach ist die Rechnung nicht mehr änderbar.')) {
    return
  }

  try {
    await apiClient.post(`/api/v1/invoices/${invoice.id}/finalize`)
    await loadInvoices()
    if (showDetailModal.value) {
      closeDetailModal()
    }
    showSuccess('Rechnung freigegeben', 'Die Rechnung wurde freigegeben und hat eine Rechnungsnummer erhalten')
  } catch (error) {
    handleApiError(error, 'Fehler beim Freigeben der Rechnung')
  }
}

async function cancelInvoice(invoice: any) {
  if (!confirm(`Rechnung ${invoice.invoiceNumber} stornieren? Es wird eine Stornorechnung erstellt, die diese Rechnung ausgleicht.`)) {
    return
  }

  try {
    await apiClient.post(`/api/v1/invoices/${invoice.id}/cancel`)
    await loadInvoices()
    if (showDetailModal.value) {
      closeDetailModal()
    }
    showSuccess('Rechnung storniert', 'Die Rechnung wurde storniert')
  } catch (error) {
    handleApiError(error, 'Fehler beim Stornieren der Rechnung')
  }
}

async function sendInvoiceEmail(invoice: any) {
  try {
    await apiClient.post(`/api/v1/invoices/${invoice.id}/send-email`)
    closeSendDialog()
    showSuccess('Rechnung versendet', 'Die Rechnung wurde per E-Mail versendet')
  } catch (error) {
    handleApiError(error, 'Fehler beim Versenden der Rechnung')
    // Dialog bleibt bewusst offen, damit sofort auf "Manuell versenden"
    // ausgewichen werden kann (design.md Decision D8).
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
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    overdue: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    reminded: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
  }
  return classes[status as keyof typeof classes] || classes.draft
}

function invoiceStatusLabel(status: string) {
  const labels = {
    draft: 'Entwurf',
    sent: 'Versendet',
    paid: 'Bezahlt',
    overdue: 'Überfällig',
    reminded: 'Gemahnt',
    cancelled: 'Storniert'
  }
  return labels[status as keyof typeof labels] || status
}
</script>
