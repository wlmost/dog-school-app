import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { RouteRecordRaw } from 'vue-router'

const routes: RouteRecordRaw[] = [
  // Public routes with PublicLayout
  {
    path: '/',
    component: () => import('@/layouts/PublicLayout.vue'),
    meta: { requiresAuth: false },
    children: [
      {
        path: '',
        name: 'Home',
        component: () => import('@/views/HomeView.vue'),
        meta: { title: 'Hundeschule HomoCanis - Professionelles Hundetraining' }
      },
      {
        path: 'contact',
        name: 'Contact',
        component: () => import('@/views/ContactView.vue'),
        meta: { title: 'Kontakt - Hundeschule HomoCanis' }
      },
      {
        path: 'legal',
        name: 'Legal',
        component: () => import('@/views/LegalView.vue'),
        meta: { title: 'Impressum - Hundeschule HomoCanis' }
      }
    ]
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { requiresAuth: false, title: 'Anmelden' }
  },
  // Authenticated routes with DefaultLayout
  {
    path: '/app',
    component: () => import('@/layouts/DefaultLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'Dashboard',
        component: () => import('@/views/DashboardView.vue'),
        meta: { title: 'Dashboard' }
      },
      {
        path: 'customers',
        name: 'Customers',
        component: () => import('@/views/customers/CustomersView.vue'),
        meta: { title: 'Kunden' }
      },
      {
        path: 'dogs',
        name: 'Dogs',
        component: () => import('@/views/dogs/DogsView.vue'),
        meta: { title: 'Hunde' }
      },
      {
        path: 'anamnesis',
        name: 'Anamnesis',
        component: () => import('@/views/anamnesis/AnamnesisView.vue'),
        meta: { title: 'Anamnese' }
      },
      {
        path: 'courses',
        name: 'Courses',
        component: () => import('@/views/courses/CoursesView.vue'),
        meta: { title: 'Kurse' }
      },
      {
        path: 'trainers',
        name: 'Trainers',
        component: () => import('@/views/trainers/TrainersView.vue'),
        meta: { title: 'Trainer' }
      },
      {
        path: 'bookings',
        name: 'Bookings',
        component: () => import('@/views/bookings/BookingsView.vue'),
        meta: { title: 'Buchungen' }
      },
      {
        path: 'invoices',
        name: 'Invoices',
        component: () => import('@/views/invoices/InvoicesView.vue'),
        meta: { title: 'Rechnungen' }
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('@/views/SettingsView.vue'),
        meta: { title: 'Systemeinstellungen', requiresAdmin: true }
      },
      {
        path: 'training-logs',
        name: 'TrainingLogs',
        component: () => import('@/views/training/TrainingLogsView.vue'),
        meta: { title: 'Trainings-Dokumentation' }
      },
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { title: '404 - Seite nicht gefunden' }
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// Navigation Guard for Authentication
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()
  
  // Set page title
  document.title = to.meta.title as string || 'Hundeschule HomoCanis'

  // Check if route requires authentication
  if (to.meta.requiresAuth !== false) {
    if (!authStore.isAuthenticated) {
      // Try to restore session
      await authStore.checkAuth()
      
      if (!authStore.isAuthenticated) {
        return next({ name: 'Login', query: { redirect: to.fullPath } })
      }
    }
  }

  // Redirect logged-in users trying to access login page to dashboard
  if (to.name === 'Login' && authStore.isAuthenticated) {
    return next({ name: 'Dashboard' })
  }

  next()
})

export default router
