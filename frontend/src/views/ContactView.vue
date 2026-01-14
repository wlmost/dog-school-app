<template>
  <div class="py-16 bg-white dark:bg-gray-900">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
          Kontakt
        </h1>
        <p class="text-lg text-gray-600 dark:text-gray-300">
          Haben Sie Fragen oder möchten Sie einen Termin vereinbaren? Wir freuen uns auf Ihre Nachricht!
        </p>
      </div>

      <div class="grid md:grid-cols-2 gap-12">
        <!-- Contact Information -->
        <div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
            Kontaktinformationen
          </h2>

          <div class="space-y-6">
            <!-- Address -->
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Adresse</h3>
                <p class="mt-1 text-gray-600 dark:text-gray-300">
                  Hundeschule HomoCanis<br>
                  Musterstraße 123<br>
                  12345 Musterstadt
                </p>
              </div>
            </div>

            <!-- Phone -->
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Telefon</h3>
                <p class="mt-1 text-gray-600 dark:text-gray-300">
                  <a href="tel:+49123456789" class="hover:text-primary-600 dark:hover:text-primary-400">
                    +49 123 456789
                  </a>
                </p>
              </div>
            </div>

            <!-- Email -->
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">E-Mail</h3>
                <p class="mt-1 text-gray-600 dark:text-gray-300">
                  <a href="mailto:info@homocanis.de" class="hover:text-primary-600 dark:hover:text-primary-400">
                    info@homocanis.de
                  </a>
                </p>
              </div>
            </div>

            <!-- Opening Hours -->
            <div class="flex items-start">
              <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="ml-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Öffnungszeiten</h3>
                <div class="mt-1 text-gray-600 dark:text-gray-300">
                  <p>Montag - Freitag: 9:00 - 18:00 Uhr</p>
                  <p>Samstag: 10:00 - 14:00 Uhr</p>
                  <p>Sonntag: Geschlossen</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Social Media / Additional Info -->
          <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
              Weitere Informationen
            </h3>
            <p class="text-gray-600 dark:text-gray-300">
              Termine können Sie ganz einfach über unser Online-Portal buchen. 
              Bitte melden Sie sich dazu in Ihrem Kundenkonto an.
            </p>
            <div class="mt-4">
              <RouterLink to="/login" class="btn btn-primary">
                Zum Login
              </RouterLink>
            </div>
          </div>
        </div>

        <!-- Contact Form -->
        <div>
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
            Nachricht senden
          </h2>

          <form @submit.prevent="handleSubmit" class="space-y-6">
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Name *
              </label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                required
                class="input"
                placeholder="Ihr vollständiger Name"
              />
            </div>

            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                E-Mail *
              </label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                required
                class="input"
                placeholder="ihre-email@beispiel.de"
              />
            </div>

            <div>
              <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Telefon
              </label>
              <input
                id="phone"
                v-model="form.phone"
                type="tel"
                class="input"
                placeholder="+49 123 456789"
              />
            </div>

            <div>
              <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Betreff *
              </label>
              <input
                id="subject"
                v-model="form.subject"
                type="text"
                required
                class="input"
                placeholder="Worum geht es?"
              />
            </div>

            <div>
              <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Nachricht *
              </label>
              <textarea
                id="message"
                v-model="form.message"
                rows="6"
                required
                class="input"
                placeholder="Ihre Nachricht an uns..."
              ></textarea>
            </div>

            <div>
              <button
                type="submit"
                :disabled="loading"
                class="btn btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="!loading">Nachricht senden</span>
                <span v-else>Wird gesendet...</span>
              </button>
            </div>

            <div v-if="successMessage" class="p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg">
              <p class="text-sm text-green-800 dark:text-green-200">{{ successMessage }}</p>
            </div>

            <div v-if="errorMessage" class="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
              <p class="text-sm text-red-800 dark:text-red-200">{{ errorMessage }}</p>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'

const form = ref({
  name: '',
  email: '',
  phone: '',
  subject: '',
  message: ''
})

const loading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const handleSubmit = async () => {
  loading.value = true
  successMessage.value = ''
  errorMessage.value = ''

  try {
    // TODO: Implement actual form submission to backend
    // For now, just show success message
    await new Promise(resolve => setTimeout(resolve, 1000))
    
    successMessage.value = 'Vielen Dank für Ihre Nachricht! Wir werden uns so schnell wie möglich bei Ihnen melden.'
    
    // Reset form
    form.value = {
      name: '',
      email: '',
      phone: '',
      subject: '',
      message: ''
    }
  } catch (error) {
    errorMessage.value = 'Es gab einen Fehler beim Senden Ihrer Nachricht. Bitte versuchen Sie es später erneut.'
  } finally {
    loading.value = false
  }
}

// SEO Meta Tags
onMounted(() => {
  document.title = 'Kontakt - Hundeschule HomoCanis'
  
  let metaDescription = document.querySelector('meta[name="description"]')
  if (!metaDescription) {
    metaDescription = document.createElement('meta')
    metaDescription.setAttribute('name', 'description')
    document.head.appendChild(metaDescription)
  }
  metaDescription.setAttribute('content', 'Kontaktieren Sie die Hundeschule HomoCanis. Wir freuen uns auf Ihre Anfrage zu unserem Hundetraining und unseren Kursen.')
})
</script>
