<template>
  <div class="email-template-editor bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
    <!-- Header -->
    <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">E-Mail Templates</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Passen Sie Ihre E-Mail-Vorlagen an. Verfügbare Variablen werden automatisch ersetzt.
      </p>
    </div>

    <div class="p-6 space-y-6">
      <!-- Template Selector -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Vorlage auswählen
        </label>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
          <button
            v-for="template in templates"
            :key="template.key"
            type="button"
            @click="selectedTemplate = template.key"
            :class="[
              'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
              selectedTemplate === template.key
                ? 'bg-primary-600 text-white'
                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
            ]"
          >
            {{ template.label }}
          </button>
        </div>
      </div>

      <!-- Current Template Editor -->
      <div v-if="currentTemplate" class="space-y-4">
        <!-- Subject -->
        <div>
          <label :for="`subject-${currentTemplate.key}`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Betreff
          </label>
          <input
            :id="`subject-${currentTemplate.key}`"
            v-model="formData[currentTemplate.subjectKey]"
            type="text"
            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
            placeholder="Z.B. Buchungsbestätigung - {booking_number}"
          />
        </div>

        <!-- Message -->
        <div>
          <label :for="`message-${currentTemplate.key}`" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Nachricht
          </label>
          <textarea
            :id="`message-${currentTemplate.key}`"
            v-model="formData[currentTemplate.messageKey]"
            rows="8"
            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 font-mono text-sm"
            placeholder="Ihre Nachricht... Verwenden Sie {variable} für Platzhalter"
          ></textarea>
        </div>

        <!-- Available Variables -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
          <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-2">
            Verfügbare Variablen für {{ currentTemplate.label }}
          </h3>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            <button
              v-for="variable in currentTemplate.variables"
              type="button"
              :key="variable.key"
              @click="insertVariable(variable.key)"
              class="text-left px-3 py-2 bg-white dark:bg-gray-800 border border-blue-200 dark:border-blue-700 rounded text-xs hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors"
              :title="variable.description"
            >
              <code class="text-blue-600 dark:text-blue-400">{{ variable.key }}</code>
              <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">{{ variable.description }}</p>
            </button>
          </div>
        </div>

        <!-- Preview Button -->
        <div class="flex justify-end">
          <button
            type="button"
            @click="showPreview = true"
            class="btn btn-secondary"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Vorschau
          </button>
        </div>
      </div>
    </div>

    <!-- Email Preview Modal -->
    <EmailPreviewModal
      v-if="showPreview && currentTemplate"
      :is-open="showPreview"
      :subject="formData[currentTemplate.subjectKey]"
      :message="formData[currentTemplate.messageKey]"
      :variables="getPreviewData(currentTemplate.key)"
      :company-data="companyData"
      @close="showPreview = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import EmailPreviewModal from './EmailPreviewModal.vue'

interface Props {
  modelValue: Record<string, any>
  companyData?: Record<string, any>
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:modelValue': [value: Record<string, any>]
}>()

const formData = ref<Record<string, any>>({ ...props.modelValue })
const selectedTemplate = ref('booking')
const showPreview = ref(false)

// Template definitions
const templates = [
  {
    key: 'booking',
    label: 'Buchung',
    subjectKey: 'email_booking_subject',
    messageKey: 'email_booking_message',
    variables: [
      { key: '{customer_name}', description: 'Kundenname' },
      { key: '{booking_number}', description: 'Buchungsnummer' },
      { key: '{course_name}', description: 'Kursname' },
      { key: '{date}', description: 'Datum' },
      { key: '{time}', description: 'Uhrzeit' },
      { key: '{trainer_name}', description: 'Trainername' },
    ]
  },
  {
    key: 'invoice',
    label: 'Rechnung',
    subjectKey: 'email_invoice_subject',
    messageKey: 'email_invoice_message',
    variables: [
      { key: '{customer_name}', description: 'Kundenname' },
      { key: '{invoice_number}', description: 'Rechnungsnummer' },
      { key: '{amount}', description: 'Betrag' },
      { key: '{due_date}', description: 'Fälligkeitsdatum' },
      { key: '{download_link}', description: 'Download-Link' },
    ]
  },
  {
    key: 'welcome',
    label: 'Willkommen',
    subjectKey: 'email_welcome_subject',
    messageKey: 'email_welcome_message',
    variables: [
      { key: '{customer_name}', description: 'Kundenname' },
      { key: '{email}', description: 'E-Mail-Adresse' },
      { key: '{password}', description: 'Passwort (nur bei Neuanmeldung)' },
      { key: '{login_url}', description: 'Login-URL' },
    ]
  },
  {
    key: 'reminder',
    label: 'Erinnerung',
    subjectKey: 'email_reminder_subject',
    messageKey: 'email_reminder_message',
    variables: [
      { key: '{customer_name}', description: 'Kundenname' },
      { key: '{invoice_number}', description: 'Rechnungsnummer' },
      { key: '{amount}', description: 'Offener Betrag' },
      { key: '{days_overdue}', description: 'Tage überfällig' },
      { key: '{payment_link}', description: 'Zahlungslink' },
    ]
  }
]

