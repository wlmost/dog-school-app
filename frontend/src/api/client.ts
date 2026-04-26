import axios from 'axios'
import type { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

// In production the API lives on the same origin (no CORS). Use window.location.origin
// as fallback so the built package works on any domain without reconfiguration.
const apiBase = import.meta.env.VITE_API_BASE_URL
  || (typeof window !== 'undefined' ? window.location.origin : 'http://localhost:8081')

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
