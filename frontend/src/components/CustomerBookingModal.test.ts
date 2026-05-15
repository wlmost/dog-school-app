import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import CustomerBookingModal from '@/components/CustomerBookingModal.vue'
import apiClient from '@/api/client'
import { showSuccess } from '@/utils/errorHandler'

vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

vi.mock('@/utils/errorHandler', () => ({
  handleApiError: vi.fn(),
  showSuccess: vi.fn(),
  showWarning: vi.fn(),
}))

// --- Fixtures ---
const mockSession1 = {
  id: 1,
  sessionDate: '2026-05-20',
  startTime: '10:00:00',
  endTime: '11:00:00',
  location: 'Halle A',
  status: 'scheduled',
}
const mockSession2 = {
  id: 2,
  sessionDate: '2026-05-27',
  startTime: '10:00:00',
  endTime: '11:00:00',
  location: 'Halle A',
  status: 'scheduled',
}
const mockDog = { id: 1, name: 'Rex' }

// HeadlessUI-Stubs: TransitionRoot respektiert show-Prop; restliche Komponenten
// rendern den Slot direkt, ohne Transitions-Overhead im Test.
const headlessUiStubs = {
  TransitionRoot: {
    props: ['show'],
    template: '<div v-if="show"><slot /></div>',
  },
  TransitionChild: {
    template: '<div><slot /></div>',
  },
  Dialog: {
    template: '<div><slot /></div>',
  },
  DialogPanel: {
    template: '<div><slot /></div>',
  },
  DialogTitle: {
    template: '<div><slot /></div>',
  },
}

function mountModal(props: Record<string, unknown> = {}) {
  return mount(CustomerBookingModal, {
    props: {
      isOpen: false,
      courseId: 1,
      courseName: 'Grundkurs',
      ...props,
    },
    global: {
      stubs: headlessUiStubs,
    },
  })
}

/** Standard-Mocks: 1 Session (scheduled), customerId 42, 1 Hund (Rex) */
function setupDefaultMocks(): void {
  vi.mocked(apiClient.get).mockImplementation((url: string) => {
    if (url.includes('/sessions'))
      return Promise.resolve({ data: { data: [mockSession1] } })
    if (url.includes('/profile'))
      return Promise.resolve({ data: { data: { id: 42 } } })
    if (url.includes('/dogs'))
      return Promise.resolve({ data: { data: [mockDog] } })
    return Promise.reject(new Error(`Unerwartete URL: ${url}`))
  })
}

