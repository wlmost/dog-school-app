import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import DogFormModal from '@/components/DogFormModal.vue'
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

const mockCreatedDog = { id: 99, name: 'Rex', breed: 'Labrador' }

function mockCustomerAuth(): void {
  // 'customer' role renders the owner as read-only text instead of a
  // dropdown, so tests don't need to mock GET /api/v1/customers.
  vi.mocked(useAuthStore).mockReturnValue({ user: { role: 'customer' } } as any)
}

function mountModal(props: Record<string, unknown> = {}): VueWrapper<any> {
  return mount(DogFormModal, {
    props: {
      isOpen: true,
      dog: undefined,
      ...props,
    },
    global: {
      stubs: headlessUiStubs,
    },
  })
}

async function fillRequiredFields(wrapper: VueWrapper<any>): Promise<void> {
  const textInputs = wrapper.findAll('input[type="text"]')
  await textInputs[0]!.setValue('Rex')
  await textInputs[1]!.setValue('Labrador')
}

async function selectImage(wrapper: VueWrapper<any>): Promise<void> {
  const fileInput = wrapper.find('input[type="file"]')
  const file = new File(['dummy-image-content'], 'photo.png', { type: 'image/png' })
  Object.defineProperty(fileInput.element, 'files', { value: [file] })
  await fileInput.trigger('change')
  await flushPromises()
}

describe('DogFormModal', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockCustomerAuth()
  })

  // ------------------------------------------------------------------ //
  // Bild-Upload-Fehler                                                    //
  // ------------------------------------------------------------------ //
  describe('Bild-Upload schlägt fehl', () => {
    function setupFailingImageUpload(): void {
      vi.mocked(apiClient.post).mockImplementation((url: string) => {
        if (url === '/api/v1/dogs') {
          return Promise.resolve({ data: { data: mockCreatedDog } })
        }
        if (url.includes('/upload-image')) {
          return Promise.reject({ response: { data: { message: 'Datei zu groß' } } })
        }
        return Promise.reject(new Error(`Unerwartete URL: ${url}`))
      })
    }

    it('emittiert weder saved noch close, wenn der Bild-Upload fehlschlägt', async () => {
      setupFailingImageUpload()

      const wrapper = mountModal()
      await fillRequiredFields(wrapper)
      await selectImage(wrapper)

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.emitted('saved')).toBeFalsy()
      expect(wrapper.emitted('close')).toBeFalsy()
    })

    it('zeigt einen dauerhaften Fehlerbanner statt nur eines Toasts', async () => {
      setupFailingImageUpload()

      const wrapper = mountModal()
      await fillRequiredFields(wrapper)
      await selectImage(wrapper)

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.text()).toContain('Profilbild konnte nicht hochgeladen werden')
      expect(vi.mocked(handleApiError)).toHaveBeenCalled()
    })

    it('legt beim Retry keinen zweiten Hund an (kein zweiter POST auf /api/v1/dogs)', async () => {
      setupFailingImageUpload()

      const wrapper = mountModal()
      await fillRequiredFields(wrapper)
      await selectImage(wrapper)

      // Erster Versuch: Stammdaten erfolgreich, Bild-Upload schlägt fehl
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      // Zweiter Versuch (Retry über denselben Speichern-Button)
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      const createCalls = vi
        .mocked(apiClient.post)
        .mock.calls.filter(([url]) => url === '/api/v1/dogs')
      expect(createCalls).toHaveLength(1)

      const uploadCalls = vi
        .mocked(apiClient.post)
        .mock.calls.filter(([url]) => (url as string).includes('/upload-image'))
      expect(uploadCalls).toHaveLength(2)
    })

    it('erlaubt einen erfolgreichen Retry des Bild-Uploads, der dann saved und close auslöst', async () => {
      let uploadAttempts = 0
      vi.mocked(apiClient.post).mockImplementation((url: string) => {
        if (url === '/api/v1/dogs') {
          return Promise.resolve({ data: { data: mockCreatedDog } })
        }
        if (url.includes('/upload-image')) {
          uploadAttempts += 1
          if (uploadAttempts === 1) {
            return Promise.reject({ response: { data: { message: 'Netzwerkfehler' } } })
          }
          return Promise.resolve({ data: {} })
        }
        return Promise.reject(new Error(`Unerwartete URL: ${url}`))
      })

      const wrapper = mountModal()
      await fillRequiredFields(wrapper)
      await selectImage(wrapper)

      // Erster Versuch schlägt fehl
      await wrapper.find('form').trigger('submit')
      await flushPromises()
      expect(wrapper.emitted('saved')).toBeFalsy()

      // Retry gelingt
      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.emitted('saved')).toBeTruthy()
      expect(wrapper.emitted('close')).toBeTruthy()

      const createCalls = vi
        .mocked(apiClient.post)
        .mock.calls.filter(([url]) => url === '/api/v1/dogs')
      expect(createCalls).toHaveLength(1)
    })

    it('erlaubt das Schließen über den Abbrechen-Button ohne zweiten Hund anzulegen', async () => {
      setupFailingImageUpload()

      const wrapper = mountModal()
      await fillRequiredFields(wrapper)
      await selectImage(wrapper)

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      const cancelButton = wrapper
        .findAll('button[type="button"]')
        .find((btn) => btn.text().includes('Abbrechen'))
      await cancelButton!.trigger('click')

      expect(wrapper.emitted('close')).toBeTruthy()

      const createCalls = vi
        .mocked(apiClient.post)
        .mock.calls.filter(([url]) => url === '/api/v1/dogs')
      expect(createCalls).toHaveLength(1)
    })
  })

  // ------------------------------------------------------------------ //
  // Regressionstests: erfolgreicher Speichervorgang                      //
  // ------------------------------------------------------------------ //
  describe('Erfolgreicher Speichervorgang (Regression)', () => {
    it('emittiert saved und schließt, wenn kein Bild ausgewählt wurde', async () => {
      vi.mocked(apiClient.post).mockResolvedValue({ data: { data: mockCreatedDog } })

      const wrapper = mountModal()
      await fillRequiredFields(wrapper)

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.emitted('saved')).toBeTruthy()
      expect(wrapper.emitted('close')).toBeTruthy()
      expect(
        vi.mocked(apiClient.post).mock.calls.some(([url]) => (url as string).includes('/upload-image')),
      ).toBe(false)
    })

    it('emittiert saved und schließt, wenn der Bild-Upload erfolgreich war', async () => {
      vi.mocked(apiClient.post).mockImplementation((url: string) => {
        if (url === '/api/v1/dogs') {
          return Promise.resolve({ data: { data: mockCreatedDog } })
        }
        if (url.includes('/upload-image')) {
          return Promise.resolve({ data: {} })
        }
        return Promise.reject(new Error(`Unerwartete URL: ${url}`))
      })

      const wrapper = mountModal()
      await fillRequiredFields(wrapper)
      await selectImage(wrapper)

      await wrapper.find('form').trigger('submit')
      await flushPromises()

      expect(wrapper.emitted('saved')).toBeTruthy()
      expect(wrapper.emitted('close')).toBeTruthy()
    })
  })
})
