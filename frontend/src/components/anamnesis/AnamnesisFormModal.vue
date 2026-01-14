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
                  {{ isEditMode ? 'Anamnese bearbeiten' : 'Neue Anamnese erstellen' }}
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
              <div v-if="error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-800">{{ error }}</p>
              </div>

              <form @submit.prevent="save" class="space-y-6">
                <!-- Dog Selection (only for create mode) -->
                <div v-if="!isEditMode">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Hund <span class="text-red-500">*</span>
                  </label>
                  <select v-model="form.dogId" required class="input">
                    <option value="">Bitte wählen</option>
                    <option v-for="dog in dogs" :key="dog.id" :value="dog.id">
                      {{ dog.name }} ({{ dog.customerName }})
                    </option>
                  </select>
                </div>

                <!-- Template Selection (only for create mode) -->
                <div v-if="!isEditMode">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Anamnese-Vorlage <span class="text-red-500">*</span>
                  </label>
                  <select v-model="form.templateId" @change="loadTemplate" required class="input">
                    <option value="">Bitte wählen</option>
                    <option v-for="template in templates" :key="template.id" :value="template.id">
                      {{ template.name }}
                    </option>
                  </select>
                  <p v-if="selectedTemplate" class="mt-2 text-sm text-gray-600">
                    {{ selectedTemplate.description }}
                  </p>
                </div>

                <!-- Questions -->
                <div v-if="questions.length > 0" class="space-y-6">
                  <h4 class="text-lg font-semibold text-gray-900 border-b pb-2">Fragen</h4>
                  
                  <div v-for="(question, index) in questions" :key="question.id" class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                      {{ index + 1 }}. {{ question.questionText }}
                      <span v-if="question.isRequired" class="text-red-500">*</span>
                    </label>
                    
                    <p v-if="question.helpText" class="text-sm text-gray-500">
                      {{ question.helpText }}
                    </p>

                    <!-- Text Input -->
                    <input
                      v-if="question.questionType === 'text'"
                      v-model="answers[question.id]"
                      type="text"
                      :required="question.isRequired"
                      class="input"
                      placeholder="Ihre Antwort..."
                    />

                    <!-- Textarea -->
                    <textarea
                      v-else-if="question.questionType === 'textarea'"
                      v-model="answers[question.id]"
                      :required="question.isRequired"
                      rows="4"
                      class="input"
                      placeholder="Ihre Antwort..."
                    ></textarea>

                    <!-- Radio Buttons -->
                    <div v-else-if="question.questionType === 'radio'" class="space-y-2">
                      <label v-for="option in getOptions(question)" :key="option" class="flex items-center space-x-2">
                        <input
                          type="radio"
                          v-model="answers[question.id]"
                          :value="option"
                          :required="question.isRequired"
                          class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300"
                        />
                        <span class="text-sm text-gray-700">{{ option }}</span>
                      </label>
                    </div>

                    <!-- Select Dropdown -->
                    <select
                      v-else-if="question.questionType === 'select'"
                      v-model="answers[question.id]"
                      :required="question.isRequired"
                      class="input"
                    >
                      <option value="">Bitte wählen</option>
                      <option v-for="option in getOptions(question)" :key="option" :value="option">
                        {{ option }}
                      </option>
                    </select>

                    <!-- Checkboxes -->
                    <div v-else-if="question.questionType === 'checkbox'" class="space-y-2">
                      <label v-for="option in getOptions(question)" :key="option" class="flex items-center space-x-2">
                        <input
                          type="checkbox"
                          :value="option"
                          v-model="checkboxAnswers[question.id]"
                          class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                        />
                        <span class="text-sm text-gray-700">{{ option }}</span>
                      </label>
                    </div>
                  </div>
                </div>

                <div v-else-if="form.templateId" class="text-center py-8 text-gray-500">
                  <svg class="animate-spin h-8 w-8 text-primary-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <p class="mt-2">Lade Fragen...</p>
                </div>
              </form>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 bg-gray-50 px-6 py-4 rounded-b-xl border-t border-gray-200">
              <div class="flex justify-end space-x-3">
                <button type="button" @click="close" class="btn bg-white hover:bg-gray-50 text-gray-700 border border-gray-300">
                  Abbrechen
                </button>
                <button type="submit" @click="save" :disabled="loading" class="btn btn-primary disabled:opacity-50">
                  <span v-if="!loading">{{ isEditMode ? 'Speichern' : 'Erstellen' }}</span>
                  <span v-else class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Speichern...
                  </span>
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
import { ref, watch, computed } from 'vue'
import { anamnesisTemplatesApi, anamnesisResponsesApi, type AnamnesisTemplate, type AnamnesisQuestion, type AnamnesisResponse } from '@/api/anamnesis'