describe('CustomerBookingModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // ------------------------------------------------------------------ //
  // Rendering                                                             //
  // ------------------------------------------------------------------ //
  describe('Rendering', () => {
    it('rendert den Dialog-Inhalt nicht wenn isOpen false ist', () => {
      const wrapper = mountModal({ isOpen: false })

      expect(wrapper.find('form').exists()).toBe(false)
      expect(wrapper.text()).toBe('')
    })

    it('zeigt den Lade-Indikator während der API-Calls laufen', async () => {
      // API löst nie auf → loading bleibt true
      vi.mocked(apiClient.get).mockReturnValue(new Promise(() => {}))

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await nextTick()

      expect(wrapper.text()).toContain('Lade Daten...')
    })

    it('zeigt eine Fehlermeldung wenn das Laden der Daten fehlschlägt', async () => {
      vi.mocked(apiClient.get).mockRejectedValue(new Error('Netzwerkfehler'))

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      expect(wrapper.text()).toContain('Fehler beim Laden der Daten.')
    })

    it('zeigt einen Hinweis wenn keine buchbaren Termine vorhanden sind', async () => {
      vi.mocked(apiClient.get).mockImplementation((url: string) => {
        if (url.includes('/sessions')) return Promise.resolve({ data: { data: [] } })
        if (url.includes('/profile')) return Promise.resolve({ data: { data: { id: 42 } } })
        if (url.includes('/dogs')) return Promise.resolve({ data: { data: [mockDog] } })
        return Promise.reject(new Error(`Unerwartete URL: ${url}`))
      })

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      expect(wrapper.text()).toContain('Keine buchbaren Termine verfügbar')
    })

    it('zeigt das Buchungsformular wenn buchbare Termine geladen wurden', async () => {
      setupDefaultMocks()

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      expect(wrapper.find('form').exists()).toBe(true)
    })
  })

  // ------------------------------------------------------------------ //
  // Session-Anzeige                                                      //
  // ------------------------------------------------------------------ //
  describe('Session-Anzeige', () => {
    it('zeigt bei einer Session die Info-Anzeige ohne Checkbox an', async () => {
      setupDefaultMocks()

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
      expect(wrapper.text()).toContain('20.05.2026')
    })

    it('zeigt bei mehreren Sessions Checkboxen an und wählt alle vor', async () => {
      vi.mocked(apiClient.get).mockImplementation((url: string) => {
        if (url.includes('/sessions'))
          return Promise.resolve({ data: { data: [mockSession1, mockSession2] } })
        if (url.includes('/profile'))
          return Promise.resolve({ data: { data: { id: 42 } } })
        if (url.includes('/dogs'))
          return Promise.resolve({ data: { data: [mockDog] } })
        return Promise.reject(new Error(`Unerwartete URL: ${url}`))
      })

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      const checkboxes = wrapper.findAll('input[type="checkbox"]')
      expect(checkboxes).toHaveLength(2)
      checkboxes.forEach((cb) => {
        expect((cb.element as HTMLInputElement).checked).toBe(true)
      })
    })
  })

  // ------------------------------------------------------------------ //
  // Hund-Auswahl                                                         //
  // ------------------------------------------------------------------ //
  describe('Hund-Auswahl', () => {
    it('selektiert automatisch den einzigen vorhandenen Hund', async () => {
      setupDefaultMocks()

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      const select = wrapper.find<HTMLSelectElement>('#dog-select')
      expect(select.element.value).toBe(String(mockDog.id))
    })

    it('zeigt alle eigenen Hunde im Dropdown an', async () => {
      const mockDog2 = { id: 2, name: 'Bella' }
      vi.mocked(apiClient.get).mockImplementation((url: string) => {
        if (url.includes('/sessions'))
          return Promise.resolve({ data: { data: [mockSession1] } })
        if (url.includes('/profile'))
          return Promise.resolve({ data: { data: { id: 42 } } })
        if (url.includes('/dogs'))
          return Promise.resolve({ data: { data: [mockDog, mockDog2] } })
        return Promise.reject(new Error(`Unerwartete URL: ${url}`))
      })

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      // Placeholder-Option + Rex + Bella
      const options = wrapper.findAll('#dog-select option')
      expect(options).toHaveLength(3)
      expect(options[1]?.text()).toContain('Rex')
      expect(options[2]?.text()).toContain('Bella')
    })
  })

  // ------------------------------------------------------------------ //
  // Submit-Button                                                         //
  // ------------------------------------------------------------------ //
  describe('Submit-Button', () => {
    it('deaktiviert den Buchen-Button wenn keine Session ausgewählt ist', async () => {
      vi.mocked(apiClient.get).mockImplementation((url: string) => {
        if (url.includes('/sessions'))
          return Promise.resolve({ data: { data: [mockSession1, mockSession2] } })
        if (url.includes('/profile'))
          return Promise.resolve({ data: { data: { id: 42 } } })
        if (url.includes('/dogs'))
          return Promise.resolve({ data: { data: [mockDog] } })
        return Promise.reject(new Error(`Unerwartete URL: ${url}`))
      })

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      // Alle Checkboxen durch toggle-change abwählen
      const checkboxes = wrapper.findAll('input[type="checkbox"]')
      for (const cb of checkboxes) {
        await cb.trigger('change')
        await nextTick()
      }

      const submitBtn = wrapper.find('button[type="submit"]')
      expect(submitBtn.attributes('disabled')).toBeDefined()
    })

    it('deaktiviert den Buchen-Button wenn kein Hund ausgewählt ist', async () => {
      vi.mocked(apiClient.get).mockImplementation((url: string) => {
        if (url.includes('/sessions'))
          return Promise.resolve({ data: { data: [mockSession1] } })
        if (url.includes('/profile'))
          return Promise.resolve({ data: { data: { id: 42 } } })
        // Zwei Hunde → kein Auto-Select → selectedDogId bleibt null
        if (url.includes('/dogs'))
          return Promise.resolve({ data: { data: [mockDog, { id: 2, name: 'Bella' }] } })
        return Promise.reject(new Error(`Unerwartete URL: ${url}`))
      })

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      const submitBtn = wrapper.find('button[type="submit"]')
      expect(submitBtn.attributes('disabled')).toBeDefined()
    })

    it('aktiviert den Buchen-Button wenn Session und Hund ausgewählt sind', async () => {
      // 1 Session (auto-vorselektiert) + 1 Hund (auto-selektiert) + customerId gesetzt
      setupDefaultMocks()

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      const submitBtn = wrapper.find('button[type="submit"]')
      expect(submitBtn.attributes('disabled')).toBeUndefined()
    })
  })

  // ------------------------------------------------------------------ //
  // Emits                                                                 //
  // ------------------------------------------------------------------ //
  describe('Emits', () => {
    it('emittiert close beim Klick auf den Abbrechen-Button', async () => {
      setupDefaultMocks()

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      await wrapper.find('button[type="button"]').trigger('click')

      expect(wrapper.emitted('close')).toBeTruthy()
      expect(wrapper.emitted('close')).toHaveLength(1)
    })

    it('emittiert booked und close nach erfolgreicher Buchung', async () => {
      setupDefaultMocks()
      vi.mocked(apiClient.post).mockResolvedValue({ data: {} })

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.emitted('booked')).toBeTruthy()
      expect(wrapper.emitted('close')).toBeTruthy()
      expect(vi.mocked(showSuccess)).toHaveBeenCalled()
    })
  })
})
