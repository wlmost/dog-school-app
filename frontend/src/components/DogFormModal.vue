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
                <!-- Basic Info -->
                <div class="grid grid-cols-2 gap-4">
                  <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Besitzer *</label>
                    <select v-model="form.customer_id" required class="input">
                      <option value="">Besitzer auswählen...</option>
                      <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                        {{ customer.user?.full_name }}
                      </option>
                    </select>
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
                    <input v-model="form.date_of_birth" type="date" class="input" />
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
                      <input v-model="form.is_neutered" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
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
import { ref, watch, onMounted } from 'vue'
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import apiClient from '@/api/client'

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
  is_neutered: false,
  notes: ''
})

onMounted(() => {
  loadCustomers()
})

watch(() => props.dog, (newDog) => {
  if (newDog) {
    form.value = {
      customer_id: newDog.customer_id,
      name: newDog.name,
      breed: newDog.breed,
      date_of_birth: newDog.date_of_birth || '',
      gender: newDog.gender || '',
      weight: newDog.weight,
      chip_number: newDog.chip_number || '',
      color: newDog.color || '',
      special_characteristics: newDog.special_characteristics || '',
      is_neutered: newDog.is_neutered || false,
      notes: newDog.notes || ''
    }
  } else {
    resetForm()
  }
}, { immediate: true })

async function loadCustomers() {
  try {
    const response = await apiClient.get('/api/v1/customers')
    customers.value = response.data.data
  } catch (err) {
    console.error('Error loading customers:', err)
  }
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
    is_neutered: false,
    notes: ''
  }
}

async function handleSubmit() {
  loading.value = true
  error.value = null

  try {
    const payload = {
      customerId: form.value.customer_id,
      name: form.value.name,
      breed: form.value.breed,
      dateOfBirth: form.value.date_of_birth || null,
      gender: form.value.gender || null,
      weight: form.value.weight,
      chipNumber: form.value.chip_number || null,
      color: form.value.color || null,
      specialCharacteristics: form.value.special_characteristics || null,
      isNeutered: form.value.is_neutered,
      notes: form.value.notes || null
    }

    if (props.dog) {
      await apiClient.put(`/api/v1/dogs/${props.dog.id}`, payload)
    } else {
      await apiClient.post('/api/v1/dogs', payload)
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
