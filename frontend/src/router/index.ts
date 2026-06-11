import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { RouteRecordRaw } from 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    requiresAuth?: boolean
    requiresAdmin?: boolean
    requiresRole?: 'admin' | 'trainer' | 'customer'
  }
}

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
      },
      {
        path: 'datenschutz',
        name: 'Datenschutz',
        component: () => import('@/views/DatenschutzView.vue'),
        meta: { title: 'Datenschutzerklärung - Hundeschule HomoCanis' }
      },
      {
        path: 'agb',
        name: 'Agb',
        component: () => import('@/views/AgbView.vue'),
        meta: { title: 'AGB - Hundeschule HomoCanis' }
      },
      {
        path: 'courses/:id',
        name: 'CourseDetail',
        component: () => import('@/views/CourseDetailView.vue'),
        meta: { requiresAuth: false, title: 'Kursdetails' }
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
        path: 'profile',
        name: 'Profile',
        component: () => import('@/views/ProfileView.vue'),
        meta: { title: 'Mein Profil' }
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
  // Do NOT pass BASE_URL here – let Vue Router read the runtime <base href="..."> tag.
  // install.php injects <base href="/subdir/"> into index.html for subdirectory installs.
  // Passing a hardcoded value (even '/') would override that auto-detection.
  history: createWebHistory(),
  routes
})

// Navigation Guard for Authentication
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()
  const toastStore = useToastStore()
  
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

  // Check if route requires admin role
  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    toastStore.warning('Kein Zugriff', 'Diese Seite ist nur für Administratoren zugänglich.')
    return next({ name: 'Dashboard' })
  }

  // Check if route requires a specific role
  if (to.meta.requiresRole) {
    const requiredRole = to.meta.requiresRole
    const userRole = authStore.user?.role
    if (!userRole) {
      // User object missing despite passing auth check – send back to Login
      return next({ name: 'Login', query: { redirect: to.fullPath } })
    }
    // Admin always has access; other roles must match exactly
    const hasRole = userRole === 'admin' || userRole === requiredRole
    if (!hasRole) {
      toastStore.warning('Kein Zugriff', 'Sie haben keine Berechtigung für diese Seite.')
      return next({ name: 'Dashboard' })
    }
  }

  next()
})

export default router
