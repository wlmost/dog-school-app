import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient from '@/api/client'

export interface User {
  id: number
  email: string
  first_name: string
  last_name: string
  role: 'admin' | 'trainer' | 'customer'
  full_name: string
  phone?: string
  email_verified_at?: string
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface LoginResponse {
  user: User
  token: string
}

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isTrainer = computed(() => user.value?.role === 'trainer' || user.value?.role === 'admin')
  const isCustomer = computed(() => user.value?.role === 'customer')

  // Actions
  async function login(credentials: LoginCredentials): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await apiClient.post<LoginResponse>('/api/v1/auth/login', credentials)
      
      user.value = response.data.user
      token.value = response.data.token
      
      // Save token to localStorage
      localStorage.setItem('auth_token', response.data.token)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Anmeldung fehlgeschlagen'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      // Attempt to invalidate token on server
      if (token.value) {
        await apiClient.post('/api/v1/auth/logout')
      }
    } catch (err: any) {
      // Ignore 401 errors during logout - token may already be invalid
      if (err.response?.status !== 401) {
        console.error('Logout error:', err)
      }
    } finally {
      // Always clear local state regardless of API response
      user.value = null
      token.value = null
      localStorage.removeItem('auth_token')
    }
  }

  async function checkAuth(): Promise<void> {
    if (!token.value) {
      return
    }

    try {
      const response = await apiClient.get<{ user: User }>('/api/v1/auth/user')
      user.value = response.data.user
    } catch (err) {
      // Token is invalid, clear it
      token.value = null
      user.value = null
      localStorage.removeItem('auth_token')
    }
  }

  async function register(userData: any): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const response = await apiClient.post<LoginResponse>('/api/v1/auth/register', userData)
      
      user.value = response.data.user
      token.value = response.data.token
      
      localStorage.setItem('auth_token', response.data.token)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Registrierung fehlgeschlagen'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    // State
    user,
    token,
    loading,
    error,
    // Getters
    isAuthenticated,
    isAdmin,
    isTrainer,
    isCustomer,
    // Actions
    login,
    logout,
    checkAuth,
    register
  }
})
