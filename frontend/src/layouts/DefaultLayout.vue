<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Sidebar -->
    <aside class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-40">
      <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="flex items-center justify-center h-16 px-4 border-b border-gray-200">
          <img src="@/assets/HomoCanis.jpg" alt="HomoCanis" class="h-12 w-auto">
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
          <RouterLink
            v-for="item in navigation"
            :key="item.name"
            :to="item.to"
            class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors"
            active-class="bg-primary-100 text-primary-700 font-medium"
          >
            <component :is="item.icon" class="w-5 h-5 mr-3" />
            {{ item.name }}
          </RouterLink>
        </nav>

        <!-- User Menu -->
        <div class="px-4 py-4 border-t border-gray-200">
          <div class="flex items-center justify-between px-4 py-2">
            <div class="flex items-center min-w-0">
              <div class="flex-shrink-0 w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center text-white font-medium">
                {{ userInitials }}
              </div>
              <div class="ml-3 min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 truncate">{{ user?.full_name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ roleLabel }}</p>
              </div>
            </div>
            <button
              @click="handleLogout"
              class="ml-2 p-2 text-gray-400 hover:text-gray-600 transition-colors"
              title="Abmelden"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="pl-64">
      <!-- Header -->
      <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="px-8 py-4">
          <h2 class="text-2xl font-semibold text-gray-900">{{ pageTitle }}</h2>
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

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

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

const pageTitle = computed(() => route.meta.title as string || 'Dashboard')

const navigation = computed(() => {
  const items = [
    {
      name: 'Dashboard',
      to: { name: 'Dashboard' },
      icon: 'svg',
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Kunden',
      to: { name: 'Customers' },
      icon: 'svg',
      roles: ['admin', 'trainer']
    },
    {
      name: 'Hunde',
      to: { name: 'Dogs' },
      icon: 'svg',
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Anamnese',
      to: { name: 'Anamnesis' },
      icon: 'svg',
      roles: ['admin', 'trainer']
    },
    {
      name: 'Kurse',
      to: { name: 'Courses' },
      icon: 'svg',
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Trainer',
      to: { name: 'Trainers' },
      icon: 'svg',
      roles: ['admin']
    },
    {
      name: 'Buchungen',
      to: { name: 'Bookings' },
      icon: 'svg',
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Rechnungen',
      to: { name: 'Invoices' },
      icon: 'svg',
      roles: ['admin', 'trainer']
    }
  ]

  return items.filter(item => !user.value || item.roles.includes(user.value.role))
})

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'Login' })
}
</script>
