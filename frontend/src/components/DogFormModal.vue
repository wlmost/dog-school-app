<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" @close="closeModal" class="relative z-50">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black bg-opacity-25" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 mb-4">
                {{ dog ? 'Hund bearbeiten' : 'Neuer Hund' }}
              </DialogTitle>

              <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Profile Image -->
                <div class="flex items-center space-x-4">
                  <div class="relative">
                    <div
                      v-if="imagePreview || dog?.profileImageUrl"
                      class="w-20 h-20 rounded-full overflow-hidden border-2 border-gray-200"
                    >
                      <img
                        :src="imagePreview || dog?.profileImageUrl"
                        alt="Hundebild"
                        class="w-full h-full object-cover"
                      />
                    </div>
                    <div
                      v-else
                      class="w-20 h-20 rounded-full bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center text-3xl"
                    >
                      🐕
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Profilbild</label>
                    <label class="cursor-pointer">
                      <span class="btn bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm">
                        Bild auswählen
                      </span>
                      <input
                        type="file"
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        class="hidden"
                        @change="handleImageChange"
                      />
                    </label>
                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, GIF oder WebP, max. 5 MB</p>
                    <button
                      v-if="imagePreview"
                      type="button"
                      @click="clearImageSelection"
                      class="text-xs text-red-600 hover:text-red-800 mt-1 block"
                    >
                      Auswahl aufheben
                    </button>
                  </div>
                </div>

                <!-- Basic Info -->
                <div class="grid grid-cols-2 gap-4">
                <!-- Besitzer: admin/trainer see dropdown, customers see their name as text -->
                <div class="col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Besitzer *</label>
                  <template v-if="isCustomer">
                    <p class="input bg-gray-50 text-gray-600">{{ dog?.customer?.user?.fullName || 'Sie' }}</p>
                  </template>
                  <template v-else>
                    <select v-model="form.customer_id" required class="input">
                      <option value="">Besitzer auswählen...</option>
                      <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                        {{ customer.user?.fullName }}
                      </option>
                    </select>
                  </template>
                </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input v-model="form.name" type="text" required class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rasse *</label>
                    <input v-model="form.breed" type="text" required class="input" />
                  </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Geburtsdatum</label>
                    <input 
                      v-model="form.date_of_birth" 
                      type="date" 
                      class="input" 
                      @click="($event.target as HTMLInputElement).showPicker?.()" 
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Geschlecht</label>
                    <select v-model="form.gender" class="input">
                      <option value="">Nicht angegeben</option>
                      <option value="male">Rüde</option>
                      <option value="female">Hündin</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gewicht (kg)</label>
                    <input v-model.number="form.weight" type="number" step="0.1" class="input" />
                  </div>
                </div>

                <!-- Additional Info -->
                <div class="pt-4 border-t border-gray-200 space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chipnummer</label>
                    <input v-model="form.chip_number" type="text" class="input" />
                  </div>

                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Farbe</label>
                      <input v-model="form.color" type="text" class="input" />
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Besondere Merkmale</label>
                      <input v-model="form.special_characteristics" type="text" class="input" />
                    </div>
                  </div>

                  <div>
                    <label class="flex items-center">
                      <input v-model="form.neutered" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                      <span class="ml-2 text-sm text-gray-700">Kastriert/Sterilisiert</span>
                    </label>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                    <textarea v-model="form.notes" rows="3" class="input"></textarea>
                  </div>
                </div>

                <!-- Error Message -->
                <div v-if="error" class="rounded-md bg-red-50 p-4">
                  <p class="text-sm text-red-800">{{ error }}</p>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-3 pt-4">
                  <button type="button" @click="closeModal" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Abbrechen
                  </button>
                  <button type="submit" :disabled="loading" class="btn btn-primary disabled:opacity-50">
                    <span v-if="loading">Speichert...</span>
                    <span v-else>{{ dog ? 'Aktualisieren' : 'Erstellen' }}</span>
                  </button>
                </div>
              </form>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
/**
 * DogFormModal Component
 * 
 * Modal dialog for creating and editing dog records.
 * 
 * Features:
 * - Dual-mode operation: create new dog or edit existing
 * - Inline error display with German translation of validation messages
 * - Toast notifications for successful operations
 * - Automatic customer dropdown population
 * - Smart form state management preserving errors during validation failures
 * - Profile image upload with preview
 * 
 * Error Handling:
 * - Displays inline errors that persist until next submission attempt
 * - Translates backend English validation messages to German
 * - Shows toast notifications for both success and errors
 * - Clears errors when modal reopens fresh
 * 
 * @emits close - Emitted when modal should close
 * @emits saved - Emitted after successful dog creation/update
 */
import { ref, watch, computed } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import apiClient from '@/api/client'
import { handleApiError, showSuccess } from '@/utils/errorHandler'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const isCustomer = computed(() => authStore.user?.role === 'customer')

const props = defineProps<{
  isOpen: boolean
  dog?: any
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const customers = ref<any[]>([])

const selectedImageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)

const form = ref({
  customer_id: '',
  name: '',
  breed: '',
  date_of_birth: '',
  gender: '',
  weight: null as number | null,
  chip_number: '',
  color: '',
  special_characteristics: '',
  neutered: false,
  notes: ''
})

/**
 * Watch for changes to the dog prop to populate the form.
 * Important: Only reset form when modal is closing (!props.isOpen),
 * not when dog is cleared while modal is still open (would lose validation state).
 */
