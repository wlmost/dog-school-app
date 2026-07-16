<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" @close="handleClose" class="relative z-50">
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

              <!-- Success State -->
              <template v-if="submitted">
                <DialogTitle as="h3" class="text-lg font-medium leading-6 text-green-700 mb-4">
                  Anfrage gesendet! ✓
                </DialogTitle>
                <p class="text-sm text-gray-600 mb-6">
                  Ihre Anfrage zur Registrierung von <strong>{{ submittedDogName }}</strong> wurde erfolgreich
                  an den Administrator gesendet und wird zeitnah bearbeitet. Sie erhalten eine
                  Benachrichtigung, sobald Ihr Hund angelegt wurde.
                </p>
                <div class="flex justify-end">
                  <button
                    type="button"
                    @click="handleSuccessClose"
                    class="btn btn-primary"
                  >
                    Schließen
                  </button>
                </div>
              </template>

              <!-- Form State -->
              <template v-else>
                <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 mb-4">
                  Hund anmelden
                </DialogTitle>

                <form @submit.prevent="handleSubmit" class="space-y-4">
                  <!-- Name -->
                  <div>
                    <label for="dog-name" class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input
                      id="dog-name"
                      v-model="form.name"
                      type="text"
                      required
                      class="input"
                      placeholder="Name des Hundes"
                    />
                  </div>

                  <!-- Breed & Gender -->
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label for="dog-breed" class="block text-sm font-medium text-gray-700 mb-1">Rasse</label>
                      <input
                        id="dog-breed"
                        v-model="form.breed"
                        type="text"
                        class="input"
                        placeholder="z.B. Labrador"
                      />
                    </div>

                    <div>
                      <label for="dog-gender" class="block text-sm font-medium text-gray-700 mb-1">Geschlecht</label>
                      <select id="dog-gender" v-model="form.gender" class="input">
                        <option value="">Nicht angegeben</option>
                        <option value="male">Rüde</option>
                        <option value="female">Hündin</option>
                      </select>
                    </div>
                  </div>

                  <!-- Date of Birth & Neutered -->
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label for="dog-dob" class="block text-sm font-medium text-gray-700 mb-1">
                        Geburtsdatum (oder ungefähres Alter)
                      </label>
                      <input
                        id="dog-dob"
                        v-model="form.dateOfBirth"
                        type="date"
                        class="input"
                        @click="($event.target as HTMLInputElement).showPicker?.()"
                      />
                    </div>

                    <div class="flex items-end pb-2">
                      <label class="flex items-center cursor-pointer">
                        <input
                          v-model="form.neutered"
                          type="checkbox"
                          class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        <span class="ml-2 text-sm text-gray-700">Kastriert/Sterilisiert</span>
                      </label>
                    </div>
                  </div>

                  <!-- Chip Number -->
                  <div>
                    <label for="dog-chip" class="block text-sm font-medium text-gray-700 mb-1">Chipnummer</label>
                    <input
                      id="dog-chip"
                      v-model="form.chipNumber"
                      type="text"
                      class="input"
                      placeholder="15-stellige Chipnummer"
                    />
                  </div>

                  <!-- Owner Since & Origin -->
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label for="dog-owner-since" class="block text-sm font-medium text-gray-700 mb-1">
                        Beim Halter seit
                      </label>
                      <input
                        id="dog-owner-since"
                        v-model="form.ownerSince"
                        type="date"
                        class="input"
                        @click="($event.target as HTMLInputElement).showPicker?.()"
                      />
                    </div>

                    <div>
                      <label for="dog-origin" class="block text-sm font-medium text-gray-700 mb-1">Herkunft</label>
                      <select id="dog-origin" v-model="form.origin" class="input">
                        <option value="">Nicht angegeben</option>
                        <option value="breeder">Züchter</option>
                        <option value="shelter">Tierschutz</option>
                        <option value="private">Privat</option>
                        <option value="unknown">unbekannt</option>
                      </select>
                    </div>
                  </div>

                  <!-- Age at Acquisition -->
                  <div>
                    <label for="dog-age-at-acquisition" class="block text-sm font-medium text-gray-700 mb-1">
                      Alter bei Einzug
                    </label>
                    <input
                      id="dog-age-at-acquisition"
                      v-model="form.ageAtAcquisition"
                      type="text"
                      class="input"
                      placeholder="z.B. ca. 2 Jahre"
                    />
                  </div>

                  <!-- Notes -->
                  <div>
                    <label for="dog-notes" class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                    <textarea
                      id="dog-notes"
                      v-model="form.notes"
                      rows="3"
                      class="input"
                      placeholder="Besonderheiten, Informationen für den Trainer..."
                    ></textarea>
                  </div>

                  <!-- Error Message -->
                  <div v-if="error" class="rounded-md bg-red-50 p-4">
                    <p class="text-sm text-red-800">{{ error }}</p>
                  </div>

                  <!-- Buttons -->
                  <div class="flex justify-end space-x-3 pt-4">
                    <button
                      type="button"
                      @click="handleClose"
                      class="btn bg-gray-100 hover:bg-gray-200 text-gray-700"
                    >
                      Abbrechen
                    </button>
                    <button
                      type="submit"
                      :disabled="loading"
                      class="btn btn-primary disabled:opacity-50"
                    >
                      <span v-if="loading">Sende Anfrage...</span>
                      <span v-else>Anfrage senden</span>
                    </button>
                  </div>
                </form>
              </template>

            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import apiClient from '@/api/client'
