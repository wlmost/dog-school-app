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
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Anamnese-Vorlagen</h3>
        <button @click="openTemplateModal()" class="btn btn-primary">
          <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Neue Vorlage
        </button>
      </div>

      <!-- Template Tabs -->
      <div class="mb-4 border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
          <button
            @click="templateTab = 'custom'"
            :class="[
              'py-2 px-1 border-b-2 font-medium text-sm',
              templateTab === 'custom'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]"
          >
            Eigene Vorlagen
          </button>
          <button
            @click="templateTab = 'default'"
            :class="[
              'py-2 px-1 border-b-2 font-medium text-sm',
              templateTab === 'default'
                ? 'border-primary-500 text-primary-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]"
          >
            Standard-Vorlagen
          </button>
        </nav>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-if="loadingTemplates" class="text-center py-8 col-span-full">
          <svg class="animate-spin h-8 w-8 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p class="mt-2 text-gray-500">Lade Vorlagen...</p>
        </div>
        <div v-else-if="filteredTemplates.length === 0" class="col-span-full text-center py-12">
          <p class="text-gray-500">Keine Vorlagen gefunden</p>
          <button v-if="templateTab === 'custom'" @click="openTemplateModal()" class="mt-4 btn btn-primary">
            Erste Vorlage erstellen
          </button>
        </div>
        <div v-else v-for="template in filteredTemplates" :key="template.id" class="p-4 border border-gray-200 rounded-lg hover:border-primary-300 hover:shadow-md transition-all">
          <div class="flex items-start justify-between mb-2">
            <h4 class="font-semibold text-gray-900">{{ template.name }}</h4>
            <span v-if="template.isDefault" class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">Standard</span>
          </div>
          <p class="text-sm text-gray-600 mb-3">{{ template.description }}</p>
          <div class="text-xs text-gray-500 mb-3">
            {{ template.questionsCount || 0 }} Fragen
          </div>
          <div class="flex flex-wrap gap-2">
            <button @click="useTemplate(template)" class="btn btn-primary text-sm flex-1">Verwenden</button>
            <button v-if="!template.isDefault" @click="openTemplateModal(template)" class="btn btn-secondary text-sm" title="Bearbeiten">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>
            <button v-if="!template.isDefault" @click="duplicateTemplate(template)" class="btn btn-secondary text-sm" title="Duplizieren">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </button>
            <button v-if="!template.isDefault" @click="deleteTemplate(template.id)" class="btn btn-danger text-sm" title="Löschen">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
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

    <!-- Template Form Modal -->
    <AnamnesisTemplateFormModal
      v-model="showTemplateModal"
      :template="selectedTemplate"
      @saved="handleTemplateSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { anamnesisTemplatesApi, anamnesisResponsesApi, type AnamnesisTemplate, type AnamnesisResponse } from '@/api/anamnesis'
import AnamnesisFormModal from '@/components/anamnesis/AnamnesisFormModal.vue'
import AnamnesisDetailModal from '@/components/anamnesis/AnamnesisDetailModal.vue'
import AnamnesisTemplateFormModal from '@/components/anamnesis/AnamnesisTemplateFormModal.vue'
import apiClient from '@/api/client'

const loadingTemplates = ref(true)
const loadingResponses = ref(true)
const filterTemplateId = ref('')
const filterCompleted = ref('')
const showFormModal = ref(false)
const showDetailModal = ref(false)
const showTemplateModal = ref(false)
const selectedResponse = ref<AnamnesisResponse | null>(null)
const selectedTemplate = ref<AnamnesisTemplate | null>(null)
const templateTab = ref<'custom' | 'default'>('custom')
const responses = ref<AnamnesisResponse[]>([])
const templates = ref<AnamnesisTemplate[]>([])
const dogs = ref<any[]>([])

const filteredTemplates = computed(() => {
  return templates.value.filter(t => 
    templateTab.value === 'default' ? t.isDefault : !t.isDefault
  )
})

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

function openTemplateModal(template?: AnamnesisTemplate) {
  selectedTemplate.value = template || null
  showTemplateModal.value = true
}

async function handleTemplateSaved() {
  await loadTemplates()
  showTemplateModal.value = false
  selectedTemplate.value = null
}

async function duplicateTemplate(template: AnamnesisTemplate) {
  if (!confirm(`Möchten Sie die Vorlage "${template.name}" duplizieren?`)) return

  try {
    await anamnesisTemplatesApi.duplicate(template.id)
    await loadTemplates()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Duplizieren')
  }
}

async function deleteTemplate(id: number) {
  if (!confirm('Möchten Sie diese Vorlage wirklich löschen?')) return

  try {
    await anamnesisTemplatesApi.delete(id)
    await loadTemplates()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Löschen')
  }
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
