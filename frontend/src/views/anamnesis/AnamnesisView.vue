<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4">
        <select v-model="selectedTemplate" class="input max-w-xs">
          <option value="">Alle Vorlagen</option>
          <option v-for="template in templates" :key="template.id" :value="template.id">
            {{ template.name }}
          </option>
        </select>
        <select v-model="filterStatus" class="input max-w-xs">
          <option value="">Alle Status</option>
          <option value="pending">Ausstehend</option>
          <option value="completed">Abgeschlossen</option>
        </select>
      </div>
      <button class="btn btn-primary" @click="showNewAnamnesisModal = true">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Neue Anamnese
      </button>
    </div>

    <!-- Anamnesis Table -->
    <div class="card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hund</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Besitzer</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vorlage</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Erstellt am</th>
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
                <p class="mt-2">Lade Anamnese-Daten...</p>
              </td>
            </tr>
            <tr v-else-if="!responses.length">
              <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                Keine Anamnese-Bögen gefunden
              </td>
            </tr>
            <tr v-else v-for="response in responses" :key="response.id" class="hover:bg-gray-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ response.dog }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ response.owner }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ response.template }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ response.created_at }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="statusClass(response.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ statusLabel(response.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button class="text-primary-600 hover:text-primary-900">Anzeigen</button>
                <button class="text-blue-600 hover:text-blue-900">PDF</button>
                <button v-if="response.status === 'pending'" class="text-green-600 hover:text-green-900">Bearbeiten</button>
                <button class="text-red-600 hover:text-red-900">Löschen</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Templates Section -->
    <div class="card">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Anamnese-Vorlagen</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-if="loadingTemplates" class="text-center py-8 col-span-full">
          <svg class="animate-spin h-8 w-8 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p class="mt-2 text-gray-500">Lade Vorlagen...</p>
        </div>
        <div v-else v-for="template in templates" :key="template.id" class="p-4 border border-gray-200 rounded-lg hover:border-primary-300 hover:shadow-md transition-all">
          <h4 class="font-semibold text-gray-900 mb-2">{{ template.name }}</h4>
          <p class="text-sm text-gray-600 mb-3">{{ template.description }}</p>
          <div class="text-xs text-gray-500 mb-3">
            {{ template.questions_count }} Fragen
          </div>
          <div class="flex space-x-2">
            <button class="btn btn-primary text-sm flex-1">Verwenden</button>
            <button class="btn bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm">Bearbeiten</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const loading = ref(true)
const loadingTemplates = ref(true)
const selectedTemplate = ref('')
const filterStatus = ref('')
const showNewAnamnesisModal = ref(false)
const responses = ref<any[]>([])
const templates = ref<any[]>([])

onMounted(async () => {
  try {
    // Placeholder - replace with actual API calls
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    responses.value = [
      { id: 1, dog: 'Bello', owner: 'Max Mustermann', template: 'Welpen-Anamnese', created_at: '01.01.2026', status: 'completed' },
      { id: 2, dog: 'Luna', owner: 'Anna Schmidt', template: 'Standard-Anamnese', created_at: '02.01.2026', status: 'pending' },
      { id: 3, dog: 'Rex', owner: 'Peter Weber', template: 'Verhaltens-Anamnese', created_at: '03.01.2026', status: 'completed' }
    ]

    templates.value = [
      { id: 1, name: 'Welpen-Anamnese', description: 'Anamnese für Welpen bis 6 Monate', questions_count: 15 },
      { id: 2, name: 'Standard-Anamnese', description: 'Allgemeine Anamnese für alle Hunde', questions_count: 25 },
      { id: 3, name: 'Verhaltens-Anamnese', description: 'Detaillierte Verhaltensanalyse', questions_count: 30 }
    ]
  } catch (error) {
    console.error('Error loading anamnesis data:', error)
  } finally {
    loading.value = false
    loadingTemplates.value = false
  }
})

function statusClass(status: string) {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    completed: 'bg-green-100 text-green-800'
  }
  return classes[status as keyof typeof classes] || classes.pending
}

function statusLabel(status: string) {
  const labels = {
    pending: 'Ausstehend',
    completed: 'Abgeschlossen'
  }
  return labels[status as keyof typeof labels] || status
}
</script>
