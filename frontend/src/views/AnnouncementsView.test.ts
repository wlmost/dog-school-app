import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { ref, nextTick } from 'vue'
import AnnouncementsView from '@/views/AnnouncementsView.vue'
import FileUpload from '@/components/FileUpload.vue'
import type { Announcement } from '@/api/announcements'

// Variables starting with `mock` are hoisted alongside vi.mock by Vitest.
// Real vue refs are required (not plain `{ value: ... }` objects) because
// the component both reads `loadError.value`/`mutationError.value` in
// script code (mirroring PricingItemForm.vue's pattern) AND relies on the
// template compiler's `unref()` auto-unwrapping for `v-else-if="loadError"`
// / `v-if="mutationError"` / `v-for="... in announcements"` — a plain
// object without `__v_isRef` would be truthy regardless of its `.value`,
// breaking the empty/error-state branching.
//
// loadError and mutationError are intentionally separate refs (not a single
// shared `error`): a lingering mutation failure (e.g. a failed delete) must
// not hide the still-valid, unchanged announcement list — see
// useAnnouncements.ts for the full rationale.
const mockAnnouncements = ref<Announcement[]>([])
const mockLoading = ref(false)
const mockLoadError = ref<string | null>(null)
const mockMutationError = ref<string | null>(null)
const mockLoadAll = vi.fn()
const mockCreateAnnouncement = vi.fn()
const mockUpdateAnnouncement = vi.fn()
const mockDeleteAnnouncement = vi.fn()

vi.mock('@/composables/useAnnouncements', () => ({
  useAnnouncements: vi.fn(() => ({
    announcements: mockAnnouncements,
    loading: mockLoading,
    loadError: mockLoadError,
    mutationError: mockMutationError,
    loadAll: mockLoadAll,
    createAnnouncement: mockCreateAnnouncement,
    updateAnnouncement: mockUpdateAnnouncement,
    deleteAnnouncement: mockDeleteAnnouncement,
  })),
}))

// HtmlEditor wraps Tiptap/ProseMirror which needs a real contenteditable
// DOM environment; it is stubbed with a plain textarea so the v-model
// contract (`modelValue` / `update:modelValue`) can still be exercised.
const HtmlEditorStub = {
  name: 'HtmlEditor',
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template:
    '<textarea data-testid="html-editor" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
}

const activeAnnouncement: Announcement = {
  id: 1,
  title: 'Sommerpause',
  body: '<p>Wir sind vom 1. bis 15. August im Urlaub.</p>',
  imageUrl: 'https://example.com/announcement.jpg',
  displayDays: 14,
  expiresAt: '2099-01-01T00:00:00.000Z',
  isActive: true,
  createdAt: '2026-01-01T00:00:00.000Z',
  updatedAt: '2026-01-01T00:00:00.000Z',
}

const expiredAnnouncement: Announcement = {
  id: 2,
  title: 'Altes Angebot',
  body: '<p>Dieses Angebot ist abgelaufen.</p>',
  imageUrl: null,
  displayDays: 7,
  expiresAt: '2020-01-01T00:00:00.000Z',
  isActive: false,
  createdAt: '2019-12-01T00:00:00.000Z',
  updatedAt: '2019-12-01T00:00:00.000Z',
}

// Tracks the wrapper of the currently running test so it can be unmounted
// afterwards. `mockAnnouncements`/`mockLoading`/`mockLoadError`/
// `mockMutationError` are real, shared Vue refs (see comment above) —
// without unmounting, a later test mutating those refs would still trigger
// reactive updates on components mounted (and torn down by jsdom) in
// earlier tests, causing "Cannot read properties of null" errors from stale
// vnode trees.
let wrapper: VueWrapper | undefined

function mountView() {
  wrapper = mount(AnnouncementsView, {
    global: {
      stubs: { HtmlEditor: HtmlEditorStub },
    },
  })
  return wrapper
}

