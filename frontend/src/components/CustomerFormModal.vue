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
                {{ customer ? 'Kunde bearbeiten' : 'Neuer Kunde' }}
              </DialogTitle>

              <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Benutzer-Informationen -->
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Vorname *</label>
                    <input v-model="form.first_name" type="text" required class="input" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nachname *</label>
                    <input v-model="form.last_name" type="text" required class="input" />
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail *</label>
                  <input v-model="form.email" type="email" required class="input" />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Telefon</label>
                  <input v-model="form.phone" type="tel" class="input" />
                </div>

                <div v-if="!customer">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Passwort *</label>
                  <input v-model="form.password" type="password" required class="input" :class="{'border-red-500': passwordError}" />
                  <p class="mt-1 text-xs text-gray-500">
                    Mind. 8 Zeichen, 1 Groß-, 1 Kleinbuchstabe, 1 Ziffer, 1 Sonderzeichen
                  </p>
                  <p v-if="passwordError" class="mt-1 text-xs text-red-600">{{ passwordError }}</p>
                </div>

                <!-- Adresse -->
                <div class="pt-4 border-t border-gray-200">
                  <h4 class="text-sm font-medium text-gray-900 mb-3">Adresse</h4>
                  <div class="space-y-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Straße</label>
                      <input v-model="form.street" type="text" class="input" />
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PLZ</label>
                        <input v-model="form.postal_code" type="text" class="input" />
                      </div>
                      <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stadt</label>
                        <input v-model="form.city" type="text" class="input" />
                      </div>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Land</label>
                      <input v-model="form.country" type="text" class="input" placeholder="Deutschland" />
                    </div>
                  </div>
                </div>

                <!-- Notizen -->
                <div class="pt-4 border-t border-gray-200">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                  <textarea v-model="form.notes" rows="3" class="input"></textarea>
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
                    <span v-else>{{ customer ? 'Aktualisieren' : 'Erstellen' }}</span>
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
  customer?: any
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const passwordError = ref<string | null>(null)

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  password: '',
  street: '',
  postal_code: '',
  city: '',
  country: 'Deutschland',
  notes: ''
})

watch(() => props.customer, (newCustomer) => {
  if (newCustomer) {
    form.value = {
      first_name: newCustomer.user?.first_name || '',
      last_name: newCustomer.user?.last_name || '',
      email: newCustomer.user?.email || '',
      phone: newCustomer.user?.phone || '',
      password: '',
      street: newCustomer.street || '',
      postal_code: newCustomer.postal_code || '',
      city: newCustomer.city || '',
      country: newCustomer.country || 'Deutschland',
      notes: newCustomer.notes || ''
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
    street: '',
    postal_code: '',
    city: '',
    country: 'Deutschland',
    notes: ''
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
  error.value = null
  passwordError.value = null

  // Validate password for new customers
  if (!props.customer) {
    const pwdValidation = validatePassword(form.value.password)
    if (pwdValidation) {
      passwordError.value = pwdValidation
      loading.value = false
      return
    }
  }

  try {
    if (props.customer) {
      // Update existing customer
      await apiClient.put(`/api/v1/customers/${props.customer.id}`, {
        street: form.value.street,
        postalCode: form.value.postal_code,
        city: form.value.city,
        country: form.value.country,
        notes: form.value.notes
      })
      
      // Update user info separately if needed
      await apiClient.put(`/api/v1/users/${props.customer.user_id}`, {
        firstName: form.value.first_name,
        lastName: form.value.last_name,
        phone: form.value.phone
      })
    } else {
      // Create new customer with user
      const userResponse = await apiClient.post('/api/v1/auth/register', {
        email: form.value.email,
        password: form.value.password,
        role: 'customer',
        firstName: form.value.first_name,
        lastName: form.value.last_name,
        phone: form.value.phone
      })

      await apiClient.post('/api/v1/customers', {
        userId: userResponse.data.user.id,
        street: form.value.street,
        postalCode: form.value.postal_code,
        city: form.value.city,
        country: form.value.country,
        notes: form.value.notes
      })
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
