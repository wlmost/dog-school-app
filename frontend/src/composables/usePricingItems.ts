import { ref } from 'vue'
import { pricingItemsApi, type PricingItem, type PricingGroup } from '@/api/pricingItems'

export function usePricingItems() {
  const groups = ref<PricingGroup[]>([])
  const items = ref<PricingItem[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function loadPublic(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      groups.value = await pricingItemsApi.getPublic()
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Fehler beim Laden der Preise'
    } finally {
      loading.value = false
    }
  }

  async function loadAll(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      items.value = await pricingItemsApi.getAll()
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Fehler beim Laden der Preise'
    } finally {
      loading.value = false
    }
  }

  async function createItem(
    data: Omit<PricingItem, 'id' | 'createdAt' | 'updatedAt'>,
  ): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const created = await pricingItemsApi.create(data)
      items.value = [...items.value, created]
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Fehler beim Erstellen des Preiseintrags'
    } finally {
      loading.value = false
    }
  }

  async function updateItem(
    id: number,
    data: Partial<Omit<PricingItem, 'id' | 'createdAt' | 'updatedAt'>>,
  ): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const updated = await pricingItemsApi.update(id, data)
      items.value = items.value.map((item) => (item.id === id ? updated : item))
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Fehler beim Aktualisieren des Preiseintrags'
    } finally {
      loading.value = false
    }
  }

  async function deleteItem(id: number): Promise<void> {
    loading.value = true
    error.value = null
    try {
      await pricingItemsApi.delete(id)
      items.value = items.value.filter((item) => item.id !== id)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Fehler beim Löschen des Preiseintrags'
    } finally {
      loading.value = false
    }
  }

  return {
    groups,
    items,
    loading,
    error,
    loadPublic,
    loadAll,
    createItem,
    updateItem,
    deleteItem,
  }
}
