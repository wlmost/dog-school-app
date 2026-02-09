<template>
  <div class="settings-view">
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Systemeinstellungen</h1>
      <p class="mt-2 text-gray-600 dark:text-gray-400">
        Verwalten Sie die Stammdaten und E-Mail-Konfiguration Ihrer Hundeschule.
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
      <p class="text-red-800">{{ error }}</p>
    </div>

    <!-- Settings Form -->
    <form v-else @submit.prevent="saveSettings" class="space-y-8">
      <!-- Company Settings -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-900">Stammdaten</h2>
        </div>
        <div class="p-6 space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Company Name -->
            <div>
              <label for="company_name" class="block text-sm font-medium text-gray-700">
                Firmenname
              </label>
              <input
                id="company_name"
                v-model="formData.company_name"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Company Email -->
            <div>
              <label for="company_email" class="block text-sm font-medium text-gray-700">
                E-Mail
              </label>
              <input
                id="company_email"
                v-model="formData.company_email"
                type="email"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Company Street -->
            <div>
              <label for="company_street" class="block text-sm font-medium text-gray-700">
                Straße
              </label>
              <input
                id="company_street"
                v-model="formData.company_street"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Company ZIP -->
            <div>
              <label for="company_zip" class="block text-sm font-medium text-gray-700">
                PLZ
              </label>
              <input
                id="company_zip"
                v-model="formData.company_zip"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Company City -->
            <div>
              <label for="company_city" class="block text-sm font-medium text-gray-700">
                Stadt
              </label>
              <input
                id="company_city"
                v-model="formData.company_city"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Company Country -->
            <div>
              <label for="company_country" class="block text-sm font-medium text-gray-700">
                Land
              </label>
              <input
                id="company_country"
                v-model="formData.company_country"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Company Phone -->
            <div>
              <label for="company_phone" class="block text-sm font-medium text-gray-700">
                Telefon
              </label>
              <input
                id="company_phone"
                v-model="formData.company_phone"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Company Website -->
            <div>
              <label for="company_website" class="block text-sm font-medium text-gray-700">
                Webseite
              </label>
              <input
                id="company_website"
                v-model="formData.company_website"
                type="url"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Company Tax ID -->
            <div>
              <label for="company_tax_id" class="block text-sm font-medium text-gray-700">
                Steuernummer
              </label>
              <input
                id="company_tax_id"
                v-model="formData.company_tax_id"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Company Registration Number -->
            <div>
              <label for="company_registration_number" class="block text-sm font-medium text-gray-700">
                Handelsregisternummer
              </label>
              <input
                id="company_registration_number"
                v-model="formData.company_registration_number"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>
          </div>

          <!-- Small Business Regulation -->
          <div class="flex items-start">
            <div class="flex items-center h-5">
              <input
                id="company_small_business"
                v-model="formData.company_small_business"
                type="checkbox"
                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
              />
            </div>
            <div class="ml-3 text-sm">
              <label for="company_small_business" class="font-medium text-gray-700">
                Kleinunternehmerregelung (§19 UStG)
              </label>
              <p class="text-gray-500">
                Als Kleinunternehmer wird keine Umsatzsteuer ausgewiesen. Rechnungen enthalten keine Mehrwertsteuer.
              </p>
            </div>
          </div>

          <!-- Logo Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Firmenlogo
            </label>
            <div class="flex items-center space-x-4">
              <div v-if="logoPreview" class="shrink-0">
                <img :src="logoPreview" alt="Logo" class="h-16 w-auto" />
              </div>
              <input
                type="file"
                accept="image/png,image/jpeg,image/svg+xml"
                @change="handleLogoChange"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
              />
            </div>
            <p class="mt-1 text-sm text-gray-500">PNG, JPG oder SVG bis 2MB</p>
          </div>

          <!-- Favicon Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Favicon
            </label>
            <div class="flex items-center space-x-4">
              <div v-if="faviconPreview" class="shrink-0">
                <img :src="faviconPreview" alt="Favicon" class="h-8 w-8" />
              </div>
              <input
                type="file"
                accept="image/png,image/x-icon"
                @change="handleFaviconChange"
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
              />
            </div>
            <p class="mt-1 text-sm text-gray-500">PNG oder ICO bis 512KB</p>
          </div>
        </div>
      </div>

      <!-- Email Settings -->
      <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-900">E-Mail-Konfiguration</h2>
        </div>
        <div class="p-6 space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Email From Address -->
            <div>
              <label for="email_from_address" class="block text-sm font-medium text-gray-700">
                Absender E-Mail
              </label>
              <input
                id="email_from_address"
                v-model="formData.email_from_address"
                type="email"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Email From Name -->
            <div>
              <label for="email_from_name" class="block text-sm font-medium text-gray-700">
                Absender Name
              </label>
              <input
                id="email_from_name"
                v-model="formData.email_from_name"
                type="text"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              />
            </div>

            <!-- Email Driver -->
            <div>
              <label for="email_driver" class="block text-sm font-medium text-gray-700">
                E-Mail Treiber
              </label>
              <select
                id="email_driver"
                v-model="formData.email_driver"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
              >
                <option value="smtp">SMTP</option>
                <option value="sendmail">Sendmail</option>
                <option value="mailgun">Mailgun</option>
                <option value="ses">Amazon SES</option>
                <option value="postmark">Postmark</option>
                <option value="log">Log (Entwicklung)</option>
              </select>
            </div>
          </div>

          <!-- SMTP Settings (show only when driver is smtp) -->
          <div v-if="formData.email_driver === 'smtp'" class="border-t border-gray-200 pt-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">SMTP Einstellungen</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- SMTP Host -->
              <div>
                <label for="smtp_host" class="block text-sm font-medium text-gray-700">
                  SMTP Host
                </label>
                <input
                  id="smtp_host"
                  v-model="formData.smtp_host"
                  type="text"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
              </div>

              <!-- SMTP Port -->
              <div>
                <label for="smtp_port" class="block text-sm font-medium text-gray-700">
                  SMTP Port
                </label>
                <input
                  id="smtp_port"
                  v-model.number="formData.smtp_port"
                  type="number"
                  min="1"
                  max="65535"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
              </div>

              <!-- SMTP Username -->
              <div>
                <label for="smtp_username" class="block text-sm font-medium text-gray-700">
                  SMTP Benutzername
                </label>
                <input
                  id="smtp_username"
                  v-model="formData.smtp_username"
                  type="text"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                />
              </div>

              <!-- SMTP Password -->
              <div>
                <label for="smtp_password" class="block text-sm font-medium text-gray-700">
                  SMTP Passwort
                </label>
                <input
                  id="smtp_password"
                  v-model="formData.smtp_password"
                  type="password"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                  placeholder="Leer lassen, um nicht zu ändern"
                />
              </div>

              <!-- SMTP Encryption -->
              <div>
                <label for="smtp_encryption" class="block text-sm font-medium text-gray-700">
                  SMTP Verschlüsselung
                </label>
                <select
                  id="smtp_encryption"
                  v-model="formData.smtp_encryption"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                  <option value="null">Keine</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Email Template Editor -->
      <EmailTemplateEditor
        v-model="formData"
        :company-data="formData"
      />

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-3">
        <button
          type="button"
          @click="loadSettings"
          :disabled="saving"
          class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Zurücksetzen
        </button>
        <button
          type="submit"
          :disabled="saving"
          class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span v-if="saving">Speichern...</span>
          <span v-else>Speichern</span>
        </button>
      </div>

      <!-- Success Message -->
      <div
        v-if="successMessage"
        class="bg-green-50 border border-green-200 rounded-lg p-4"
      >
        <p class="text-green-800">{{ successMessage }}</p>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import EmailTemplateEditor from '@/components/EmailTemplateEditor.vue'
