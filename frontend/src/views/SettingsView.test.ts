import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import SettingsView from '@/views/SettingsView.vue'
import { settingsApi } from '@/api/settings'

// --- Mock: Settings API ---
vi.mock('@/api/settings', () => ({
  settingsApi: {
    getSettings: vi.fn(),
    updateSettings: vi.fn(),
  },
}))

// --- Mock: Pricing composable (außerhalb des Test-Fokus) ---
vi.mock('@/composables/usePricingItems', () => ({
  usePricingItems: () => ({
    items: [],
    loading: false,
    error: null,
    loadAll: vi.fn().mockResolvedValue(undefined),
    deleteItem: vi.fn().mockResolvedValue(undefined),
    groups: [],
  }),
}))

// --- Stubs für Unter-Komponenten mit eigenen Abhängigkeiten ---
const globalStubs = {
  EmailTemplateEditor: { template: '<div data-testid="email-template-editor" />' },
  PricingItemForm: { template: '<div data-testid="pricing-item-form" />' },
}

/** Minimale gültige API-Antwort für getSettings */
const mockSettingsResponse = {
  data: { company: [], email: [], general: [] },
}

/** Erstellt und mounted SettingsView mit globalen Stubs */
function mountView() {
  return mount(SettingsView, {
    global: { stubs: globalStubs },
  })
}

// --- Tests ---
describe('SettingsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // ------------------------------------------------------------------ //
  // Formular-Sichtbarkeit nach Speicherfehler                            //
  // ------------------------------------------------------------------ //
  describe('Speicherfehler lässt Formular sichtbar', () => {
    it('zeigt das Formular nach einem 422-Fehler beim Speichern weiterhin an', async () => {
      vi.mocked(settingsApi.getSettings).mockResolvedValue(mockSettingsResponse)
      vi.mocked(settingsApi.updateSettings).mockRejectedValue(
        Object.assign(new Error('Unprocessable Entity'), {
          response: { status: 422, data: { message: 'Ungültige Eingabe.' } },
        }),
      )

      const wrapper = mountView()
      await flushPromises() // loadSettings abwarten → Formular sichtbar

      await wrapper.find('form').trigger('submit')
      await flushPromises() // updateSettings schlägt fehl

      expect(wrapper.find('form').exists()).toBe(true)
    })

    it('zeigt die saveError-Meldung unterhalb der Buttons an', async () => {
      vi.mocked(settingsApi.getSettings).mockResolvedValue(mockSettingsResponse)
      vi.mocked(settingsApi.updateSettings).mockRejectedValue(
        Object.assign(new Error('Unprocessable Entity'), {
          response: { status: 422, data: { message: 'Ungültige Eingabe.' } },
        }),
      )

      const wrapper = mountView()
      await flushPromises()

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.text()).toContain('Ungültige Eingabe.')
    })

    it('setzt saveError beim nächsten Speicherversuch zurück', async () => {
      vi.mocked(settingsApi.getSettings).mockResolvedValue(mockSettingsResponse)
      vi.mocked(settingsApi.updateSettings)
        .mockRejectedValueOnce(
          Object.assign(new Error('Unprocessable Entity'), {
            response: { status: 422, data: { message: 'Ungültige Eingabe.' } },
          }),
        )
        .mockResolvedValueOnce({ data: {}, message: 'Gespeichert.' } as any)

      const wrapper = mountView()
      await flushPromises()

      // Erster Speicherversuch → Fehler erscheint
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.text()).toContain('Ungültige Eingabe.')

      // Zweiter Speicherversuch → Fehler wird zurückgesetzt
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.text()).not.toContain('Ungültige Eingabe.')
    })
  })

  // ------------------------------------------------------------------ //
  // Ladefehler blendet Formular aus                                      //
  // ------------------------------------------------------------------ //
  describe('Ladefehler blendet Formular aus', () => {
    it('versteckt das Formular bei einem 500-Fehler in loadSettings', async () => {
      vi.mocked(settingsApi.getSettings).mockRejectedValue(
        Object.assign(new Error('Internal Server Error'), {
          response: { status: 500, data: { message: 'Interner Serverfehler.' } },
        }),
      )

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.find('form').exists()).toBe(false)
    })

    it('zeigt die loadError-Meldung an wenn das Laden fehlschlägt', async () => {
      vi.mocked(settingsApi.getSettings).mockRejectedValue(
        Object.assign(new Error('Internal Server Error'), {
          response: { status: 500, data: { message: 'Interner Serverfehler.' } },
        }),
      )

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Interner Serverfehler.')
    })
  })
})
