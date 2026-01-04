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
            <svg v-if="item.icon === 'HomeIcon'" class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <svg v-else-if="item.icon === 'UsersIcon'" class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span v-else-if="item.icon === 'DogIcon'" class="text-xl mr-2">🐕</span>
            <svg v-else-if="item.icon === 'ClipboardIcon'" class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <svg v-else-if="item.icon === 'BookIcon'" class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <svg v-else-if="item.icon === 'UserIcon'" class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <svg v-else-if="item.icon === 'CalendarIcon'" class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <svg v-else-if="item.icon === 'InvoiceIcon'" class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
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
      icon: 'HomeIcon',
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Kunden',
      to: { name: 'Customers' },
      icon: 'UsersIcon',
      roles: ['admin', 'trainer']
    },
    {
      name: 'Hunde',
      to: { name: 'Dogs' },
      icon: 'DogIcon',
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Anamnese',
      to: { name: 'Anamnesis' },
      icon: 'ClipboardIcon',
      roles: ['admin', 'trainer']
    },
    {
      name: 'Kurse',
      to: { name: 'Courses' },
      icon: 'BookIcon',
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Trainer',
      to: { name: 'Trainers' },
      icon: 'UserIcon',
      roles: ['admin']
    },
    {
      name: 'Buchungen',
      to: { name: 'Bookings' },
      icon: 'CalendarIcon',
      roles: ['admin', 'trainer', 'customer']
    },
    {
      name: 'Rechnungen',
      to: { name: 'Invoices' },
      icon: 'InvoiceIcon',
      roles: ['admin', 'trainer', 'customer']
    }
  ]

  return items.filter(item => !user.value || item.roles.includes(user.value.role))
})

async function handleLogout() {
  await authStore.logout()
  router.push({ name: 'Login' })
}
</script>
