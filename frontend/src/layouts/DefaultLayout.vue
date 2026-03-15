<template>
  <div class="min-h-screen" :style="backgroundStyle">
    <!-- Sidebar -->
    <aside class="fixed inset-y-0 left-0 w-64 bg-white dark:bg-gray-800 shadow-lg z-40 border-r border-gray-200 dark:border-gray-700">
      <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="flex items-center justify-center h-16 px-4 border-b border-gray-200 dark:border-gray-700">
          <img src="@/assets/HomoCanis.jpg" alt="HomoCanis" class="h-12 w-auto">
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
          <RouterLink
            v-for="item in navigation"
            :key="item.name"
            :to="item.to"
            class="flex items-center px-4 py-3 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-primary-50 dark:hover:bg-gray-700 hover:text-primary-700 dark:hover:text-primary-400 transition-colors"
            active-class="bg-primary-100 dark:bg-gray-700 text-primary-700 dark:text-primary-400 font-medium"
          >
            <component :is="item.icon" class="w-5 h-5 mr-3" />
            {{ item.name }}
          </RouterLink>
        </nav>

        <!-- User Menu -->
        <div class="px-4 py-4 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between px-4 py-2">
            <div class="flex items-center min-w-0">
              <div class="flex-shrink-0 w-8 h-8 bg-primary-600 dark:bg-primary-500 rounded-full flex items-center justify-center text-white font-medium">
                {{ userInitials }}
              </div>
              <div class="ml-3 min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ user?.full_name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ roleLabel }}</p>
              </div>
            </div>
            <button
              @click="handleLogout"
              class="ml-2 p-2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
              title="Abmelden"
            >
              <ArrowRightOnRectangleIcon class="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="pl-64">
      <!-- Header -->
      <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="px-8 py-4 flex items-center justify-between">
          <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ pageTitle }}</h2>
          
          <!-- Theme Toggle -->
          <button
            @click="themeStore.toggleTheme()"
            class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            title="Theme wechseln"
          >
            <SunIcon v-if="themeStore.isDark" class="w-5 h-5" />
            <MoonIcon v-else class="w-5 h-5" />
          </button>
        </div>
      </header>

      <!-- Page Content -->
      <main class="p-8">
        <RouterView />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import {
  HomeIcon,
  UsersIcon,
  ClipboardDocumentListIcon,
  AcademicCapIcon,
  UserIcon,
  CalendarIcon,
  DocumentTextIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  SunIcon,
  MoonIcon
} from '@heroicons/vue/24/outline'
import { h } from 'vue'
import backgroundImage from '@/assets/pet-01-1280x664.jpg'

// Custom Dog Icon component
const DogIcon = () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24',
  xmlns: 'http://www.w3.org/2000/svg'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M7 4a1 1 0 011-1h1a1 1 0 011 1v1a1 1 0 01-1 1H8a1 1 0 01-1-1V4zm8 0a1 1 0 011-1h1a1 1 0 011 1v1a1 1 0 01-1 1h-1a1 1 0 01-1-1V4zM5 8a2 2 0 012-2h10a2 2 0 012 2v2a2 2 0 01-2 2h-.5a.5.5 0 00-.5.5 3 3 0 01-6 0 .5.5 0 00-.5-.5H7a2 2 0 01-2-2V8zm1 7a1 1 0 011-1h1a1 1 0 011 1v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-1zm9 0a1 1 0 011-1h1a1 1 0 011 1v1a1 1 0 01-1 1h-1a1 1 0 01-1-1v-1z'
  })
])

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const themeStore = useThemeStore()

const user = computed(() => authStore.user)

const userInitials = computed(() => {
  if (!user.value || !user.value.first_name || !user.value.last_name) return '?'
  return `${user.value.first_name[0]}${user.value.last_name[0]}`.toUpperCase()
})

const roleLabel = computed(() => {
  const roles = {
    admin: 'Administrator',
    trainer: 'Trainer',
    customer: 'Kunde'
  }
  return user.value ? roles[user.value.role] : ''
})

const backgroundStyle = computed(() => ({
  background: `linear-gradient(rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.8)), url(${backgroundImage})`,
  backgroundSize: 'cover',
  backgroundPosition: 'center',
  backgroundAttachment: 'fixed'
}))

const pageTitle = computed(() => route.meta.title as string || 'Dashboard')

const navigation = computed(() => {
  const items = [
    {
      name: 'Dashboard',
      to: { name: 'Dashboard' },
      icon: HomeIcon,
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Kunden',
      to: { name: 'Customers' },
      icon: UsersIcon,
      roles: ['admin', 'trainer']
    },
    {
      name: 'Hunde',
      to: { name: 'Dogs' },
      icon: DogIcon,
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Anamnese',
      to: { name: 'Anamnesis' },
      icon: ClipboardDocumentListIcon,
      roles: ['admin', 'trainer']
    },
    {
      name: 'Kurse',
      to: { name: 'Courses' },
      icon: AcademicCapIcon,
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Trainer',
      to: { name: 'Trainers' },
      icon: UserIcon,
      roles: ['admin']
    },
    {
      name: 'Buchungen',
      to: { name: 'Bookings' },
      icon: CalendarIcon,
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Rechnungen',
      to: { name: 'Invoices' },
      icon: DocumentTextIcon,
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Einstellungen',
      to: { name: 'Settings' },
      icon: Cog6ToothIcon,
      roles: ['admin']
    }
  ]

  return items.filter(item => !user.value || item.roles.includes(user.value.role))
})

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'Login' })
}
</script>
