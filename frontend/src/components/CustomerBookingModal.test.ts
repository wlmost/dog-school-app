import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import CustomerBookingModal from '@/components/CustomerBookingModal.vue'
import apiClient from '@/api/client'
import { showSuccess, showWarning } from '@/utils/errorHandler'

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
const mockRun = {
  id: 10,
  startDate: '2026-05-01',
  endDate: '2026-06-30',
  status: 'active',
  sessions: [mockSession1, mockSession2],
}

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

/**
 * Standard-Mocks (Legacy-Pfad):
 * 1 Session (scheduled), customerId 42, 1 Hund (Rex), keine Runs → Legacy-Pfad
 */
function setupDefaultMocks(): void {
  vi.mocked(apiClient.get).mockImplementation((url: string) => {
    if (url.includes('/sessions'))
      return Promise.resolve({ data: { data: [mockSession1] } })
    if (url.includes('/profile'))
      return Promise.resolve({ data: { data: { id: 42 } } })
    if (url.includes('/dogs'))
      return Promise.resolve({ data: { data: [mockDog] } })
    if (url.includes('/runs'))
      return Promise.resolve({ data: { data: [] } })
    return Promise.reject(new Error(`Unerwartete URL: ${url}`))
  })
}

/**
 * CourseRun-Mocks: 1 aktiver Run mit 2 Sessions, customerId 42, 1 Hund (Rex)
 */
function setupRunMocks(runs = [mockRun]): void {
  vi.mocked(apiClient.get).mockImplementation((url: string) => {
    if (url.includes('/sessions'))
      return Promise.resolve({ data: { data: [] } })
    if (url.includes('/profile'))
      return Promise.resolve({ data: { data: { id: 42 } } })
    if (url.includes('/dogs'))
      return Promise.resolve({ data: { data: [mockDog] } })
    if (url.includes('/runs'))
      return Promise.resolve({ data: { data: runs } })
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
        if (url.includes('/runs')) return Promise.resolve({ data: { data: [] } })
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
  // Session-Anzeige (Legacy-Pfad, keine Runs)                            //
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
        if (url.includes('/runs'))
          return Promise.resolve({ data: { data: [] } })
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
        if (url.includes('/runs'))
          return Promise.resolve({ data: { data: [] } })
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
  // Submit-Button (Legacy-Pfad)                                          //
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
        if (url.includes('/runs'))
          return Promise.resolve({ data: { data: [] } })
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
        if (url.includes('/runs'))
          return Promise.resolve({ data: { data: [] } })
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
  // Emits (Legacy-Pfad)                                                  //
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

  // ------------------------------------------------------------------ //
  // CourseRun-Pfad                                                        //
  // ------------------------------------------------------------------ //
  describe('CourseRun-Pfad', () => {
    it('zeigt das Run-Dropdown wenn aktive Runs vorhanden sind', async () => {
      setupRunMocks()

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      expect(wrapper.text()).toContain('Kursdurchlauf')
      // Run-Dropdown enthält den formatierten Label
      expect(wrapper.text()).toContain('01.05.2026 – 30.06.2026')
    })

    it('selektiert automatisch den einzigen Run und zeigt dessen Termine an', async () => {
      setupRunMocks()

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      // Der einzige Run wird auto-selektiert → Termine werden angezeigt
      expect(wrapper.text()).toContain('Enthaltene Termine')
      expect(wrapper.text()).toContain('20.05.2026')
      expect(wrapper.text()).toContain('27.05.2026')
    })

    it('zeigt keine Checkboxen im CourseRun-Pfad', async () => {
      setupRunMocks()

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
    })

    it('formatiert Runs ohne endDate als "ab DD.MM.YYYY"', async () => {
      const runWithoutEnd = { ...mockRun, endDate: null }
      setupRunMocks([runWithoutEnd])

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      expect(wrapper.text()).toContain('ab 01.05.2026')
    })

    it('ignoriert inaktive Runs und fällt auf Legacy-Pfad zurück wenn keine aktiven Runs übrig bleiben', async () => {
      const inactiveRun = { ...mockRun, status: 'draft' }
      vi.mocked(apiClient.get).mockImplementation((url: string) => {
        if (url.includes('/sessions'))
          return Promise.resolve({ data: { data: [mockSession1] } })
        if (url.includes('/profile'))
          return Promise.resolve({ data: { data: { id: 42 } } })
        if (url.includes('/dogs'))
          return Promise.resolve({ data: { data: [mockDog] } })
        if (url.includes('/runs'))
          return Promise.resolve({ data: { data: [inactiveRun] } })
        return Promise.reject(new Error(`Unerwartete URL: ${url}`))
      })

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      // Kein Run-Dropdown → Legacy-Pfad mit Sessions
      expect(wrapper.text()).not.toContain('Kursdurchlauf')
      expect(wrapper.text()).toContain('20.05.2026')
    })

    it('deaktiviert den Buchen-Button wenn kein Run ausgewählt ist (mehrere Runs)', async () => {
      const run2 = { ...mockRun, id: 11, startDate: '2026-07-01', endDate: '2026-08-31' }
      setupRunMocks([mockRun, run2])

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      // Zwei Runs → kein Auto-Select → Button disabled
      const submitBtn = wrapper.find('button[type="submit"]')
      expect(submitBtn.attributes('disabled')).toBeDefined()
    })

    it('ruft POST /api/v1/course-runs/{id}/book nach erfolgreicher CourseRun-Buchung auf', async () => {
      setupRunMocks()
      vi.mocked(apiClient.post).mockResolvedValue({ data: { skipped: [] } })

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(vi.mocked(apiClient.post)).toHaveBeenCalledWith(
        '/api/v1/course-runs/10/book',
        expect.objectContaining({
          customerId: 42,
          dogId: mockDog.id,
        }),
      )
      expect(wrapper.emitted('booked')).toBeTruthy()
      expect(wrapper.emitted('close')).toBeTruthy()
      expect(vi.mocked(showSuccess)).toHaveBeenCalled()
    })

    it('zeigt eine Warnung wenn Buchung übersprungene Termine enthält', async () => {
      setupRunMocks()
      vi.mocked(apiClient.post).mockResolvedValue({
        data: { skipped: ['20.05.2026 bereits gebucht'] },
      })

      const wrapper = mountModal()
      await wrapper.setProps({ isOpen: true })
      await flushPromises()

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(vi.mocked(showWarning)).toHaveBeenCalledWith(
        'Einige Termine übersprungen',
        '20.05.2026 bereits gebucht',
      )
    })
  })
})
