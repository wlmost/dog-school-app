import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import CourseFormModal from '@/components/CourseFormModal.vue'
import apiClient from '@/api/client'
import { handleApiError } from '@/utils/errorHandler'

vi.mock('@/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
  },
}))

vi.mock('@/utils/errorHandler', () => ({
  handleApiError: vi.fn(),
  showSuccess: vi.fn(),
  showWarning: vi.fn(),
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

function mountModal(props: Record<string, unknown> = {}): VueWrapper<any> {
  return mount(CourseFormModal, {
    props: {
      isOpen: true,
      course: undefined,
      ...props,
    },
    global: {
      stubs: headlessUiStubs,
    },
  })
}

function trainerSelect(wrapper: VueWrapper<any>) {
  return wrapper.findAll('select')[0]!
}

describe('CourseFormModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  // ------------------------------------------------------------------ //
  // Laden der Trainerliste über den neuen Options-Endpoint               //
  // ------------------------------------------------------------------ //
  describe('Laden der Trainerliste', () => {
    it('ruft GET /api/v1/trainers/options auf (nicht mehr /api/v1/trainers)', async () => {
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [] } })

      mountModal()
      await flushPromises()

      expect(apiClient.get).toHaveBeenCalledWith('/api/v1/trainers/options')
      expect(apiClient.get).not.toHaveBeenCalledWith('/api/v1/trainers')
    })

    it('befüllt die Trainer-Select-Box bei Erfolg mit den geladenen Optionen', async () => {
      vi.mocked(apiClient.get).mockResolvedValue({
        data: {
          data: [
            { id: 1, firstName: 'Anna', lastName: 'Trainer', fullName: 'Anna Trainer' },
            { id: 2, firstName: 'Bert', lastName: 'Coach', fullName: 'Bert Coach' },
          ],
        },
      })

      const wrapper = mountModal()
      await flushPromises()

      const options = trainerSelect(wrapper).findAll('option')
      // erste Option ist der Platzhalter "Trainer auswählen..."
      expect(options).toHaveLength(3)
      expect(options[1]!.text()).toBe('Anna Trainer')
      expect(options[2]!.text()).toBe('Bert Coach')
    })

    it('zeigt den Namen aus firstName/lastName an, wenn fullName fehlt (kein E-Mail-Fallback mehr)', async () => {
      vi.mocked(apiClient.get).mockResolvedValue({
        data: {
          data: [{ id: 3, firstName: 'Clara', lastName: 'Mueller', fullName: null }],
        },
      })

      const wrapper = mountModal()
      await flushPromises()

      const options = trainerSelect(wrapper).findAll('option')
      expect(options[1]!.text()).toBe('Clara Mueller')
      expect(options[1]!.text()).not.toContain('undefined')
    })

    it('ruft handleApiError mit einer verständlichen Meldung auf, wenn der Request fehlschlägt (z.B. 403)', async () => {
      vi.mocked(apiClient.get).mockRejectedValue({
        response: { status: 403, data: {} },
      })

      mountModal()
      await flushPromises()

      expect(vi.mocked(handleApiError)).toHaveBeenCalledWith(
        expect.objectContaining({ response: expect.objectContaining({ status: 403 }) }),
        'Fehler beim Laden der Trainerliste',
      )
    })

    it('lässt die Trainer-Select-Box bei einem fehlschlagenden Request leer, statt den Fehler nur zu verschlucken', async () => {
      vi.mocked(apiClient.get).mockRejectedValue(new Error('Network Error'))

      const wrapper = mountModal()
      await flushPromises()

      const options = trainerSelect(wrapper).findAll('option')
      expect(options).toHaveLength(1) // nur der Platzhalter
      expect(vi.mocked(handleApiError)).toHaveBeenCalledTimes(1)
    })

    it('zeigt nur den Platzhalter an und ruft handleApiError nicht auf, wenn keine Trainer im System vorhanden sind', async () => {
      vi.mocked(apiClient.get).mockResolvedValue({ data: { data: [] } })

      const wrapper = mountModal()
      await flushPromises()

      const options = trainerSelect(wrapper).findAll('option')
      expect(options).toHaveLength(1)
      expect(options[0]!.text()).toBe('Trainer auswählen...')
      expect(vi.mocked(handleApiError)).not.toHaveBeenCalled()
    })
  })
})
