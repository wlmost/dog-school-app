import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import CourseDetailView from '@/views/CourseDetailView.vue'
import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'

vi.mock('vue-router', () => ({
  useRoute: vi.fn(() => ({ params: { id: '1' } })),
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@/utils/errorHandler', () => ({
  handleApiError: vi.fn(),
  showSuccess: vi.fn(),
  showWarning: vi.fn(),
}))

vi.mock('axios', () => ({
  default: {
    isAxiosError: vi.fn((err: any) => !!err?.isAxiosError),
  },
}))

// --- Fixtures ---
const mockCourse = {
  id: 1,
  name: 'Agility Grundkurs',
  description: '<p>Beschreibung</p>',
  courseType: 'group',
  level: 'beginner',
  price: null,
  maxParticipants: 10,
  startDate: '2026-06-01',
  endDate: '2026-08-31',
  status: 'active',
  trainer: { id: 5, firstName: 'Anna', lastName: 'Müller' },
  sessions: [
    {
      id: 10,
      sessionDate: '2026-06-01',
      startTime: '10:00:00',
      endTime: '11:00:00',
      location: 'Halle A',
      maxParticipants: 10,
      status: 'scheduled',
    },
  ],
}

const mockBooking = {
  id: 1,
  status: 'confirmed',
  trainingSession: { course: { id: 1 } },
}

// --- Auth-Store-Helfer ---
function mockGuestAuth(): void {
  vi.mocked(useAuthStore).mockReturnValue({
    isAuthenticated: false,
    isTrainer: false,
    isCustomer: false,
  } as any)
}

function mockCustomerAuth(): void {
  vi.mocked(useAuthStore).mockReturnValue({
    isAuthenticated: true,
    isTrainer: false,
    isCustomer: true,
  } as any)
}

function mockTrainerAuth(): void {
  vi.mocked(useAuthStore).mockReturnValue({
    isAuthenticated: true,
    isTrainer: true,
    isCustomer: false,
  } as any)
}

// --- Stubs für Unter-Komponenten ---
const globalStubs = {
  CourseSessionList: {
    template: '<div data-testid="course-session-list" />',
  },
  CourseFormModal: {
    template: '<div data-testid="course-form-modal" />',
  },
  CustomerBookingModal: {
    name: 'CustomerBookingModal',
    props: ['isOpen', 'courseId', 'courseName'],
    emits: ['close', 'booked'],
    template: '<div data-testid="customer-booking-modal" />',
  },
  RouterLink: { template: '<a><slot /></a>' },
}

function mountView() {
  return mount(CourseDetailView, {
    global: {
      stubs: globalStubs,
    },
  })
}

