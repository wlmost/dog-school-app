import { describe, it, expect } from 'vitest'
import { usePagination } from '@/composables/usePagination'

describe('usePagination', () => {
  it('initializes with page 1 and lastPage 1', () => {
    const { currentPage, lastPage, total, perPage } = usePagination()

    expect(currentPage.value).toBe(1)
    expect(lastPage.value).toBe(1)
    expect(total.value).toBe(0)
    expect(perPage.value).toBe(15)
  })

  it('updateFromMeta sets all pagination values', () => {
    const { currentPage, lastPage, total, perPage, updateFromMeta } = usePagination()

    updateFromMeta({ current_page: 3, last_page: 7, total: 100, per_page: 15 })

    expect(currentPage.value).toBe(3)
    expect(lastPage.value).toBe(7)
    expect(total.value).toBe(100)
    expect(perPage.value).toBe(15)
  })

  it('setPage updates currentPage', () => {
    const { currentPage, setPage } = usePagination()

    setPage(5)

    expect(currentPage.value).toBe(5)
  })

  it('resetPage resets currentPage to 1', () => {
    const { currentPage, setPage, resetPage } = usePagination()

    setPage(4)
    expect(currentPage.value).toBe(4)

    resetPage()
    expect(currentPage.value).toBe(1)
  })

  it('each call to usePagination returns independent state', () => {
    const a = usePagination()
    const b = usePagination()

    a.setPage(3)

    expect(a.currentPage.value).toBe(3)
    expect(b.currentPage.value).toBe(1)
  })
})
