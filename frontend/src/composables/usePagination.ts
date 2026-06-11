import { ref } from 'vue'

export interface PaginationMeta {
  current_page: number
  last_page: number
  total: number
  per_page: number
}

export function usePagination() {
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const perPage = ref(15)

  function updateFromMeta(meta: PaginationMeta): void {
    currentPage.value = meta.current_page
    lastPage.value = meta.last_page
    total.value = meta.total
    perPage.value = meta.per_page
  }

  function setPage(page: number): void {
    currentPage.value = page
  }

  function resetPage(): void {
    currentPage.value = 1
  }

  return {
    currentPage,
    lastPage,
    total,
    perPage,
    updateFromMeta,
    setPage,
    resetPage,
  }
}
