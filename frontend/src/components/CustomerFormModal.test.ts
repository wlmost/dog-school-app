import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import CustomerFormModal from '@/components/CustomerFormModal.vue'
import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { handleApiError } from '@/utils/errorHandler'

vi.mock('@/stores/auth', () => ({
  useAuthStore: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@/utils/errorHandler', () => ({
  handleApiError: vi.fn(),
  showSuccess: vi.fn(),
}))

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

// Reduziertes Trainer-Format von GET /api/v1/trainers/options
// (TrainerOptionResource) — bewusst ohne email/phone/etc.
const mockTrainerSelf = { id: 7, firstName: 'Anna', lastName: 'Trainer', fullName: 'Anna Trainer' }
const mockTrainerOther = { id: 9, firstName: 'Ben', lastName: 'Coach', fullName: 'Ben Coach' }

function mockAuth(user: { id: number; role: 'admin' | 'trainer' | 'customer' } | null): void {
  vi.mocked(useAuthStore).mockReturnValue({ user } as any)
}

function mountModal(props: Record<string, unknown> = {}) {
  return mount(CustomerFormModal, {
    props: {
      isOpen: false,
      customer: undefined,
      ...props,
    },
    global: {
      stubs: headlessUiStubs,
    },
  })
}

async function openModal(props: Record<string, unknown> = {}) {
  const wrapper = mountModal({ isOpen: false, ...props })
  await wrapper.setProps({ isOpen: true })
  await flushPromises()
  return wrapper
}

describe('CustomerFormModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // ------------------------------------------------------------------ //
  // Trainerliste laden über den neuen, rollen-offenen Endpoint           //
  // ------------------------------------------------------------------ //
  describe('Trainerliste laden', () => {
    it('ruft GET /api/v1/trainers/options auf (nicht mehr die admin-only Route)', async () => {
      mockAuth({ id: 1, role: 'admin' })
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [mockTrainerSelf, mockTrainerOther] } })

      await openModal()

      expect(vi.mocked(apiClient.get)).toHaveBeenCalledWith('/api/v1/trainers/options')
      expect(vi.mocked(apiClient.get)).not.toHaveBeenCalledWith('/api/v1/trainers')
    })

    it('befüllt die Trainer-Select-Box für die Rolle admin', async () => {
      mockAuth({ id: 1, role: 'admin' })
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [mockTrainerSelf, mockTrainerOther] } })

      const wrapper = await openModal()

      // Placeholder-Option "Kein Trainer zugewiesen" + 2 Trainer
      const options = wrapper.find('select').findAll('option')
      expect(options).toHaveLength(3)
      expect(options[1]?.text()).toBe('Anna Trainer')
      expect(options[2]?.text()).toBe('Ben Coach')
    })

    it('befüllt die Trainer-Select-Box auch für die Rolle trainer (Regressionstest für den Bugfix)', async () => {
      mockAuth({ id: 7, role: 'trainer' })
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [mockTrainerSelf, mockTrainerOther] } })

      const wrapper = await openModal()

      const options = wrapper.find('select').findAll('option')
      expect(options).toHaveLength(3)
    })

    it('zeigt nur den Platzhalter an und ruft handleApiError nicht auf, wenn keine Trainer im System vorhanden sind', async () => {
      mockAuth({ id: 1, role: 'admin' })
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [] } })

      const wrapper = await openModal()

      const options = wrapper.find('select').findAll('option')
      expect(options).toHaveLength(1)
      expect(options[0]?.text()).toBe('Kein Trainer zugewiesen')
      expect(vi.mocked(handleApiError)).not.toHaveBeenCalled()
    })
  })

  // ------------------------------------------------------------------ //
  // Fehlerbehandlung beim Laden der Trainerliste                         //
  // ------------------------------------------------------------------ //
  describe('Fehlerbehandlung beim Laden der Trainerliste', () => {
    it('ruft handleApiError statt nur console.error auf, wenn der Request fehlschlägt (403)', async () => {
      mockAuth({ id: 7, role: 'trainer' })
      const forbiddenError = { response: { status: 403, data: {} } }
      vi.mocked(apiClient.get).mockRejectedValue(forbiddenError)

      await openModal()

      expect(vi.mocked(handleApiError)).toHaveBeenCalledWith(forbiddenError, 'Fehler beim Laden der Trainerliste')
    })

    it('lässt die Trainer-Select-Box bei einem Ladefehler leer, bricht aber das Formular nicht ab', async () => {
      mockAuth({ id: 1, role: 'admin' })
      vi.mocked(apiClient.get).mockRejectedValue(new Error('Netzwerkfehler'))

      const wrapper = await openModal()

      const options = wrapper.find('select').findAll('option')
      expect(options).toHaveLength(1)
      expect(options[0]?.text()).toBe('Kein Trainer zugewiesen')
      expect(wrapper.find('form').exists()).toBe(true)
    })
  })

  // ------------------------------------------------------------------ //
  // Vorauswahl für eingeloggte Trainer                                   //
  // ------------------------------------------------------------------ //
  describe('Vorauswahl für die Rolle trainer', () => {
    it('setzt trainer_id auf die eigene User-ID, sobald die Trainerliste geladen ist', async () => {
      mockAuth({ id: 7, role: 'trainer' })
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [mockTrainerSelf, mockTrainerOther] } })

      const wrapper = await openModal()

      const select = wrapper.find<HTMLSelectElement>('select')
      expect(select.element.value).toBe('7')
    })

    it('lässt die Select-Box trotz Vorauswahl bedienbar (nicht disabled) und erlaubt einen Wechsel', async () => {
      mockAuth({ id: 7, role: 'trainer' })
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [mockTrainerSelf, mockTrainerOther] } })

      const wrapper = await openModal()

      const select = wrapper.find<HTMLSelectElement>('select')
      expect(select.attributes('disabled')).toBeUndefined()

      await select.setValue('9')
      expect(select.element.value).toBe('9')
    })

    it('setzt trainer_id nicht vor, wenn die Rolle admin ist', async () => {
      mockAuth({ id: 1, role: 'admin' })
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [mockTrainerSelf, mockTrainerOther] } })

      const wrapper = await openModal()

      const select = wrapper.find<HTMLSelectElement>('select')
      expect(select.element.selectedIndex).toBe(0)
    })
  })
})
