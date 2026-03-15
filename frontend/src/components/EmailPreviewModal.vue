<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="emit('close')"
      >
        <div class="flex min-h-screen items-center justify-center p-4">
          <!-- Backdrop -->
          <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>

          <!-- Modal -->
          <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                E-Mail Vorschau
              </h3>
              <button
                @click="emit('close')"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Preview Content -->
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
              <!-- Email Container -->
              <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                <!-- Email Header -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm mb-4 p-4 border border-gray-200 dark:border-gray-700">
                  <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                      <div class="w-12 h-12 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                      </div>
                    </div>
                    <div class="flex-1">
                      <p class="text-sm text-gray-600 dark:text-gray-400">Von:</p>
                      <p class="font-medium text-gray-900 dark:text-white">
                        {{ companyData?.company_name || 'Hundeschule HomoCanis' }}
                        &lt;{{ companyData?.company_email || 'info@hundeschule.de' }}&gt;
                      </p>
                    </div>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Betreff:</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                      {{ processedSubject }}
                    </p>
                  </div>
                </div>

                <!-- Email Body -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
                  <!-- Logo -->
                  <div v-if="companyData?.company_logo" class="mb-6 text-center">
                    <img
                      :src="getLogoUrl(companyData.company_logo)"
                      alt="Logo"
                      class="h-16 mx-auto"
                    />
                  </div>
                  <div v-else class="mb-6 text-center">
                    <div class="inline-block px-6 py-3 bg-primary-100 dark:bg-primary-900 rounded-lg">
                      <h2 class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        {{ companyData?.company_name || 'Hundeschule' }}
                      </h2>
                    </div>
                  </div>

                  <!-- Message Content -->
                  <div class="prose dark:prose-invert max-w-none">
                    <div
                      class="whitespace-pre-wrap text-gray-700 dark:text-gray-300"
                      v-html="processedMessage"
                    ></div>
                  </div>

                  <!-- Footer -->
                  <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                      <p class="font-semibold text-gray-900 dark:text-white">
                        {{ companyData?.company_name || 'Hundeschule HomoCanis' }}
                      </p>
                      <p v-if="companyData?.company_street">
                        {{ companyData.company_street }}
                      </p>
                      <p v-if="companyData?.company_zip || companyData?.company_city">
                        {{ companyData?.company_zip }} {{ companyData?.company_city }}
                      </p>
                      <p v-if="companyData?.company_phone">
                        Tel: {{ companyData.company_phone }}
                      </p>
                      <p v-if="companyData?.company_email">
                        E-Mail: {{ companyData.company_email }}
                      </p>
                      <p v-if="companyData?.company_website">
                        Web: {{ companyData.company_website }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Info Box -->
              <div class="mt-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                  <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  <div class="text-sm text-blue-800 dark:text-blue-200">
                    <p class="font-medium mb-1">Dies ist eine Vorschau</p>
                    <p>Die echte E-Mail wird mit tatsächlichen Daten aus Ihrer Datenbank gefüllt. Diese Vorschau verwendet Beispieldaten.</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
              <button
                @click="emit('close')"
                class="btn btn-secondary"
              >
                Schließen
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import DOMPurify from 'dompurify'

interface Props {
  isOpen: boolean
  subject: string
  message: string
  variables: Record<string, string>
  companyData?: Record<string, any>
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
}>()

const processedSubject = computed(() => {
  let result = props.subject || ''
  Object.entries(props.variables).forEach(([key, value]) => {
    result = result.replace(new RegExp(key, 'g'), value)
  })
  return result
})

const processedMessage = computed(() => {
  let result = props.message || ''
  Object.entries(props.variables).forEach(([key, value]) => {
    result = result.replace(new RegExp(key, 'g'), `<strong>${value}</strong>`)
  })
  // Convert newlines to <br> but preserve whitespace
  result = result.replace(/\n/g, '<br>')
  
  // Sanitize HTML to prevent XSS attacks
  return DOMPurify.sanitize(result, {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
    ALLOWED_ATTR: ['href', 'target', 'rel'],
    ALLOW_DATA_ATTR: false,
  })
})

function getLogoUrl(path: string | File): string {
  // Handle File objects (preview)
  if (path instanceof File) {
    return URL.createObjectURL(path)
  }
  
  // Handle string paths
  const pathStr = String(path)
  if (pathStr.startsWith('http')) {
    return pathStr
  }
  
  const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8081'
  return `${apiUrl}/storage/${pathStr}`
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
  transition: transform 0.3s ease;
}

.modal-enter-from .bg-white,
.modal-leave-to .bg-white {
  transform: scale(0.9);
}
</style>
