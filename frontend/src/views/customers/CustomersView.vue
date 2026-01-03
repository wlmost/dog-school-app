<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex-1">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Kunden durchsuchen..."
          class="input max-w-md"
        />
      </div>
      <button class="btn btn-primary">
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
            <tr v-else v-for="customer in customers" :key="customer.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ customer.name }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ customer.email }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ customer.phone }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ customer.dogsCount }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="customer.isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ customer.isActive ? 'Aktiv' : 'Inaktiv' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button class="text-primary-600 hover:text-primary-900">Bearbeiten</button>
                <button class="text-red-600 hover:text-red-900">Löschen</button>
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
const searchQuery = ref('')
const customers = ref<any[]>([])

onMounted(async () => {
  try {
    // Placeholder - replace with actual API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    customers.value = [
      { id: 1, name: 'Max Mustermann', email: 'max@example.com', phone: '0123 456789', dogsCount: 2, isActive: true },
      { id: 2, name: 'Anna Schmidt', email: 'anna@example.com', phone: '0123 987654', dogsCount: 1, isActive: true },
      { id: 3, name: 'Peter Weber', email: 'peter@example.com', phone: '0123 123456', dogsCount: 1, isActive: false }
    ]
  } catch (error) {
    console.error('Error loading customers:', error)
  } finally {
    loading.value = false
  }
})
</script>