import { settingsApi, type Setting } from '@/api/settings'

// Form data
const formData = ref({
  // Company
  company_name: '',
  company_street: '',
  company_zip: '',
  company_city: '',
  company_country: '',
  company_phone: '',
  company_email: '',
  company_website: '',
  company_tax_id: '',
  company_registration_number: '',
  company_small_business: false,
  company_logo: null as File | null,
  company_favicon: null as File | null,
  // Email
  email_from_address: '',
  email_from_name: '',
  email_driver: 'smtp',
  // Email Templates
  email_booking_subject: '',
  email_booking_message: '',
  email_invoice_subject: '',
  email_invoice_message: '',
  email_welcome_subject: '',
  email_welcome_message: '',
  email_reminder_subject: '',
  email_reminder_message: '',
  email_logo: null as File | string | null,
  // SMTP
  smtp_host: '',
  smtp_port: 587,
  smtp_username: '',
  smtp_password: '',
  smtp_encryption: 'tls',
})

const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const logoPreview = ref<string | null>(null)
const faviconPreview = ref<string | null>(null)

/**
 * Load settings from API
 */
const loadSettings = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await settingsApi.getSettings()
    
    // Parse settings from grouped response
    const allSettings = [
      ...(response.data.company || []),
      ...(response.data.email || []),
      ...(response.data.general || []),
    ]

    // Populate form data
    allSettings.forEach((setting: Setting) => {
      if (setting.key in formData.value) {
        if (setting.type === 'file' && setting.value) {
          // Set preview for logo/favicon
          if (setting.key === 'company_logo') {
            logoPreview.value = setting.value as string
          } else if (setting.key === 'company_favicon') {
            faviconPreview.value = setting.value as string
          }
        } else if (setting.type === 'boolean' || setting.key === 'company_small_business') {
          // Handle boolean values
          const value = setting.value
          formData.value[setting.key as keyof typeof formData.value] = (value === true || value === 'true' || value === 1 || value === '1') as any
        } else {
          formData.value[setting.key as keyof typeof formData.value] = setting.value as any
        }
      }
    })
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Fehler beim Laden der Einstellungen.'
    console.error('Error loading settings:', err)
  } finally {
    loading.value = false
  }
}

/**
 * Save settings
 */
const saveSettings = async () => {
  saving.value = true
  error.value = null
  successMessage.value = null

  try {
    // Prepare settings object
    const settings: Record<string, any> = {}

    Object.entries(formData.value).forEach(([key, value]) => {
      // Skip null values and empty passwords, but keep false booleans
      if (value === null || (key === 'smtp_password' && value === '')) {
        return
      }

      // Ensure boolean values are sent correctly
      if (typeof value === 'boolean') {
        settings[key] = value
      } else {
        settings[key] = value
      }
    })

    const response = await settingsApi.updateSettings(settings)
    successMessage.value = response.message || 'Einstellungen erfolgreich gespeichert.'
    
    // Reload settings to get updated values
    await loadSettings()

    // Clear success message after 5 seconds
    setTimeout(() => {
      successMessage.value = null
    }, 5000)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Fehler beim Speichern der Einstellungen.'
    console.error('Error saving settings:', err)
  } finally {
    saving.value = false
  }
}

/**
 * Handle logo file change
 */
const handleLogoChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  if (file) {
    formData.value.company_logo = file

    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      logoPreview.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

/**
 * Handle favicon file change
 */
const handleFaviconChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  if (file) {
    formData.value.company_favicon = file

    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      faviconPreview.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

// Load settings on mount
onMounted(() => {
  loadSettings()
})
</script>

<style scoped>
/* Component-specific styles if needed */
</style>