const currentTemplate = computed(() => {
  return templates.find(t => t.key === selectedTemplate.value)
})

// Watch for changes and emit
watch(formData, (newValue) => {
  emit('update:modelValue', newValue)
}, { deep: true })

function insertVariable(variable: string) {
  if (!currentTemplate.value) return
  
  const messageKey = currentTemplate.value.messageKey
  const currentMessage = formData.value[messageKey] || ''
  formData.value[messageKey] = currentMessage + ' ' + variable
}

function getPreviewData(templateKey: string): Record<string, string> {
  const previewData = {
    booking: {
      '{customer_name}': 'Max Mustermann',
      '{booking_number}': 'B-2026-001',
      '{course_name}': 'Welpentraining Gruppe A',
      '{date}': '25.01.2026',
      '{time}': '15:00 Uhr',
      '{trainer_name}': 'Sarah Schmidt',
    },
    invoice: {
      '{customer_name}': 'Max Mustermann',
      '{invoice_number}': 'R-2026-001',
      '{amount}': '89,00 €',
      '{due_date}': '10.02.2026',
      '{download_link}': 'https://example.com/invoices/R-2026-001.pdf',
    },
    welcome: {
      '{customer_name}': 'Max Mustermann',
      '{email}': 'max@example.com',
      '{password}': 'Demo123!',
      '{login_url}': 'https://example.com/login',
    },
    reminder: {
      '{customer_name}': 'Max Mustermann',
      '{invoice_number}': 'R-2026-001',
      '{amount}': '89,00 €',
      '{days_overdue}': '7',
      '{payment_link}': 'https://example.com/pay/R-2026-001',
    }
  }

  return previewData[templateKey as keyof typeof previewData] || {}
}

// Initialize with defaults if empty
if (!formData.value.email_booking_subject) {
  formData.value.email_booking_subject = 'Buchungsbestätigung - {booking_number}'
}
if (!formData.value.email_booking_message) {
  formData.value.email_booking_message = `Hallo {customer_name},

vielen Dank für Ihre Buchung!

Kursdetails:
- Kurs: {course_name}
- Datum: {date}
- Uhrzeit: {time}
- Trainer: {trainer_name}

Wir freuen uns auf Sie!

Mit freundlichen Grüßen
Ihr Hundeschule-Team`
}

if (!formData.value.email_invoice_subject) {
  formData.value.email_invoice_subject = 'Rechnung {invoice_number}'
}
if (!formData.value.email_invoice_message) {
  formData.value.email_invoice_message = `Hallo {customer_name},

anbei erhalten Sie Ihre Rechnung {invoice_number}.

Rechnungsbetrag: {amount}
Fällig am: {due_date}

Download: {download_link}

Vielen Dank!`
}

if (!formData.value.email_welcome_subject) {
  formData.value.email_welcome_subject = 'Willkommen bei der Hundeschule!'
}
if (!formData.value.email_welcome_message) {
  formData.value.email_welcome_message = `Hallo {customer_name},

herzlich willkommen!

Ihre Zugangsdaten:
E-Mail: {email}
Passwort: {password}

Login: {login_url}

Viel Erfolg!`
}

if (!formData.value.email_reminder_subject) {
  formData.value.email_reminder_subject = 'Zahlungserinnerung - {invoice_number}'
}
if (!formData.value.email_reminder_message) {
  formData.value.email_reminder_message = `Hallo {customer_name},

die Rechnung {invoice_number} ist seit {days_overdue} Tagen überfällig.

Offener Betrag: {amount}

Bitte begleichen Sie den Betrag: {payment_link}

Vielen Dank!`
}
</script>
