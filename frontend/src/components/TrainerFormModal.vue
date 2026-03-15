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
                {{ trainer ? 'Trainer bearbeiten' : 'Neuer Trainer' }}
              </DialogTitle>

              <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Personal Info -->
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vorname *</label>
                    <input v-model="form.first_name" type="text" required class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nachname *</label>
                    <input v-model="form.last_name" type="text" required class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail *</label>
                    <input v-model="form.email" type="email" required class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                    <input v-model="form.phone" type="tel" class="input" />
                  </div>
                </div>

                <!-- Password (only for new trainers) -->
                <div v-if="!trainer" class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Passwort *</label>
                    <input v-model="form.password" type="password" required class="input" :class="{'border-red-500': passwordError}" />
                    <p class="mt-1 text-xs text-gray-500">
                      Mind. 8 Zeichen, 1 Groß-, 1 Kleinbuchstabe, 1 Ziffer, 1 Sonderzeichen
                    </p>
                    <p v-if="passwordError" class="mt-1 text-xs text-red-600">{{ passwordError }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Passwort wiederholen *</label>
                    <input v-model="form.password_confirmation" type="password" required class="input" :class="{'border-red-500': passwordError}" />
                  </div>
                </div>

                <!-- Password change for existing trainers -->
                <div v-else class="pt-4 border-t border-gray-200">
                  <h4 class="text-sm font-medium text-gray-900 mb-3">Passwort ändern (optional)</h4>
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Neues Passwort</label>
                      <input v-model="form.password" type="password" class="input" :class="{'border-red-500': passwordError}" />
                      <p class="mt-1 text-xs text-gray-500">
                        Mind. 8 Zeichen, 1 Groß-, 1 Kleinbuchstabe, 1 Ziffer, 1 Sonderzeichen
                      </p>
                      <p v-if="passwordError" class="mt-1 text-xs text-red-600">{{ passwordError }}</p>
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Passwort wiederholen</label>
                      <input v-model="form.password_confirmation" type="password" class="input" :class="{'border-red-500': passwordError}" />
                    </div>
                  </div>
                </div>

                <!-- Address -->
                <div class="pt-4 border-t border-gray-200">
                  <h4 class="text-sm font-medium text-gray-900 mb-3">Adresse</h4>
                  <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                      <label class="block text-sm font-medium text-gray-700 mb-1">Straße</label>
                      <input v-model="form.street" type="text" class="input" />
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">PLZ</label>
                      <input v-model="form.postal_code" type="text" class="input" />
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Stadt</label>
                      <input v-model="form.city" type="text" class="input" />
                    </div>

                    <div class="col-span-2">
                      <label class="block text-sm font-medium text-gray-700 mb-1">Land</label>
                      <input v-model="form.country" type="text" class="input" />
                    </div>
                  </div>
                </div>

                <!-- Qualifications -->
                <div class="pt-4 border-t border-gray-200">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Qualifikationen</label>
                  <textarea v-model="form.qualifications" rows="3" class="input" placeholder="Z.B. Hundetrainer IHK, Verhaltensberater, etc."></textarea>
                </div>

                <!-- Specializations -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Spezialisierungen</label>
                  <textarea v-model="form.specializations" rows="2" class="input" placeholder="Z.B. Welpentraining, Verhaltenstherapie, Agility"></textarea>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-3 pt-4">
                  <button type="button" @click="closeModal" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
                    Abbrechen
                  </button>
                  <button type="submit" :disabled="loading" class="btn btn-primary disabled:opacity-50">
                    <span v-if="loading">Speichert...</span>
                    <span v-else>{{ trainer ? 'Aktualisieren' : 'Erstellen' }}</span>
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
import { ref, watch } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import apiClient from '@/api/client'
import { handleApiError, showSuccess } from '@/utils/errorHandler'

const props = defineProps<{
  isOpen: boolean
  trainer?: any
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const loading = ref(false)
const passwordError = ref<string | null>(null)

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  street: '',
  postal_code: '',
  city: '',
  country: 'Deutschland',
  qualifications: '',
  specializations: ''
})

watch(() => props.trainer, (newTrainer) => {
  if (newTrainer) {
    form.value = {
      first_name: newTrainer.firstName || '',
      last_name: newTrainer.lastName || '',
      email: newTrainer.email,
      phone: newTrainer.phone || '',
      password: '',
      password_confirmation: '',
      street: newTrainer.street || '',
      postal_code: newTrainer.postalCode || '',
      city: newTrainer.city || '',
      country: newTrainer.country || 'Deutschland',
      qualifications: newTrainer.qualifications || '',
      specializations: newTrainer.specializations || ''
    }
  } else {
    resetForm()
  }
}, { immediate: true })

function resetForm() {
  form.value = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    street: '',
    postal_code: '',
    city: '',
    country: 'Deutschland',
    qualifications: '',
    specializations: ''
  }
  passwordError.value = null
}

function validatePassword(password: string): string | null {
  if (password.length < 8) {
    return 'Passwort muss mindestens 8 Zeichen lang sein'
  }
  if (!/[a-z]/.test(password)) {
    return 'Passwort muss mindestens einen Kleinbuchstaben enthalten'
  }
  if (!/[A-Z]/.test(password)) {
    return 'Passwort muss mindestens einen Großbuchstaben enthalten'
  }
  if (!/[0-9]/.test(password)) {
    return 'Passwort muss mindestens eine Ziffer enthalten'
  }
  if (!/[^a-zA-Z0-9]/.test(password)) {
    return 'Passwort muss mindestens ein Sonderzeichen enthalten'
  }
  return null
}

async function handleSubmit() {
  loading.value = true
  passwordError.value = null

  // Validate password if provided (required for new, optional for edit)
  if (form.value.password || !props.trainer) {
    const pwdValidation = validatePassword(form.value.password)
    if (pwdValidation) {
      passwordError.value = pwdValidation
      loading.value = false
      return
    }
    
    if (form.value.password !== form.value.password_confirmation) {
      handleApiError(new Error('Passwörter stimmen nicht überein'), 'Validierungsfehler')
      loading.value = false
      return
    }
  }

  try {
    const payload: any = {
      firstName: form.value.first_name,
      lastName: form.value.last_name,
      email: form.value.email,
      phone: form.value.phone || null,
      street: form.value.street || null,
      postalCode: form.value.postal_code || null,
      city: form.value.city || null,
      country: form.value.country || null,
      qualifications: form.value.qualifications || null,
      specializations: form.value.specializations || null,
      role: 'trainer'
    }

    // Include password if provided (required for new, optional for edit)
    if (form.value.password) {
      payload.password = form.value.password
    }

    if (props.trainer) {
      await apiClient.put(`/api/v1/trainers/${props.trainer.id}`, payload)
      showSuccess('Trainer aktualisiert', 'Der Trainer wurde erfolgreich aktualisiert')
    } else {
      await apiClient.post('/api/v1/trainers', payload)
      showSuccess('Trainer erstellt', 'Der Trainer wurde erfolgreich erstellt')
    }

    emit('saved')
    closeModal()
  } catch (err) {
    handleApiError(err, 'Fehler beim Speichern des Trainers')
  } finally {
    loading.value = false
  }
}

function closeModal() {
  resetForm()
  emit('close')
}
</script>
