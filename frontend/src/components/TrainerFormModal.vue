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
                    <input v-model="form.password" type="password" required class="input" />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Passwort wiederholen *</label>
                    <input v-model="form.password_confirmation" type="password" required class="input" />
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

const props = defineProps<{
  isOpen: boolean
  trainer?: any
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const loading = ref(false)
const error = ref<string | null>(null)

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
}

async function handleSubmit() {
  loading.value = true
  error.value = null

  // Validate passwords match for new trainers
  if (!props.trainer && form.value.password !== form.value.password_confirmation) {
    error.value = 'Passwörter stimmen nicht überein'
    loading.value = false
    return
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
      role: 'trainer'
    }

    // Only include password for new trainers
    if (!props.trainer) {
      payload.password = form.value.password
      payload.passwordConfirmation = form.value.password_confirmation
    }

    if (props.trainer) {
      await apiClient.put(`/api/v1/trainers/${props.trainer.id}`, payload)
    } else {
      await apiClient.post('/api/v1/trainers', payload)
    }

    emit('saved')
    closeModal()
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Ein Fehler ist aufgetreten'
  } finally {
    loading.value = false
  }
}

function closeModal() {
  resetForm()
  error.value = null
  emit('close')
}
</script>