interface Dog {
  id: number
  name: string
  customerId: number
  customerName: string
}

interface Props {
  modelValue: boolean
  anamnesisResponse?: AnamnesisResponse | null
  templates: AnamnesisTemplate[]
  dogs: Dog[]
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const form = ref({
  dogId: '',
  templateId: ''
})

const answers = ref<Record<number, string>>({})
const checkboxAnswers = ref<Record<number, string[]>>({})
const questions = ref<AnamnesisQuestion[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const isEditMode = computed(() => !!props.anamnesisResponse)
const selectedTemplate = computed(() => 
  props.templates.find(t => t.id === Number(form.value.templateId))
)

watch(() => props.modelValue, (newValue) => {
  if (newValue) {
    resetForm()
    if (props.anamnesisResponse) {
      loadExisting()
    }
  }
})

function resetForm() {
  form.value = {
    dogId: '',
    templateId: ''
  }
  answers.value = {}
  checkboxAnswers.value = {}
  questions.value = []
  error.value = null
}

async function loadExisting() {
  if (!props.anamnesisResponse) return

  form.value.dogId = String(props.anamnesisResponse.dogId)
  form.value.templateId = String(props.anamnesisResponse.templateId)
  
  await loadTemplate()

  // Load existing answers
  if (props.anamnesisResponse.answers) {
    props.anamnesisResponse.answers.forEach(answer => {
      const question = questions.value.find(q => q.id === answer.questionId)
      if (question?.questionType === 'checkbox') {
        try {
          checkboxAnswers.value[answer.questionId] = JSON.parse(answer.answerValue)
        } catch {
          checkboxAnswers.value[answer.questionId] = []
        }
      } else {
        answers.value[answer.questionId] = answer.answerValue
      }
    })
  }
}

async function loadTemplate() {
  if (!form.value.templateId) return

  try {
    const response = await anamnesisTemplatesApi.getQuestions(Number(form.value.templateId))
    questions.value = response.data
  } catch (err: any) {
    error.value = 'Fehler beim Laden der Fragen'
    console.error(err)
  }
}

async function save() {
  loading.value = true
  error.value = null

  try {
    const answersArray = questions.value.map(question => {
      let answerValue: string

      if (question.questionType === 'checkbox') {
        answerValue = JSON.stringify(checkboxAnswers.value[question.id] || [])
      } else {
        answerValue = answers.value[question.id] || ''
      }

      return {
        questionId: question.id,
        answerValue
      }
    }).filter(a => a.answerValue !== '' && a.answerValue !== '[]')

    if (isEditMode.value && props.anamnesisResponse) {
      await anamnesisResponsesApi.update(props.anamnesisResponse.id, {
        answers: answersArray
      })
    } else {
      await anamnesisResponsesApi.create({
        dogId: Number(form.value.dogId),
        templateId: Number(form.value.templateId),
        answers: answersArray
      })
    }

    emit('saved')
    close()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Fehler beim Speichern'
    console.error(err)
  } finally {
    loading.value = false
  }
}

function close() {
  emit('update:modelValue', false)
}

function getOptions(question: AnamnesisQuestion): string[] {
  if (!question.options) return []
  
  // If options is already an array, return it
  if (Array.isArray(question.options)) {
    return question.options
  }
  
  // If options is a string (shouldn't happen, but handle it), try to parse it
  if (typeof question.options === 'string') {
    try {
      const parsed = JSON.parse(question.options)
      return Array.isArray(parsed) ? parsed : []
    } catch {
      // If parsing fails, return empty array
      console.error('Failed to parse options for question:', question.id)
      return []
    }
  }
  
  return []
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
