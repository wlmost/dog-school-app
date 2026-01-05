<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">Anamnese-Verwaltung</h1>
    </div>

    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex gap-4">
        <select v-model="filterTemplateId" @change="loadResponses" class="input max-w-xs">
          <option value="">Alle Vorlagen</option>
          <option v-for="template in templates" :key="template.id" :value="template.id">
            {{ template.name }}
          </option>
        </select>
        <select v-model="filterCompleted" @change="loadResponses" class="input max-w-xs">
          <option value="">Alle Status</option>
          <option value="false">Ausstehend</option>
          <option value="true">Abgeschlossen</option>
        </select>
      </div>
      <button class="btn btn-primary" @click="openCreateModal">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Neue Anamnese
      </button>
    </div>

    <!-- Anamnesis Responses Table -->
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
            <tr v-if="loadingResponses">
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
                <div class="text-sm font-medium text-gray-900">{{ response.dogName }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ response.customerName }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ response.templateName }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">{{ formatDate(response.createdAt) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="statusClass(response.completedAt)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ statusLabel(response.completedAt) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button @click="viewResponse(response)" class="text-primary-600 hover:text-primary-900">Anzeigen</button>
                <button @click="downloadPdf(response.id)" class="text-blue-600 hover:text-blue-900">PDF</button>
                <button v-if="!response.completedAt" @click="editResponse(response)" class="text-green-600 hover:text-green-900">Bearbeiten</button>
                <button v-if="!response.completedAt" @click="completeResponse(response.id)" class="text-purple-600 hover:text-purple-900">Abschließen</button>
                <button @click="deleteResponse(response.id)" class="text-red-600 hover:text-red-900">Löschen</button>
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
          <div class="flex items-start justify-between mb-2">
            <h4 class="font-semibold text-gray-900">{{ template.name }}</h4>
            <span v-if="template.isDefault" class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">Standard</span>
          </div>
          <p class="text-sm text-gray-600 mb-3">{{ template.description }}</p>
          <div class="text-xs text-gray-500 mb-3">
            {{ template.questionsCount || 0 }} Fragen
          </div>
          <div class="flex space-x-2">
            <button @click="useTemplate(template)" class="btn btn-primary text-sm flex-1">Verwenden</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Anamnesis Form Modal -->
    <AnamnesisFormModal
      v-model="showFormModal"
      :anamnesis-response="selectedResponse"
      :templates="templates"
      :dogs="dogs"
      @saved="handleSaved"
    />

    <!-- Detail View Modal -->
    <AnamnesisDetailModal
      v-model="showDetailModal"
      :anamnesis-response="selectedResponse"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { anamnesisTemplatesApi, anamnesisResponsesApi, type AnamnesisTemplate, type AnamnesisResponse } from '@/api/anamnesis'
import AnamnesisFormModal from '@/components/anamnesis/AnamnesisFormModal.vue'
import AnamnesisDetailModal from '@/components/anamnesis/AnamnesisDetailModal.vue'
import apiClient from '@/api/client'

const loadingTemplates = ref(true)
const loadingResponses = ref(true)
const filterTemplateId = ref('')
const filterCompleted = ref('')
const showFormModal = ref(false)
const showDetailModal = ref(false)
const selectedResponse = ref<AnamnesisResponse | null>(null)
const responses = ref<AnamnesisResponse[]>([])
const templates = ref<AnamnesisTemplate[]>([])
const dogs = ref<any[]>([])

onMounted(async () => {
  await Promise.all([
    loadTemplates(),
    loadResponses(),
    loadDogs()
  ])
})

async function loadTemplates() {
  loadingTemplates.value = true
  try {
    const response = await anamnesisTemplatesApi.getAll()
    templates.value = response.data
  } catch (error) {
    console.error('Error loading templates:', error)
  } finally {
    loadingTemplates.value = false
  }
}

async function loadResponses() {
  loadingResponses.value = true
  try {
    const params: any = {}
    if (filterTemplateId.value) params.templateId = Number(filterTemplateId.value)
    if (filterCompleted.value) params.completed = filterCompleted.value === 'true'
    
    const response = await anamnesisResponsesApi.getAll(params)
    responses.value = response.data
  } catch (error) {
    console.error('Error loading responses:', error)
  } finally {
    loadingResponses.value = false
  }
}

async function loadDogs() {
  try {
    const response = await apiClient.get('/api/v1/dogs')
    dogs.value = response.data.data.map((dog: any) => ({
      id: dog.id,
      name: dog.name,
      customerId: dog.customerId,
      customerName: dog.customerName
    }))
  } catch (error) {
    console.error('Error loading dogs:', error)
  }
}

function openCreateModal() {
  selectedResponse.value = null
  showFormModal.value = true
}

function useTemplate(template: AnamnesisTemplate) {
  selectedResponse.value = null
  showFormModal.value = true
  // The template will be auto-selected if needed
}

function editResponse(response: AnamnesisResponse) {
  selectedResponse.value = response
  showFormModal.value = true
}

function viewResponse(response: AnamnesisResponse) {
  selectedResponse.value = response
  showDetailModal.value = true
}

async function completeResponse(id: number) {
  if (!confirm('Möchten Sie diese Anamnese als abgeschlossen markieren?')) return

  try {
    await anamnesisResponsesApi.complete(id)
    await loadResponses()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Abschließen')
  }
}

async function deleteResponse(id: number) {
  if (!confirm('Möchten Sie diese Anamnese wirklich löschen?')) return

  try {
    await anamnesisResponsesApi.delete(id)
    await loadResponses()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Löschen')
  }
}

async function downloadPdf(id: number) {
  try {
    const blob = await anamnesisResponsesApi.downloadPdf(id)
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `anamnesis-${id}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Download')
  }
}

function handleSaved() {
  loadResponses()
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString('de-DE')
}

function statusClass(completedAt: string | null) {
  return completedAt
    ? 'bg-green-100 text-green-800'
    : 'bg-yellow-100 text-yellow-800'
}

function statusLabel(completedAt: string | null) {
  return completedAt ? 'Abgeschlossen' : 'Ausstehend'
}
</script>
