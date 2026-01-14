<template>
  <div v-if="isOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
      <!-- Header -->
      <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
        <h3 class="text-xl font-semibold text-gray-900">
          {{ isEditMode ? 'Vorlage bearbeiten' : 'Neue Vorlage erstellen' }}
        </h3>
        <button @click="close" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto px-6 py-4">
        <!-- Error Message -->
        <div v-if="error" class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
          {{ error }}
        </div>

        <!-- Template Info -->
        <div class="space-y-4 mb-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Vorlagenname <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="z.B. Welpen-Anamnese"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Beschreibung
            </label>
            <textarea
              v-model="form.description"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              placeholder="Kurze Beschreibung der Vorlage"
            ></textarea>
          </div>
        </div>

        <!-- Questions Section -->
        <div class="mb-4">
          <div class="flex justify-between items-center mb-3">
            <h4 class="text-lg font-semibold text-gray-900">
              Fragen ({{ form.questions.length }})
            </h4>
            <button
              @click="addQuestion"
              type="button"
              class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Frage hinzufügen
            </button>
          </div>

          <!-- Questions List -->
          <div v-if="form.questions.length === 0" class="text-center py-8 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg">
            Noch keine Fragen hinzugefügt. Klicken Sie auf "Frage hinzufügen" um zu beginnen.
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="(question, index) in form.questions"
              :key="index"
              class="border border-gray-300 rounded-lg p-4 bg-gray-50"
            >
              <div class="flex justify-between items-start mb-3">
                <div class="flex items-center space-x-2">
                  <span class="text-sm font-semibold text-gray-600 bg-white px-2 py-1 rounded">
                    #{{ index + 1 }}
                  </span>
                  <button
                    v-if="index > 0"
                    @click="moveQuestionUp(index)"
                    type="button"
                    class="text-gray-500 hover:text-gray-700"
                    title="Nach oben"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                  </button>
                  <button
                    v-if="index < form.questions.length - 1"
                    @click="moveQuestionDown(index)"
                    type="button"
                    class="text-gray-500 hover:text-gray-700"
                    title="Nach unten"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>
                <button
                  @click="removeQuestion(index)"
                  type="button"
                  class="text-red-600 hover:text-red-800"
                  title="Frage entfernen"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>

              <!-- Question Text -->
              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Fragetext <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="question.question_text"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Frage eingeben..."
                />
              </div>

              <!-- Question Type -->
              <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Fragetyp <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="question.question_type"
                    @change="onQuestionTypeChange(index)"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  >
                    <option value="text">Text (kurz)</option>
                    <option value="textarea">Text (lang)</option>
                    <option value="radio">Einfachauswahl</option>
                    <option value="select">Dropdown</option>
                    <option value="checkbox">Mehrfachauswahl</option>
                  </select>
                </div>

                <div>
                  <label class="flex items-center space-x-2 pt-7">
                    <input
                      v-model="question.is_required"
                      type="checkbox"
                      class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                    />
                    <span class="text-sm font-medium text-gray-700">Pflichtfeld</span>
                  </label>
                </div>
              </div>

              <!-- Options (for radio, select, checkbox) -->
              <div v-if="['radio', 'select', 'checkbox'].includes(question.question_type)" class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Auswahlmöglichkeiten <span class="text-red-500">*</span>
                </label>
                <div class="space-y-2">
                  <div
                    v-for="(option, optIndex) in question.options"
                    :key="optIndex"
                    class="flex items-center space-x-2"
                  >
                    <input
                      v-model="question.options[optIndex]"
                      type="text"
                      class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                      :placeholder="`Option ${optIndex + 1}`"
                    />
                    <button
                      v-if="question.options.length > 1"
                      @click="removeOption(index, optIndex)"
                      type="button"
                      class="text-red-600 hover:text-red-800"
                    >
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                  <button
                    @click="addOption(index)"
                    type="button"
                    class="text-sm text-indigo-600 hover:text-indigo-800 flex items-center"
                  >
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Option hinzufügen
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3 bg-gray-50">
        <button
          @click="close"
          type="button"
          class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
        >
          Abbrechen
        </button>
        <button
          @click="save"
          :disabled="saving || !isValid"
          type="button"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ saving ? 'Speichern...' : 'Speichern' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { anamnesisTemplatesApi } from '@/api/anamnesis'

