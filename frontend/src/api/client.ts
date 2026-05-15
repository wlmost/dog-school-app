import axios from 'axios'
import type { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

// In production the API lives on the same origin (no CORS). Use window.location.origin
// as fallback so the built package works on any domain without reconfiguration.
const apiBase = import.meta.env.VITE_API_BASE_URL
  || (typeof window !== 'undefined' ? window.location.origin : 'http://localhost:8081')

/**
 * Returns the subdirectory base path for non-root installs (e.g. '/subdir').
 * Reads the <base href> tag that install.php injects into index.html.
 * Returns '' for root installs where no <base href> is present.
 */
const getAppBasePath = (): string => {
  if (typeof document === 'undefined') return ''
  const base = document.querySelector('base')
  if (!base) return ''
  // Use getAttribute to get the raw attribute value ('/subdir/'), not the
  // browser-resolved absolute URL ('https://example.com/subdir/').
  const href = base.getAttribute('href') || '/'
  const pathname = href.startsWith('/')
    ? href
    : new URL(href, window.location.href).pathname
  return pathname === '/' ? '' : pathname.replace(/\/$/, '')
}

// Subdirectory prefix for installs not at the domain root (e.g. '/subdir').
// Only derived at runtime when no explicit build-time API URL is configured.
const basePath = import.meta.env.VITE_API_BASE_URL ? '' : getAppBasePath()

const apiClient: AxiosInstance = axios.create({
  baseURL: apiBase,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request interceptor to add auth token
apiClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const authStore = useAuthStore()
    
    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }

    // Prepend subdirectory prefix for non-root installs so that
    // e.g. /api/v1/auth/login becomes /subdir/api/v1/auth/login
    if (basePath && config.url?.startsWith('/')) {
      config.url = basePath + config.url
    }
    
    return config
  },
  (error: AxiosError) => {
    return Promise.reject(error)
  }
)

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const authStore = useAuthStore()

    // Handle 401 Unauthorized
    if (error.response?.status === 401) {
      authStore.logout()
      router.push({ name: 'Login' })
    }

    // Handle 403 Forbidden
    if (error.response?.status === 403) {
      // Show error notification
      console.error('Zugriff verweigert')
    }

    // Handle 500 Server Error
    if (error.response?.status === 500) {
      console.error('Serverfehler')
    }

    return Promise.reject(error)
  }
)

export default apiClient
