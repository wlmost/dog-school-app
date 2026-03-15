<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
        <div class="flex min-h-screen items-center justify-center p-4">
          <div class="fixed inset-0 bg-black/50 transition-opacity"></div>
          
          <div class="relative w-full max-w-4xl bg-white rounded-xl shadow-2xl" @click.stop>
            <!-- Header -->
            <div class="sticky top-0 z-10 bg-white border-b border-gray-200 px-6 py-4 rounded-t-xl">
              <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold text-gray-900">
                  Anamnese Details
                </h3>
                <button @click="close" class="text-gray-400 hover:text-gray-600 transition-colors">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Content -->
            <div class="px-6 py-4 max-h-[70vh] overflow-y-auto">
              <div v-if="loading" class="text-center py-12">
                <svg class="animate-spin h-12 w-12 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-4 text-gray-500">Lade Details...</p>
              </div>

              <div v-else-if="details" class="space-y-6">
                <!-- Info Section -->
                <div class="bg-gray-50 p-4 rounded-lg">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Hund</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ details.dogName }}</dd>
                    </div>
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Besitzer</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ details.customerName }}</dd>
                    </div>
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Vorlage</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ details.templateName }}</dd>
                    </div>
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Status</dt>
                      <dd class="mt-1">
                        <span :class="details.completedAt ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" 
                              class="px-2 py-1 text-xs font-medium rounded-full">
                          {{ details.completedAt ? 'Abgeschlossen' : 'Ausstehend' }}
                        </span>
                      </dd>
                    </div>
                    <div>
                      <dt class="text-sm font-medium text-gray-500">Erstellt am</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ formatDate(details.createdAt) }}</dd>
                    </div>
                    <div v-if="details.completedAt">
                      <dt class="text-sm font-medium text-gray-500">Abgeschlossen am</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ formatDate(details.completedAt) }}</dd>
                    </div>
                  </div>
                </div>

                <!-- Answers Section -->
                <div class="space-y-4">
                  <h4 class="text-lg font-semibold text-gray-900 border-b pb-2">Antworten</h4>
                  
                  <div v-if="!details.answers || details.answers.length === 0" class="text-center py-8 text-gray-500">
                    Noch keine Antworten vorhanden
                  </div>

                  <div v-else v-for="(answer, index) in details.answers" :key="answer.id" class="border-b border-gray-200 pb-4 last:border-0">
                    <dt class="text-sm font-medium text-gray-700 mb-2">
                      {{ index + 1 }}. {{ answer.questionText }}
                    </dt>
                    <dd class="text-sm text-gray-900 whitespace-pre-wrap pl-4">
                      {{ formatAnswer(answer.answerValue) }}
                    </dd>
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-xl border-t border-gray-200">
              <div class="flex justify-end">
                <button type="button" @click="close" class="btn btn-primary">
                  Schließen
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { anamnesisResponsesApi, type AnamnesisResponse } from '@/api/anamnesis'

interface Props {
  modelValue: boolean
  anamnesisResponse: AnamnesisResponse | null
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const loading = ref(false)
const details = ref<AnamnesisResponse | null>(null)

watch(() => props.modelValue, async (newValue) => {
  if (newValue && props.anamnesisResponse) {
    await loadDetails()
  }
})

async function loadDetails() {
  if (!props.anamnesisResponse) return

  loading.value = true
  try {
    details.value = await anamnesisResponsesApi.getById(props.anamnesisResponse.id)
  } catch (error) {
    console.error('Error loading details:', error)
  } finally {
    loading.value = false
  }
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString('de-DE', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatAnswer(value: string) {
  // Try to parse as JSON array (for checkbox answers)
  try {
    const parsed = JSON.parse(value)
    if (Array.isArray(parsed)) {
      return parsed.join(', ')
    }
  } catch {
    // Not JSON, return as-is
  }
  return value
}

function close() {
  emit('update:modelValue', false)
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
