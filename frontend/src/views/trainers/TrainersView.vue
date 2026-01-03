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

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
      <p class="mt-2 text-gray-600">Lade Trainer...</p>
    </div>

    <!-- Trainers List -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="trainer in trainers"
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

    <!-- Empty State -->
    <div v-if="!loading && trainers.length === 0" class="text-center py-12">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900">Keine Trainer</h3>
      <p class="mt-1 text-sm text-gray-500">Beginnen Sie, indem Sie einen neuen Trainer anlegen.</p>
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
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'
import TrainerFormModal from '@/components/TrainerFormModal.vue'

const loading = ref(false)
const trainers = ref<any[]>([])
const showFormModal = ref(false)
const selectedTrainer = ref<any>(null)

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
  } catch (error: any) {
    alert(error.response?.data?.message || 'Fehler beim Löschen des Trainers')
  }
}

function getInitials(firstName: string, lastName: string) {
  return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase()
}
</script>
