import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { User } from '@/stores/auth'

// ---------------------------------------------------------------------------
// Minimal route table (mirrors the meta flags used in the real router)
// ---------------------------------------------------------------------------
const testRoutes = [
  { path: '/login', name: 'Login', component: { template: '<div />' }, meta: { requiresAuth: false } },
  { path: '/app', name: 'Dashboard', component: { template: '<div />' }, meta: { requiresAuth: true } },
  {
    path: '/app/settings',
    name: 'Settings',
    component: { template: '<div />' },
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/app/trainer-area',
    name: 'TrainerArea',
    component: { template: '<div />' },
    meta: { requiresAuth: true, requiresRole: 'trainer' as const },
  },
  {
    path: '/app/customer-area',
    name: 'CustomerArea',
    component: { template: '<div />' },
    meta: { requiresAuth: true, requiresRole: 'customer' as const },
  },
]

// ---------------------------------------------------------------------------
// Helper: build a fresh router with the same guard logic as the real one
// ---------------------------------------------------------------------------
function buildRouter() {
  const router = createRouter({ history: createMemoryHistory(), routes: testRoutes })

  router.beforeEach(async (to, _from, next) => {
    const authStore = useAuthStore()
    const toastStore = useToastStore()

    if (to.meta.requiresAuth !== false) {
      if (!authStore.isAuthenticated) {
        await authStore.checkAuth()
        if (!authStore.isAuthenticated) {
          return next({ name: 'Login', query: { redirect: to.fullPath } })
        }
      }
    }

    if (to.name === 'Login' && authStore.isAuthenticated) {
      return next({ name: 'Dashboard' })
    }

    if (to.meta.requiresAdmin && !authStore.isAdmin) {
      toastStore.warning('Kein Zugriff', 'Diese Seite ist nur für Administratoren zugänglich.')
      return next({ name: 'Dashboard' })
    }

    if (to.meta.requiresRole) {
      const requiredRole = to.meta.requiresRole
      const userRole = authStore.user?.role
      const hasRole = userRole === 'admin' || userRole === requiredRole
      if (!hasRole) {
        toastStore.warning('Kein Zugriff', 'Sie haben keine Berechtigung für diese Seite.')
        return next({ name: 'Dashboard' })
      }
    }

    next()
  })

  return router
}

