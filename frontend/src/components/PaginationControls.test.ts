import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import PaginationControls from '@/components/PaginationControls.vue'

describe('PaginationControls', () => {
  it('renders nothing when lastPage is 1', () => {
    const wrapper = mount(PaginationControls, {
      props: { currentPage: 1, lastPage: 1 },
    })

    expect(wrapper.find('div').exists()).toBe(false)
  })

  it('renders pagination when lastPage > 1', () => {
    const wrapper = mount(PaginationControls, {
      props: { currentPage: 2, lastPage: 5 },
    })

    expect(wrapper.text()).toContain('Seite 2 von 5')
    expect(wrapper.text()).toContain('Vorherige')
    expect(wrapper.text()).toContain('Nächste')
  })

  it('shows total count when provided', () => {
    const wrapper = mount(PaginationControls, {
      props: { currentPage: 1, lastPage: 3, total: 42 },
    })

    expect(wrapper.text()).toContain('42 Einträge')
  })

  it('disables Vorherige button on first page', () => {
    const wrapper = mount(PaginationControls, {
      props: { currentPage: 1, lastPage: 3 },
    })

    const buttons = wrapper.findAll('button')
    const prev = buttons.find((b) => b.text() === 'Vorherige')
    expect(prev?.attributes('disabled')).toBeDefined()
  })

  it('disables Nächste button on last page', () => {
    const wrapper = mount(PaginationControls, {
      props: { currentPage: 3, lastPage: 3 },
    })

    const buttons = wrapper.findAll('button')
    const next = buttons.find((b) => b.text() === 'Nächste')
    expect(next?.attributes('disabled')).toBeDefined()
  })

  it('emits update:currentPage with page - 1 when Vorherige is clicked', async () => {
    const wrapper = mount(PaginationControls, {
      props: { currentPage: 3, lastPage: 5 },
    })

    const buttons = wrapper.findAll('button')
    const prev = buttons.find((b) => b.text() === 'Vorherige')
    await prev?.trigger('click')

    expect(wrapper.emitted('update:currentPage')).toEqual([[2]])
  })

  it('emits update:currentPage with page + 1 when Nächste is clicked', async () => {
    const wrapper = mount(PaginationControls, {
      props: { currentPage: 2, lastPage: 5 },
    })

    const buttons = wrapper.findAll('button')
    const next = buttons.find((b) => b.text() === 'Nächste')
    await next?.trigger('click')

    expect(wrapper.emitted('update:currentPage')).toEqual([[3]])
  })
})
