import type { AxiosError } from 'axios'
import { useToastStore } from '@/stores/toast'

interface ErrorResponse {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Zeigt eine benutzerfreundliche Fehlermeldung basierend auf dem Error-Typ
 */
export function handleApiError(error: unknown, fallbackMessage = 'Ein Fehler ist aufgetreten'): void {
  const toast = useToastStore()

  if (!error) {
    toast.error('Fehler', fallbackMessage)
    return
  }

  // Axios Error
  if (typeof error === 'object' && error !== null && 'response' in error) {
    const axiosError = error as AxiosError<ErrorResponse>
    
    if (axiosError.response) {
      const { status, data } = axiosError.response

      // Validation Errors (422)
      if (status === 422 && data.errors) {
        const firstError = Object.values(data.errors)[0]?.[0]
        toast.error('Validierungsfehler', firstError || data.message || fallbackMessage)
        return
      }

      // Unauthorized (401)
      if (status === 401) {
        toast.error('Nicht autorisiert', 'Bitte melden Sie sich erneut an')
        return
      }

      // Forbidden (403)
      if (status === 403) {
        toast.error('Zugriff verweigert', 'Sie haben keine Berechtigung für diese Aktion')
        return
      }

      // Not Found (404)
      if (status === 404) {
        toast.error('Nicht gefunden', data.message || 'Die angeforderte Ressource wurde nicht gefunden')
        return
      }

      // Server Error (500+)
      if (status >= 500) {
        toast.error('Server-Fehler', 'Ein interner Fehler ist aufgetreten. Bitte versuchen Sie es später erneut')
        return
      }

      // Other errors with message
      if (data.message) {
        toast.error('Fehler', data.message)
        return
      }
    }

    // Network Error
    if (axiosError.message === 'Network Error') {
      toast.error('Netzwerkfehler', 'Bitte überprüfen Sie Ihre Internetverbindung')
      return
    }
  }

  // Standard Error
  if (error instanceof Error) {
    toast.error('Fehler', error.message || fallbackMessage)
    return
  }

  // Unknown error
  toast.error('Fehler', fallbackMessage)
}

/**
 * Zeigt eine Erfolgsmeldung
 */
export function showSuccess(title: string, message?: string): void {
  const toast = useToastStore()
  toast.success(title, message)
}

/**
 * Zeigt eine Warnung
 */
export function showWarning(title: string, message?: string): void {
  const toast = useToastStore()
  toast.warning(title, message)
}

/**
 * Zeigt eine Info-Meldung
 */
export function showInfo(title: string, message?: string): void {
  const toast = useToastStore()
  toast.info(title, message)
}