import { handleApiError, showSuccess } from '@/utils/errorHandler'

const props = defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const submitted = ref(false)
const submittedDogName = ref('')

const form = ref({
  name: '',
  breed: '',
  gender: '',
  dateOfBirth: '',
  neutered: false,
  chipNumber: '',
  ownerSince: '',
  ageAtAcquisition: '',
  origin: '',
  notes: ''
})

/** Reset form and state when modal opens fresh */
watch(
  () => props.isOpen,
  (isOpen) => {
    if (isOpen) {
      resetForm()
    }
  }
)

function resetForm() {
  form.value = {
    name: '',
    breed: '',
    gender: '',
    dateOfBirth: '',
    neutered: false,
    chipNumber: '',
    ownerSince: '',
    ageAtAcquisition: '',
    origin: '',
    notes: ''
  }
  error.value = null
  submitted.value = false
  submittedDogName.value = ''
}

async function handleSubmit() {
  loading.value = true
  error.value = null

  try {
    const payload = {
      name: form.value.name,
      breed: form.value.breed || null,
      gender: form.value.gender || null,
      dateOfBirth: form.value.dateOfBirth || null,
      neutered: form.value.neutered,
      chipNumber: form.value.chipNumber || null,
      ownerSince: form.value.ownerSince || null,
      ageAtAcquisition: form.value.ageAtAcquisition || null,
      origin: form.value.origin || null,
      notes: form.value.notes || null
    }

    await apiClient.post('/api/v1/dog-registration-requests', payload)

    submittedDogName.value = form.value.name
    submitted.value = true
    showSuccess('Anfrage gesendet', `Die Anfrage für ${form.value.name} wurde erfolgreich gesendet`)
  } catch (err: any) {
    let errorMessage = err.response?.data?.message || 'Fehler beim Senden der Anfrage'

    if (err.response?.data?.errors) {
      const firstError = (Object.values(err.response.data.errors)[0] as string[])?.[0]
      if (firstError) {
        errorMessage = firstError as string
      }
    }

    error.value = errorMessage
    handleApiError(err, errorMessage)
  } finally {
    loading.value = false
  }
}

function handleSuccessClose() {
  emit('saved')
  emit('close')
}

function handleClose() {
  if (!submitted.value) {
    resetForm()
  }
  emit('close')
}
</script>
