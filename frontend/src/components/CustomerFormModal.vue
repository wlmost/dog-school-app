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

                <div v-if="!customer" class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Initiales Passwort</label>
                    <div class="flex items-center gap-2">
                      <input
                        :value="generatedPassword"
                        type="text"
                        readonly
                        class="input flex-1 bg-gray-50 font-mono text-sm"
                      />
                      <button
                        type="button"
                        @click="copyPassword"
                        class="btn bg-white hover:bg-gray-50 border border-gray-300 flex items-center gap-1 whitespace-nowrap"
                        :class="passwordCopied ? 'text-green-600' : 'text-gray-700'"
                      >
                        <svg v-if="!passwordCopied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ passwordCopied ? 'Kopiert!' : 'Kopieren' }}
                      </button>
                      <button
                        type="button"
                        @click="refreshPassword"
                        class="btn bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 flex items-center gap-1 whitespace-nowrap"
                        title="Neues Passwort generieren"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Neu
                      </button>
                    </div>
                    <p class="mt-2 text-xs text-amber-600 flex items-start gap-1">
                      <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                      </svg>
                      Bitte notieren oder kopieren Sie das Passwort jetzt – es wird nach der Bestätigung nicht mehr angezeigt.
                    </p>
                  </div>
                </div>

                <!-- Trainer Assignment -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Zugewiesener Trainer</label>
                  <select v-model="form.trainer_id" class="input">
                    <option :value="null">Kein Trainer zugewiesen</option>
                    <option v-for="trainer in trainers" :key="trainer.id" :value="trainer.id">
                      {{ trainer.fullName || `${trainer.firstName} ${trainer.lastName}` }}
                    </option>
                  </select>
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

                <!-- Hunde -->
                <div v-if="customer" class="pt-4 border-t border-gray-200">
                  <div class="flex justify-between items-center mb-3">
                    <h4 class="text-sm font-medium text-gray-900">Hunde</h4>
                    <button type="button" @click="showDogForm = true" class="text-sm text-primary-600 hover:text-primary-700 font-medium">
                      + Hund hinzufügen
                    </button>
                  </div>
                  
                  <!-- Dog List -->
                  <div v-if="dogs.length > 0" class="space-y-2 mb-3">
                    <div v-for="dog in dogs" :key="dog.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                      <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">{{ dog.name }}</p>
                        <p class="text-xs text-gray-500">{{ dog.breed }}</p>
                      </div>
                      <button type="button" @click="removeDog(dog)" class="text-red-600 hover:text-red-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </div>
                  <p v-else class="text-sm text-gray-500 italic">Noch keine Hunde zugeordnet</p>

                  <!-- Dog Form -->
                  <div v-if="showDogForm" class="mt-4 p-4 bg-gray-50 rounded-lg space-y-3">
                    <div class="flex justify-between items-center mb-2">
                      <h5 class="text-sm font-medium text-gray-900">Neuer Hund</h5>
                      <button type="button" @click="cancelDogForm" class="text-gray-500 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Name *</label>
                        <input v-model="dogForm.name" type="text" required class="input text-sm" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Rasse *</label>
                        <input v-model="dogForm.breed" type="text" required class="input text-sm" />
                      </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Geburtsdatum</label>
                        <input v-model="dogForm.date_of_birth" type="date" class="input text-sm" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Geschlecht</label>
                        <select v-model="dogForm.gender" class="input text-sm">
                          <option value="">-</option>
                          <option value="male">Rüde</option>
                          <option value="female">Hündin</option>
                        </select>
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Gewicht (kg)</label>
                        <input v-model.number="dogForm.weight" type="number" step="0.1" class="input text-sm" />
                      </div>
                    </div>
                    <div class="flex justify-end space-x-2">
                      <button type="button" @click="cancelDogForm" class="btn bg-white hover:bg-gray-50 text-gray-700 text-sm px-3 py-1.5">
                        Abbrechen
                      </button>
                      <button type="button" @click="saveDog" :disabled="!dogForm.name || !dogForm.breed" class="btn btn-primary text-sm px-3 py-1.5 disabled:opacity-50">
                        Hund hinzufügen
                      </button>
                    </div>
                  </div>
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
import { ref, watch, computed } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/api/client'
import { handleApiError, showSuccess } from '@/utils/errorHandler'

const props = defineProps<{
  isOpen: boolean
  customer?: any
}>()

const emit = defineEmits<{
  close: []
  saved: []
}>()

const authStore = useAuthStore()
const currentUser = computed(() => authStore.user)

const loading = ref(false)
const generatedPassword = ref('')
const passwordCopied = ref(false)
const trainers = ref<any[]>([])
const dogs = ref<any[]>([])
const showDogForm = ref(false)

const dogForm = ref({
  name: '',
  breed: '',
  date_of_birth: '',
  gender: '',
  weight: null as number | null
})

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  trainer_id: null as number | null,
  street: '',
  postal_code: '',
  city: '',
  country: 'Deutschland',
  notes: ''
})

watch(() => props.isOpen, (isOpen) => {
  if (isOpen) {
    loadTrainers()
    if (props.customer) {
      loadDogs()
    } else {
      generatedPassword.value = generatePassword()
      passwordCopied.value = false
      // Set trainer_id to current user if they are a trainer
      if (currentUser.value?.role === 'trainer') {
        form.value.trainer_id = currentUser.value.id
      }
    }
  } else {
    showDogForm.value = false
    dogs.value = []
  }
})

