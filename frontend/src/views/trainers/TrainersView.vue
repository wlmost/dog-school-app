<template>
  <div class="p-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Trainer</h1>
      <button @click="openCreateModal" class="btn btn-primary">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Neuer Trainer
      </button>
    </div>

    <!-- Filter and View Controls -->
    <div class="mb-6 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
      <!-- Search Bar -->
      <div class="flex-1 max-w-md">
        <div class="relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Trainer suchen..."
            class="input pl-10"
          />
          <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
      </div>

      <!-- Sort and View Controls -->
      <div class="flex gap-3">
        <!-- Sort Dropdown -->
        <select v-model="sortBy" class="input">
          <option value="name">Nach Name</option>
          <option value="email">Nach E-Mail</option>
          <option value="city">Nach Stadt</option>
        </select>

        <!-- View Toggle -->
        <div class="flex bg-gray-100 rounded-lg p-1">
          <button
            @click="viewMode = 'cards'"
            :class="[
              'px-3 py-2 rounded transition-colors',
              viewMode === 'cards' ? 'bg-white shadow text-primary-600' : 'text-gray-600 hover:text-gray-900'
            ]"
            title="Kachelansicht"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
          </button>
          <button
            @click="viewMode = 'table'"
            :class="[
              'px-3 py-2 rounded transition-colors',
              viewMode === 'table' ? 'bg-white shadow text-primary-600' : 'text-gray-600 hover:text-gray-900'
            ]"
            title="Tabellenansicht"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-gray-600">Lade Trainer...</p>
    </div>

    <!-- Cards View -->
    <div v-else-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="trainer in filteredTrainers"
        :key="trainer.id"
        class="card hover:shadow-lg transition-shadow duration-200"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center space-x-3">
            <div class="w-12 h-12 rounded-full bg-primary-100 flex items-center justify-center">
              <span class="text-primary-600 font-semibold text-lg">
                {{ getInitials(trainer.firstName, trainer.lastName) }}
              </span>
            </div>
            <div>
              <h3 class="text-lg font-semibold text-gray-900">
                {{ trainer.fullName || `${trainer.firstName} ${trainer.lastName}` }}
              </h3>
              <p class="text-sm text-gray-500">Trainer</p>
            </div>
          </div>
        </div>

        <div class="space-y-2 mb-4">
          <div class="flex items-center text-sm text-gray-600">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            {{ trainer.email }}
          </div>

          <div v-if="trainer.phone" class="flex items-center text-sm text-gray-600">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
            {{ trainer.phone }}
          </div>

          <div v-if="trainer.city" class="flex items-center text-sm text-gray-600">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            {{ trainer.city }}
          </div>

          <div v-if="trainer.specializations" class="flex items-start text-sm text-gray-600">
            <svg class="w-4 h-4 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
            </svg>
            <span class="line-clamp-2">{{ trainer.specializations }}</span>
          </div>
        </div>

        <!-- Active Courses Count -->
        <div class="pt-3 border-t border-gray-200">
          <p class="text-sm text-gray-500">
            Aktive Kurse: <span class="font-semibold text-gray-900">{{ trainer.activeCourses || 0 }}</span>
          </p>
        </div>

        <!-- Actions -->
        <div class="flex space-x-2 mt-4">
          <button @click="editTrainer(trainer)" class="flex-1 btn bg-blue-50 hover:bg-blue-100 text-blue-700">
            Bearbeiten
          </button>
          <button @click="deleteTrainer(trainer)" class="flex-1 btn bg-red-50 hover:bg-red-100 text-red-700">
            Löschen
          </button>
        </div>
      </div>
    </div>

    <!-- Table View -->
    <div v-else-if="viewMode === 'table' && !loading" class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E-Mail</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefon</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stadt</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spezialisierungen</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kurse</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="trainer in filteredTrainers" :key="trainer.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                  <span class="text-primary-600 font-semibold">
                    {{ getInitials(trainer.firstName, trainer.lastName) }}
                  </span>
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-gray-900">
                    {{ trainer.fullName || `${trainer.firstName} ${trainer.lastName}` }}
                  </div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ trainer.email }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ trainer.phone || '-' }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ trainer.city || '-' }}</div>
            </td>
            <td class="px-6 py-4">
              <div class="text-sm text-gray-900 max-w-xs truncate" :title="trainer.specializations">
                {{ trainer.specializations || '-' }}
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                {{ trainer.activeCourses || 0 }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button @click="editTrainer(trainer)" class="text-blue-600 hover:text-blue-900 mr-4">
                Bearbeiten
              </button>
              <button @click="deleteTrainer(trainer)" class="text-red-600 hover:text-red-900">
                Löschen
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty State -->
    <div v-if="!loading && filteredTrainers.length === 0" class="text-center py-12">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900">
        {{ searchQuery ? 'Keine Trainer gefunden' : 'Keine Trainer' }}
      </h3>
      <p class="mt-1 text-sm text-gray-500">
        {{ searchQuery ? 'Versuchen Sie eine andere Suche.' : 'Beginnen Sie, indem Sie einen neuen Trainer anlegen.' }}
      </p>
    </div>

    <!-- Trainer Form Modal -->
    <TrainerFormModal 
      :is-open="showFormModal"
      :trainer="selectedTrainer"
      @close="closeFormModal"
      @saved="handleTrainerSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import apiClient from '@/api/client'
import TrainerFormModal from '@/components/TrainerFormModal.vue'
import { handleApiError, showSuccess } from '@/utils/errorHandler'

const loading = ref(false)
const trainers = ref<any[]>([])
const showFormModal = ref(false)
const selectedTrainer = ref<any>(null)
const searchQuery = ref('')
const sortBy = ref('name')
const viewMode = ref<'cards' | 'table'>('cards')

const filteredTrainers = computed(() => {
  let result = [...trainers.value]

  // Apply search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(trainer => {
      const fullName = `${trainer.firstName} ${trainer.lastName}`.toLowerCase()
      const email = trainer.email.toLowerCase()
      const city = (trainer.city || '').toLowerCase()
      return fullName.includes(query) || email.includes(query) || city.includes(query)
    })
  }

  // Apply sorting
  result.sort((a, b) => {
    switch (sortBy.value) {
      case 'name':
        const nameA = `${a.lastName} ${a.firstName}`.toLowerCase()
        const nameB = `${b.lastName} ${b.firstName}`.toLowerCase()
        return nameA.localeCompare(nameB)
      case 'email':
        return a.email.localeCompare(b.email)
      case 'city':
        const cityA = (a.city || '').toLowerCase()
        const cityB = (b.city || '').toLowerCase()
        return cityA.localeCompare(cityB)
      default:
        return 0
    }
  })

  return result
})

onMounted(() => {
  loadTrainers()
})

async function loadTrainers() {
  loading.value = true
  try {
    const response = await apiClient.get('/api/v1/trainers')
    trainers.value = response.data.data
  } catch (error) {
    console.error('Error loading trainers:', error)
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  selectedTrainer.value = null
  showFormModal.value = true
}

function editTrainer(trainer: any) {
  selectedTrainer.value = trainer
  showFormModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  selectedTrainer.value = null
}

async function handleTrainerSaved() {
  await loadTrainers()
  closeFormModal()
}

async function deleteTrainer(trainer: any) {
  if (!confirm(`Möchten Sie ${trainer.fullName || trainer.email} wirklich löschen?`)) {
    return
  }

  try {
    await apiClient.delete(`/api/v1/trainers/${trainer.id}`)
    await loadTrainers()
    showSuccess('Trainer gelöscht', 'Der Trainer wurde erfolgreich gelöscht')
  } catch (error) {
    handleApiError(error, 'Fehler beim Löschen des Trainers')
  }
}

function getInitials(firstName: string, lastName: string) {
  return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase()
}
</script>