// ---------------------------------------------------------------------------
// Helper: set the auth store to a logged-in user with the given role
// ---------------------------------------------------------------------------
function loginAs(authStore: ReturnType<typeof useAuthStore>, role: User['role']) {
  authStore.user = {
    id: 1,
    email: `${role}@example.com`,
    first_name: 'Test',
    last_name: 'User',
    role,
    full_name: `Test User`,
  }
  authStore.token = 'fake-token'
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------
describe('Router navigation guard', () => {
  let router: ReturnType<typeof buildRouter>
  let authStore: ReturnType<typeof useAuthStore>
  let toastStore: ReturnType<typeof useToastStore>

  beforeEach(() => {
    setActivePinia(createPinia())
    authStore = useAuthStore()
    toastStore = useToastStore()
    // Prevent real HTTP calls inside checkAuth
    vi.spyOn(authStore, 'checkAuth').mockResolvedValue()
    vi.spyOn(toastStore, 'warning')
    router = buildRouter()
  })

  // --- unauthenticated ---

  it('redirects unauthenticated user to Login for protected route', async () => {
    await router.push('/app')
    expect(router.currentRoute.value.name).toBe('Login')
  })

  it('keeps redirect query param when redirecting to Login', async () => {
    await router.push('/app/settings')
    expect(router.currentRoute.value.name).toBe('Login')
    expect(router.currentRoute.value.query.redirect).toBe('/app/settings')
  })

  it('allows unauthenticated user to visit Login page', async () => {
    await router.push('/login')
    expect(router.currentRoute.value.name).toBe('Login')
  })

  // --- authenticated admin ---

  it('allows admin to access requiresAdmin route', async () => {
    loginAs(authStore, 'admin')
    await router.push('/app/settings')
    expect(router.currentRoute.value.name).toBe('Settings')
    expect(toastStore.warning).not.toHaveBeenCalled()
  })

  it('allows admin to access requiresRole trainer route', async () => {
    loginAs(authStore, 'admin')
    await router.push('/app/trainer-area')
    expect(router.currentRoute.value.name).toBe('TrainerArea')
    expect(toastStore.warning).not.toHaveBeenCalled()
  })

  it('allows admin to access requiresRole customer route', async () => {
    loginAs(authStore, 'admin')
    await router.push('/app/customer-area')
    expect(router.currentRoute.value.name).toBe('CustomerArea')
    expect(toastStore.warning).not.toHaveBeenCalled()
  })

  it('redirects logged-in admin away from Login to Dashboard', async () => {
    loginAs(authStore, 'admin')
    await router.push('/login')
    expect(router.currentRoute.value.name).toBe('Dashboard')
  })

  // --- authenticated trainer ---

  it('redirects trainer from requiresAdmin route to Dashboard with toast', async () => {
    loginAs(authStore, 'trainer')
    await router.push('/app/settings')
    expect(router.currentRoute.value.name).toBe('Dashboard')
    expect(toastStore.warning).toHaveBeenCalledOnce()
  })

  it('allows trainer to access requiresRole trainer route', async () => {
    loginAs(authStore, 'trainer')
    await router.push('/app/trainer-area')
    expect(router.currentRoute.value.name).toBe('TrainerArea')
    expect(toastStore.warning).not.toHaveBeenCalled()
  })

  it('redirects trainer from requiresRole customer route to Dashboard with toast', async () => {
    loginAs(authStore, 'trainer')
    await router.push('/app/customer-area')
    expect(router.currentRoute.value.name).toBe('Dashboard')
    expect(toastStore.warning).toHaveBeenCalledOnce()
  })

  // --- authenticated customer ---

  it('redirects customer from requiresAdmin route to Dashboard with toast', async () => {
    loginAs(authStore, 'customer')
    await router.push('/app/settings')
    expect(router.currentRoute.value.name).toBe('Dashboard')
    expect(toastStore.warning).toHaveBeenCalledOnce()
  })

  it('redirects customer from requiresRole trainer route to Dashboard with toast', async () => {
    loginAs(authStore, 'customer')
    await router.push('/app/trainer-area')
    expect(router.currentRoute.value.name).toBe('Dashboard')
    expect(toastStore.warning).toHaveBeenCalledOnce()
  })

  it('allows customer to access requiresRole customer route', async () => {
    loginAs(authStore, 'customer')
    await router.push('/app/customer-area')
    expect(router.currentRoute.value.name).toBe('CustomerArea')
    expect(toastStore.warning).not.toHaveBeenCalled()
  })

  // --- Dashboard (no role restriction) ---

  it('allows admin to access Dashboard', async () => {
    loginAs(authStore, 'admin')
    await router.push('/app')
    expect(router.currentRoute.value.name).toBe('Dashboard')
  })

  it('allows trainer to access Dashboard', async () => {
    loginAs(authStore, 'trainer')
    await router.push('/app')
    expect(router.currentRoute.value.name).toBe('Dashboard')
  })

  it('allows customer to access Dashboard', async () => {
    loginAs(authStore, 'customer')
    await router.push('/app')
    expect(router.currentRoute.value.name).toBe('Dashboard')
  })

  // --- edge case: requiresRole with null user (token set, user missing) ---

  it('redirects to Login on requiresRole route when user is null despite token', async () => {
    // token present but user cleared (e.g. race condition / corrupted state)
    authStore.token = 'fake-token'
    authStore.user = null
    // checkAuth is already mocked as a no-op, so user stays null.
    // isAuthenticated = computed(!!token && !!user) → false → guard sends to Login.
    await router.push('/app/trainer-area')
    expect(router.currentRoute.value.name).toBe('Login')
    expect(router.currentRoute.value.query.redirect).toBe('/app/trainer-area')
  })
})