// --- Tests ---
describe('CourseDetailView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // ------------------------------------------------------------------ //
  // Grundrendering                                                        //
  // ------------------------------------------------------------------ //
  describe('Grundrendering', () => {
    it('zeigt den Lade-Spinner während loadCourse läuft', async () => {
      mockGuestAuth()
      // API löst nie auf → loading bleibt true
      vi.mocked(apiClient.get).mockReturnValue(new Promise(() => {}))

      const wrapper = mountView()
      await nextTick()

      expect(wrapper.text()).toContain('Lade Kurs...')
    })

    it('zeigt die 404-Meldung wenn der Kurs nicht gefunden wird', async () => {
      mockGuestAuth()
      const notFoundError = Object.assign(new Error('Not Found'), {
        isAxiosError: true,
        response: { status: 404 },
      })
      vi.mocked(apiClient.get).mockRejectedValue(notFoundError)

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Kurs nicht gefunden')
    })

    it('zeigt den Kursnamen nach erfolgreichem Laden', async () => {
      mockGuestAuth()
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: mockCourse } })

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Agility Grundkurs')
    })
  })

  // ------------------------------------------------------------------ //
  // API-Endpoint-Auswahl                                                  //
  // ------------------------------------------------------------------ //
  describe('API-Endpoint-Auswahl', () => {
    it('verwendet den Trainer-API-Endpunkt wenn isTrainerOrAdmin true ist', async () => {
      mockTrainerAuth()
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: mockCourse } })

      mountView()
      await flushPromises()

      expect(vi.mocked(apiClient.get)).toHaveBeenCalledWith('/api/v1/courses/1')
    })

    it('verwendet den öffentlichen API-Endpunkt wenn isTrainerOrAdmin false ist', async () => {
      mockGuestAuth()
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: mockCourse } })

      mountView()
      await flushPromises()

      expect(vi.mocked(apiClient.get)).toHaveBeenCalledWith('/api/v1/public/courses/1')
    })
  })

  // ------------------------------------------------------------------ //
  // CTA-Block (Kern der T03-Änderung)                                    //
  // ------------------------------------------------------------------ //
  describe('CTA-Block', () => {
    it('zeigt dem Gast Kontakt- und Login-Links aber keinen Buchen-Button', async () => {
      mockGuestAuth()
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: mockCourse } })

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Kontakt aufnehmen')
      expect(wrapper.text()).toContain('Anmelden')
      const buchBtn = wrapper.findAll('button').find((b) => b.text().includes('Buchen'))
      expect(buchBtn).toBeUndefined()
    })

    it('zeigt dem nicht-gebuchten Kunden den Buchen-Button aber kein Bereits-gebucht-Badge', async () => {
      mockCustomerAuth()
      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({ data: { data: mockCourse } }) // loadCourse
        .mockResolvedValueOnce({ data: { data: [] } })          // loadBookingStatus

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.find('button[type="button"]').text()).toContain('Buchen')
      expect(wrapper.text()).not.toContain('Bereits gebucht')
    })

    it('zeigt dem gebuchten Kunden das Bereits-gebucht-Badge ohne Buchen-Button', async () => {
      mockCustomerAuth()
      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({ data: { data: mockCourse } })    // loadCourse
        .mockResolvedValueOnce({ data: { data: [mockBooking] } }) // loadBookingStatus

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Bereits gebucht')
      const buchBtn = wrapper.findAll('button').find((b) => b.text().includes('Buchen'))
      expect(buchBtn).toBeUndefined()
    })

    it('zeigt Trainern weder Buchen-Button noch Kontakt-Links noch Bereits-gebucht-Badge', async () => {
      mockTrainerAuth()
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: mockCourse } })

      const wrapper = mountView()
      await flushPromises()

      const buchBtn = wrapper.findAll('button').find((b) => b.text() === 'Buchen')
      expect(buchBtn).toBeUndefined()
      expect(wrapper.text()).not.toContain('Bereits gebucht')
      expect(wrapper.text()).not.toContain('Kontakt aufnehmen')
    })
  })

  // ------------------------------------------------------------------ //
  // loadBookingStatus                                                     //
  // ------------------------------------------------------------------ //
  describe('loadBookingStatus', () => {
    it('wird nicht aufgerufen wenn der Nutzer kein Kunde ist', async () => {
      mockGuestAuth()
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: mockCourse } })

      mountView()
      await flushPromises()

      // Nur der loadCourse-Call, kein /api/v1/bookings-Call
      expect(vi.mocked(apiClient.get)).toHaveBeenCalledTimes(1)
      expect(vi.mocked(apiClient.get)).not.toHaveBeenCalledWith('/api/v1/bookings')
    })

    it('setzt alreadyBooked auf true wenn ein bestätigtes Booking für diesen Kurs vorliegt', async () => {
      mockCustomerAuth()
      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({ data: { data: mockCourse } })
        .mockResolvedValueOnce({ data: { data: [mockBooking] } })

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Bereits gebucht')
    })

    it('ruft console.warn auf und wirft keine Exception wenn der API-Call fehlschlägt', async () => {
      mockCustomerAuth()
      const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({ data: { data: mockCourse } })
        .mockRejectedValueOnce(new Error('Netzwerkfehler'))

      mountView()
      await flushPromises()

      expect(warnSpy).toHaveBeenCalled()
      warnSpy.mockRestore()
    })
  })

  // ------------------------------------------------------------------ //
  // Modal-Interaktion                                                     //
  // ------------------------------------------------------------------ //
  describe('Modal-Interaktion', () => {
    it('öffnet das CustomerBookingModal beim Klick auf den Buchen-Button', async () => {
      mockCustomerAuth()
      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({ data: { data: mockCourse } })
        .mockResolvedValueOnce({ data: { data: [] } })

      const wrapper = mountView()
      await flushPromises()

      const modal = wrapper.findComponent({ name: 'CustomerBookingModal' })
      expect(modal.props('isOpen')).toBe(false)

      await wrapper.find('button[type="button"]').trigger('click')
      await nextTick()

      expect(modal.props('isOpen')).toBe(true)
    })

    it('schließt das Modal und ruft loadBookingStatus nach dem booked-Event erneut auf', async () => {
      mockCustomerAuth()
      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({ data: { data: mockCourse } })     // loadCourse
        .mockResolvedValueOnce({ data: { data: [] } })              // loadBookingStatus initial
        .mockResolvedValueOnce({ data: { data: [mockBooking] } })  // loadBookingStatus nach booked

      const wrapper = mountView()
      await flushPromises()

      // Modal öffnen
      await wrapper.find('button[type="button"]').trigger('click')
      await nextTick()

      const modal = wrapper.findComponent({ name: 'CustomerBookingModal' })
      expect(modal.props('isOpen')).toBe(true)

      // booked-Event abfeuern (simuliert erfolgreiche Buchung im Modal)
      await modal.vm.$emit('booked')
      await flushPromises()

      // Modal muss geschlossen sein
      expect(modal.props('isOpen')).toBe(false)
      // loadBookingStatus wurde erneut aufgerufen → alreadyBooked = true
      expect(wrapper.text()).toContain('Bereits gebucht')
    })
  })
})