watch(() => props.dog, (newDog) => {
  if (newDog) {
    form.value = {
      customer_id: newDog.customerId,
      name: newDog.name,
      breed: newDog.breed,
      date_of_birth: newDog.dateOfBirth || '',
      gender: newDog.gender || '',
      weight: newDog.weight,
      chip_number: newDog.chipNumber || '',
      color: newDog.color || '',
      special_characteristics: newDog.specialCharacteristics || '',
      neutered: newDog.neutered ?? false, // Use nullish coalescing to preserve false value
      notes: newDog.notes || ''
    }
  } else if (!props.isOpen) {
    resetForm()
  }
}, { immediate: true })

/**
 * Clear error message when modal reopens.
 * This ensures users don't see stale errors from previous submissions.
 */
watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    error.value = null
    selectedImageFile.value = null
    imagePreview.value = null
    if (!isCustomer.value) {
      loadCustomers()
    }
  }
})

async function loadCustomers() {
  try {
    const response = await apiClient.get('/api/v1/customers')
    customers.value = response.data.data
  } catch (err: any) {
    handleApiError(err, 'Fehler beim Laden der Besitzer')
  }
}

function handleImageChange(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  selectedImageFile.value = file

  const reader = new FileReader()
  reader.onload = (e) => {
    imagePreview.value = e.target?.result as string
  }
  reader.readAsDataURL(file)
}

function clearImageSelection() {
  selectedImageFile.value = null
  imagePreview.value = null
}

function resetForm() {
  form.value = {
    customer_id: '',
    name: '',
    breed: '',
    date_of_birth: '',
    gender: '',
    weight: null,
    chip_number: '',
    color: '',
    special_characteristics: '',
    neutered: false,
    notes: ''
  }
  error.value = null
  selectedImageFile.value = null
  imagePreview.value = null
}

/**
 * Translate common English validation errors from backend to German.
 * Backend validation messages are in English, but users expect German.
 * Falls back to original message if no translation is found.
 */
function translateError(errorMessage: string): string {
  const translations: Record<string, string> = {
    'The gender field is required': 'Das Geschlecht ist erforderlich',
    'The name field is required': 'Der Name ist erforderlich',
    'The breed field is required': 'Die Rasse ist erforderlich',
    'The customer id field is required': 'Der Besitzer ist erforderlich',
    'The date of birth field is required': 'Das Geburtsdatum ist erforderlich',
    'The date of birth must be a date before today': 'Das Geburtsdatum muss in der Vergangenheit liegen',
    'The gender field must be male or female': 'Das Geschlecht muss Rüde oder Hündin sein',
    'The chip number has already been taken': 'Diese Chipnummer wird bereits verwendet'
  }
  
  // Check for exact match
  if (translations[errorMessage]) {
    return translations[errorMessage]
  }
  
  // Check for partial matches
  for (const [english, german] of Object.entries(translations)) {
    if (errorMessage.includes(english)) {
      return german
    }
  }
  
  return errorMessage
}

async function handleSubmit() {
  loading.value = true
  error.value = null

  try {
    const payload: any = {
      name: form.value.name,
      breed: form.value.breed,
      dateOfBirth: form.value.date_of_birth || null,
      gender: form.value.gender || null,
      weight: form.value.weight,
      chipNumber: form.value.chip_number || null,
      color: form.value.color || null,
      specialCharacteristics: form.value.special_characteristics || null,
      neutered: form.value.neutered,
      notes: form.value.notes || null
    }

    // Only admins/trainers can change the owner
    if (!isCustomer.value) {
      payload.customerId = form.value.customer_id
    }

    let savedDog: any
    if (props.dog) {
      const response = await apiClient.put(`/api/v1/dogs/${props.dog.id}`, payload)
      savedDog = response.data.data
      showSuccess('Hund aktualisiert', `${form.value.name} wurde erfolgreich aktualisiert`)
    } else {
      const response = await apiClient.post('/api/v1/dogs', payload)
      savedDog = response.data.data
      showSuccess('Hund erstellt', `${form.value.name} wurde erfolgreich erstellt`)
    }

    // Upload image if one was selected
    if (selectedImageFile.value && savedDog?.id) {
      try {
        const formData = new FormData()
        formData.append('image', selectedImageFile.value)
        await apiClient.post(`/api/v1/dogs/${savedDog.id}/upload-image`, formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
      } catch (imgErr: any) {
        const imgError = imgErr.response?.data?.message || 'Fehler beim Hochladen des Bildes'
        handleApiError(imgErr, imgError)
      }
    }

    emit('saved')
    closeModal()
  } catch (err: any) {
    let errorMessage = err.response?.data?.message || 'Fehler beim Speichern des Hundes'
    
    // Extract first validation error if available
    if (err.response?.data?.errors) {
      const firstError = (Object.values(err.response.data.errors)[0] as string[])?.[0]
      if (firstError) {
        errorMessage = firstError as string
      }
    }
    
    // Translate to German
    errorMessage = translateError(errorMessage)
    
    error.value = errorMessage
    handleApiError(err, errorMessage)
  } finally {
    loading.value = false
  }
}

/**
 * Close modal and optionally reset form.
 * Preserves error state if validation failed - allows user to see/fix errors.
 * Only resets form if there's no error (successful save or cancel).
 */
function closeModal() {
  if (!error.value) {
    resetForm()
  }
  emit('close')
}
</script>
