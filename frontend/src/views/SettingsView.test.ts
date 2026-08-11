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

  // ------------------------------------------------------------------ //
  // Bankverbindung — neue Formularfelder (T02)                          //
  // ------------------------------------------------------------------ //
  describe('Bankverbindung-Formularfelder', () => {
    it('zeigt alle fünf bankdaten-felder beschriftet im stammdaten-formular an', async () => {
      vi.mocked(settingsApi.getSettings).mockResolvedValue(mockSettingsResponse)

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.find('label[for="company_bank_account_holder"]').text().trim()).toBe('Kontoinhaber')
      expect(wrapper.find('#company_bank_account_holder').exists()).toBe(true)

      expect(wrapper.find('label[for="company_bank_name"]').text().trim()).toBe('Bankname')
      expect(wrapper.find('#company_bank_name').exists()).toBe(true)

      expect(wrapper.find('label[for="company_bank_iban"]').text().trim()).toBe('IBAN')
      expect(wrapper.find('#company_bank_iban').exists()).toBe(true)

      expect(wrapper.find('label[for="company_bank_bic"]').text().trim()).toBe('BIC')
      expect(wrapper.find('#company_bank_bic').exists()).toBe(true)

      expect(wrapper.find('label[for="company_payment_term_weeks"]').text().trim()).toBe('Zahlungsziel (Wochen)')
      expect(wrapper.find('#company_payment_term_weeks').exists()).toBe(true)
    })

    it('verbindet die eingabefelder über v-model mit formData', async () => {
      vi.mocked(settingsApi.getSettings).mockResolvedValue(mockSettingsResponse)

      const wrapper = mountView()
      await flushPromises()

      await wrapper.find('#company_bank_account_holder').setValue('Hundeschule Wuffwuff')
      await wrapper.find('#company_bank_iban').setValue('DE89370400440532013000')
      await wrapper.find('#company_payment_term_weeks').setValue(6)

      expect((wrapper.find('#company_bank_account_holder').element as HTMLInputElement).value).toBe(
        'Hundeschule Wuffwuff',
      )
      expect((wrapper.find('#company_bank_iban').element as HTMLInputElement).value).toBe(
        'DE89370400440532013000',
      )
      expect((wrapper.find('#company_payment_term_weeks').element as HTMLInputElement).value).toBe('6')
    })

    it('zeigt vom backend gelieferte bankdaten-werte nach dem laden im formular an', async () => {
      vi.mocked(settingsApi.getSettings).mockResolvedValue({
        data: {
          company: [
            {
              key: 'company_bank_account_holder',
              value: 'Hundeschule Beispiel',
              type: 'string',
              description: null,
              group: 'company',
            },
            { key: 'company_bank_name', value: 'Musterbank', type: 'string', description: null, group: 'company' },
            {
              key: 'company_bank_iban',
              value: 'DE89370400440532013000',
              type: 'string',
              description: null,
              group: 'company',
            },
            { key: 'company_bank_bic', value: 'COBADEFFXXX', type: 'string', description: null, group: 'company' },
            {
              key: 'company_payment_term_weeks',
              value: 4,
              type: 'integer',
              description: null,
              group: 'company',
            },
          ],
          email: [],
          general: [],
        },
      })

      const wrapper = mountView()
      await flushPromises()

      expect((wrapper.find('#company_bank_account_holder').element as HTMLInputElement).value).toBe(
        'Hundeschule Beispiel',
      )
      expect((wrapper.find('#company_bank_name').element as HTMLInputElement).value).toBe('Musterbank')
      expect((wrapper.find('#company_bank_iban').element as HTMLInputElement).value).toBe('DE89370400440532013000')
      expect((wrapper.find('#company_bank_bic').element as HTMLInputElement).value).toBe('COBADEFFXXX')
      expect((wrapper.find('#company_payment_term_weeks').element as HTMLInputElement).value).toBe('4')
    })

    it('behält den formData-default wenn ein bankdaten-key noch nicht in der datenbank existiert', async () => {
      // mockSettingsResponse liefert eine leere company-Gruppe — simuliert den
      // Zustand vor einem SettingsSeeder-Lauf bzw. auf einer Bestandsinstallation.
      vi.mocked(settingsApi.getSettings).mockResolvedValue(mockSettingsResponse)

      const wrapper = mountView()
      await flushPromises()

      expect((wrapper.find('#company_bank_account_holder').element as HTMLInputElement).value).toBe('')
      expect((wrapper.find('#company_bank_name').element as HTMLInputElement).value).toBe('')
      expect((wrapper.find('#company_bank_iban').element as HTMLInputElement).value).toBe('')
      expect((wrapper.find('#company_bank_bic').element as HTMLInputElement).value).toBe('')
      expect((wrapper.find('#company_payment_term_weeks').element as HTMLInputElement).value).toBe('2')
    })

    it('sendet alle fünf bankdaten-felder beim speichern an die settings-api', async () => {
      vi.mocked(settingsApi.getSettings).mockResolvedValue(mockSettingsResponse)
      vi.mocked(settingsApi.updateSettings).mockResolvedValue({ data: {}, message: 'Gespeichert.' } as any)

      const wrapper = mountView()
      await flushPromises()

      await wrapper.find('#company_bank_account_holder').setValue('Hundeschule Wuffwuff')
      await wrapper.find('#company_bank_name').setValue('Sparkasse Musterstadt')
      await wrapper.find('#company_bank_iban').setValue('DE12500105170648489890')
      await wrapper.find('#company_bank_bic').setValue('INGDDEFFXXX')
      await wrapper.find('#company_payment_term_weeks').setValue(6)

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(settingsApi.updateSettings).toHaveBeenCalledWith(
        expect.objectContaining({
          company_bank_account_holder: 'Hundeschule Wuffwuff',
          company_bank_name: 'Sparkasse Musterstadt',
          company_bank_iban: 'DE12500105170648489890',
          company_bank_bic: 'INGDDEFFXXX',
          company_payment_term_weeks: 6,
        }),
      )
    })
  })
})
