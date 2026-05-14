import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import PricingItemForm from '@/components/PricingItemForm.vue'
import type { PricingItem } from '@/api/pricingItems'

// Variables starting with `mock` are hoisted alongside vi.mock by Vitest
const mockCreateItem = vi.fn()
const mockUpdateItem = vi.fn()
const mockApiError = { value: null as string | null }

vi.mock('@/composables/usePricingItems', () => ({
  usePricingItems: vi.fn(() => ({
    createItem: mockCreateItem,
    updateItem: mockUpdateItem,
    error: mockApiError,
  })),
}))

const testItem: PricingItem = {
  id: 1,
  category: 'Einzeltraining',
  title: 'Erstberatung',
  price: '80.00',
  unit: 'pro Stunde',
  description: 'Beschreibung zum Test',
  isFromPrice: false,
  createdAt: null,
  updatedAt: null,
}

describe('PricingItemForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockCreateItem.mockResolvedValue(undefined)
    mockUpdateItem.mockResolvedValue(undefined)
    mockApiError.value = null
  })

  it('rendert nicht wenn visible false ist', () => {
    const wrapper = mount(PricingItemForm, {
      props: { visible: false, item: null },
    })
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('rendert das Formular wenn visible true ist und item null ist', () => {
    const wrapper = mount(PricingItemForm, {
      props: { visible: true, item: null },
    })
    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.find('h2').text()).toContain('Neuen Preis anlegen')
  })

  it('zeigt Fehlermeldung wenn Kategorie beim Submit leer ist', async () => {
    const wrapper = mount(PricingItemForm, {
      props: { visible: true, item: null },
    })
    await wrapper.find('#pf-title').setValue('Ein Titel')
    await wrapper.find('#pf-price').setValue('50')
    await wrapper.find('form').trigger('submit')
    await nextTick()

    expect(wrapper.html()).toContain('Kategorie ist erforderlich')
    expect(wrapper.emitted('saved')).toBeFalsy()
  })

  it('zeigt Fehlermeldung wenn Leistungsbezeichnung beim Submit leer ist', async () => {
    const wrapper = mount(PricingItemForm, {
      props: { visible: true, item: null },
    })
    await wrapper.find('#pf-category').setValue('Eine Kategorie')
    await wrapper.find('#pf-price').setValue('50')
    await wrapper.find('form').trigger('submit')
    await nextTick()

    expect(wrapper.html()).toContain('Leistungsbezeichnung ist erforderlich')
    expect(wrapper.emitted('saved')).toBeFalsy()
  })

  it('zeigt Fehlermeldung bei leerem Preisfeld (F04-Verifikation)', async () => {
    const wrapper = mount(PricingItemForm, {
      props: { visible: true, item: null },
    })
    await wrapper.find('#pf-category').setValue('Einzeltraining')
    await wrapper.find('#pf-title').setValue('Grundkurs')
    // setValue('') on a number input: v-model.number may yield '' or NaN
    // depending on the runtime; the validation should catch both
    await wrapper.find('#pf-price').setValue('')
    await wrapper.find('form').trigger('submit')
    await nextTick()

    expect(wrapper.html()).toContain('Bitte einen gültigen Preis eingeben')
    expect(wrapper.emitted('saved')).toBeFalsy()
  })

  it('zeigt Fehlermeldung bei negativem Preis', async () => {
    const wrapper = mount(PricingItemForm, {
      props: { visible: true, item: null },
    })
    await wrapper.find('#pf-category').setValue('Einzeltraining')
    await wrapper.find('#pf-title').setValue('Grundkurs')
    await wrapper.find('#pf-price').setValue('-5')
    await wrapper.find('form').trigger('submit')
    await nextTick()

    expect(wrapper.html()).toContain('Preis darf nicht negativ sein')
    expect(wrapper.emitted('saved')).toBeFalsy()
  })

  it('befüllt Formularfelder mit Item-Daten wenn visible auf true wechselt', async () => {
    const wrapper = mount(PricingItemForm, {
      props: { visible: false, item: testItem },
    })
    await wrapper.setProps({ visible: true })
    await nextTick()

    expect(wrapper.find<HTMLInputElement>('#pf-category').element.value).toBe('Einzeltraining')
    expect(wrapper.find<HTMLInputElement>('#pf-title').element.value).toBe('Erstberatung')
    // parseFloat('80.00') = 80; number input renders as '80'
    expect(wrapper.find<HTMLInputElement>('#pf-price').element.value).toBe('80')
    expect(wrapper.find<HTMLInputElement>('#pf-unit').element.value).toBe('pro Stunde')
  })

  it('emittiert saved-Event nach erfolgreichem Submit bei Neu-Anlage', async () => {
    const wrapper = mount(PricingItemForm, {
      props: { visible: true, item: null },
    })
    await wrapper.find('#pf-category').setValue('Einzeltraining')
    await wrapper.find('#pf-title').setValue('Grundkurs')
    await wrapper.find('#pf-price').setValue('80')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockCreateItem).toHaveBeenCalledWith(
      expect.objectContaining({
        category: 'Einzeltraining',
        title: 'Grundkurs',
        price: '80',
      }),
    )
    expect(wrapper.emitted('saved')).toBeTruthy()
  })

  it('ruft updateItem statt createItem auf wenn ein bestehendes Item bearbeitet wird', async () => {
    const wrapper = mount(PricingItemForm, {
      props: { visible: false, item: testItem },
    })
    await wrapper.setProps({ visible: true })
    await nextTick()

    // Titel ändern und speichern
    await wrapper.find('#pf-title').setValue('Geänderter Titel')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(mockUpdateItem).toHaveBeenCalledWith(
      testItem.id,
      expect.objectContaining({ title: 'Geänderter Titel' }),
    )
    expect(mockCreateItem).not.toHaveBeenCalled()
    expect(wrapper.emitted('saved')).toBeTruthy()
  })
})
