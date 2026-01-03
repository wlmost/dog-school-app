<template>
  <div class="space-y-6">
    <!-- Header Actions -->
    <div class="flex justify-between items-center">
      <div class="flex-1">
        <input
          v-model="searchQuery"
          @input="loadDogs"
          type="text"
          placeholder="Hunde durchsuchen..."
          class="input max-w-md"
        />
      </div>
      <button @click="openCreateModal" class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
        </svg>
        Neuer Hund
      </button>
    </div>

    <!-- Dogs Grid -->
    <div v-if="loading" class="text-center py-12">
      <svg class="animate-spin h-12 w-12 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="mt-4 text-gray-500">Lade Hundedaten...</p>
    </div>

    <div v-else-if="!dogs.length" class="card text-center py-12 text-gray-500">
      Keine Hunde gefunden
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="dog in dogs" :key="dog.id" class="card hover:shadow-lg transition-shadow cursor-pointer" @click="viewDog(dog)">
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1">
            <h3 class="text-lg font-semibold text-gray-900">{{ dog.name }}</h3>
            <p class="text-sm text-gray-600">{{ dog.breed }}</p>
          </div>
          <span class="text-3xl">🐕</span>
        </div>
        
        <div class="space-y-2 mb-4">
          <div class="flex items-center text-sm text-gray-600">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            {{ dog.customer?.user?.fullName || '-' }}
          </div>
          <div v-if="dog.dateOfBirth" class="flex items-center text-sm text-gray-600">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            {{ formatDate(dog.dateOfBirth) }}
          </div>
          <div v-if="dog.chipNumber" class="flex items-center text-sm text-gray-600">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
            Chip: {{ dog.chipNumber }}
          </div>
        </div>

        <div class="flex space-x-2 pt-4 border-t border-gray-200" @click.stop>
          <button @click="editDog(dog)" class="btn btn-primary flex-1 text-sm">Bearbeiten</button>
          <button @click="deleteDog(dog)" class="btn bg-red-100 hover:bg-red-200 text-red-700 flex-1 text-sm">Löschen</button>
        </div>
      </div>
    </div>

    <!-- Dog Form Modal -->
    <DogFormModal 
      :is-open="showFormModal" 
      :dog="selectedDog"
      @close="closeFormModal"
      @saved="handleDogSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'
import DogFormModal from '@/components/DogFormModal.vue'

const loading = ref(true)
const searchQuery = ref('')
const dogs = ref<any[]>([])
const showFormModal = ref(false)
const selectedDog = ref<any>(null)

onMounted(() => {
  loadDogs()
})

async function loadDogs() {
  loading.value = true
  try {
    const params: any = {}
    if (searchQuery.value) {
      params.search = searchQuery.value
    }
    
    const response = await apiClient.get('/api/v1/dogs', { params })
    dogs.value = response.data.data
  } catch (error) {
    console.error('Error loading dogs:', error)
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  selectedDog.value = null
  showFormModal.value = true
}

function editDog(dog: any) {
  selectedDog.value = dog
  showFormModal.value = true
}

function viewDog(dog: any) {
  // Could open a detail modal here
  console.log('View dog:', dog)
}

function closeFormModal() {
  showFormModal.value = false
  selectedDog.value = null
}

async function handleDogSaved() {
  await loadDogs()
  closeFormModal()
}

async function deleteDog(dog: any) {
  if (!confirm(`Möchten Sie ${dog.name} wirklich löschen?`)) {
    return
  }

  try {
    await apiClient.delete(`/api/v1/dogs/${dog.id}`)
    await loadDogs()
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Löschen des Hundes')
  }
}

function formatDate(date: string) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('de-DE')
}
</script>
