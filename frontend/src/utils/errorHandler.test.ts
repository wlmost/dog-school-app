import { createPinia, setActivePinia } from 'pinia'
import { describe, it, expect, beforeEach } from 'vitest'
import { handleApiError } from '@/utils/errorHandler'
import { useToastStore } from '@/stores/toast'

// Baut einen axios-ähnlichen Fehler nach, wie ihn `apiClient` bei einer
// fehlgeschlagenen Response wirft (siehe u.a. InvoicesView.test.ts,
// Test "reicht die Backend-Fehlermeldung ... unverändert an handleApiError weiter").
function makeAxiosError(status: number, data: Record<string, unknown>) {
  return Object.assign(new Error(`Request failed with status code ${status}`), {
    response: { status, data },
  })
}

describe('handleApiError', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  describe('Server-Fehler (status >= 500)', () => {
    it('zeigt die vom Backend gelieferte Nachricht an, wenn vorhanden (z.B. 502-Fallback-Hinweis beim Rechnungsversand)', () => {
      const backendMessage =
        'Die Rechnung konnte nicht per E-Mail versendet werden. Bitte laden Sie das PDF herunter und versenden Sie es manuell.'
      const error = makeAxiosError(502, { message: backendMessage })

      handleApiError(error)

      const toast = useToastStore()
      expect(toast.toasts).toHaveLength(1)
      expect(toast.toasts[0]).toMatchObject({ type: 'error', title: 'Server-Fehler', message: backendMessage })
    })

    it('fällt auf die generische Server-Fehler-Meldung zurück, wenn das Backend keine Nachricht liefert', () => {
      const error = makeAxiosError(500, {})

      handleApiError(error)

      const toast = useToastStore()
      expect(toast.toasts).toHaveLength(1)
      expect(toast.toasts[0]).toMatchObject({
        type: 'error',
        title: 'Server-Fehler',
        message: 'Ein interner Fehler ist aufgetreten. Bitte versuchen Sie es später erneut',
      })
    })
  })

  describe('Validierungsfehler (422)', () => {
    it('zeigt den ersten Feldfehler an', () => {
      const error = makeAxiosError(422, { errors: { email: ['Die E-Mail-Adresse ist ungültig.'] } })

      handleApiError(error)

      const toast = useToastStore()
      expect(toast.toasts[0]).toMatchObject({ title: 'Validierungsfehler', message: 'Die E-Mail-Adresse ist ungültig.' })
    })
  })

  describe('Unbekannter Fehler', () => {
    it('zeigt die Fallback-Nachricht, wenn kein Error-Objekt übergeben wird', () => {
      handleApiError(null, 'Etwas ist schiefgelaufen')

      const toast = useToastStore()
      expect(toast.toasts[0]).toMatchObject({ title: 'Fehler', message: 'Etwas ist schiefgelaufen' })
    })
  })
})