watch(() => props.customer, (newCustomer) => {
  if (newCustomer) {
    form.value = {
      first_name: newCustomer.user?.firstName || '',
      last_name: newCustomer.user?.lastName || '',
      email: newCustomer.user?.email || '',
      phone: newCustomer.user?.phone || '',
      trainer_id: newCustomer.trainerId || null,
      street: newCustomer.street || '',
      postal_code: newCustomer.postalCode || '',
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
    trainer_id: null,
    street: '',
    postal_code: '',
    city: '',
    country: 'Deutschland',
    notes: ''
  }
  generatedPassword.value = ''
  passwordCopied.value = false
}

async function loadTrainers() {
  try {
    const response = await apiClient.get('/api/v1/trainers')
    trainers.value = response.data.data || response.data
  } catch (err) {
    console.error('Error loading trainers:', err)
  }
}

async function loadDogs() {
  if (!props.customer) return
  
  try {
    const response = await apiClient.get(`/api/v1/customers/${props.customer.id}`)
    dogs.value = response.data.data?.dogs || []
  } catch (err) {
    console.error('Error loading dogs:', err)
  }
}

function resetDogForm() {
  dogForm.value = {
    name: '',
    breed: '',
    date_of_birth: '',
    gender: '',
    weight: null
  }
}

function cancelDogForm() {
  showDogForm.value = false
  resetDogForm()
}

async function saveDog() {
  if (!dogForm.value.name || !dogForm.value.breed || !props.customer) return
  
  try {
    const payload = {
      customerId: props.customer.id,
      name: dogForm.value.name,
      breed: dogForm.value.breed,
      dateOfBirth: dogForm.value.date_of_birth || null,
      gender: dogForm.value.gender || null,
      weight: dogForm.value.weight
    }
    
    await apiClient.post('/api/v1/dogs', payload)
    showSuccess('Hund hinzugefügt', 'Der Hund wurde erfolgreich hinzugefügt')
    await loadDogs()
    cancelDogForm()
  } catch (err) {
    handleApiError(err, 'Fehler beim Hinzufügen des Hundes')
  }
}

async function removeDog(dog: any) {
  if (!confirm(`Möchten Sie ${dog.name} wirklich löschen?`)) return
  
  try {
    await apiClient.delete(`/api/v1/dogs/${dog.id}`)
    showSuccess('Hund gelöscht', 'Der Hund wurde erfolgreich gelöscht')
    await loadDogs()
  } catch (err) {
    handleApiError(err, 'Fehler beim Löschen des Hundes')
  }
}

function generatePassword(): string {
  const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
  const lowercase = 'abcdefghijklmnopqrstuvwxyz'
  const digits = '0123456789'
  const special = '!@#$%^&*()_+-='
  const all = uppercase + lowercase + digits + special

  // Guarantee at least one character from each required group using secure random
  const secureRandom = (max: number) => {
    const arr = new Uint32Array(1)
    crypto.getRandomValues(arr)
    return (arr[0] as number) % max
  }

  const chars = [
    uppercase[secureRandom(uppercase.length)],
    lowercase[secureRandom(lowercase.length)],
    digits[secureRandom(digits.length)],
    special[secureRandom(special.length)],
  ]

  // Fill up to 12 characters total
  for (let i = chars.length; i < 12; i++) {
    chars.push(all[secureRandom(all.length)])
  }

  // Fisher-Yates shuffle with secure random values
  for (let i = chars.length - 1; i > 0; i--) {
    const j = secureRandom(i + 1)
    const temp = chars[i] as string
    chars[i] = chars[j] as string
    chars[j] = temp
  }

  return chars.join('')
}

function refreshPassword() {
  generatedPassword.value = generatePassword()
  passwordCopied.value = false
}

async function copyPassword() {
  try {
    await navigator.clipboard.writeText(generatedPassword.value)
    passwordCopied.value = true
    setTimeout(() => { passwordCopied.value = false }, 2000)
  } catch {
    // Fallback for environments without Clipboard API
    try {
      const input = document.createElement('input')
      input.value = generatedPassword.value
      document.body.appendChild(input)
      input.select()
      document.execCommand('copy')
      document.body.removeChild(input)
      passwordCopied.value = true
      setTimeout(() => { passwordCopied.value = false }, 2000)
    } catch {
      handleApiError(new Error('Kopieren fehlgeschlagen'), 'Das Passwort konnte nicht in die Zwischenablage kopiert werden. Bitte manuell kopieren.')
    }
  }
}

async function handleSubmit() {
  loading.value = true

  try {
    if (props.customer) {
      // Update existing customer
      await apiClient.put(`/api/v1/customers/${props.customer.id}`, {
        firstName: form.value.first_name,
        lastName: form.value.last_name,
        email: form.value.email,
        phone: form.value.phone,
        trainerId: form.value.trainer_id,
        street: form.value.street,
        postalCode: form.value.postal_code,
        city: form.value.city,
        country: form.value.country,
        notes: form.value.notes
      })
      showSuccess('Kunde aktualisiert', 'Der Kunde wurde erfolgreich aktualisiert')
    } else {
      // Create new customer with auto-generated password
      const userResponse = await apiClient.post('/api/v1/auth/register', {
        email: form.value.email,
        password: generatedPassword.value,
        password_confirmation: generatedPassword.value,
        role: 'customer',
        first_name: form.value.first_name,
        last_name: form.value.last_name,
        phone: form.value.phone
      })

      await apiClient.post('/api/v1/customers', {
        userId: userResponse.data.user.id,
        trainerId: form.value.trainer_id,
        street: form.value.street,
        postalCode: form.value.postal_code,
        city: form.value.city,
        country: form.value.country,
        notes: form.value.notes
      })
      showSuccess('Kunde erstellt', 'Der Kunde wurde erfolgreich erstellt')
    }

    emit('saved')
    closeModal()
  } catch (err) {
    handleApiError(err, 'Fehler beim Speichern des Kunden')
  } finally {
    loading.value = false
  }
}

function closeModal() {
  resetForm()
  emit('close')
}
</script>
