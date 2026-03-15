<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Navigation Bar -->
    <nav class="bg-white dark:bg-gray-800 shadow-md fixed w-full top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <!-- Logo -->
          <div class="flex items-center">
            <RouterLink to="/" class="flex items-center">
              <img src="@/assets/HomoCanis.jpg" alt="HomoCanis" class="h-10 w-auto">
              <span class="ml-3 text-xl font-bold text-gray-900 dark:text-white hidden sm:block">
                Hundeschule HomoCanis
              </span>
            </RouterLink>
          </div>

          <!-- Desktop Navigation -->
          <div class="hidden md:flex items-center space-x-8">
            <RouterLink
              to="/"
              class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 px-3 py-2 text-sm font-medium transition-colors"
              active-class="text-primary-600 dark:text-primary-400 font-semibold"
            >
              Home
            </RouterLink>
            <RouterLink
              to="/contact"
              class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 px-3 py-2 text-sm font-medium transition-colors"
              active-class="text-primary-600 dark:text-primary-400 font-semibold"
            >
              Kontakt
            </RouterLink>
            <RouterLink
              to="/legal"
              class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 px-3 py-2 text-sm font-medium transition-colors"
              active-class="text-primary-600 dark:text-primary-400 font-semibold"
            >
              Impressum
            </RouterLink>
            
            <!-- Theme Toggle -->
            <button
              @click="themeStore.toggleTheme()"
              class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
              title="Theme wechseln"
            >
              <svg v-if="themeStore.isDark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
              </svg>
            </button>
            
            <RouterLink
              to="/login"
              class="btn btn-primary text-sm"
            >
              Login
            </RouterLink>
          </div>

          <!-- Mobile menu button -->
          <div class="md:hidden">
            <button
              @click="mobileMenuOpen = !mobileMenuOpen"
              type="button"
              class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 focus:outline-none"
              aria-expanded="false"
            >
              <span class="sr-only">Open main menu</span>
              <svg v-if="!mobileMenuOpen" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
              <svg v-else class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile menu -->
      <div v-show="mobileMenuOpen" class="md:hidden border-t border-gray-200 dark:border-gray-700">
        <div class="px-2 pt-2 pb-3 space-y-1">
          <RouterLink
            to="/"
            @click="mobileMenuOpen = false"
            class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700"
            active-class="text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-gray-700 font-semibold"
          >
            Home
          </RouterLink>
          <RouterLink
            to="/contact"
            @click="mobileMenuOpen = false"
            class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700"
            active-class="text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-gray-700 font-semibold"
          >
            Kontakt
          </RouterLink>
          <RouterLink
            to="/legal"
            @click="mobileMenuOpen = false"
            class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700"
            active-class="text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-gray-700 font-semibold"
          >
            Impressum
          </RouterLink>
          <RouterLink
            to="/login"
            @click="mobileMenuOpen = false"
            class="block px-3 py-2 rounded-md text-base font-medium text-white bg-primary-600 hover:bg-primary-700"
          >
            Login
          </RouterLink>
        </div>
      </div>
    </nav>

    <!-- Main Content with top padding for fixed nav -->
    <main class="pt-16">
      <RouterView />
    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="md:flex md:items-center md:justify-between">
          <div class="flex justify-center md:justify-start">
            <img src="@/assets/HomoCanis.jpg" alt="HomoCanis" class="h-10 w-auto">
          </div>
          <div class="mt-4 md:mt-0">
            <p class="text-center md:text-right text-sm text-gray-500 dark:text-gray-400">
              © {{ new Date().getFullYear() }} Hundeschule HomoCanis. Alle Rechte vorbehalten.
            </p>
          </div>
        </div>
        <div class="mt-4 flex justify-center md:justify-end space-x-6">
          <RouterLink to="/legal" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
            Impressum
          </RouterLink>
          <RouterLink to="/contact" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
            Kontakt
          </RouterLink>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { useThemeStore } from '@/stores/theme'

const mobileMenuOpen = ref(false)
const themeStore = useThemeStore()
</script>
