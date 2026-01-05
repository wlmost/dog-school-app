<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex-1">
        <input
          v-model="searchQuery"
          @input="loadCustomers"
          type="text"
          placeholder="Kunden durchsuchen..."
          class="input max-w-md"
        />
      </div>
      <button @click="openCreateModal" class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Neuer Kunde
      </button>
    </div>

    <!-- Customers Table -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E-Mail</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefon</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hunde</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                <svg class="animate-spin h-8 w-8 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2">Lade Kundendaten...</p>
              </td>
            </tr>
            <tr v-else-if="!customers.length">
              <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                Keine Kunden gefunden
              </td>
            </tr>
            <tr v-else v-for="customer in customers" :key="customer.id" class="hover:bg-gray-50 cursor-pointer" @click="viewCustomer(customer)">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ customer.user?.fullName || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ customer.user?.email || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ customer.user?.phone || '-' }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ customer.dogs?.length || 0 }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                  Aktiv
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2" @click.stop>
                <button @click="editCustomer(customer)" class="text-primary-600 hover:text-primary-900">Bearbeiten</button>
                <button @click="deleteCustomer(customer)" class="text-red-600 hover:text-red-900">Löschen</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Customer Form Modal -->
    <CustomerFormModal 
      :is-open="showFormModal" 
      :customer="selectedCustomer"
      @close="closeFormModal"
      @saved="handleCustomerSaved"
    />

    <!-- Customer Detail Modal -->
    <CustomerDetailModal
      :is-open="showDetailModal"
      :customer="selectedCustomer"
      @close="closeDetailModal"
      @edit="editCustomer"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'
import CustomerFormModal from '@/components/CustomerFormModal.vue'
import CustomerDetailModal from '@/components/CustomerDetailModal.vue'
import { handleApiError, showSuccess } from '@/utils/errorHandler'

const router = useRouter()
const loading = ref(true)
const searchQuery = ref('')
const customers = ref<any[]>([])
const showFormModal = ref(false)
const showDetailModal = ref(false)
const selectedCustomer = ref<any>(null)

onMounted(() => {
  loadCustomers()
})

async function loadCustomers() {
  loading.value = true
  try {
    const params: any = {}
    if (searchQuery.value) {
      params.search = searchQuery.value
    }
    
    const response = await apiClient.get('/api/v1/customers', { params })
    customers.value = response.data.data
  } catch (error) {
    console.error('Error loading customers:', error)
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  selectedCustomer.value = null
  showFormModal.value = true
}

function editCustomer(customer: any) {
  selectedCustomer.value = customer
  showFormModal.value = true
  showDetailModal.value = false
}

function viewCustomer(customer: any) {
  selectedCustomer.value = customer
  showDetailModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  selectedCustomer.value = null
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedCustomer.value = null
}

async function handleCustomerSaved() {
  await loadCustomers()
  closeFormModal()
}

async function deleteCustomer(customer: any) {
  if (!confirm(`Möchten Sie den Kunden "${customer.user?.fullName}" wirklich löschen?`)) {
    return
  }

  try {
    await apiClient.delete(`/api/v1/customers/${customer.id}`)
    await loadCustomers()
    showSuccess('Kunde gelöscht', `${customer.user?.fullName} wurde erfolgreich gelöscht`)
  } catch (error) {
    handleApiError(error, 'Fehler beim Löschen des Kunden')
  }
}
</script>