describe('AnnouncementsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockAnnouncements.value = []
    mockLoading.value = false
    mockLoadError.value = null
    mockMutationError.value = null
    mockLoadAll.mockResolvedValue(undefined)
    mockCreateAnnouncement.mockResolvedValue(undefined)
    mockUpdateAnnouncement.mockResolvedValue(undefined)
    mockDeleteAnnouncement.mockResolvedValue(undefined)
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
    vi.unstubAllGlobals()
  })

  it('lädt alle Ankündigungen beim Mounten', () => {
    mountView()
    expect(mockLoadAll).toHaveBeenCalledTimes(1)
  })

  it('zeigt einen Ladeindikator während des initialen Ladens', () => {
    mockLoading.value = true
    const wrapper = mountView()
    expect(wrapper.find('.animate-spin').exists()).toBe(true)
  })

  it('zeigt die Fehlermeldung wenn das Laden fehlschlägt', () => {
    mockLoadError.value = 'Fehler beim Laden der Ankündigungen'
    const wrapper = mountView()
    expect(wrapper.text()).toContain('Fehler beim Laden der Ankündigungen')
  })

  it('zeigt weiterhin die vorhandene Liste an, wenn eine Mutation fehlschlägt (Reviewer-Blocker)', () => {
    mockAnnouncements.value = [activeAnnouncement, expiredAnnouncement]
    mockMutationError.value = 'Fehler beim Löschen der Ankündigung'
    const wrapper = mountView()

    // The mutation error banner is shown ...
    expect(wrapper.text()).toContain('Fehler beim Löschen der Ankündigung')
    // ... but it must not hide the still-valid, unchanged list.
    expect(wrapper.findAll('li')).toHaveLength(2)
    expect(wrapper.text()).not.toContain('Noch keine Ankündigungen vorhanden')
  })

  it('zeigt einen Leerzustand wenn keine Ankündigungen vorhanden sind', () => {
    const wrapper = mountView()
    expect(wrapper.text()).toContain('Noch keine Ankündigungen vorhanden')
  })

  it('zeigt aktive und abgelaufene Ankündigungen jeweils mit korrektem Status-Badge', () => {
    mockAnnouncements.value = [activeAnnouncement, expiredAnnouncement]
    const wrapper = mountView()

    const items = wrapper.findAll('li')
    expect(items).toHaveLength(2)
    const [activeItem, expiredItem] = items
    expect(activeItem!.text()).toContain('Aktiv')
    expect(activeItem!.find('.bg-green-100').exists()).toBe(true)
    expect(expiredItem!.text()).toContain('Abgelaufen')
    expect(expiredItem!.find('.bg-gray-100').exists()).toBe(true)
  })

  it('öffnet ein leeres Formular bei Klick auf "Neue Ankündigung"', async () => {
    const wrapper = mountView()
    await wrapper.find('button').trigger('click') // "Neue Ankündigung" is the first button
    await nextTick()

    expect(wrapper.find('#af-title').exists()).toBe(true)
    expect(wrapper.find<HTMLInputElement>('#af-title').element.value).toBe('')
    expect(wrapper.html()).toContain('Neue Ankündigung')
  })

  it('öffnet das Formular vorausgefüllt bei Klick auf "Bearbeiten"', async () => {
    mockAnnouncements.value = [activeAnnouncement]
    const wrapper = mountView()

    const editButton = wrapper.findAll('button').find((btn) => btn.text() === 'Bearbeiten')
    expect(editButton).toBeTruthy()
    await editButton!.trigger('click')
    await nextTick()

    expect(wrapper.find<HTMLInputElement>('#af-title').element.value).toBe('Sommerpause')
    expect(wrapper.find<HTMLInputElement>('#af-display-days').element.value).toBe('14')
    expect(wrapper.find<HTMLTextAreaElement>('[data-testid="html-editor"]').element.value).toBe(
      activeAnnouncement.body,
    )
  })

  it('zeigt eine Fehlermeldung wenn der Titel beim Speichern leer ist', async () => {
    const wrapper = mountView()
    await wrapper.find('button').trigger('click')
    await nextTick()

    await wrapper.find('#af-display-days').setValue(10)
    await wrapper.find('form').trigger('submit')
    await nextTick()

    expect(wrapper.html()).toContain('Titel ist erforderlich')
    expect(mockCreateAnnouncement).not.toHaveBeenCalled()
  })

  it('zeigt eine Fehlermeldung wenn die Anzeigedauer außerhalb von 1-365 liegt', async () => {
    const wrapper = mountView()
    await wrapper.find('button').trigger('click')
    await nextTick()

    await wrapper.find('#af-title').setValue('Testtitel')
    await wrapper.find('[data-testid="html-editor"]').setValue('Testtext')
    await wrapper.find('#af-display-days').setValue(400)
    await wrapper.find('form').trigger('submit')
    await nextTick()

    expect(wrapper.html()).toContain('Anzeigedauer muss zwischen 1 und 365 Tagen liegen')
    expect(mockCreateAnnouncement).not.toHaveBeenCalled()
  })

  it('ruft createAnnouncement mit den Formulardaten auf und schließt das Formular', async () => {
    const wrapper = mountView()
    await wrapper.find('button').trigger('click')
    await nextTick()

    await wrapper.find('#af-title').setValue('Neue Aktion')
    await wrapper.find('[data-testid="html-editor"]').setValue('<p>Ein toller Text</p>')
    await wrapper.find('#af-display-days').setValue(21)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreateAnnouncement).toHaveBeenCalledWith(
      expect.objectContaining({
        title: 'Neue Aktion',
        body: '<p>Ein toller Text</p>',
        displayDays: 21,
        image: null,
      }),
    )
    expect(wrapper.find('#af-title').exists()).toBe(false)
  })

  it('ruft updateAnnouncement mit der ID der bearbeiteten Ankündigung auf', async () => {
    mockAnnouncements.value = [activeAnnouncement]
    const wrapper = mountView()

    const editButton = wrapper.findAll('button').find((btn) => btn.text() === 'Bearbeiten')
    await editButton!.trigger('click')
    await nextTick()

    await wrapper.find('#af-title').setValue('Geänderter Titel')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockUpdateAnnouncement).toHaveBeenCalledWith(
      activeAnnouncement.id,
      expect.objectContaining({ title: 'Geänderter Titel' }),
    )
    expect(mockCreateAnnouncement).not.toHaveBeenCalled()
  })

  it('setzt das ausgewählte Bild im Formular beim Datei-Upload', async () => {
    const wrapper = mountView()
    await wrapper.find('button').trigger('click')
    await nextTick()

    await wrapper.find('#af-title').setValue('Mit Bild')
    await wrapper.find('[data-testid="html-editor"]').setValue('Text')

    const file = new File(['image-bytes'], 'banner.png', { type: 'image/png' })
    await wrapper.findComponent(FileUpload).vm.$emit('upload', [file])

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreateAnnouncement).toHaveBeenCalledWith(
      expect.objectContaining({ image: file }),
    )
  })

  it('hält das Formular offen und zeigt einen Serverfehler wenn das Speichern fehlschlägt', async () => {
    mockCreateAnnouncement.mockImplementation(async () => {
      mockMutationError.value = 'Fehler beim Erstellen der Ankündigung'
    })

    const wrapper = mountView()
    await wrapper.find('button').trigger('click')
    await nextTick()

    await wrapper.find('#af-title').setValue('Titel')
    await wrapper.find('[data-testid="html-editor"]').setValue('Text')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('#af-title').exists()).toBe(true)
    expect(wrapper.text()).toContain('Fehler beim Erstellen der Ankündigung')
  })

  it('hält das Formular offen und zeigt einen Serverfehler wenn das Aktualisieren fehlschlägt', async () => {
    mockUpdateAnnouncement.mockImplementation(async () => {
      mockMutationError.value = 'Fehler beim Aktualisieren der Ankündigung'
    })
    mockAnnouncements.value = [activeAnnouncement]

    const wrapper = mountView()
    const editButton = wrapper.findAll('button').find((btn) => btn.text() === 'Bearbeiten')
    await editButton!.trigger('click')
    await nextTick()

    await wrapper.find('#af-title').setValue('Geänderter Titel')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('#af-title').exists()).toBe(true)
    expect(wrapper.text()).toContain('Fehler beim Aktualisieren der Ankündigung')
  })

  it('zeigt eine Fehlermeldung an wenn das Löschen fehlschlägt', async () => {
    vi.stubGlobal('confirm', vi.fn().mockReturnValue(true))
    mockDeleteAnnouncement.mockImplementation(async () => {
      mockMutationError.value = 'Fehler beim Löschen der Ankündigung'
    })
    mockAnnouncements.value = [activeAnnouncement]

    const wrapper = mountView()
    const deleteButton = wrapper.findAll('button').find((btn) => btn.text() === 'Löschen')
    await deleteButton!.trigger('click')
    await flushPromises()

    expect(mockDeleteAnnouncement).toHaveBeenCalledWith(activeAnnouncement.id)
    expect(wrapper.text()).toContain('Fehler beim Löschen der Ankündigung')
    // Reviewer-Blocker: a failed mutation must not hide the remaining,
    // unchanged list behind the error message.
    expect(wrapper.findAll('li')).toHaveLength(1)
  })

  it('löscht eine Ankündigung nach Bestätigung', async () => {
    vi.stubGlobal('confirm', vi.fn().mockReturnValue(true))
    mockAnnouncements.value = [activeAnnouncement]
    const wrapper = mountView()

    const deleteButton = wrapper.findAll('button').find((btn) => btn.text() === 'Löschen')
    await deleteButton!.trigger('click')
    await flushPromises()

    expect(mockDeleteAnnouncement).toHaveBeenCalledWith(activeAnnouncement.id)
  })

  it('löscht eine Ankündigung nicht wenn die Bestätigung abgebrochen wird', async () => {
    vi.stubGlobal('confirm', vi.fn().mockReturnValue(false))
    mockAnnouncements.value = [activeAnnouncement]
    const wrapper = mountView()

    const deleteButton = wrapper.findAll('button').find((btn) => btn.text() === 'Löschen')
    await deleteButton!.trigger('click')
    await flushPromises()

    expect(mockDeleteAnnouncement).not.toHaveBeenCalled()
  })
})