interface Question {
  question_text: string
  question_type: 'text' | 'textarea' | 'radio' | 'select' | 'checkbox'
  is_required: boolean
  options: string[]
  order: number
}

interface TemplateForm {
  name: string
  description: string | null
  questions: Question[]
}

interface Props {
  isOpen: boolean
  template?: any
}

interface Emits {
  (e: 'close'): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const saving = ref(false)
const error = ref<string | null>(null)

const form = ref<TemplateForm>({
  name: '',
  description: null,
  questions: []
})

const isEditMode = computed(() => !!props.template)

const isValid = computed(() => {
  if (!form.value.name.trim()) return false
  if (form.value.questions.length === 0) return false
  
  // Check all questions are valid
  return form.value.questions.every(q => {
    if (!q.question_text.trim()) return false
    if (['radio', 'select', 'checkbox'].includes(q.question_type)) {
      return q.options.length > 0 && q.options.every(opt => opt.trim())
    }
    return true
  })
})

// Watch for template changes
watch(() => props.template, (newTemplate) => {
  if (newTemplate) {
    form.value = {
      name: newTemplate.name || '',
      description: newTemplate.description || null,
      questions: newTemplate.questions?.map((q: any, index: number) => ({
        question_text: q.questionText || '',
        question_type: q.questionType || 'text',
        is_required: q.isRequired || false,
        options: Array.isArray(q.options) ? [...q.options] : [],
        order: index
      })) || []
    }
  } else {
    resetForm()
  }
}, { immediate: true })

watch(() => props.isOpen, (isOpen) => {
  if (isOpen && !props.template) {
    resetForm()
  }
  error.value = null
})

function resetForm() {
  form.value = {
    name: '',
    description: null,
    questions: []
  }
}

function addQuestion() {
  form.value.questions.push({
    question_text: '',
    question_type: 'text',
    is_required: false,
    options: [],
    order: form.value.questions.length
  })
}

function removeQuestion(index: number) {
  form.value.questions.splice(index, 1)
  // Update order
  form.value.questions.forEach((q, i) => {
    q.order = i
  })
}

function moveQuestionUp(index: number) {
  if (index === 0) return
  const temp = form.value.questions[index]
  form.value.questions[index] = form.value.questions[index - 1]
  form.value.questions[index - 1] = temp
  // Update order
  form.value.questions.forEach((q, i) => {
    q.order = i
  })
}

function moveQuestionDown(index: number) {
  if (index === form.value.questions.length - 1) return
  const temp = form.value.questions[index]
  form.value.questions[index] = form.value.questions[index + 1]
  form.value.questions[index + 1] = temp
  // Update order
  form.value.questions.forEach((q, i) => {
    q.order = i
  })
}

function onQuestionTypeChange(index: number) {
  const question = form.value.questions[index]
  // Initialize options for selection types
  if (['radio', 'select', 'checkbox'].includes(question.question_type)) {
    if (question.options.length === 0) {
      question.options = ['', '']
    }
  } else {
    question.options = []
  }
}

function addOption(questionIndex: number) {
  form.value.questions[questionIndex].options.push('')
}

function removeOption(questionIndex: number, optionIndex: number) {
  form.value.questions[questionIndex].options.splice(optionIndex, 1)
}

async function save() {
  if (!isValid.value) return

  saving.value = true
  error.value = null

  try {
    const payload = {
      name: form.value.name.trim(),
      description: form.value.description?.trim() || null,
      isDefault: false,
      questions: form.value.questions.map((q, index) => ({
        questionText: q.question_text.trim(),
        questionType: q.question_type,
        isRequired: q.is_required,
        options: ['radio', 'select', 'checkbox'].includes(q.question_type) 
          ? q.options.filter(opt => opt.trim()).map(opt => opt.trim())
          : null,
        order: index
      }))
    }

    if (isEditMode.value) {
      await anamnesisTemplatesApi.updateTemplate(props.template.id, payload)
    } else {
      await anamnesisTemplatesApi.createTemplate(payload)
    }

    emit('saved')
    close()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Fehler beim Speichern der Vorlage'
    console.error('Error saving template:', err)
  } finally {
    saving.value = false
  }
}

function close() {
  emit('close')
}
</script>
