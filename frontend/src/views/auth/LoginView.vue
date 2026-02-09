<template>
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8" style="background: url('https://ausbildungszentrum.homocanis.de/wp-content/uploads/2020/09/pet-01.jpg'); background-size: cover; background-position: center;">
    <div class="max-w-md w-full">
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-8">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
          <div class="mb-4">
            <img src="@/assets/HomoCanis.jpg" alt="HomoCanis Logo" class="mx-auto h-24 w-auto">
          </div>
          <h2 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
            Hundeschule HomoCanis
          </h2>
          <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Melden Sie sich an, um fortzufahren
          </p>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
          <p class="text-sm text-red-800 dark:text-red-200">{{ error }}</p>
        </div>

        <!-- Login Form -->
        <form @submit.prevent="handleLogin" class="space-y-6">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              E-Mail-Adresse
            </label>
            <input
              id="email"
              v-model="credentials.email"
              type="email"
              required
              autocomplete="email"
              class="input"
              placeholder="ihre-email@beispiel.de"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Passwort
            </label>
            <input
              id="password"
              v-model="credentials.password"
              type="password"
              required
              autocomplete="current-password"
              class="input"
              placeholder="••••••••"
            />
          </div>

          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <input
                id="remember-me"
                v-model="rememberMe"
                type="checkbox"
                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded"
              />
              <label for="remember-me" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                Angemeldet bleiben
              </label>
            </div>

            <div class="text-sm">
              <a href="#" class="font-medium text-primary-600 dark:text-primary-400 hover:text-primary-500 dark:hover:text-primary-300">
                Passwort vergessen?
              </a>
            </div>
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="btn btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="!loading">Anmelden</span>
            <span v-else class="flex items-center justify-center">
              <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Anmeldung läuft...
            </span>
          </button>
        </form>

        <!-- Footer -->
        <div class="mt-6 text-center">
          <p class="text-xs text-gray-500 dark:text-gray-400">
            © {{ new Date().getFullYear() }} Hundeschule HomoCanis. Alle Rechte vorbehalten.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const credentials = ref({
  email: '',
  password: ''
})

const rememberMe = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)

async function handleLogin() {
  loading.value = true
  error.value = null

  try {
    await authStore.login(credentials.value)
    router.push({ name: 'Dashboard' })
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Anmeldung fehlgeschlagen. Bitte überprüfen Sie Ihre Anmeldedaten.'
  } finally {
    loading.value = false
  }
}
</script>
