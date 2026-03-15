import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Toast {
  id: number
  type: 'success' | 'error' | 'warning' | 'info'
  title: string
  message?: string
  duration?: number
}

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])
  let nextId = 1

  function addToast(toast: Omit<Toast, 'id'>) {
    const id = nextId++
    const duration = toast.duration ?? 5000
    
    toasts.value.push({
      id,
      ...toast,
      duration
    })

    if (duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, duration)
    }
  }

  function removeToast(id: number) {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  function success(title: string, message?: string, duration?: number) {
    addToast({ type: 'success', title, message, duration })
  }

  function error(title: string, message?: string, duration?: number) {
    addToast({ type: 'error', title, message, duration })
  }

  function warning(title: string, message?: string, duration?: number) {
    addToast({ type: 'warning', title, message, duration })
  }

  function info(title: string, message?: string, duration?: number) {
    addToast({ type: 'info', title, message, duration })
  }

  function clear() {
    toasts.value = []
  }

  return {
    toasts,
    addToast,
    removeToast,
    success,
    error,
    warning,
    info,
    clear
  }
})
