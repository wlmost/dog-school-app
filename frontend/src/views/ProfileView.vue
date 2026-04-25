<template>
  <div class="space-y-6 max-w-3xl">
    <!-- Header -->
    <div class="card">
      <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Meine Daten</h3>
      <p class="text-gray-600 dark:text-gray-400 mt-1">
        Hier können Sie Ihre persönlichen Daten und Einstellungen verwalten.
      </p>
    </div>

    <div v-if="loading" class="card flex justify-center items-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <p class="ml-3 text-gray-500">Lade Profildaten...</p>
    </div>

    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Personal Data -->
      <div class="card space-y-4">
        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
          Persönliche Daten
        </h4>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vorname *</label>
            <input v-model="form.firstName" type="text" required class="input" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nachname *</label>
            <input v-model="form.lastName" type="text" required class="input" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">E-Mail *</label>
          <input v-model="form.email" type="email" required class="input" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telefon</label>
            <input v-model="form.phone" type="tel" class="input" placeholder="+49 30 12345678" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mobil-Telefon</label>
            <input v-model="form.mobilePhone" type="tel" class="input" placeholder="+49 170 12345678" />
          </div>
        </div>
      </div>

      <!-- Address -->
      <div class="card space-y-4">
        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
          Anschrift
        </h4>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Straße und Hausnummer</label>
          <input v-model="form.addressLine1" type="text" class="input" placeholder="Musterstraße 1" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Adresszusatz</label>
          <input v-model="form.addressLine2" type="text" class="input" placeholder="Apartment, Etage, etc." />
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">PLZ</label>
            <input v-model="form.postalCode" type="text" class="input" />
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stadt</label>
            <input v-model="form.city" type="text" class="input" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Land</label>
          <input v-model="form.country" type="text" class="input" placeholder="Deutschland" />
        </div>
      </div>

      <!-- Payment / Bank Account -->
      <div class="card space-y-4">
        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
          Zahlungsdaten
        </h4>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bevorzugte Zahlungsmethode</label>
          <select v-model="form.paymentMethod" class="input">
            <option value="">Keine Angabe</option>
            <option value="cash">Barzahlung</option>
            <option value="invoice">Rechnung</option>
            <option value="direct_debit">Lastschrift</option>
          </select>
        </div>

        <div v-if="form.paymentMethod === 'direct_debit'" class="space-y-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Bitte geben Sie Ihre Bankdaten für das SEPA-Lastschriftverfahren an.
          </p>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kontoinhaber *</label>
            <input v-model="form.bankAccountHolder" type="text" class="input" placeholder="Max Mustermann" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IBAN *</label>
            <input
              v-model="form.bankIban"
              type="text"
              class="input uppercase"
              placeholder="DE89 3704 0044 0532 0130 00"
              maxlength="34"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">BIC</label>
            <input
              v-model="form.bankBic"
              type="text"
              class="input uppercase"
              placeholder="COBADEFFXXX"
              maxlength="11"
            />
          </div>
        </div>
      </div>

      <!-- Password Change -->
      <div class="card space-y-4">
        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 border-b border-gray-200 dark:border-gray-700 pb-2">
          Passwort ändern
        </h4>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Lassen Sie die Felder leer, wenn Sie Ihr Passwort nicht ändern möchten.
        </p>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Neues Passwort</label>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="input pr-10"
              :class="{ 'border-red-500': passwordError }"
              autocomplete="new-password"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
            >
              <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
            </button>
          </div>
          <p class="mt-1 text-xs text-gray-500">
            Mind. 8 Zeichen, 1 Groß-, 1 Kleinbuchstabe, 1 Ziffer, 1 Sonderzeichen
          </p>
          <p v-if="passwordError" class="mt-1 text-xs text-red-600">{{ passwordError }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Neues Passwort bestätigen</label>
          <div class="relative">
            <input
              v-model="form.passwordConfirmation"
              :type="showPasswordConfirmation ? 'text' : 'password'"
              class="input pr-10"
              :class="{ 'border-red-500': passwordConfirmError }"
              autocomplete="new-password"
            />
            <button
              type="button"
              @click="showPasswordConfirmation = !showPasswordConfirmation"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
            >
              <svg v-if="showPasswordConfirmation" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
            </button>
          </div>
          <p v-if="passwordConfirmError" class="mt-1 text-xs text-red-600">{{ passwordConfirmError }}</p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-3">
        <button type="button" @click="resetForm" class="btn bg-gray-100 hover:bg-gray-200 text-gray-700">
          Zurücksetzen
        </button>
        <button type="submit" :disabled="saving" class="btn btn-primary disabled:opacity-50">
          <span v-if="saving">Speichert...</span>
          <span v-else>Änderungen speichern</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/api/client'
import { handleApiError, showSuccess } from '@/utils/errorHandler'

const loading = ref(true)
const saving = ref(false)
const customerId = ref<number | null>(null)
const passwordError = ref<string | null>(null)
const passwordConfirmError = ref<string | null>(null)
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

interface ProfileForm {
  firstName: string
  lastName: string
  email: string
  phone: string
  mobilePhone: string
  addressLine1: string
  addressLine2: string
  postalCode: string
  city: string
  country: string
  paymentMethod: string
  bankAccountHolder: string
  bankIban: string
  bankBic: string
  password: string
  passwordConfirmation: string
}

const form = ref<ProfileForm>({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  mobilePhone: '',
  addressLine1: '',
  addressLine2: '',
  postalCode: '',
  city: '',
  country: 'Deutschland',
  paymentMethod: '',
  bankAccountHolder: '',
  bankIban: '',
  bankBic: '',
  password: '',
  passwordConfirmation: ''
})

const originalData = ref<ProfileForm | null>(null)

onMounted(async () => {
  await loadProfile()
})

async function loadProfile() {
  loading.value = true
  try {
    const response = await apiClient.get('/api/v1/customers/profile')
    const customer = response.data.data
    customerId.value = customer.id
    populateForm(customer)
  } catch (error) {
    handleApiError(error, 'Fehler beim Laden des Profils')
  } finally {
    loading.value = false
  }
}

function populateForm(customer: any) {
  const data: ProfileForm = {
    firstName: customer.user?.firstName || '',
    lastName: customer.user?.lastName || '',
    email: customer.user?.email || '',
    phone: customer.user?.phone || '',
    mobilePhone: customer.user?.mobilePhone || '',
    addressLine1: customer.addressLine1 || '',
    addressLine2: customer.addressLine2 || '',
    postalCode: customer.postalCode || '',
    city: customer.city || '',
    country: customer.country || 'Deutschland',
    paymentMethod: customer.paymentMethod || '',
    bankAccountHolder: customer.bankAccountHolder || '',
    bankIban: customer.bankIban || '',
    bankBic: customer.bankBic || '',
    password: '',
    passwordConfirmation: ''
  }
  form.value = data
  originalData.value = { ...data }
}

function resetForm() {
  if (originalData.value) {
    form.value = { ...originalData.value, password: '', passwordConfirmation: '' }
    passwordError.value = null
    passwordConfirmError.value = null
    showPassword.value = false
    showPasswordConfirmation.value = false
  }
}

function validatePassword(password: string): string | null {
  if (password.length < 8) return 'Passwort muss mindestens 8 Zeichen lang sein'
  if (!/[a-z]/.test(password)) return 'Passwort muss mindestens einen Kleinbuchstaben enthalten'
  if (!/[A-Z]/.test(password)) return 'Passwort muss mindestens einen Großbuchstaben enthalten'
  if (!/[0-9]/.test(password)) return 'Passwort muss mindestens eine Ziffer enthalten'
  if (!/[^a-zA-Z0-9]/.test(password)) return 'Passwort muss mindestens ein Sonderzeichen enthalten'
  return null
}

async function handleSubmit() {
  if (!customerId.value) return

  passwordError.value = null
  passwordConfirmError.value = null

  if (form.value.password) {
    const pwdValidation = validatePassword(form.value.password)
    if (pwdValidation) {
      passwordError.value = pwdValidation
      return
    }
    if (form.value.password !== form.value.passwordConfirmation) {
      passwordConfirmError.value = 'Passwörter stimmen nicht überein'
      return
    }
  }

  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      firstName: form.value.firstName,
      lastName: form.value.lastName,
      email: form.value.email,
      phone: form.value.phone,
      mobilePhone: form.value.mobilePhone,
      addressLine1: form.value.addressLine1,
      addressLine2: form.value.addressLine2,
      postalCode: form.value.postalCode,
      city: form.value.city,
      country: form.value.country,
      paymentMethod: form.value.paymentMethod || null,
      bankAccountHolder: form.value.paymentMethod === 'direct_debit' ? form.value.bankAccountHolder : null,
      bankIban: form.value.paymentMethod === 'direct_debit' ? form.value.bankIban : null,
      bankBic: form.value.paymentMethod === 'direct_debit' ? form.value.bankBic : null,
    }

    if (form.value.password) {
      payload.password = form.value.password
      payload.password_confirmation = form.value.passwordConfirmation
    }

    const response = await apiClient.put(`/api/v1/customers/${customerId.value}`, payload)
    const customer = response.data.data
    populateForm(customer)
    showSuccess('Profil aktualisiert', 'Ihre Daten wurden erfolgreich gespeichert')
  } catch (error) {
    handleApiError(error, 'Fehler beim Speichern der Daten')
  } finally {
    saving.value = false
  }
}
</script>
