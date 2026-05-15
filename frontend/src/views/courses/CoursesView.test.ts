import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import CoursesView from '@/views/courses/CoursesView.vue'
import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'

vi.mock('vue-router', () => ({
  useRoute: vi.fn(() => ({ params: {} })),
  RouterLink: { template: '<a><slot /></a>' },
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/utils/errorHandler', () => ({
  handleApiError: vi.fn(),
  showSuccess: vi.fn(),
  showWarning: vi.fn(),
}))

// --- Fixtures ---
const mockCourse = {
  id: 1,
  name: 'Agility Grundkurs',
  description: '<p>Beschreibung</p>',
  courseType: 'group',
  level: 'beginner',
  maxParticipants: 10,
  currentParticipants: 3,
  startDate: '2026-06-01',
  endDate: '2026-08-31',
  status: 'active',
  cancellationDeadlineHours: 24,
}

const mockBooking = {
  id: 1,
  status: 'confirmed',
  trainingSession: { course: { id: 1 } },
}

// --- Auth-Store-Helfer ---
function mockTrainerAuth(): void {
  vi.mocked(useAuthStore).mockReturnValue({
    isAuthenticated: true,
    isTrainer: true,
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

// --- Stubs für Unter-Komponenten ---
const globalStubs = {
  CourseFormModal: {
    template: '<div data-testid="course-form-modal" />',
  },
  CustomerBookingModal: {
    name: 'CustomerBookingModal',
    props: ['isOpen', 'courseId', 'courseName'],
    emits: ['close', 'booked'],
    template: '<div data-testid="customer-booking-modal" />',
  },
}

function mountView() {
  return mount(CoursesView, {
    global: {
      stubs: globalStubs,
    },
  })
}

// --- Tests ---
describe('CoursesView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // ------------------------------------------------------------------ //
  // Trainer-Ansicht                                                       //
  // ------------------------------------------------------------------ //
  describe('Trainer-Ansicht', () => {
    it('zeigt den "Neuer Kurs"-Button wenn der Nutzer Trainer ist', async () => {
      mockTrainerAuth()
      vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: [mockCourse] } })

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Neuer Kurs')
    })

    it('zeigt die "Bearbeiten"- und "Löschen"-Buttons für Kurse wenn der Nutzer Trainer ist', async () => {
      mockTrainerAuth()
      vi.mocked(apiClient.get).mockResolvedValueOnce({ data: { data: [mockCourse] } })

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Bearbeiten')
      expect(wrapper.text()).toContain('Löschen')
    })
  })

  // ------------------------------------------------------------------ //
  // Kunden-Ansicht                                                        //
  // ------------------------------------------------------------------ //
  describe('Kunden-Ansicht', () => {
    it('versteckt den "Neuer Kurs"-Button wenn der Nutzer Kunde ist', async () => {
      mockCustomerAuth()
      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({ data: { data: [mockCourse] } }) // loadCourses
        .mockResolvedValueOnce({ data: { data: [] } })           // loadOwnBookings

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).not.toContain('Neuer Kurs')
    })

    it('zeigt den "Buchen"-Button wenn der Kurs noch nicht gebucht ist', async () => {
      mockCustomerAuth()
      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({ data: { data: [mockCourse] } }) // loadCourses
        .mockResolvedValueOnce({ data: { data: [] } })           // loadOwnBookings: keine Buchungen

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Buchen')
      expect(wrapper.text()).not.toContain('Bereits gebucht')
    })

    it('zeigt das "Bereits gebucht"-Badge und versteckt den "Buchen"-Button wenn der Kurs bereits gebucht ist', async () => {
      mockCustomerAuth()
      vi.mocked(apiClient.get)
        .mockResolvedValueOnce({ data: { data: [mockCourse] } })  // loadCourses
        .mockResolvedValueOnce({ data: { data: [mockBooking] } }) // loadOwnBookings: eine Buchung

      const wrapper = mountView()
      await flushPromises()

      expect(wrapper.text()).toContain('Bereits gebucht')
      expect(wrapper.text()).not.toContain('Buchen')
    })
  })
})
